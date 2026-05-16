<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../repository/employee_repository.php';

csrf_init();

$hasArchiveCol = employee_has_archive_column($conn);

$locations = employee_list_locations($conn);
$locationsJson = json_encode($locations, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if (!is_string($locationsJson)) {
	$locationsJson = '[]';
}

$employees = employee_list_employees($conn, $hasArchiveCol);

foreach ($employees as &$row) {
	$first = trim((string)($row['first_name'] ?? ''));
	$middle = trim((string)($row['middle_name'] ?? ''));
	$last = trim((string)($row['last_name'] ?? ''));

	$mi = '';
	if ($middle !== '') {
		$mi = strtoupper(substr($middle, 0, 1)) . '.';
	}

	$row['full_name'] = trim($first . ' ' . ($mi !== '' ? $mi . ' ' : '') . $last);

	foreach (['department', 'position', 'location_name', 'salary_type'] as $k) {
		if (!isset($row[$k]) || $row[$k] === null) {
			$row[$k] = '';
		}
	}
	$row['is_archived'] = (int)($row['is_archived'] ?? 0);
}
unset($row);

$employeesJson = json_encode($employees, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if (!is_string($employeesJson)) {
	$employeesJson = '[]';
}

$csrfToken = csrf_token();
$employeeBackendUrl = BASE_URL . 'controller/employees/employee_process.php';

