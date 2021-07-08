<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

ini_set("max_execution_time", "0");

use app\inc\Model;
use app\inc\Util;
use Exception;
use PDOException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;


/**
 * Class Stream
 * @package app\models
 */
class Stream extends Model
{
    /**
     * @var string
     */
    private $srs;

    /**
     * Sql_to_es constructor.
     * @param string $srs
     */
    function __construct($srs = "4326")
    {
        parent::__construct();
        $this->srs = $srs;
    }

    /**
     * @param string $q
     * @return string
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function runSql(string $q): string
    {
        $i = 0;
        $geometries = null;
        $bulkSize = 1000;
        $json = "";
        $name = "_" . rand(1, 999999999) . microtime();
        $view = self::toAscii($name, null, "_");
        $sqlView = "CREATE TEMPORARY VIEW {$view} as {$q}";

        try {
            $res = $this->prepare($sqlView);
            $res->execute();
        } catch (PDOException $e) {
            //$this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return serialize($response);
        }

        $arrayWithFields = $this->getMetaData($view, true); // Temp VIEW
        $fieldsArr = [];
        foreach ($arrayWithFields as $key => $arr) {
            if ($arr['type'] == "geometry") {
                $fieldsArr[] = "ST_asGeoJson(ST_Transform(ST_Force2D(\"" . $key . "\")," . $this->srs . ")) as \"" . $key . "\"";
            } else {
                $fieldsArr[] = "\"{$key}\"";
            }
        }
        $sql = implode(",", $fieldsArr);

        $sql = "SELECT {$sql} FROM {$view}";

        $this->begin();

        try {
            $this->prepare("DECLARE curs CURSOR FOR {$sql}")->execute();
            $innerStatement = $this->prepare("FETCH 1 FROM curs");
        } catch (PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return serialize($response);
        }

        Util::disableOb();
        header('Content-type: text/plain; charset=utf-8');
        try {
            while ($innerStatement->execute() && $row = $this->fetchRow($innerStatement)) {
                $arr = [];
                foreach ($row as $key => $value) {
                    if ($arrayWithFields[$key]['type'] == "geometry") {
                        $geometries[] = json_decode($row[$key]);
                    } elseif ($arrayWithFields[$key]['type'] == "json" || $arrayWithFields[$key]['type'] == "jsonb") {
                        $arr = $this->array_push_assoc($arr, $key, json_decode($value));
                    } else {
                        $arr = $this->array_push_assoc($arr, $key, $value);
                    }
                }

                if ($geometries == null) {
                    $features = array("type" => "Feature", "properties" => $arr);
                } elseif (count($geometries) == 1) {
                    $features = array("type" => "Feature", "properties" => $arr, "geometry" => $geometries[0]);
                } else {
                    $features = array("type" => "Feature", "properties" => $arr, "geometry" => array("type" => "GeometryCollection", "geometries" => $geometries));
                }
                $geometries = null;

                $json .= json_encode($features);
                $json .= "\n";
                $i++;
                if (is_int($i/$bulkSize)) {
                    echo str_pad($json, 4096);
                    $json = "";
                }
                flush();
                ob_flush();
            }
            if ($json) {
                echo str_pad($json, 4096);
            }
            $this->execQuery("CLOSE curs");
            $this->commit();

        } catch (Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 410;
            return serialize($response);
        }
        die();
    }

    /**
     * @param array<mixed> $array
     * @param string $key
     * @param mixed $value
     * @return array<mixed>
     */
    private function array_push_assoc(array $array, string $key, $value) : array
    {
        $array[$key] = $value;
        return $array;
    }
}
