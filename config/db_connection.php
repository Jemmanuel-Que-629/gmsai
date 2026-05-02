<?php

date_default_timezone_set('Asia/Manila');

$local_addresses = ['127.0.0.1', '::1'];

$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';

if (in_array($remoteAddr, $local_addresses, true)) {
    $servername = "localhost";
    $username   = "root";
    $password   = "";
    $dbname     = "gmsai";
} else {
    $servername = "prod_name";
    $username   = "prod_user";
    $password   = "prod_pass";
    $dbname     = "prod_db";
}

// Create PDO connection
$dsn = "mysql:host={$servername};dbname={$dbname};charset=utf8mb4";

try {
    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die('Connection failed');
}