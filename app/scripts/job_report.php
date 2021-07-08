<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

include_once(__DIR__ . "/../conf/App.php");
include_once(__DIR__ . "/../vendor/autoload.php");

use app\conf\App;
use app\inc\Model;
use app\models\Database;
use Postmark\PostmarkClient;
use Postmark\Models\PostmarkException;

new App();

echo date(DATE_RFC822) . "\n\n";

Database::setDb("gc2scheduler");
$model = new Model();

$failedJobs = [];

$sql = "SELECT * FROM jobs WHERE lastcheck = FALSE AND lastrun > (now() - '24 hour' :: INTERVAL) ORDER BY id";

$res = $model->prepare($sql);
try {
    $res->execute();
} catch (PDOException $e) {
    print_r($e);
    exit(1);
}
$failedJobs[] = "<table class=\"table table-striped\">\n";
$failedJobs[] = "<tr><th>Id</th><th>Database</th><th>Schema</th><th>Table</th><th>Log</th></tr>\n";
while ($row = $model->fetchRow($res)) {
    $failedJobs[] = "<tr><td>{$row["id"]}</td><td>{$row["db"]}</td><td>{$row["schema"]}</td><td>{$row["name"]}</td><td><a target=\"_blank\" href=\"" . App::$param["notification"]["logUrl"] . "/logs/" . $row["id"] . "_scheduler.log\">{$row["id"]}_scheduler.log</a></td></tr>\n";
}
$failedJobs[] = "</table>\n";

$html = "<p>Failed jobs in the last 24 hours</p>\n";
$html .= implode("", $failedJobs);

if (App::$param["notification"]) {
    try {
        $client = new PostmarkClient(App::$param["notification"]["key"]);
        $message = [
            'To' => implode(",", App::$param["notification"]["to"]),
            'From' => App::$param["notification"]["from"],
            'TrackOpens' => false,
            'Subject' => "GC2Scheduler jobs with error. " . App::$param["notification"]["logUrl"],
            'HtmlBody' => "<html><head><link rel=\"stylesheet\" href=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css\"/></head><body style=\"padding: 20px\">" . $html . "</body></html>",
        ];
        try {
            $sendResult = $client->sendEmailBatch([$message]);
            print_r($sendResult);
        } catch (Exception $generalException) {
            exit(1);
        }
    } catch (PostmarkException $ex) {
        echo $ex->httpStatusCode;
        echo $ex->postmarkApiErrorCode;
    } catch (Exception $generalException) {
        exit(1);
    }
}
exit(0);