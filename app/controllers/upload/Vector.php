<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */


namespace app\controllers\upload;

use app\conf\Connection;
use app\conf\App;
use app\inc\Controller;

/**
 * Class Vector
 * @package app\controllers\upload
 */
class Vector extends Controller
{

    /**
     * Vector constructor.
     */
    function __construct()
    {
        parent::__construct();
    }

    /**
     * @return array<bool|string>
     */
    public function post_index(): array
    {
        @set_time_limit(5 * 60);
        $mainDir = App::$param['path'] . "/app/tmp/" . Connection::$param["postgisdb"];
        $targetDir = $mainDir . "/__vectors";

        $maxFileAge = 5 * 3600;

        if (!file_exists($mainDir)) {
            @mkdir($mainDir);
        }
        if (!file_exists($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }

        if (isset($_REQUEST["name"])) {
            $fileName = $_REQUEST["name"];
        } elseif (!empty($_FILES)) {
            $fileName = $_FILES["file"]["name"];
        } else {
            $fileName = uniqid("file_");
        }

        $filePath = $targetDir . DIRECTORY_SEPARATOR . $fileName;

        $chunk = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
        $chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 0;

        if (!is_dir($targetDir) || !$dir = opendir($targetDir)) {
            return [
                "success" => false,
                "code" => "400",
                "message" => "Failed to open temp directory.",
            ];
        }
        while (($file = readdir($dir)) !== false) {
            $tmpfilePath = $targetDir . DIRECTORY_SEPARATOR . $file;

            // If temp file is current file proceed to the next
            if ($tmpfilePath == "{$filePath}.part") {
                continue;
            }

            // Remove temp file if it is older than the max age and is not the current file
            if (preg_match('/\.part$/', $file) && (filemtime($tmpfilePath) < time() - $maxFileAge)) {
                @unlink($tmpfilePath);
            }
        }

        closedir($dir);
        // Open temp file
        if (!$out = @fopen("{$filePath}.part", $chunks ? "ab" : "wb")) {
            return [
                "success" => false,
                "code" => "400",
                "message" => "Failed to open output stream.",
            ];
        }

        if (!empty($_FILES)) {
            if ($_FILES["file"]["error"] || !is_uploaded_file($_FILES["file"]["tmp_name"])) {
                return [
                    "success" => false,
                    "code" => "400",
                    "message" => "Failed to move uploaded file.",
                ];
            }

            // Read binary input stream and append it to temp file
            if (!$in = @fopen($_FILES["file"]["tmp_name"], "rb")) {
                return [
                    "success" => false,
                    "code" => "400",
                    "message" => "Failed to open input stream.",
                ];
            }
        } else {
            if (!$in = @fopen("php://input", "rb")) {
                return [
                    "success" => false,
                    "code" => "400",
                    "message" => "Failed to open input stream.",
                ];
            }
        }

        while ($buff = fread($in, 4096)) {
            fwrite($out, $buff);
        }

        @fclose($out);
        @fclose($in);

        // Check if file has been uploaded
        if (!$chunks || $chunk == $chunks - 1) {
            // Strip the temp .part suffix off
            rename("{$filePath}.part", $filePath);
        }
        return [
            "success" => true,
            "message" => "File uploaded"
        ];
    }
}