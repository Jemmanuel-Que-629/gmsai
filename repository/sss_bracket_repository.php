<?php
declare(strict_types=1);

/**
 * Prefer the currently-effective schedule (by date range), else fall back to the most recent effective_from.
 */
function sss_get_effective_from(PDO $conn): ?string
{
	$stmt = $conn->query(
		"SELECT effective_from
		 FROM sss_bracket
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
		 FROM sss_bracket
		 ORDER BY effective_from DESC
		 LIMIT 1"
	);
	$effectiveFrom = $stmt ? $stmt->fetchColumn() : null;
	return $effectiveFrom ? (string)$effectiveFrom : null;
}

/**
 * @return array<int, array<string, mixed>>
 */
function sss_get_brackets_by_effective_from(PDO $conn, string $effectiveFrom): array
{
	$stmt = $conn->prepare(
		'SELECT sss_id, lower_limit, upper_limit, msc, regular_msc, mpf_msc,
				employee_contribution, employer_contribution, effective_from, effective_to
		 FROM sss_bracket
		 WHERE effective_from = :effective_from
		 ORDER BY COALESCE(lower_limit, 0) ASC, COALESCE(upper_limit, 999999999) ASC'
	);
	$stmt->execute([':effective_from' => $effectiveFrom]);
	return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
