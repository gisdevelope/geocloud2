<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *  
 */

namespace app\controllers;

use \app\conf\App;
use \app\inc\Util;
use \app\inc\Input;

include "libs/PEAR/XML/Unserializer.php";

/**
 * Class Wms
 * @package app\controllers
 */
class Wms extends \app\inc\Controller
{

    public $service;

    /**
     * Wms constructor.
     */
    function __construct()
    {
        parent::__construct();

        $layers = [];
        $postgisschema = \app\inc\Input::getPath()->part(3);
        $db = \app\inc\Input::getPath()->part(2);
        $dbSplit = explode("@", $db);
        $this->service = null;
        if (sizeof($dbSplit) == 2) {
            $subUser = $dbSplit[0];
            $db = $dbSplit[1];
        } else {
            $subUser = null;
        }

        $trusted = false;
        foreach (App::$param["trustedAddresses"] as $address) {
            if (Util::ipInRange(Util::clientIp(), $address)) {
                $trusted = true;
                break;
            }
        }

        // Both WMS and WFS can use GET
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            foreach ($_GET as $k => $v) {
                // Get the layer names from either WMS (layer) or WFS (typename)
                if (strtolower($k) == "layers" || strtolower($k) == "layer" || strtolower($k) == "typename" || strtolower($k) == "typenames") {
                    $layers[] = $v;
                }

                // Get the service. WMS or WFS
                if (strtolower($k) == "service") {
                    $this->service = strtolower($v);
                }
            }

            // If IP not trusted, when check auth on layers
            if (!$trusted) {
                foreach ($layers as $layer) {
                    $this->basicHttpAuthLayer($layer, $db, $subUser);
                }
            }

            $this->get($db, $postgisschema);
        }

        // Only WFS uses POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Parse the XML request
            $unserializer = new \XML_Unserializer(['parseAttributes' => TRUE, 'typeHints' => FALSE]);
            $request = Input::get(null, true);
            $status = $unserializer->unserialize($request);
            $arr = $unserializer->getUnserializedData();

            // Get service. Only WFS for now
            $this->service = strtolower($arr["service"]);

            // Get the layer name
            $layer = sizeof(explode(":", $arr["wfs:Query"]["typeName"])) > 1 ? explode(":", $arr["wfs:Query"]["typeName"])[1] : $arr["wfs:Query"]["typeName"];

            // If IP not trusted, when check auth on layer
            if (!$trusted) {
                $this->basicHttpAuthLayer($layer, $db, $subUser);
            }
            $this->post($db, $postgisschema, $request);
        }
    }

    /**
     * @param $db string
     * @param $postgisschema string
     */
    private function get($db, $postgisschema)
    {

        // Set MapFile for either WMS or WFS
        switch ($this->service) {
            case "wms":
                $mapFile = $db . "_" . $postgisschema . "_wms.map";
                break;

            case "wfs":
                $mapFile = $db . "_" . $postgisschema . "_wfs.map";
                break;

            default:
                break;
        }

        $url = "http://127.0.0.1/cgi-bin/mapserv.fcgi?map=/var/www/geocloud2/app/wms/mapfiles/{$mapFile}&" . $_SERVER["QUERY_STRING"];

        header("X-Powered-By: GC2 WMS");
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header_line) {
            $bits = explode(":", $header_line);
            if ($bits[0] == "Content-Type") {
                $this->type = trim($bits[1]);
            }
            // Send text/xml instead of application/vnd.ogc.se_xml
            if ($bits[0] == "Content-Type" && trim($bits[1]) == "application/vnd.ogc.se_xml") {
                header("Content-Type: text/xml");
            } elseif ($bits[0] != "Content-Encoding" && trim($bits[1]) != "chunked") {
                header($header_line);
            }
            return strlen($header_line);
        });
        $content = curl_exec($ch);
        curl_close($ch);
        echo $content;
        exit();
    }

    private function post($db, $postgisschema, $data)
    {
        // Set MapFile. For now this can only be WFS
        switch ($this->service) {
            case "wms":
                $mapFile = $db . "_" . $postgisschema . "_wms.map";
                break;

            case "wfs":
                $mapFile = $db . "_" . $postgisschema . "_wfs.map";
                break;

            default:
                break;
        }

        $url = "http://127.0.0.1/cgi-bin/mapserv.fcgi?map=/var/www/geocloud2/app/wms/mapfiles/{$mapFile}";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: text/xml',
                'Content-Length: ' . strlen($data))
        );
        $content = curl_exec($ch);
        header("Content-Type: text/xml");
        echo $content;
        exit();
    }
}