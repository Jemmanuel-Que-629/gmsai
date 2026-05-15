<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connection.php';
require_once __DIR__ . '/../../middleware/csrf.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

csrf_init();

function json_response(array $payload, int $status = 200): never
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function require_accounting_role(): void
{
    $uid = $_SESSION['user_id'] ?? null;
    $role = $_SESSION['user_role'] ?? null;

    if (!$uid) {
        json_response(['success' => false, 'error' => 'Unauthorized'], 401);
    }
    if ($role !== 'ACCOUNTING') {
        json_response(['success' => false, 'error' => 'Forbidden'], 403);
    }
}

function require_post_and_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_response(['success' => false, 'error' => 'Invalid request'], 405);
    }

    $token = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : null;
    if (!csrf_validate($token)) {
        json_response(['success' => false, 'error' => 'CSRF validation failed'], 400);
    }
}

function clean_string(mixed $val, int $maxLen, bool $required = false): string
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

require_accounting_role();
require_post_and_csrf();

$action = isset($_POST['action']) ? (string)$_POST['action'] : '';
$employeeId = isset($_POST['employee_id']) ? (int)$_POST['employee_id'] : 0;

if ($employeeId <= 0) {
    json_response(['success' => false, 'error' => 'Invalid employee id'], 400);
}

try {
    if ($action === 'update') {
        $firstName = clean_string($_POST['first_name'] ?? '', 100, true);
        $middleName = clean_string($_POST['middle_name'] ?? '', 100, false);
        $lastName = clean_string($_POST['last_name'] ?? '', 100, true);
        $position = clean_string($_POST['position'] ?? '', 100, true);
        $department = clean_string($_POST['department'] ?? '', 100, true);
        $salaryType = clean_string($_POST['salary_type'] ?? '', 20, true);
        $locationId = isset($_POST['location_id']) ? (int)$_POST['location_id'] : 0;

        if ($firstName === '' || $lastName === '' || $position === '' || $department === '' || $salaryType === '' || $locationId <= 0) {
            json_response(['success' => false, 'error' => 'Missing required fields'], 400);
        }

        $allowedSalaryTypes = ['daily', 'weekly', 'bi-weekly', 'semi-monthly', 'monthly'];
        if (!in_array($salaryType, $allowedSalaryTypes, true)) {
            // Backwards-compat for older schema dumps.
            if ($salaryType !== 'monthly') {
                json_response(['success' => false, 'error' => 'Invalid salary type'], 400);
            }
        }

        // Verify location exists
        $stmt = $conn->prepare('SELECT location_id, location_name FROM location_rate WHERE location_id = :id LIMIT 1');
        $stmt->execute([':id' => $locationId]);
        $location = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$location) {
            json_response(['success' => false, 'error' => 'Invalid location'], 400);
        }

        $sql = 'UPDATE employees SET first_name = :first, middle_name = :middle, last_name = :last, position = :pos, department = :dept, location_id = :loc, salary_type = :stype WHERE employee_id = :id LIMIT 1';
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':first' => $firstName,
            ':middle' => ($middleName !== '' ? $middleName : null),
            ':last' => $lastName,
            ':pos' => $position,
            ':dept' => $department,
            ':loc' => $locationId,
            ':stype' => $salaryType,
            ':id' => $employeeId,
        ]);

        json_response([
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
        // Soft-archive requires employees.is_archived (see migration).
        $stmt = $conn->prepare('UPDATE employees SET is_archived = 1 WHERE employee_id = :id LIMIT 1');
        $stmt->execute([':id' => $employeeId]);
        json_response(['success' => true]);
    }

    json_response(['success' => false, 'error' => 'Unknown action'], 400);
} catch (PDOException $e) {
    $msg = 'Database error';
    // Friendly hint when archive column doesn't exist yet.
    if ($action === 'archive' && str_contains(strtolower($e->getMessage()), 'is_archived')) {
        $msg = 'Archive feature requires DB migration (add employees.is_archived)';
    }
    json_response(['success' => false, 'error' => $msg], 500);
} catch (Throwable $e) {
    json_response(['success' => false, 'error' => 'Server error'], 500);
}
