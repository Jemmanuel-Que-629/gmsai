<?php
declare(strict_types=1);

$pageTitle = 'Philhealth Contribution';
require_once __DIR__ . '/../../global/header.php';
require_once __DIR__ . '/../../config/db_connection.php';

$effectiveFrom = null;
$rows = [];

try {
	// Prefer currently-effective row; else fall back to the latest effective_from.
	$stmtEffective = $conn->query(
		"SELECT effective_from
		 FROM philhealth_contribution
		 WHERE effective_from <= CURDATE()
		   AND (effective_to IS NULL OR effective_to >= CURDATE())
		 ORDER BY effective_from DESC
		 LIMIT 1"
	);
	$effectiveFrom = $stmtEffective ? $stmtEffective->fetchColumn() : null;

	if (!$effectiveFrom) {
		$stmtEffective = $conn->query(
			"SELECT effective_from
			 FROM philhealth_contribution
			 ORDER BY effective_from DESC
			 LIMIT 1"
		);
		$effectiveFrom = $stmtEffective ? $stmtEffective->fetchColumn() : null;
	}

	if ($effectiveFrom) {
		$stmt = $conn->prepare(
			"SELECT philhealth_id, monthly_rate, employee_share, employer_share, salary_floor, salary_ceiling, effective_from, effective_to
			 FROM philhealth_contribution
			 WHERE effective_from = :effective_from
			 ORDER BY philhealth_id ASC"
		);
		$stmt->execute([':effective_from' => $effectiveFrom]);
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
	}
} catch (Throwable $e) {
	$effectiveFrom = null;
	$rows = [];
}

function fmtPercent(?string $rate): string
{
	if ($rate === null || $rate === '') {
		return '-';
	}
	return rtrim(rtrim(number_format(((float)$rate) * 100, 4), '0'), '.') . '%';
}

function fmtMoney(?string $amount): string
{
	if ($amount === null || $amount === '') {
		return '-';
	}
	return number_format((float)$amount, 2);
}
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

<style>
	body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
	#wrapper { overflow-x: hidden; }
	#page-content-wrapper { min-width: 100vw; }
	@media (min-width: 768px) {
		#page-content-wrapper { min-width: 0; width: 100%; }
	}
	.ph-table thead th { vertical-align: middle; white-space: nowrap; }
	.ph-table .num { text-align: right; }
</style>

<div class="d-flex" id="wrapper">
	<?php include __DIR__ . '/../../global/sidebar.php'; ?>

	<div id="page-content-wrapper" class="w-100">
		<div class="container-fluid px-4 py-4">
			<div class="card mb-4">
				<div class="card-header bg-primary text-white">
					<h5 class="card-title mb-0 text-center" style="font-size: 1.25rem; font-weight: bold;">
						PhilHealth Contribution Settings (Effective <?php echo htmlspecialchars((string)($effectiveFrom ?: 'N/A'), ENT_QUOTES, 'UTF-8'); ?>)
					</h5>
				</div>
				<div class="card-body">
					<div class="table-responsive">
						<table class="table table-bordered table-striped ph-table mb-0">
							<thead>
								<tr class="bg-primary text-white">
									<th>MONTHLY RATE</th>
									<th>EMPLOYEE SHARE</th>
									<th>EMPLOYER SHARE</th>
									<th class="text-end">SALARY FLOOR</th>
									<th class="text-end">SALARY CEILING</th>
									<th>EFFECTIVE FROM</th>
									<th>EFFECTIVE TO</th>
								</tr>
							</thead>
							<tbody>
								<?php if (!$rows): ?>
									<tr>
										<td colspan="7" class="text-center text-muted py-4">No PhilHealth rows found<?php echo $effectiveFrom ? (' for ' . htmlspecialchars((string)$effectiveFrom, ENT_QUOTES, 'UTF-8')) : ''; ?>.</td>
									</tr>
								<?php else: ?>
									<?php foreach ($rows as $row): ?>
										<tr>
											<td><?php echo htmlspecialchars(fmtPercent($row['monthly_rate'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
											<td><?php echo htmlspecialchars(fmtPercent($row['employee_share'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
											<td><?php echo htmlspecialchars(fmtPercent($row['employer_share'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
											<td class="num"><?php echo htmlspecialchars(fmtMoney($row['salary_floor'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
											<td class="num"><?php echo htmlspecialchars(fmtMoney($row['salary_ceiling'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
											<td><?php echo htmlspecialchars((string)($row['effective_from'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
											<td><?php echo htmlspecialchars((string)($row['effective_to'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

