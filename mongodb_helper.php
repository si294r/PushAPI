<?php

defined('IS_DEVELOPMENT') OR exit('No direct script access allowed');

require '/var/www/vendor/autoload.php';


// get mongodb object database
function get_mongodb($is_development = false) {

/* Example file content '/var/www/mongodb.php'
 
    $config['username'] = '***********';
    $config['password'] = '***********';
    $config['hostname'] = 'api.alegrium.com';
    $config['database'] = 'billionaire_prod';
    $config['database_dev'] = 'billionaire_dev';
    $config['options'] = array('ssl' => true);
 
 */
    include '/var/www/mongodb_push.php';

    $database = $is_development == true ? $config['database_dev'] : $config['database'];

    $connection_string = "mongodb://"
            . $config['username'] . ":"
            . $config['password'] . "@"
            . $config['hostname'] . "/"
            . $database;

    $client = new MongoDB\Client($connection_string, $config['options']); // create object client 

    return $client->$database; // select database
}

// get mongodb document object id
function bson_oid($id) {
    return new MongoDB\BSON\ObjectID($id);
}

// convert BSON Object Document to Array PHP
function bson_document_to_array($document) {
    $array = NULL;

    if (is_object($document)) {
        foreach ($document as $k => $v) {
            if (is_object($v)) {
                $array[$k] = (string) $v;
            } else {
                $array[$k] = $v;
            }
        }
    }
    return $array;
}

// convert BSON Object Documents to Array PHP
function bson_documents_to_array($documents) {
    $array = array();

    if (is_object($documents)) {
        foreach ($documents as $document) {
            $array[] = bson_document_to_array($document);
        }
    }
    return $array;
}
