<?php

declare(strict_types=1);

/**
 * Basic request guard:
 * - Reject oversized POST/PUT/PATCH bodies
 * - Reject extremely long query strings
 */

function rg_int_env(string $key, int $default): int
{
    if (function_exists('env')) {
        $v = env($key, null);
        if ($v !== null && is_numeric($v)) {
            return (int)$v;
        }
    }
    return $default;
}

function enforce_request_guards(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    $maxBytes = rg_int_env('REQUEST_MAX_BYTES', 1024 * 1024); // 1MB default for non-upload POST bodies
    $maxQueryChars = rg_int_env('QUERY_MAX_CHARS', 2048);

    $query = (string)($_SERVER['QUERY_STRING'] ?? '');
    if ($maxQueryChars > 0 && strlen($query) > $maxQueryChars) {
        http_response_code(414);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Request-URI Too Long';
        exit;
    }

    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($contentType, 'multipart/form-data')) {
            // Allow uploads up to UPLOAD_MAX_BYTES (+ 1MB overhead) by default.
            $uploadMax = defined('UPLOAD_MAX_BYTES') ? (int)UPLOAD_MAX_BYTES : (10 * 1024 * 1024);
            $maxBytes = max($maxBytes, $uploadMax + (1024 * 1024));
        }

        $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($maxBytes > 0 && $len > $maxBytes) {
            http_response_code(413);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Payload Too Large';
            exit;
        }
    }
}
