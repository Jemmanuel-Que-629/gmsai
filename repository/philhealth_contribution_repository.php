<?php
declare(strict_types=1);

/**
 * Prefer currently-effective schedule (by date range), else fall back to the latest effective_from.
 */
function philhealth_get_effective_from(PDO $conn): ?string
{
	$stmt = $conn->query(
		"SELECT effective_from
		 FROM philhealth_contribution
		 WHERE effective_from <= CURDATE()
		   AND (effective_to IS NULL OR effective_to >= CURDATE())
		 ORDER BY effective_from DESC
		 LIMIT 1"
	);
	$effectiveFrom = $stmt ? $stmt->fetchColumn() : null;
	if ($effectiveFrom) {
		return (string)$effectiveFrom;
	}

	$stmt = $conn->query(
		"SELECT effective_from
		 FROM philhealth_contribution
		 ORDER BY effective_from DESC
		 LIMIT 1"
	);
	$effectiveFrom = $stmt ? $stmt->fetchColumn() : null;
	return $effectiveFrom ? (string)$effectiveFrom : null;
}

/**
 * @return array<int, array<string, mixed>>
 */
function philhealth_get_rows_by_effective_from(PDO $conn, string $effectiveFrom): array
{
	$stmt = $conn->prepare(
		"SELECT philhealth_id, monthly_rate, employee_share, employer_share, salary_floor, salary_ceiling, effective_from, effective_to
		 FROM philhealth_contribution
		 WHERE effective_from = :effective_from
		 ORDER BY philhealth_id ASC"
	);
	$stmt->execute([':effective_from' => $effectiveFrom]);
	return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
