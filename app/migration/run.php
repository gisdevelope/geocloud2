<?php
use app\migration\Sql;
header("Content-type: text/plain");
include_once(__DIR__ ."/../conf/App.php");
include_once(__DIR__ ."/../conf/Connection.php");
include_once(__DIR__ ."/../inc/Model.php");
include_once(__DIR__ ."/../models/Database.php");
include_once(__DIR__ ."/Sql.php");
new \app\conf\App();
\app\conf\Connection::$param["postgisschema"] = "public";
$database = new \app\models\Database();
$arr = $database->listAllDbs();
foreach ($arr['data'] as $db) {
    if ($db != "rdsadmin" && $db != "template1" AND $db != "template0" AND $db != "postgres" AND $db != "postgis_template") {
        if (1 === 1) {
            \app\models\Database::setDb($db);
            $conn = new \app\inc\Model();

            switch ($db) {
                case "mapcentia":
                    $sqls = Sql::mapcentia();
                    break;
                case "gc2scheduler":
                    $sqls = Sql::gc2scheduler();
                    break;
                default:
                    $sqls = Sql::get();
                    break;
            }

            foreach ($sqls as $sql) {
                $result = $conn->execQuery($sql, "PDO", "transaction");
                if ($conn->PDOerror[0]) {
                    echo "-";
                } else {
                    echo "+";
                }
                $conn->PDOerror = NULL;
            }
            echo " {$db}\n";
            $conn->db = NULL;
            $conn = NULL;
        }
    }
}
