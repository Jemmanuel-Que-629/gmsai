<?php

declare(strict_types=1);

/**
 * Global security headers.
 *
 * Goals:
 * - Prevent clickjacking
 * - Prevent MIME sniffing
 * - Reduce referrer leakage
 * - Add a practical CSP (nonce-based for inline scripts)
 * - Add HSTS only when HTTPS is used
 */

function csp_nonce(): string
{
    static $nonce = null;
    if (is_string($nonce) && $nonce !== '') {
        return $nonce;
    }

    // 16 bytes -> 24 chars base64 (plus padding). Safe for header + HTML attribute.
    $nonce = base64_encode(random_bytes(16));
    return $nonce;
}

function send_security_headers(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    if (headers_sent()) {
        return;
    }

    // Basic hardening headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    // HSTS only when HTTPS is actually used (avoid breaking local HTTP dev)
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    // CSP
    // Notes:
    // - We keep style-src 'unsafe-inline' because many pages use <style> blocks.
    // - We do NOT allow unsafe-inline for scripts. Instead we use a nonce.
    $nonce = csp_nonce();

    $csp = [
        "default-src 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'none'",
        "form-action 'self'",
        // Allow images from self + data (for inline previews/placeholders)
        "img-src 'self' data:",
        // Allow fonts from Google Fonts
        "font-src 'self' https://fonts.gstatic.com data:",
        // Allow styles from self + google fonts + jsdelivr; keep unsafe-inline for existing <style>
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://unpkg.com",
        // Allow scripts from self + CDNs, but require nonce for inline scripts
        "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://unpkg.com",
        // Most pages are same-origin; expand if you later call external APIs.
        "connect-src 'self'",
        // Reduce mixed-content issues in production if HTTPS is used
        $isHttps ? 'upgrade-insecure-requests' : null,
    ];

    $csp = array_values(array_filter($csp, static fn($v) => is_string($v) && $v !== ''));
    header('Content-Security-Policy: ' . implode('; ', $csp));
}
