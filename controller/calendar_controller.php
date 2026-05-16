<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../repository/calendar_repository.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

csrf_init();

function calendar_json_response(array $payload, int $status = 200): never
{
	if (!headers_sent()) {
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
	}
	echo json_encode($payload, JSON_UNESCAPED_SLASHES);
	exit;
}

function calendar_require_accounting_role(): void
{
	if (($_SESSION['user_id'] ?? null) === null) {
		calendar_json_response(['success' => false, 'error' => 'Unauthorized'], 401);
	}
	if (($_SESSION['user_role'] ?? null) !== 'ACCOUNTING') {
		calendar_json_response(['success' => false, 'error' => 'Forbidden'], 403);
	}
}

function calendar_require_post_and_csrf(): void
{
	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
		calendar_json_response(['success' => false, 'error' => 'Invalid request'], 405);
	}
	$token = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : null;
	if (!csrf_validate($token)) {
		calendar_json_response(['success' => false, 'error' => 'CSRF validation failed'], 400);
	}
}

function calendar_read_action(): string
{
	$action = $_GET['action'] ?? $_POST['action'] ?? '';
	return is_string($action) ? trim($action) : '';
}

function calendar_read_int(mixed $val): int
{
	if (!is_scalar($val)) {
		return 0;
	}
	return (int)$val;
}

function calendar_clean_string(mixed $val, int $maxLen, bool $required = false): string
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

function calendar_parse_date(string $ymd): ?DateTime
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

function calendar_validate_year(int $year): void
{
	if ($year < 2000 || $year > 2100) {
		calendar_json_response(['success' => false, 'error' => 'Invalid year'], 400);
	}
}

function calendar_validate_month(int $month): void
{
	if ($month < 1 || $month > 12) {
		calendar_json_response(['success' => false, 'error' => 'Invalid month'], 400);
	}
}

function calendar_normalize_holiday_type(string $type): string
{
	$t = strtolower(trim($type));
	$t = str_replace([' ', '-'], '_', $t);

	return match ($t) {
		'regular' => 'regular',
		'special_non_working', 'special_nonworking' => 'special_non_working',
		'special_working' => 'special_working',
		'company', 'company_holiday', 'companyholiday' => 'company',
		default => '',
	};
}

function calendar_require_valid_holiday_type(string $type): string
{
	$normalized = calendar_normalize_holiday_type($type);
	if ($normalized === '') {
		calendar_json_response(['success' => false, 'error' => 'Invalid holiday type'], 400);
	}
	return $normalized;
}

function calendar_default_payroll_rate_id(string $holidayType): ?int
{
	return match ($holidayType) {
		'regular' => 1,
		'special_non_working' => 2,
		'special_working' => 3,
		'company' => 4,
		default => null,
	};
}

calendar_require_accounting_role();

$action = calendar_read_action();

try {
	if ($action === 'list') {
		$year = calendar_read_int($_GET['year'] ?? $_POST['year'] ?? date('Y'));
		$month = calendar_read_int($_GET['month'] ?? $_POST['month'] ?? date('n'));

		calendar_validate_year($year);
		calendar_validate_month($month);

		// Auto-generate recurring holidays for future years (e.g., 2027+) so they show up automatically.
		$systemYear = (int)date('Y');
		if ($year > $systemYear) {
			try {
				$conn->beginTransaction();
				$sources = calendar_fetch_recurring_sources($conn);
				foreach ($sources as $row) {
					$srcName = trim((string)($row['holiday_name'] ?? ''));
					$srcTypeRaw = (string)($row['holiday_type'] ?? '');
					$monthSrc = (int)($row['m'] ?? 0);
					$daySrc = (int)($row['d'] ?? 0);

					if ($srcName === '' || $srcTypeRaw === '' || $monthSrc < 1 || $monthSrc > 12 || $daySrc < 1 || $daySrc > 31) {
						continue;
					}
					if (!checkdate($monthSrc, $daySrc, $year)) {
						continue;
					}

					$newDate = sprintf('%04d-%02d-%02d', $year, $monthSrc, $daySrc);
					if (calendar_holiday_exists($conn, $newDate, $srcName)) {
						continue;
					}

					$holidayType = calendar_normalize_holiday_type($srcTypeRaw);
					if ($holidayType === '') {
						continue;
					}
					$rate = $row['payroll_rate_id'] ?? null;
					$payrollRateId = $rate === null ? calendar_default_payroll_rate_id($holidayType) : (int)$rate;
					$isPaid = isset($row['is_paid']) ? ((int)$row['is_paid'] ? 1 : 0) : 1;

					calendar_create_holiday(
						$conn,
						$newDate,
						$srcName,
						$holidayType,
						$payrollRateId,
						$isPaid,
						1,
						$year
					);
				}
				$conn->commit();
			} catch (Throwable $e) {
				if ($conn->inTransaction()) {
					$conn->rollBack();
				}
				// Non-fatal: still return whatever exists.
			}
		}

		$rows = calendar_list_holidays_for_month($conn, $year, $month);
		calendar_json_response([
			'success' => true,
			'year' => $year,
			'month' => $month,
			'holidays' => $rows,
		]);
	}

	if ($action === 'create') {
		calendar_require_post_and_csrf();

		$holidayDate = calendar_clean_string($_POST['holiday_date'] ?? '', 10, true);
		$holidayName = calendar_clean_string($_POST['holiday_name'] ?? '', 100, true);
		$holidayType = calendar_require_valid_holiday_type(calendar_clean_string($_POST['holiday_type'] ?? '', 30, true));

		$isRecurring = calendar_read_int($_POST['is_recurring'] ?? 0) ? 1 : 0;
		$isPaid = calendar_read_int($_POST['is_paid'] ?? 1) ? 1 : 0;
		$rateId = calendar_read_int($_POST['payroll_rate_id'] ?? 0);
		$payrollRateId = $rateId > 0 ? $rateId : calendar_default_payroll_rate_id($holidayType);

		$dt = calendar_parse_date($holidayDate);
		if (!$dt) {
			calendar_json_response(['success' => false, 'error' => 'Invalid holiday date'], 400);
		}
		$year = (int)$dt->format('Y');
		calendar_validate_year($year);

		if (calendar_holiday_exists($conn, $holidayDate, $holidayName)) {
			calendar_json_response(['success' => false, 'error' => 'Holiday already exists'], 409);
		}

		$newId = calendar_create_holiday($conn, $holidayDate, $holidayName, $holidayType, $payrollRateId, $isPaid, $isRecurring, $year);
		calendar_json_response([
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
		calendar_require_post_and_csrf();

		$holidayId = calendar_read_int($_POST['holiday_id'] ?? 0);
		if ($holidayId <= 0) {
			calendar_json_response(['success' => false, 'error' => 'Invalid holiday id'], 400);
		}

		$holidayDate = calendar_clean_string($_POST['holiday_date'] ?? '', 10, true);
		$holidayName = calendar_clean_string($_POST['holiday_name'] ?? '', 100, true);
		$holidayType = calendar_require_valid_holiday_type(calendar_clean_string($_POST['holiday_type'] ?? '', 30, true));
		$isRecurring = calendar_read_int($_POST['is_recurring'] ?? 0) ? 1 : 0;
		$isPaid = calendar_read_int($_POST['is_paid'] ?? 1) ? 1 : 0;

		$rateId = calendar_read_int($_POST['payroll_rate_id'] ?? 0);
		$payrollRateId = $rateId > 0 ? $rateId : calendar_default_payroll_rate_id($holidayType);

		$dt = calendar_parse_date($holidayDate);
		if (!$dt) {
			calendar_json_response(['success' => false, 'error' => 'Invalid holiday date'], 400);
		}
		$year = (int)$dt->format('Y');
		calendar_validate_year($year);

		if (calendar_holiday_exists($conn, $holidayDate, $holidayName, $holidayId)) {
			calendar_json_response(['success' => false, 'error' => 'Another holiday with same date and name exists'], 409);
		}

		calendar_update_holiday($conn, $holidayId, $holidayDate, $holidayName, $holidayType, $payrollRateId, $isPaid, $isRecurring, $year);
		calendar_json_response([
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
		calendar_require_post_and_csrf();

		$holidayId = calendar_read_int($_POST['holiday_id'] ?? 0);
		if ($holidayId <= 0) {
			calendar_json_response(['success' => false, 'error' => 'Invalid holiday id'], 400);
		}

		calendar_delete_holiday($conn, $holidayId);
		calendar_json_response(['success' => true]);
	}

	if ($action === 'clone_recurring') {
		calendar_require_post_and_csrf();

		$targetYear = calendar_read_int($_POST['target_year'] ?? $_POST['year'] ?? 0);
		calendar_validate_year($targetYear);

		$successCount = 0;
		$skippedCount = 0;

		$conn->beginTransaction();
		$sources = calendar_fetch_recurring_sources($conn);
		foreach ($sources as $row) {
			$srcName = trim((string)($row['holiday_name'] ?? ''));
			$srcTypeRaw = (string)($row['holiday_type'] ?? '');
			$monthSrc = (int)($row['m'] ?? 0);
			$daySrc = (int)($row['d'] ?? 0);

			if ($srcName === '' || $srcTypeRaw === '' || $monthSrc < 1 || $monthSrc > 12 || $daySrc < 1 || $daySrc > 31) {
				$skippedCount++;
				continue;
			}
			if (!checkdate($monthSrc, $daySrc, $targetYear)) {
				$skippedCount++;
				continue;
			}

			$newDate = sprintf('%04d-%02d-%02d', $targetYear, $monthSrc, $daySrc);
			if (calendar_holiday_exists($conn, $newDate, $srcName)) {
				$skippedCount++;
				continue;
			}

			$holidayType = calendar_normalize_holiday_type($srcTypeRaw);
			if ($holidayType === '') {
				$skippedCount++;
				continue;
			}
			$rate = $row['payroll_rate_id'] ?? null;
			$payrollRateId = $rate === null ? calendar_default_payroll_rate_id($holidayType) : (int)$rate;
			$isPaid = isset($row['is_paid']) ? ((int)$row['is_paid'] ? 1 : 0) : 1;

			calendar_create_holiday(
				$conn,
				$newDate,
				$srcName,
				$holidayType,
				$payrollRateId,
				$isPaid,
				1,
				$targetYear
			);
			$successCount++;
		}
		$conn->commit();

		calendar_json_response([
			'success' => true,
			'success_count' => $successCount,
			'skipped_count' => $skippedCount,
			'message' => "Cloned recurring holidays for {$targetYear}.",
		]);
	}

	calendar_json_response(['success' => false, 'error' => 'Unknown action'], 400);
} catch (PDOException $e) {
	if ($conn instanceof PDO && $conn->inTransaction()) {
		$conn->rollBack();
	}
	calendar_json_response(['success' => false, 'error' => 'Database error'], 500);
} catch (Throwable $e) {
	if ($conn instanceof PDO && $conn->inTransaction()) {
		$conn->rollBack();
	}
	calendar_json_response(['success' => false, 'error' => 'Server error'], 500);
}

