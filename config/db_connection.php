<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$appEnv = strtoupper((string)env('APP_ENV', 'local'));
$isProd = in_array($appEnv, ['PROD', 'PRODUCTION'], true);

$prefix = $isProd ? 'PROD_DB_' : 'DB_';

$driver = (string)env($prefix . 'CONNECTION', env('DB_CONNECTION', 'mysql'));
$host = (string)env($prefix . 'HOST', '127.0.0.1');
$port = (string)env($prefix . 'PORT', '3306');
$dbName = (string)env($prefix . 'DATABASE', 'gmsai');
$username = (string)env($prefix . 'USERNAME', 'root');
$password = (string)env($prefix . 'PASSWORD', '');

// Create PDO connection
$dsn = sprintf('%s:host=%s;port=%s;dbname=%s;charset=%s', $driver, $host, $port, $dbName, 'utf8mb4');

try {
    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
	// Avoid leaking credentials in production
	error_log('DB connection failed: ' . $e->getMessage());
	if (env_bool('APP_DEBUG', false)) {
		die('Connection failed: ' . $e->getMessage());
	}
	die('Connection failed');
}