<?php

declare(strict_types=1);

/**
 * File-based rate limiter.
 *
 * Storage: DOMAIN_PATH/storage/ratelimit
 *
 * Notes:
 * - Uses REMOTE_ADDR by default (does not trust X-Forwarded-For unless TRUST_PROXY=true)
 * - Intended to be included early (before output)
 */

function rl_trust_proxy(): bool
{
    return function_exists('env_bool') ? env_bool('TRUST_PROXY', false) : false;
}

function rl_client_ip(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');

    if (rl_trust_proxy()) {
        $xff = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($xff !== '') {
            // Use the first IP in the chain.
            $parts = array_map('trim', explode(',', $xff));
            if (isset($parts[0]) && $parts[0] !== '') {
                $ip = $parts[0];
            }
        }
    }

    return $ip !== '' ? $ip : 'unknown';
}

function rl_storage_dir(): string
{
    if (defined('DOMAIN_PATH')) {
        return rtrim((string)DOMAIN_PATH, '/\\') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'ratelimit';
    }

    // Fallback: system temp.
    return rtrim((string)sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'ratelimit';
}

/**
 * Comma-separated allowlist of IPs (and/or IPv4 CIDRs) that bypass rate limiting.
 * Example: RATE_LIMIT_BYPASS_IPS="127.0.0.1,::1,192.168.1.10,192.168.0.0/16"
 *
 * IMPORTANT: Do not set this broadly in production.
 * If you run behind a reverse proxy, ensure REMOTE_ADDR reflects real clients
 * (or enable TRUST_PROXY cautiously) before relying on this.
 *
 * @return string[]
 */
function rl_bypass_ip_rules(): array
{
    if (!function_exists('env')) {
        return [];
    }
    $raw = (string)env('RATE_LIMIT_BYPASS_IPS', '');
    if (trim($raw) === '') {
        return [];
    }
    $parts = preg_split('/[;,\n\r]+/', $raw);
    if (!is_array($parts)) {
        return [];
    }
    $rules = [];
    foreach ($parts as $p) {
        $p = trim((string)$p);
        if ($p !== '') {
            $rules[] = $p;
        }
    }
    return $rules;
}

function rl_ipv4_in_cidr(string $ip, string $cidr): bool
{
    if (!str_contains($cidr, '/')) {
        return false;
    }

    [$base, $bits] = explode('/', $cidr, 2);
    $base = trim($base);
    $bits = trim($bits);

    if ($base === '' || $bits === '' || !ctype_digit($bits)) {
        return false;
    }

    $ipLong = ip2long($ip);
    $baseLong = ip2long($base);
    if ($ipLong === false || $baseLong === false) {
        return false;
    }

    $maskBits = (int)$bits;
    if ($maskBits < 0 || $maskBits > 32) {
        return false;
    }

    $mask = $maskBits === 0 ? 0 : (-1 << (32 - $maskBits));
    return (($ipLong & $mask) === ($baseLong & $mask));
}

function rl_is_bypassed_ip(string $ip): bool
{
    if ($ip === '' || $ip === 'unknown') {
        return false;
    }

    $rules = rl_bypass_ip_rules();
    if ($rules === []) {
        return false;
    }

    foreach ($rules as $rule) {
        if ($rule === $ip) {
            return true;
        }

        // CIDR support for IPv4
        if (str_contains($rule, '/') && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if (rl_ipv4_in_cidr($ip, $rule)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * @return array{allowed:bool, remaining:int, reset:int}
 */
function rate_limit(string $namespace, int $max, int $windowSeconds, ?string $identifier = null): array
{
    $max = max(1, $max);
    $windowSeconds = max(1, $windowSeconds);

    $id = $identifier ?? rl_client_ip();

    // Optional bypass: if the identifier is an IP (or comes from client IP), do not limit.
    $ipToCheck = null;
    if ($identifier === null) {
        $ipToCheck = $id;
    } elseif (filter_var($identifier, FILTER_VALIDATE_IP)) {
        $ipToCheck = $identifier;
    }
    if (is_string($ipToCheck) && rl_is_bypassed_ip($ipToCheck)) {
        $now = time();
        return ['allowed' => true, 'remaining' => $max, 'reset' => $now + $windowSeconds];
    }

    $key = hash('sha256', $namespace . '|' . $id);

    $dir = rl_storage_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $key . '.json';
    $now = time();

    $fp = @fopen($path, 'c+');
    if ($fp === false) {
        // If storage is not writable, fail-open (do not lock out users).
        return ['allowed' => true, 'remaining' => $max - 1, 'reset' => $now + $windowSeconds];
    }

    try {
        if (!flock($fp, LOCK_EX)) {
            return ['allowed' => true, 'remaining' => $max - 1, 'reset' => $now + $windowSeconds];
        }

        $raw = '';
        $size = @filesize($path);
        if (is_int($size) && $size > 0) {
            rewind($fp);
            $raw = (string)stream_get_contents($fp);
        }

        $state = [
            'count' => 0,
            'reset' => $now + $windowSeconds,
        ];

        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['count'], $decoded['reset'])) {
                $state['count'] = (int)$decoded['count'];
                $state['reset'] = (int)$decoded['reset'];
            }
        }

        if ($state['reset'] <= $now) {
            $state['count'] = 0;
            $state['reset'] = $now + $windowSeconds;
        }

        $allowed = $state['count'] < $max;
        if ($allowed) {
            $state['count']++;
        }

        $remaining = max(0, $max - $state['count']);

        // Persist
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($state));
        fflush($fp);
        flock($fp, LOCK_UN);

        return ['allowed' => $allowed, 'remaining' => $remaining, 'reset' => $state['reset']];
    } finally {
        fclose($fp);
    }
}

function rl_wants_json(): bool
{
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $xhr = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    return str_contains($accept, 'application/json') || $xhr === 'xmlhttprequest';
}

function enforce_rate_limit(string $namespace, int $max, int $windowSeconds, ?string $identifier = null): void
{
    $res = rate_limit($namespace, $max, $windowSeconds, $identifier);

    header('X-RateLimit-Limit: ' . $max);
    header('X-RateLimit-Remaining: ' . $res['remaining']);
    header('X-RateLimit-Reset: ' . $res['reset']);

    if ($res['allowed']) {
        return;
    }

    $retryAfter = max(1, $res['reset'] - time());
    header('Retry-After: ' . $retryAfter);

    if (!headers_sent()) {
        http_response_code(429);
        if (rl_wants_json()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'Too many requests',
                'retry_after_seconds' => $retryAfter,
            ]);
            exit;
        }

        header('Content-Type: text/plain; charset=utf-8');
        echo "Too many requests. Please try again later.";
        exit;
    }
}
