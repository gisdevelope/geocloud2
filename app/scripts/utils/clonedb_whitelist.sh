#!/bin/bash
# Usage: sh clonedb_whitelist.sh -h SourceHost -d SourceDb -u SourceUser -p SourcePw -H TargetHost -D TargetDb -U TargetUser -P TargetPw

# Reset in case getopts has been used previously in the shell.
OPTIND=1

# Initialize our own variables:
sourcehost=""
targethost=""
sourcedb=""
targetdb=""
sourceuser=""
targetuser=""
sourcepw=""
targetpw=""
usage="Usage: sh clonedb_whitelist.sh -h SourceHost -d SourceDb -u SourceUser -p SourcePw -H TargetHost -D TargetDb -U TargetUser -P TargetPw"

while getopts "k?:h:d:H:D:u:U:p:P:" opt; do
    case "$opt" in
    k|\?)
        echo $usage
        exit 0
        ;;
    h)  sourcehost=$OPTARG
        ;;
    d)  sourcedb=$OPTARG
        ;;
    H)  targethost=$OPTARG
        ;;
    D)  targetdb=$OPTARG
        ;;
    u)  sourceuser=$OPTARG
        ;;
    U)  targetuser=$OPTARG
        ;;
    p)  sourcepw=$OPTARG
        ;;
    P)  targetpw=$OPTARG
        ;;
    :)  echo "Option -$OPTARG requires an argument." >&2
        exit 1
        ;;
    esac
done

shift $((OPTIND-1))

if [ -z "$sourcehost" ] || [ -z "$sourcedb" ] || [ -z "$targethost" ] || [ -z "$targetdb" ] || [ -z "$sourceuser" ] || [ -z "$targetuser" ]
then
    echo $usage
    exit 1
fi

##################
function if_error
##################
{
    if [[ $? -ne 0 ]]; then # check return code passed to function
        print "$1 DATE: `date +%Y-%m-%d:%H:%M:%S`"
        exit $?
    fi
}

IFS=$'\r\n' GLOBIGNORE='*' :; ARRAY=($(cat ./whitelist.txt))

export PGUSER=$sourceuser
export PGHOST=$sourcehost
export PGDATABASE=$sourcedb
export PGPASSWORD=$sourcepw

for SCHEMA in "${ARRAY[@]}"
    do
        c="$c --schema=$SCHEMA"
    done
echo $c
pg_dump --format=d --jobs=4 --file dump.bak $c
if_error "Could not dump database."

# Load in target
tmpdb="${targetdb}_`date +%Y_%m_%d_%H_%M_%S`"
echo $tmpdb

export PGUSER=$targetuser
export PGHOST=$targethost
export PGDATABASE=$tmpdb
export PGPASSWORD=$targetpw

createdb --template=template0 --locale=da_DK.UTF-8 --encoding=UTF8

psql -c "CREATE EXTENSION postgis;"
psql -c "CREATE EXTENSION pgrouting;"
psql -c "CREATE EXTENSION pgcrypto;"
psql -c "CREATE EXTENSION \"uuid-ossp\";"
psql -c "CREATE EXTENSION dblink;"
psql -c "CREATE EXTENSION hstore;"
psql -c "CREATE EXTENSION ogr_fdw;"
psql -c "CREATE EXTENSION postgres_fdw;"
psql -c "CREATE EXTENSION pgagent;"

# Run any custom SQL before restoring
psql -f ./custom_restore.sql
if_error "No custom_restore.sql file."

# pg_restore will ignore errors (some errors are harmless). In such case it will exit with status 1. Therefore we can't check.
pg_restore dump.bak --jobs=4 --dbname=$tmpdb
#if_error "Could not restore database."

# Make sure all MATERIALIZED VIEWs are refreshed
for MATVIEW in `psql -qAt -c "SELECT schemaname||'.'||matviewname AS mview FROM pg_matviews"`
        do
                psql -c "REFRESH MATERIALIZED VIEW $MATVIEW"
        done

# Check if settings schema is available in target.
psql -c "SELECT * FROM settings.geometry_columns_view" >/dev/null;
if_error "The Settings schema is missing";

# Make sure all are disconnected from the target db before dropping target if it exists
psql postgres -c "
SELECT pg_terminate_backend(pg_stat_activity.pid) FROM pg_stat_activity WHERE pg_stat_activity.datname = '$targetdb' AND pid <> pg_backend_pid();
"
psql postgres -c "
DROP DATABASE IF EXISTS $targetdb;
"
if_error "Could not drop database."

# Make sure all are disconnected from the target db before renaming tmpdb to target
psql postgres -c "
BEGIN;
SELECT pg_terminate_backend(pg_stat_activity.pid) FROM pg_stat_activity WHERE pg_stat_activity.datname = '$tmpdb' AND pid <> pg_backend_pid();
alter database $tmpdb rename to $targetdb;
COMMIT;
"
if_error "Could not rename database."

psql postgres -c "drop database $tmpdb"

#Clean up
rm dump.bak -R

# Write out the MapFiles
echo "Writing MapFiles"
for SCHEMA in "${ARRAY[@]}"
    do
       RES=$(curl -XGET -s "$targethost/api/v2/mapfile/write/$targetdb/$SCHEMA" | python3 -c "import sys, json; print(json.load(sys.stdin))")
       echo $RES
    done

#Write out the MapCache file
echo "Writing MapCache file"
RES=$(curl -XGET -s "$targethost/api/v2/mapcachefile/write/$targetdb" | python3 -c "import sys, json; print(json.load(sys.stdin))")
echo $RES

#Write out the QGIS files
echo "Writing QGIS files"
RES=$(curl -XGET -s "$targethost/api/v2/qgis/write/$targetdb" | python3 -c "import sys, json; print(json.load(sys.stdin))")
echo $RES

