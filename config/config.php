<?php

declare(strict_types=1);

// Project root on disk
if (!defined('DOMAIN_PATH')) {
    define('DOMAIN_PATH', dirname(__DIR__));
}

// Timezone
define('DEFAULT_TIMEZONE', 'Asia/Manila');
ini_set('date.timezone', DEFAULT_TIMEZONE);
date_default_timezone_set(DEFAULT_TIMEZONE);

// Dates
define('YEAR', date('Y'));
define('MONTH', date('m'));
define('DAY', date('d'));
define('DATE_NOW', date('Y-m-d'));
define('TIME_NOW', date('H:i:s'));
define('DATE_TIME', DATE_NOW . ' ' . TIME_NOW);

// Environment (simple: local IPs = DEV)
$localAddresses = ['127.0.0.1', '::1'];
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
$systemFlag = in_array($remoteAddr, $localAddresses, true) ? 'DEV' : 'PROD';
define('SYSTEM_FLAG', $systemFlag);

function get_protocol(): string
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
}

function base_url(): string
{
    $protocol = get_protocol();
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $appRoot = realpath(DOMAIN_PATH) ?: '';

    if ($docRoot !== '' && $appRoot !== '' && str_starts_with($appRoot, $docRoot)) {
        $relative = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
        $relative = '/' . ltrim($relative, '/');
        return rtrim($protocol . $host . $relative, '/') . '/';
    }

    return rtrim($protocol . $host, '/') . '/';
}

function active_page(): string
{
    return basename($_SERVER['PHP_SELF'] ?? '', '.php');
}

define('BASE_URL', base_url());
define('ACTIVE_PAGE', active_page());

// System identity (GMSAI)
define('SYSTEM_NAME', 'Green Meadows Security Agency, Inc.');
define('SYSTEM_ACRONYM', 'GMSAI');
define('SYSTEM_SUB_NAME', 'Security & Commitment');
define('YEAR_CREATED', '2026');
define('FILE_VERSION', '1.0.0');

// Upload limits (per INSTRUCTIONS.TXT: 10MB)
define('UPLOAD_MAX_BYTES', 10 * 1024 * 1024);

// Paths
define('ERROR_LOG_PATH', DOMAIN_PATH . '/error/php_error.log');
define('UPLOADS_PATH', DOMAIN_PATH . '/uploads/');

// URLs
define('LOGO_URL', BASE_URL . 'images/logo.jpg?v=' . FILE_VERSION);

// Error handling (per INSTRUCTIONS.TXT)
ini_set('log_errors', '1');
ini_set('error_log', ERROR_LOG_PATH);

if (SYSTEM_FLAG === 'DEV') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

// HTTP error pages (existing folder is /error)
define('HTTP_403', DOMAIN_PATH . '/error/403.php');
