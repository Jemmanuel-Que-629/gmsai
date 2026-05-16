<?php

declare(strict_types=1);

/**
 * Fetch employees (optionally filtered by location), and join stored payroll summary for the selected period.
 *
 * @return array<int, array{employee_id:mixed,name:mixed,location_name:mixed,payroll_id:mixed,gross_pay:mixed,total_deductions:mixed,net_pay:mixed}>
 */
function payslip_list_employee_payroll_summaries(PDO $conn, string $startDate, string $endDate, ?string $locationName = null): array
{
	$sql = "SELECT e.employee_id,
			CONCAT(e.first_name, ' ',
				CASE WHEN e.middle_name IS NOT NULL AND e.middle_name != ''
					THEN CONCAT(UPPER(LEFT(e.middle_name, 1)), '. ')
					ELSE '' END,
				e.last_name) AS name,
			lr.location_name,
			p.payroll_id,
			p.gross_pay,
			p.total_deductions,
			p.net_pay
		FROM employees e
		JOIN location_rate lr ON e.location_id = lr.location_id
		LEFT JOIN payroll p
			ON p.employee_id = e.employee_id
			AND p.period_start = :start_date
			AND p.period_end = :end_date";

	$params = [
		':start_date' => $startDate,
		':end_date' => $endDate,
	];

	$locationName = $locationName !== null ? trim($locationName) : '';
	if ($locationName !== '') {
		$sql .= ' WHERE lr.location_name = :location_name';
		$params[':location_name'] = $locationName;
	}

	$sql .= ' ORDER BY e.last_name ASC, e.first_name ASC';

	$stmt = $conn->prepare($sql);
	$stmt->execute($params);
	return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @param int[] $payrollIds
 * @return array<int, array<string, array{hours:float, amount:float}>> keyed by payroll_id then type
 */
function payslip_fetch_details_by_payroll_ids(PDO $conn, array $payrollIds): array
{
	$payrollIds = array_values(array_filter(array_map('intval', $payrollIds), static function (int $v): bool {
		return $v > 0;
	}));
	if ($payrollIds === []) {
		return [];
	}

	$params = [];
	$placeholders = [];
	foreach ($payrollIds as $i => $id) {
		$key = ':p' . $i;
		$placeholders[] = $key;
		$params[$key] = $id;
	}

	$sql = 'SELECT payroll_id, type, COALESCE(SUM(hours), 0) AS total_hours, COALESCE(SUM(amount), 0) AS total_amount '
		. 'FROM payroll_details '
		. 'WHERE payroll_id IN (' . implode(',', $placeholders) . ') '
		. 'GROUP BY payroll_id, type';

	$stmt = $conn->prepare($sql);
	$stmt->execute($params);
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

	$out = [];
	foreach ($rows as $r) {
		$pid = (int)($r['payroll_id'] ?? 0);
		if ($pid <= 0) {
			continue;
		}
		$type = (string)($r['type'] ?? '');
		if ($type === '') {
			continue;
		}
		$out[$pid][$type] = [
			'hours' => (float)($r['total_hours'] ?? 0),
			'amount' => (float)($r['total_amount'] ?? 0),
		];
	}

	return $out;
}

/**
 * @param int[] $payrollIds
 * @return array<int, array<string, float>> keyed by payroll_id then deduction_type
 */
function payslip_fetch_deductions_by_payroll_ids(PDO $conn, array $payrollIds): array
{
	$payrollIds = array_values(array_filter(array_map('intval', $payrollIds), static function (int $v): bool {
		return $v > 0;
	}));
	if ($payrollIds === []) {
		return [];
	}

	$params = [];
	$placeholders = [];
	foreach ($payrollIds as $i => $id) {
		$key = ':p' . $i;
		$placeholders[] = $key;
		$params[$key] = $id;
	}

	$sql = 'SELECT payroll_id, deduction_type, COALESCE(SUM(amount), 0) AS total_amount '
		. 'FROM payroll_deductions '
		. 'WHERE payroll_id IN (' . implode(',', $placeholders) . ') '
		. 'GROUP BY payroll_id, deduction_type';

	$stmt = $conn->prepare($sql);
	$stmt->execute($params);
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

	$out = [];
	foreach ($rows as $r) {
		$pid = (int)($r['payroll_id'] ?? 0);
		if ($pid <= 0) {
			continue;
		}
		$type = (string)($r['deduction_type'] ?? '');
		if ($type === '') {
			continue;
		}
		$out[$pid][$type] = (float)($r['total_amount'] ?? 0);
	}

	return $out;
}
