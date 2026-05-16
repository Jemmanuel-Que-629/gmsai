<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * @param string|array<int, string> $allowedRole One role or a list of roles.
 */
function checkAccess(string|array $allowedRole): void
{
    $allowedRoles = is_array($allowedRole) ? $allowedRole : [$allowedRole];

    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . 'login.php?error=' . urlencode('Please login'), true, 303);
        exit();
    }

    $userRole = (string)($_SESSION['user_role'] ?? '');
    if (!in_array($userRole, $allowedRoles, true)) {
        header('Location: ' . BASE_URL . 'error/403.php', true, 303);
        exit();
    }
}
