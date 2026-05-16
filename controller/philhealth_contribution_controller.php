<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../repository/philhealth_contribution_repository.php';

$effectiveFrom = null;
$rows = [];

try {
	$effectiveFrom = philhealth_get_effective_from($conn);
	if ($effectiveFrom) {
		$rows = philhealth_get_rows_by_effective_from($conn, $effectiveFrom);
	}
} catch (Throwable $e) {
	$effectiveFrom = null;
	$rows = [];
}

$fmtPercent = static function (?string $rate): string {
	if ($rate === null || $rate === '') {
		return '-';
	}
	return rtrim(rtrim(number_format(((float)$rate) * 100, 4), '0'), '.') . '%';
};

$fmtMoney = static function (?string $amount): string {
	if ($amount === null || $amount === '') {
		return '-';
	}
	return number_format((float)$amount, 2);
};

// Pre-shape display rows so the view stays template-only.
$displayRows = [];
foreach ($rows as $row) {
	$displayRows[] = [
		'monthly_rate' => $fmtPercent($row['monthly_rate'] ?? null),
		'employee_share' => $fmtPercent($row['employee_share'] ?? null),
		'employer_share' => $fmtPercent($row['employer_share'] ?? null),
		'salary_floor' => $fmtMoney($row['salary_floor'] ?? null),
		'salary_ceiling' => $fmtMoney($row['salary_ceiling'] ?? null),
		'effective_from' => (string)($row['effective_from'] ?? ''),
		'effective_to' => (string)($row['effective_to'] ?? ''),
	];
}
