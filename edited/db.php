<?php
// Database connection settings - update these for your environment
$DB_HOST = 'localhost';
$DB_NAME = 'edge_trading';
$DB_USER = 'root';
$DB_PASS = '';

function get_db() {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($mysqli->connect_error) {
        die('DB connection error: ' . $mysqli->connect_error);
    }
    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}
