<?php
declare(strict_types=1);

/**
 * @return array<int, array<string, mixed>>
 */
function calendar_list_holidays_for_month(PDO $conn, int $year, int $month): array
{
	$start = sprintf('%04d-%02d-01', $year, $month);
	$end = (new DateTime($start))->modify('last day of this month')->format('Y-m-d');

	$stmt = $conn->prepare(
		'SELECT holiday_id, holiday_date, holiday_name, holiday_type, is_recurring
		 FROM holidays
		 WHERE applicable_year = :year
		   AND holiday_date BETWEEN :start AND :end
		 ORDER BY holiday_date ASC, holiday_name ASC'
	);
	$stmt->execute([
		':year' => (string)$year,
		':start' => $start,
		':end' => $end,
	]);

	return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function calendar_holiday_exists(PDO $conn, string $holidayDate, string $holidayName, ?int $excludeHolidayId = null): bool
{
	if ($excludeHolidayId !== null && $excludeHolidayId > 0) {
		$stmt = $conn->prepare('SELECT 1 FROM holidays WHERE holiday_date = :d AND holiday_name = :n AND holiday_id <> :id LIMIT 1');
		$stmt->execute([':d' => $holidayDate, ':n' => $holidayName, ':id' => $excludeHolidayId]);
		return (bool)$stmt->fetchColumn();
	}

	$stmt = $conn->prepare('SELECT 1 FROM holidays WHERE holiday_date = :d AND holiday_name = :n LIMIT 1');
	$stmt->execute([':d' => $holidayDate, ':n' => $holidayName]);
	return (bool)$stmt->fetchColumn();
}

function calendar_create_holiday(
	PDO $conn,
	string $holidayDate,
	string $holidayName,
	string $holidayType,
	?int $payrollRateId,
	int $isPaid,
	int $isRecurring,
	int $applicableYear
): int {
	$stmt = $conn->prepare(
		'INSERT INTO holidays (holiday_date, holiday_name, holiday_type, payroll_rate_id, is_paid, is_recurring, applicable_year)
		 VALUES (:d, :n, :t, :r, :p, :rec, :y)'
	);
	$stmt->execute([
		':d' => $holidayDate,
		':n' => $holidayName,
		':t' => $holidayType,
		':r' => $payrollRateId,
		':p' => $isPaid,
		':rec' => $isRecurring,
		':y' => (string)$applicableYear,
	]);

	return (int)$conn->lastInsertId();
}

function calendar_update_holiday(
	PDO $conn,
	int $holidayId,
	string $holidayDate,
	string $holidayName,
	string $holidayType,
	?int $payrollRateId,
	int $isPaid,
	int $isRecurring,
	int $applicableYear
): void {
	$stmt = $conn->prepare(
		'UPDATE holidays
		 SET holiday_date = :d,
			 holiday_name = :n,
			 holiday_type = :t,
			 payroll_rate_id = :r,
			 is_paid = :p,
			 is_recurring = :rec,
			 applicable_year = :y
		 WHERE holiday_id = :id
		 LIMIT 1'
	);
	$stmt->execute([
		':d' => $holidayDate,
		':n' => $holidayName,
		':t' => $holidayType,
		':r' => $payrollRateId,
		':p' => $isPaid,
		':rec' => $isRecurring,
		':y' => (string)$applicableYear,
		':id' => $holidayId,
	]);
}

function calendar_delete_holiday(PDO $conn, int $holidayId): void
{
	$stmt = $conn->prepare('DELETE FROM holidays WHERE holiday_id = :id LIMIT 1');
	$stmt->execute([':id' => $holidayId]);
}

/**
 * @return array<int, array{holiday_name:mixed, holiday_type:mixed, payroll_rate_id:mixed, is_paid:mixed, m:mixed, d:mixed}>
 */
function calendar_fetch_recurring_sources(PDO $conn): array
{
	$stmt = $conn->prepare(
		'SELECT DISTINCT holiday_name, holiday_type, payroll_rate_id, is_paid, MONTH(holiday_date) AS m, DAY(holiday_date) AS d '
		. 'FROM holidays WHERE is_recurring = 1'
	);
	$stmt->execute();
	return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

