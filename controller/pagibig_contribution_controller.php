<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../repository/pagibig_contribution_repository.php';

$effectiveFrom = null;
$rows = [];

try {
	$effectiveFrom = pagibig_get_effective_from($conn);
	if ($effectiveFrom) {
		$rows = pagibig_get_rows_by_effective_from($conn, $effectiveFrom);
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

$displayRows = [];
foreach ($rows as $row) {
	$displayRows[] = [
		'salary_min' => $fmtMoney($row['salary_min'] ?? null),
		'salary_max' => $fmtMoney($row['salary_max'] ?? null),
		'employee_rate' => $fmtPercent($row['employee_rate'] ?? null),
		'employer_rate' => $fmtPercent($row['employer_rate'] ?? null),
		'salary_ceiling' => $fmtMoney($row['salary_ceiling'] ?? null),
		'effective_from' => (string)($row['effective_from'] ?? ''),
		'effective_to' => (string)($row['effective_to'] ?? ''),
	];
}
