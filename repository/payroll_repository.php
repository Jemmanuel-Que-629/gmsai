<?php

declare(strict_types=1);

/**
 * @return array{location_name:mixed, daily_rate:mixed}|null
 */
function payroll_get_employee_location_rate(PDO $conn, int $employeeId): ?array
{
	$stmt = $conn->prepare(
		'SELECT lr.location_name, lr.daily_rate '
		. 'FROM employees e '
		. 'JOIN location_rate lr ON lr.location_id = e.location_id '
		. 'WHERE e.employee_id = :id '
		. 'LIMIT 1'
	);
	$stmt->execute([':id' => $employeeId]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	return $row ?: null;
}

/**
 * @return array<int, array{work_date:mixed, time_in:mixed, time_out:mixed}>
 */
function payroll_get_attendance_records(PDO $conn, int $employeeId, string $startDate, string $endDate): array
{
	$stmt = $conn->prepare(
		'SELECT work_date, time_in, time_out '
		. 'FROM attendance '
		. 'WHERE employee_id = :id '
		. 'AND work_date BETWEEN :start AND :end '
		. 'AND time_in IS NOT NULL '
		. 'AND time_out IS NOT NULL '
		. 'ORDER BY work_date ASC, time_in ASC'
	);
	$stmt->execute([
		':id' => $employeeId,
		':start' => $startDate,
		':end' => $endDate,
	]);
	return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @return string[]
 */
function payroll_get_holiday_for_date(PDO $conn, string $date): ?array
{
    $sql = "
        SELECT
            h.holiday_name,
            h.holiday_type,
            h.is_paid,

            pr.worked_multiplier,
            pr.unworked_multiplier

        FROM holidays h

        LEFT JOIN payroll_rates pr
            ON pr.rate_id = h.payroll_rate_id

        WHERE h.holiday_date = :holiday_date

        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ':holiday_date' => $date
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/**
 * Some payroll days can have multiple holiday rows (e.g., double holidays).
 *
 * @return string[] holiday_type values (e.g., regular, special_non_working, special_working, company)
 */
function payroll_get_holiday_types_for_date(PDO $conn, string $date): array
{
	$stmt = $conn->prepare(
		'SELECT holiday_type '
		. 'FROM holidays '
		. 'WHERE holiday_date = :d'
	);
	$stmt->execute([':d' => $date]);
	$rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
	if (!$rows) {
		return [];
	}
	return array_values(array_filter(array_map('strval', $rows), static function ($v) {
		return trim($v) !== '';
	}));
}

function payroll_get_payroll_id(PDO $conn, int $employeeId, string $startDate, string $endDate): ?int
{
	$stmt = $conn->prepare(
		'SELECT payroll_id FROM payroll '
		. 'WHERE employee_id = :id AND period_start = :start AND period_end = :end '
		. 'LIMIT 1'
	);
	$stmt->execute([
		':id' => $employeeId,
		':start' => $startDate,
		':end' => $endDate,
	]);
	$id = $stmt->fetchColumn();
	return $id !== false ? (int)$id : null;
}

function payroll_sum_deductions(PDO $conn, int $payrollId, string $type): float
{
	$stmt = $conn->prepare(
		'SELECT COALESCE(SUM(amount), 0) '
		. 'FROM payroll_deductions '
		. 'WHERE payroll_id = :pid AND deduction_type = :t'
	);
	$stmt->execute([':pid' => $payrollId, ':t' => $type]);
	$val = $stmt->fetchColumn();
	return $val !== false ? (float)$val : 0.0;
}

function payroll_get_sss_employee_contribution(PDO $conn, float $monthlyCompensation, string $asOf): ?float
{
	$stmt = $conn->prepare(
		'SELECT employee_contribution '
		. 'FROM sss_bracket '
		. 'WHERE :comp BETWEEN lower_limit AND upper_limit '
		. 'AND effective_from <= :asof '
		. 'AND (effective_to IS NULL OR effective_to >= :asof2) '
		. 'ORDER BY effective_from DESC '
		. 'LIMIT 1'
	);
	$stmt->execute([
		':comp' => $monthlyCompensation,
		':asof' => $asOf,
		':asof2' => $asOf,
	]);
	$val = $stmt->fetchColumn();
	if ($val === false || $val === null) {
		return null;
	}
	return (float)$val;
}

/**
 * @return array{monthly_rate:float, employee_share:float, salary_floor:float, salary_ceiling:float}|null
 */
function payroll_get_philhealth_row(PDO $conn, string $asOf): ?array
{
	$stmt = $conn->prepare(
		'SELECT monthly_rate, employee_share, salary_floor, salary_ceiling '
		. 'FROM philhealth_contribution '
		. 'WHERE effective_from <= :asof '
		. 'AND (effective_to IS NULL OR effective_to >= :asof2) '
		. 'ORDER BY effective_from DESC '
		. 'LIMIT 1'
	);
	$stmt->execute([':asof' => $asOf, ':asof2' => $asOf]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$row) {
		return null;
	}
	return [
		'monthly_rate' => (float)($row['monthly_rate'] ?? 0),
		'employee_share' => (float)($row['employee_share'] ?? 0),
		'salary_floor' => (float)($row['salary_floor'] ?? 0),
		'salary_ceiling' => (float)($row['salary_ceiling'] ?? 0),
	];
}

/**
 * @return array{employee_rate:float, salary_ceiling:float}|null
 */
function payroll_get_pagibig_row(PDO $conn, float $monthlyGross, string $asOf): ?array
{
	$stmt = $conn->prepare(
		'SELECT employee_rate, salary_ceiling '
		. 'FROM pagibig_contribution '
		. 'WHERE :gross BETWEEN salary_min AND salary_max '
		. 'AND effective_from <= :asof '
		. 'AND (effective_to IS NULL OR effective_to >= :asof2) '
		. 'ORDER BY effective_from DESC '
		. 'LIMIT 1'
	);
	$stmt->execute([
		':gross' => $monthlyGross,
		':asof' => $asOf,
		':asof2' => $asOf,
	]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$row) {
		return null;
	}
	return [
		'employee_rate' => (float)($row['employee_rate'] ?? 0),
		'salary_ceiling' => (float)($row['salary_ceiling'] ?? 0),
	];
}

/**
 * @return array{department:string, position:string}|null
 */
function payroll_get_employee_department_position(PDO $conn, int $employeeId): ?array
{
	$stmt = $conn->prepare(
		'SELECT department, position '
		. 'FROM employees '
		. 'WHERE employee_id = :id '
		. 'LIMIT 1'
	);
	$stmt->execute([':id' => $employeeId]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$row) {
		return null;
	}
	return [
		'department' => (string)($row['department'] ?? ''),
		'position' => (string)($row['position'] ?? ''),
	];
}

function payroll_get_cash_advance_amount(PDO $conn, int $employeeId, int $periodYear, int $periodMonth, int $cutoff): float
{
	$stmt = $conn->prepare(
		'SELECT amount '
		. 'FROM cash_advances '
		. 'WHERE employee_id = :eid '
		. 'AND period_year = :py '
		. 'AND period_month = :pm '
		. 'AND cutoff = :c '
		. 'LIMIT 1'
	);
	$stmt->execute([
		':eid' => $employeeId,
		':py' => $periodYear,
		':pm' => $periodMonth,
		':c' => $cutoff,
	]);
	$val = $stmt->fetchColumn();
	return $val !== false ? (float)$val : 0.0;
}

/**
 * @return array{target_amount:float, per_cutoff_amount:float, total_paid:float, is_active:int}|null
 */
function payroll_get_cash_bond_account(PDO $conn, int $employeeId): ?array
{
	$stmt = $conn->prepare(
		'SELECT target_amount, per_cutoff_amount, total_paid, is_active '
		. 'FROM cash_bond_accounts '
		. 'WHERE employee_id = :id '
		. 'LIMIT 1'
	);
	$stmt->execute([':id' => $employeeId]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$row) {
		return null;
	}
	return [
		'target_amount' => (float)($row['target_amount'] ?? 10000),
		'per_cutoff_amount' => (float)($row['per_cutoff_amount'] ?? 100),
		'total_paid' => (float)($row['total_paid'] ?? 0),
		'is_active' => (int)($row['is_active'] ?? 1),
	];
}

function payroll_get_cash_bond_total_paid(PDO $conn, int $employeeId): float
{
	$stmt = $conn->prepare(
		'SELECT COALESCE(SUM(amount), 0) '
		. 'FROM cash_bond_payments '
		. 'WHERE employee_id = :id'
	);
	$stmt->execute([':id' => $employeeId]);
	$val = $stmt->fetchColumn();
	return $val !== false ? (float)$val : 0.0;
}

function payroll_get_cash_bond_payment_amount(PDO $conn, int $employeeId, int $periodYear, int $periodMonth, int $cutoff): ?float
{
	$stmt = $conn->prepare(
		'SELECT amount '
		. 'FROM cash_bond_payments '
		. 'WHERE employee_id = :eid '
		. 'AND period_year = :py '
		. 'AND period_month = :pm '
		. 'AND cutoff = :c '
		. 'LIMIT 1'
	);
	$stmt->execute([
		':eid' => $employeeId,
		':py' => $periodYear,
		':pm' => $periodMonth,
		':c' => $cutoff,
	]);
	$val = $stmt->fetchColumn();
	if ($val === false || $val === null) {
		return null;
	}
	return (float)$val;
}
