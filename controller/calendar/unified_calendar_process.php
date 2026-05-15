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
    if (($_SESSION['user_id'] ?? null) === null) {
        json_response(['success' => false, 'error' => 'Unauthorized'], 401);
    }
    if (($_SESSION['user_role'] ?? null) !== 'ACCOUNTING') {
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

function read_action(): string
{
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    return is_string($action) ? trim($action) : '';
}

function read_int(mixed $val): int
{
    if (!is_scalar($val)) {
        return 0;
    }
    return (int)$val;
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

function parse_date(string $ymd): ?DateTime
{
    $dt = DateTime::createFromFormat('Y-m-d', $ymd);
    if (!$dt) {
        return null;
    }
    if ($dt->format('Y-m-d') !== $ymd) {
        return null;
    }
    return $dt;
}

function validate_year(int $year): void
{
    if ($year < 2000 || $year > 2100) {
        json_response(['success' => false, 'error' => 'Invalid year'], 400);
    }
}

function validate_month(int $month): void
{
    if ($month < 1 || $month > 12) {
        json_response(['success' => false, 'error' => 'Invalid month'], 400);
    }
}

function validate_holiday_type(string $type): string
{
    $t = strtolower(trim($type));
    $allowed = ['regular', 'special_non_working', 'special_working', 'company'];
    if (!in_array($t, $allowed, true)) {
        json_response(['success' => false, 'error' => 'Invalid holiday type'], 400);
    }
    return $t;
}

function default_payroll_rate_id(string $holidayType): ?int
{
    return match ($holidayType) {
        'regular' => 1,
        'special_non_working' => 2,
        'special_working' => 3,
        'company' => 4,
        default => null,
    };
}

function clone_fixed_recurring_holidays(PDO $conn, int $targetYear): array
{
    $successCount = 0;
    $skippedCount = 0;

    // Use DISTINCT month/day + name as the source-of-truth for recurring holidays
    // to avoid re-cloning duplicates from multiple years.
    $srcStmt = $conn->prepare(
        'SELECT DISTINCT holiday_name, holiday_type, payroll_rate_id, is_paid, MONTH(holiday_date) AS m, DAY(holiday_date) AS d '
        . 'FROM holidays WHERE is_recurring = 1'
    );
    $srcStmt->execute();
    $sources = $srcStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $existsStmt = $conn->prepare('SELECT 1 FROM holidays WHERE holiday_date = :holiday_date AND holiday_name = :holiday_name LIMIT 1');
    $insertStmt = $conn->prepare('INSERT INTO holidays (holiday_date, holiday_name, holiday_type, payroll_rate_id, is_paid, is_recurring, applicable_year) VALUES (:holiday_date, :holiday_name, :holiday_type, :payroll_rate_id, :is_paid, 1, :applicable_year)');

    foreach ($sources as $row) {
        $srcName = trim((string)($row['holiday_name'] ?? ''));
        $srcType = (string)($row['holiday_type'] ?? '');
        $month = (int)($row['m'] ?? 0);
        $day = (int)($row['d'] ?? 0);

        if ($srcName === '' || $srcType === '' || $month < 1 || $month > 12 || $day < 1 || $day > 31) {
            $skippedCount++;
            continue;
        }

        // Fixed recurring only.
        if (!checkdate($month, $day, $targetYear)) {
            $skippedCount++;
            continue;
        }

        $newDate = sprintf('%04d-%02d-%02d', $targetYear, $month, $day);

        $existsStmt->execute([
            ':holiday_date' => $newDate,
            ':holiday_name' => $srcName,
        ]);
        if ($existsStmt->fetch()) {
            $skippedCount++;
            continue;
        }

        $holidayType = validate_holiday_type($srcType);
        $rate = $row['payroll_rate_id'] ?? null;
        if ($rate === null) {
            $rate = default_payroll_rate_id($holidayType);
        }

        $insertStmt->execute([
            ':holiday_date' => $newDate,
            ':holiday_name' => $srcName,
            ':holiday_type' => $holidayType,
            ':payroll_rate_id' => $rate,
            ':is_paid' => isset($row['is_paid']) ? (int)$row['is_paid'] : 1,
            ':applicable_year' => (string)$targetYear,
        ]);

        $successCount++;
    }

    return ['success_count' => $successCount, 'skipped_count' => $skippedCount];
}

require_accounting_role();

$action = read_action();

try {
    if ($action === 'list') {
        $year = read_int($_GET['year'] ?? $_POST['year'] ?? date('Y'));
        $month = read_int($_GET['month'] ?? $_POST['month'] ?? date('n'));

        validate_year($year);
        validate_month($month);

        // Auto-generate recurring holidays for future years (e.g., 2027+) so they show up automatically.
        $systemYear = (int)date('Y');
        if ($year > $systemYear) {
            try {
                $conn->beginTransaction();
                clone_fixed_recurring_holidays($conn, $year);
                $conn->commit();
            } catch (Throwable $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                // Non-fatal: still return whatever exists.
            }
        }

        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = (new DateTime($start))->modify('last day of this month')->format('Y-m-d');

        $stmt = $conn->prepare('SELECT holiday_id, holiday_date, holiday_name, holiday_type, is_recurring FROM holidays WHERE applicable_year = :year AND holiday_date BETWEEN :start AND :end ORDER BY holiday_date ASC, holiday_name ASC');
        $stmt->execute([
            ':year' => (string)$year,
            ':start' => $start,
            ':end' => $end,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        json_response([
            'success' => true,
            'year' => $year,
            'month' => $month,
            'holidays' => $rows,
        ]);
    }

    if ($action === 'create') {
        require_post_and_csrf();

        $holidayDate = clean_string($_POST['holiday_date'] ?? '', 10, true);
        $holidayName = clean_string($_POST['holiday_name'] ?? '', 100, true);
        $holidayType = validate_holiday_type(clean_string($_POST['holiday_type'] ?? '', 30, true));

        $isRecurring = read_int($_POST['is_recurring'] ?? 0) ? 1 : 0;
        $isPaid = read_int($_POST['is_paid'] ?? 1) ? 1 : 0;
        $rateId = read_int($_POST['payroll_rate_id'] ?? 0);
        $payrollRateId = $rateId > 0 ? $rateId : default_payroll_rate_id($holidayType);

        $dt = parse_date($holidayDate);
        if (!$dt) {
            json_response(['success' => false, 'error' => 'Invalid holiday date'], 400);
        }
        $year = (int)$dt->format('Y');
        validate_year($year);

        $existsStmt = $conn->prepare('SELECT 1 FROM holidays WHERE holiday_date = :d AND holiday_name = :n LIMIT 1');
        $existsStmt->execute([':d' => $holidayDate, ':n' => $holidayName]);
        if ($existsStmt->fetch()) {
            json_response(['success' => false, 'error' => 'Holiday already exists'], 409);
        }

        $stmt = $conn->prepare('INSERT INTO holidays (holiday_date, holiday_name, holiday_type, payroll_rate_id, is_paid, is_recurring, applicable_year) VALUES (:d, :n, :t, :r, :p, :rec, :y)');
        $stmt->execute([
            ':d' => $holidayDate,
            ':n' => $holidayName,
            ':t' => $holidayType,
            ':r' => $payrollRateId,
            ':p' => $isPaid,
            ':rec' => $isRecurring,
            ':y' => (string)$year,
        ]);

        $newId = (int)$conn->lastInsertId();
        json_response([
            'success' => true,
            'holiday' => [
                'holiday_id' => $newId,
                'holiday_date' => $holidayDate,
                'holiday_name' => $holidayName,
                'holiday_type' => $holidayType,
                'is_recurring' => $isRecurring,
            ],
        ]);
    }

    if ($action === 'update') {
        require_post_and_csrf();

        $holidayId = read_int($_POST['holiday_id'] ?? 0);
        if ($holidayId <= 0) {
            json_response(['success' => false, 'error' => 'Invalid holiday id'], 400);
        }

        $holidayDate = clean_string($_POST['holiday_date'] ?? '', 10, true);
        $holidayName = clean_string($_POST['holiday_name'] ?? '', 100, true);
        $holidayType = validate_holiday_type(clean_string($_POST['holiday_type'] ?? '', 30, true));
        $isRecurring = read_int($_POST['is_recurring'] ?? 0) ? 1 : 0;
        $isPaid = read_int($_POST['is_paid'] ?? 1) ? 1 : 0;

        $rateId = read_int($_POST['payroll_rate_id'] ?? 0);
        $payrollRateId = $rateId > 0 ? $rateId : default_payroll_rate_id($holidayType);

        $dt = parse_date($holidayDate);
        if (!$dt) {
            json_response(['success' => false, 'error' => 'Invalid holiday date'], 400);
        }
        $year = (int)$dt->format('Y');
        validate_year($year);

        $existsStmt = $conn->prepare('SELECT 1 FROM holidays WHERE holiday_date = :d AND holiday_name = :n AND holiday_id <> :id LIMIT 1');
        $existsStmt->execute([':d' => $holidayDate, ':n' => $holidayName, ':id' => $holidayId]);
        if ($existsStmt->fetch()) {
            json_response(['success' => false, 'error' => 'Another holiday with same date and name exists'], 409);
        }

        $stmt = $conn->prepare('UPDATE holidays SET holiday_date = :d, holiday_name = :n, holiday_type = :t, payroll_rate_id = :r, is_paid = :p, is_recurring = :rec, applicable_year = :y WHERE holiday_id = :id LIMIT 1');
        $stmt->execute([
            ':d' => $holidayDate,
            ':n' => $holidayName,
            ':t' => $holidayType,
            ':r' => $payrollRateId,
            ':p' => $isPaid,
            ':rec' => $isRecurring,
            ':y' => (string)$year,
            ':id' => $holidayId,
        ]);

        json_response([
            'success' => true,
            'holiday' => [
                'holiday_id' => $holidayId,
                'holiday_date' => $holidayDate,
                'holiday_name' => $holidayName,
                'holiday_type' => $holidayType,
                'is_recurring' => $isRecurring,
            ],
        ]);
    }

    if ($action === 'delete') {
        require_post_and_csrf();

        $holidayId = read_int($_POST['holiday_id'] ?? 0);
        if ($holidayId <= 0) {
            json_response(['success' => false, 'error' => 'Invalid holiday id'], 400);
        }

        $stmt = $conn->prepare('DELETE FROM holidays WHERE holiday_id = :id LIMIT 1');
        $stmt->execute([':id' => $holidayId]);

        json_response(['success' => true]);
    }

    if ($action === 'clone_recurring') {
        require_post_and_csrf();

        $targetYear = read_int($_POST['target_year'] ?? $_POST['year'] ?? 0);
        validate_year($targetYear);

        $conn->beginTransaction();
        $result = clone_fixed_recurring_holidays($conn, $targetYear);
        $conn->commit();

        json_response([
            'success' => true,
            'success_count' => (int)($result['success_count'] ?? 0),
            'skipped_count' => (int)($result['skipped_count'] ?? 0),
            'message' => "Cloned recurring holidays for {$targetYear}.",
        ]);
    }

    json_response(['success' => false, 'error' => 'Unknown action'], 400);
} catch (PDOException $e) {
    if ($conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    json_response(['success' => false, 'error' => 'Database error'], 500);
} catch (Throwable $e) {
    if ($conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    json_response(['success' => false, 'error' => 'Server error'], 500);
}
