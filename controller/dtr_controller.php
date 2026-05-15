<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../repository/dtr_repository.php';

// -------------------------
// Input parsing / validation
// -------------------------

$dtrParseDate = static function (?string $s): ?string {
	if ($s === null) return null;
	$s = trim($s);
	if ($s === '') return null;
	$dt = DateTime::createFromFormat('Y-m-d', $s);
	if (!$dt) return null;
	$errs = DateTime::getLastErrors();
	if (is_array($errs) && (($errs['warning_count'] ?? 0) > 0 || ($errs['error_count'] ?? 0) > 0)) {
		return null;
	}
	return $dt->format('Y-m-d');
};

$dtrTimeToSeconds = static function (mixed $t): ?int {
	if ($t === null) return null;
	if (!is_scalar($t)) return null;
	$s = trim((string)$t);
	if ($s === '') return null;
	$dt = DateTime::createFromFormat('H:i:s', $s) ?: DateTime::createFromFormat('H:i', $s);
	if (!$dt) return null;
	$errs = DateTime::getLastErrors();
	if (is_array($errs) && (($errs['warning_count'] ?? 0) > 0 || ($errs['error_count'] ?? 0) > 0)) {
		return null;
	}
	$h = (int)$dt->format('H');
	$m = (int)$dt->format('i');
	$sec = (int)$dt->format('s');
	return ($h * 3600) + ($m * 60) + $sec;
};

$rawStart = isset($_GET['start_date']) ? (string)$_GET['start_date'] : null;
$rawEnd = isset($_GET['end_date']) ? (string)$_GET['end_date'] : null;
$rawLocation = isset($_GET['location_id']) ? (string)$_GET['location_id'] : '';

$today = (new DateTime('now'))->format('Y-m-d');
$startDate = $dtrParseDate($rawStart) ?? $today;
$endDate = $dtrParseDate($rawEnd) ?? $today;

if ($startDate > $endDate) {
	[$startDate, $endDate] = [$endDate, $startDate];
}

$locationId = null;
if ($rawLocation !== '' && ctype_digit($rawLocation)) {
	$tmp = (int)$rawLocation;
	if ($tmp > 0) {
		$locationId = $tmp;
	}
}

// -------------------------
// Fetch data (SQL via repository)
// -------------------------

$locations = [];
$rows = [];
try {
	$locations = dtr_get_locations($conn);
} catch (Throwable $e) {
	$locations = [];
}

try {
	$rows = dtr_get_rows($conn, $startDate, $endDate, $locationId);
} catch (Throwable $e) {
	$rows = [];
}

$selectedLocationName = 'All Locations';
if ($locationId !== null) {
	foreach ($locations as $loc) {
		if ((int)($loc['location_id'] ?? 0) === $locationId) {
			$selectedLocationName = (string)($loc['location_name'] ?? 'Selected Location');
			break;
		}
	}
}

// -------------------------
// Shape for Tabulator + stats
// -------------------------

$tableData = [];
$employeeStats = [];

foreach ($rows as $r) {
	$first = trim((string)($r['first_name'] ?? ''));
	$middle = trim((string)($r['middle_name'] ?? ''));
	$last = trim((string)($r['last_name'] ?? ''));
	$employeeNum = trim((string)($r['employee_num_id'] ?? ''));

	$mi = $middle !== '' ? (strtoupper(substr($middle, 0, 1)) . '.') : '';
	$nameParts = array_values(array_filter([
		$last !== '' ? ($last . ',') : '',
		$first,
		$mi,
	], static fn($v) => is_string($v) && trim($v) !== ''));
	$employeeName = trim(implode(' ', $nameParts));

	$workDate = $r['work_date'] ?? null;
	$timeIn = $r['time_in'] ?? null;
	$timeOut = $r['time_out'] ?? null;
	$employeeId = (int)($r['employee_id'] ?? 0);

	if (!isset($employeeStats[$employeeId])) {
		$employeeStats[$employeeId] = [
			'employee_id' => $employeeId,
			'employee_num_id' => $employeeNum,
			'employee_name' => $employeeName,
			'location' => (string)($r['location_name'] ?? ''),
			'total_seconds' => 0,
			'has_any_date' => false,
			'has_any_complete' => false,
			'missing_days' => 0,
			'discrepancy_days' => 0,
			'bucket' => 'discrepancy',
		];
	}

	$hasDate = ($workDate !== null && (string)$workDate !== '');
	if ($hasDate) {
		$employeeStats[$employeeId]['has_any_date'] = true;
	}

	$inSec = $dtrTimeToSeconds($timeIn);
	$outSec = $dtrTimeToSeconds($timeOut);
	$rowSeconds = 0;
	$hasTimeIn = ($inSec !== null);
	$hasTimeOut = ($outSec !== null);

	$status = 'No Record';
	if ($workDate !== null && (string)$workDate !== '') {
		if ($hasTimeIn && $hasTimeOut) {
			if ($outSec >= $inSec) {
				$status = 'Complete';
				$rowSeconds = $outSec - $inSec;
				$employeeStats[$employeeId]['total_seconds'] += $rowSeconds;
				$employeeStats[$employeeId]['has_any_complete'] = true;
			} else {
				$status = 'Discrepancy';
				$employeeStats[$employeeId]['discrepancy_days']++;
			}
		} elseif ($hasTimeIn && !$hasTimeOut) {
			$status = 'Missing Log';
			$employeeStats[$employeeId]['missing_days']++;
		} elseif (!$hasTimeIn && $hasTimeOut) {
			$status = 'Discrepancy';
			$employeeStats[$employeeId]['discrepancy_days']++;
		} else {
			$status = 'Present (No Time)';
			$employeeStats[$employeeId]['discrepancy_days']++;
		}
	}

	$tableData[] = [
		'employee_id' => $employeeId,
		'employee_num_id' => $employeeNum,
		'employee_name' => $employeeName,
		'department' => (string)($r['department'] ?? ''),
		'position' => (string)($r['position'] ?? ''),
		'location' => (string)($r['location_name'] ?? ''),
		'work_date' => $workDate,
		'time_in' => $timeIn,
		'time_out' => $timeOut,
		'status' => $status,
		'hours' => $rowSeconds > 0 ? round($rowSeconds / 3600, 2) : 0,
		'employee_bucket' => 'discrepancy',
		'employee_total_hours' => 0,
	];
}

$missingEmployees = 0;
$discrepancyEmployees = 0;
$readyEmployees = 0;

foreach ($employeeStats as $eid => $s) {
	$bucket = 'discrepancy';
	if (($s['missing_days'] ?? 0) > 0) {
		$bucket = 'missing';
	} elseif (($s['discrepancy_days'] ?? 0) > 0 || !($s['has_any_date'] ?? false)) {
		$bucket = 'discrepancy';
	} elseif (($s['has_any_complete'] ?? false)) {
		$bucket = 'ready';
	} else {
		$bucket = 'discrepancy';
	}

	$employeeStats[$eid]['bucket'] = $bucket;
	$employeeStats[$eid]['total_hours'] = round(((int)($s['total_seconds'] ?? 0)) / 3600, 2);

	if ($bucket === 'missing') {
		$missingEmployees++;
	} elseif ($bucket === 'ready') {
		$readyEmployees++;
	} else {
		$discrepancyEmployees++;
	}
}

foreach ($tableData as &$row) {
	$eid = (int)($row['employee_id'] ?? 0);
	if (isset($employeeStats[$eid])) {
		$row['employee_bucket'] = (string)$employeeStats[$eid]['bucket'];
		$row['employee_total_hours'] = (float)$employeeStats[$eid]['total_hours'];
		if (!isset($row['employee_num_id']) || (string)$row['employee_num_id'] === '') {
			$row['employee_num_id'] = (string)($employeeStats[$eid]['employee_num_id'] ?? '');
		}
	}
}
unset($row);

$tableJson = json_encode(
	$tableData,
	JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE
);

$filtersAppliedByUser = array_key_exists('start_date', $_GET) || array_key_exists('end_date', $_GET) || array_key_exists('location_id', $_GET);
$filterToastText = 'Date: ' . $startDate . ' to ' . $endDate . ' | ' . $selectedLocationName;

