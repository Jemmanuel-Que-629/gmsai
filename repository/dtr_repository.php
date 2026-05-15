<?php
declare(strict_types=1);

function dtr_get_locations(PDO $conn): array
{
	$stmt = $conn->query('SELECT location_id, location_name FROM location_rate ORDER BY location_name ASC');
	return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
}

/**
 * Returns employee rows with attendance (left-joined) within a date range.
 *
 * @return array<int, array<string, mixed>>
 */
function dtr_get_rows(PDO $conn, string $startDate, string $endDate, ?int $locationId): array
{
	$sql = '
		SELECT
			e.employee_id,
			e.employee_num_id,
			e.first_name,
			e.middle_name,
			e.last_name,
			e.department,
			e.position,
			lr.location_name,
			a.work_date,
			a.time_in,
			a.time_out
		FROM employees e
		INNER JOIN location_rate lr ON lr.location_id = e.location_id
		LEFT JOIN attendance a
			ON a.employee_id = e.employee_id
			AND a.work_date BETWEEN :start_date AND :end_date
	';

	$params = [
		':start_date' => $startDate,
		':end_date' => $endDate,
	];

	if ($locationId !== null) {
		$sql .= ' WHERE e.location_id = :location_id';
		$params[':location_id'] = $locationId;
	}

	$sql .= ' ORDER BY e.last_name ASC, e.first_name ASC, a.work_date ASC';

	$stmt = $conn->prepare($sql);
	$stmt->execute($params);
	return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

