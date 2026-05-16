<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connection.php';
require_once __DIR__ . '/../../middleware/csrf.php';
require_once __DIR__ . '/../../repository/employee_repository.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

csrf_init();

function employee_json_response(array $payload, int $status = 200): never
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function employee_require_accounting_role(): void
{
    $uid = $_SESSION['user_id'] ?? null;
    $role = $_SESSION['user_role'] ?? null;

    if (!$uid) {
        employee_json_response(['success' => false, 'error' => 'Unauthorized'], 401);
    }
    if ($role !== 'ACCOUNTING') {
        employee_json_response(['success' => false, 'error' => 'Forbidden'], 403);
    }
}

function employee_require_post_and_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        employee_json_response(['success' => false, 'error' => 'Invalid request'], 405);
    }

    $token = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : null;
    if (!csrf_validate($token)) {
        employee_json_response(['success' => false, 'error' => 'CSRF validation failed'], 400);
    }
}

function employee_clean_string(mixed $val, int $maxLen, bool $required = false): string
{
    if (!is_scalar($val)) {
        return '';
    }

    $s = trim((string)$val);
    $s = str_replace("\0", '', $s);

    if ($maxLen > 0 && strlen($s) > $maxLen) {
        $s = substr($s, 0, $maxLen);
    }

    if ($required && $s === '') {
        return '';
    }

    return $s;
}

employee_require_accounting_role();
employee_require_post_and_csrf();

$action = isset($_POST['action']) ? (string)$_POST['action'] : '';
$employeeId = isset($_POST['employee_id']) ? (int)$_POST['employee_id'] : 0;

if ($employeeId <= 0) {
    employee_json_response(['success' => false, 'error' => 'Invalid employee id'], 400);
}

try {
    if ($action === 'update') {
        $firstName = employee_clean_string($_POST['first_name'] ?? '', 100, true);
        $middleName = employee_clean_string($_POST['middle_name'] ?? '', 100, false);
        $lastName = employee_clean_string($_POST['last_name'] ?? '', 100, true);
        $position = employee_clean_string($_POST['position'] ?? '', 100, true);
        $department = employee_clean_string($_POST['department'] ?? '', 100, true);
        $salaryType = employee_clean_string($_POST['salary_type'] ?? '', 20, true);
        $locationId = isset($_POST['location_id']) ? (int)$_POST['location_id'] : 0;

        if ($firstName === '' || $lastName === '' || $position === '' || $department === '' || $salaryType === '' || $locationId <= 0) {
            employee_json_response(['success' => false, 'error' => 'Missing required fields'], 400);
        }

        $allowedSalaryTypes = ['daily', 'weekly', 'bi-weekly', 'semi-monthly', 'monthly'];
        if (!in_array($salaryType, $allowedSalaryTypes, true)) {
            employee_json_response(['success' => false, 'error' => 'Invalid salary type'], 400);
        }

        $location = employee_find_location($conn, $locationId);
        if (!$location) {
            employee_json_response(['success' => false, 'error' => 'Invalid location'], 400);
        }

        employee_update($conn, $employeeId, [
            'first_name' => $firstName,
            'middle_name' => ($middleName !== '' ? $middleName : null),
            'last_name' => $lastName,
            'position' => $position,
            'department' => $department,
            'location_id' => $locationId,
            'salary_type' => $salaryType,
        ]);

        employee_json_response([
            'success' => true,
            'employee' => [
                'employee_id' => $employeeId,
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
                'position' => $position,
                'department' => $department,
                'location_id' => $locationId,
                'location_name' => (string)($location['location_name'] ?? ''),
                'salary_type' => $salaryType,
            ],
        ]);
    }

    if ($action === 'archive') {
        $hasArchiveCol = employee_has_archive_column($conn);
        if (!$hasArchiveCol) {
            employee_json_response(['success' => false, 'error' => 'Archive feature requires DB migration (add employees.is_archived)'], 500);
        }

        employee_archive($conn, $employeeId);
        employee_json_response(['success' => true]);
    }

    employee_json_response(['success' => false, 'error' => 'Unknown action'], 400);
} catch (PDOException $e) {
    employee_json_response(['success' => false, 'error' => 'Database error'], 500);
} catch (Throwable $e) {
    employee_json_response(['success' => false, 'error' => 'Server error'], 500);
}
