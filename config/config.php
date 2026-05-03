<?php

declare(strict_types=1);

// -------------------------
// Dotenv loading (optional)
// -------------------------
// Loads values from /.env into $_ENV/getenv. Uses vlucas/phpdotenv if installed,
// otherwise falls back to a tiny parser so the app can still run.
function load_env_file(string $projectRoot): void
{
    $envPath = rtrim($projectRoot, '/\\') . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($envPath)) {
        return;
    }

    $autoload = rtrim($projectRoot, '/\\') . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
        if (class_exists('Dotenv\\Dotenv')) {
            try {
                $dotenv = Dotenv\Dotenv::createImmutable($projectRoot);
                $dotenv->safeLoad();
                return;
            } catch (Throwable $e) {
                // Fall back to simple parser.
            }
        }
    }

    $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));
        if ($key === '') {
            continue;
        }
        // Strip simple quotes
        if ((str_starts_with($val, '"') && str_ends_with($val, '"')) || (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
            $val = substr($val, 1, -1);
        }
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $val;
            @putenv($key . '=' . $val);
        }
    }
}

function env(string $key, mixed $default = null): mixed
{
    $val = $_ENV[$key] ?? getenv($key);
    if ($val === false || $val === null || $val === '') {
        return $default;
    }
    return $val;
}

function env_bool(string $key, bool $default = false): bool
{
    $val = env($key, null);
    if ($val === null) return $default;
    if (is_bool($val)) return $val;
    $val = strtolower(trim((string)$val));
    return in_array($val, ['1', 'true', 'yes', 'on'], true);
}

// Project root on disk
if (!defined('DOMAIN_PATH')) {
    define('DOMAIN_PATH', dirname(__DIR__));
}

// Load /.env
load_env_file(DOMAIN_PATH);

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

// Environment
$appEnv = strtoupper((string)env('APP_ENV', 'local'));
$systemFlag = in_array($appEnv, ['PROD', 'PRODUCTION'], true) ? 'PROD' : 'DEV';
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

if (env_bool('APP_DEBUG', SYSTEM_FLAG === 'DEV')) {
	ini_set('display_errors', '1');
	error_reporting(E_ALL);
} else {
	ini_set('display_errors', '0');
	error_reporting(E_ALL);
}

// HTTP error pages (existing folder is /error)
define('HTTP_403', DOMAIN_PATH . '/error/403.php');
