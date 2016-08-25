<?php

/**
 * @param $http2ch          the curl connection
 * @param $http2_server     the Apple server url
 * @param $apple_cert       the path to the certificate
 * @param $app_bundle_id    the app bundle id
 * @param $message          the payload to send (JSON)
 * @param $token            the token of the device
 * @return mixed            the status code (see https://developer.apple.com/library/ios/documentation/NetworkingInternet/Conceptual/RemoteNotificationsPG/Chapters/APNsProviderAPI.html#//apple_ref/doc/uid/TP40008194-CH101-SW18)
 */
function sendHTTP2Push($http2ch, $http2_server, $apple_cert, $app_bundle_id, $message, $token) {

    // url (endpoint)
    $url = "{$http2_server}/3/device/{$token}";

    // certificate
    $cert = realpath($apple_cert);

    // headers
    $headers = array(
        "apns-topic: {$app_bundle_id}",
        "User-Agent: PHP-CURL Alegrium"
    );

    // other curl options
    curl_setopt_array($http2ch, array(
        CURLOPT_URL => "{$url}",
        CURLOPT_PORT => 443,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POST => TRUE,
        CURLOPT_POSTFIELDS => $message,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSLCERT => $cert,
        CURLOPT_HEADER => 1
    ));

    // go...
    $result = curl_exec($http2ch);
    if ($result === FALSE) {
//        throw new Exception('Curl failed with error: ' . curl_error($http2ch));
        $result = 'Curl failed with error: ' . curl_error($http2ch);
    }

    return $result;
}

$start_time = microtime(true);

// open connection
//if (!defined('CURL_HTTP_VERSION_2_0')) {
//    define('CURL_HTTP_VERSION_2_0', 3);
//}
$http2ch = curl_init();
curl_setopt($http2ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);

define('IS_DEVELOPMENT', true);
require 'mongodb_helper.php';

$db = get_mongodb(IS_DEVELOPMENT);

$apps = $db->apps->find();
$arr_apps = bson_documents_to_array($apps);
//var_dump($arr_apps);
$map_apps = array();
$folder_cert = "/tmp/push_cron_" . gmdate('YmdHis');
mkdir($folder_cert);

foreach ($arr_apps as $value) {
    $map_apps[$value['apps_name']] = $value;

    if (isset($value['pem_file']) && $value['pem_file'] != "") {
        file_put_contents($folder_cert . "/" . $value['pem_file'], $value['pem_content']);
    }
}
//var_dump($map_apps);

$push_sent_by = defined('PUSH_SENT_BY') ? PUSH_SENT_BY : 'PUSH_SENT_BY';
$documents = $db->push->find([ 'sent_by' => $push_sent_by, 'sent_status' => 0]);

$arr_docs = bson_documents_to_array($documents);

foreach ($arr_docs as $value) {

    if (isset($map_apps[$value['apps_name']])) {
        $http2_server = 'https://' . $map_apps[$value['apps_name']]['apps_url'];
        $apple_cert = $folder_cert . "/" . $map_apps[$value['apps_name']]['pem_file'];
        $app_bundle_id = $map_apps[$value['apps_name']]['bundle_id'];

        $message = json_encode(array("aps" => array("alert" => $value['message'], "sound" => "default")));
        $result = sendHTTP2Push($http2ch, $http2_server, $apple_cert, $app_bundle_id, $message, $value['device_token']);
        $status = curl_getinfo($http2ch, CURLINFO_HTTP_CODE);
    } else {
        $status = -1;
        $result = "apps_name is not available";
    }

//    $result = "OK";
//    $status = 200;

    $update_data['sent_status'] = $status;
    $update_data['sent_date'] = gmdate('Y-m-d H:i:s');
    $update_data['sent_result'] = $result;
    $db->push->updateOne(['_id' => bson_oid((string) $value['_id'])], ['$set' => $update_data]);
}

curl_close($http2ch);

exec("rm -r " . $folder_cert);

echo "Process Push: " . count($arr_docs);
