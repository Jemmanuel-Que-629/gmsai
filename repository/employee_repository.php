<?php
declare(strict_types=1);

function employee_has_archive_column(PDO $conn): bool
{
	try {
		$colStmt = $conn->query(
			"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS "
			. "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'is_archived'"
		);
		return ((int)$colStmt->fetchColumn() > 0);
	} catch (Throwable $e) {
		return false;
	}
}

/**
 * @return array<int, array{location_id:mixed, location_name:mixed}>
 */
function employee_list_locations(PDO $conn): array
{
	$stmt = $conn->prepare('SELECT location_id, location_name FROM location_rate ORDER BY location_name ASC');
	$stmt->execute();
	return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @return array<int, array<string, mixed>>
 */
function employee_list_employees(PDO $conn, bool $hasArchiveCol): array
{
	$sql =
		"SELECT 
			e.employee_id,
			e.employee_num_id,
			e.user_id,
			e.first_name,
			e.middle_name,
			e.last_name,
			e.position,
			e.department,
			e.location_id,
			e.salary_type,
			" . ($hasArchiveCol ? "e.is_archived" : "0 AS is_archived") . ",
			lr.location_name,
			e.created_at,
			e.updated_at
		 FROM employees e
		 JOIN location_rate lr ON e.location_id = lr.location_id
		 ORDER BY e.last_name ASC, e.first_name ASC";

	$stmt = $conn->prepare($sql);
	$stmt->execute();
	return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @return array{location_id:mixed, location_name:mixed}|null
 */
function employee_find_location(PDO $conn, int $locationId): ?array
{
	$stmt = $conn->prepare('SELECT location_id, location_name FROM location_rate WHERE location_id = :id LIMIT 1');
	$stmt->execute([':id' => $locationId]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	return $row ?: null;
}

function employee_update(PDO $conn, int $employeeId, array $fields): void
{
	$sql =
		'UPDATE employees SET'
		. ' first_name = :first,'
		. ' middle_name = :middle,'
		. ' last_name = :last,'
		. ' position = :pos,'
		. ' department = :dept,'
		. ' location_id = :loc,'
		. ' salary_type = :stype'
		. ' WHERE employee_id = :id'
		. ' LIMIT 1';

	$stmt = $conn->prepare($sql);
	$stmt->execute([
		':first' => (string)($fields['first_name'] ?? ''),
		':middle' => ($fields['middle_name'] ?? null),
		':last' => (string)($fields['last_name'] ?? ''),
		':pos' => (string)($fields['position'] ?? ''),
		':dept' => (string)($fields['department'] ?? ''),
		':loc' => (int)($fields['location_id'] ?? 0),
		':stype' => (string)($fields['salary_type'] ?? ''),
		':id' => $employeeId,
	]);
}

function employee_archive(PDO $conn, int $employeeId): void
{
	$stmt = $conn->prepare('UPDATE employees SET is_archived = 1 WHERE employee_id = :id LIMIT 1');
	$stmt->execute([':id' => $employeeId]);
}

