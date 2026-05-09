<?php

declare(strict_types=1);

function csrf_init(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        // Must be started by the caller. We do not start sessions here.
        return;
    }
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }
    csrf_init();
    return (string)($_SESSION['csrf_token'] ?? '');
}

function csrf_validate(?string $token): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    $expected = (string)($_SESSION['csrf_token'] ?? '');
    if ($expected === '' || $token === null) {
        return false;
    }
    return hash_equals($expected, $token);
}
