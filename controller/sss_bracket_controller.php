<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../repository/sss_bracket_repository.php';

$effectiveFrom = null;
$rows = [];

try {
	$effectiveFrom = sss_get_effective_from($conn);
	if ($effectiveFrom) {
		$rows = sss_get_brackets_by_effective_from($conn, $effectiveFrom);
	}
} catch (Throwable $e) {
	$effectiveFrom = null;
	$rows = [];
}

// ---- formatting helpers (kept out of the view) ----

$formatLimit = static function (?string $value): string {
	if ($value === null || $value === '') {
		return '';
	}

	$num = (float)$value;
	$decimal = $num - floor($num);
	if (abs($decimal - 0.99) < 0.001) {
		return number_format((float)ceil($num), 0);
	}

	if (abs($decimal) < 0.001) {
		return number_format($num, 0);
	}

	return number_format($num, 2);
};

$formatRange = static function (?string $lower, ?string $upper) use ($formatLimit): string {
	$lower = ($lower !== null && $lower !== '') ? (string)$lower : null;
	$upper = ($upper !== null && $upper !== '') ? (string)$upper : null;

	if ($lower === null && $upper !== null) {
		return 'BELOW ' . $formatLimit($upper);
	}
	if ($lower !== null && $upper !== null) {
		return $formatLimit($lower) . ' - ' . $formatLimit($upper);
	}
	if ($lower !== null && $upper === null) {
		return $formatLimit($lower) . ' AND ABOVE';
	}
	return '';
};

// Pre-shape display rows so the view stays template-only.
$displayRows = [];
foreach ($rows as $row) {
	$mscRegular = (float)($row['regular_msc'] ?? 0);
	$mscMpf = (float)($row['mpf_msc'] ?? 0);
	$mscTotal = (float)($row['msc'] ?? 0);
	$employee = (float)($row['employee_contribution'] ?? 0);
	$employer = (float)($row['employer_contribution'] ?? 0);
	$total = $employee + $employer;

	$displayRows[] = [
		'range' => $formatRange($row['lower_limit'] ?? null, $row['upper_limit'] ?? null),
		'msc_regular' => number_format($mscRegular, 2),
		'msc_mpf' => number_format($mscMpf, 2),
		'msc_total' => number_format($mscTotal, 2),
		'employer' => number_format($employer, 2),
		'employee' => number_format($employee, 2),
		'total' => number_format($total, 2),
	];
}
