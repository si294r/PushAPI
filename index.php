<?php

$start_time = microtime(true);

define('IS_DEVELOPMENT', true);
require '/var/www/token.php';

function show_error($response_code, $status_code, $message) {
    http_response_code($response_code);
    header('Content-Type: application/json');
    echo json_encode(array('status_code' => $status_code, 'message' => $message, 'remote_addr' => $_SERVER["REMOTE_ADDR"]));
    die;
}

$pass_token = isset($_SERVER["REMOTE_ADDR"]) && in_array($_SERVER["REMOTE_ADDR"], ['127.0.0.1']);

if (function_exists("getallheaders")) {
    $headers = getallheaders();
} else {
    $headers['Push-Token'] = isset($_SERVER["HTTP_PUSH_TOKEN"]) ? $_SERVER["HTTP_PUSH_TOKEN"] : "";
}
if (!$pass_token && (!isset($headers['Push-Token']) || $headers['Push-Token'] != PUSH_TOKEN)) {
    show_error(401, "401 Unauthorized", "Invalid Push Token");
}

$query_string = isset($_SERVER["QUERY_STRING"]) ? $_SERVER["QUERY_STRING"] : "";
$params = explode("/", $query_string);

$service = isset($params[0]) ? $params[0] : "";

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] != 'POST') {
    show_error(405, "405 Method Not Allowed", "Invalid Method");
}

try {
    require 'mongodb_helper.php';
    $input = file_get_contents("php://input");

    $json = json_decode($input);

    $data['apps_name'] = isset($json->apps_name) ? $json->apps_name : "";
    $data['device_token'] = isset($json->device_token) ? $json->device_token : "";
    $data['message'] = isset($json->message) ? $json->message : "";

    if (trim($data['apps_name']) == "") {
        show_error(400, "400 Bad Request", "apps_name is empty");
    }
    if (trim($data['device_token']) == "") {
        show_error(400, "400 Bad Request", "device_token is empty");
    }
    if (trim($data['message']) == "") {
        show_error(400, "400 Bad Request", "message is empty");
    }

    $db = get_mongodb(IS_DEVELOPMENT);

    $document = $db->apps->findOne([ 'apps_name' => $data['apps_name']]);

    $affected_row = 0;
    if (is_object($document)) {
        $push_data['apps_name'] = $document->apps_name;
        $push_data['device_token'] = $data['device_token'];
        $push_data['message'] = $data['message'];
        $push_data['message_date'] = gmdate("Y-m-d H:i:s");
        $push_data['sent_status'] = 0;
        $push_data['sent_by'] = defined('PUSH_SENT_BY') ? PUSH_SENT_BY : 'PUSH_SENT_BY';
        $db->push->insertOne($push_data);        
        $affected_row = 1;
        
        $service_result = array("status" => TRUE, "affected_row" => $affected_row);
    } else {
//        $service_result = array("status" => FALSE, "affected_row" => $affected_row, "message" => "Apps not found");
//        var_dump($document);
        show_error(404, "404 Not Found", "Apps not found");
    }
} catch (Exception $ex) {
    show_error(500, "500 Internal Server Error", $ex->getMessage());
}

$end_time = microtime(true);

$service_result['execution_time'] = number_format($end_time - $start_time, 5);
$service_result['memory_usage'] = memory_get_usage(true);

echo json_encode($service_result);
