<?php
declare(strict_types=1);

$pageTitle = 'SSS Bracket Table';
require_once __DIR__ . '/../../global/header.php';
require_once __DIR__ . '/../../config/db_connection.php';

$effectiveFrom = null;
try {
	// Prefer the currently-effective schedule (by date range), else fall back to the most recent effective_from.
	$stmtEffective = $conn->query(
		"SELECT effective_from
		 FROM sss_bracket
		 WHERE effective_from <= CURDATE()
		   AND (effective_to IS NULL OR effective_to >= CURDATE())
		 ORDER BY effective_from DESC
		 LIMIT 1"
	);
	$effectiveFrom = $stmtEffective ? $stmtEffective->fetchColumn() : null;

	if (!$effectiveFrom) {
		$stmtEffective = $conn->query(
			"SELECT effective_from
			 FROM sss_bracket
			 ORDER BY effective_from DESC
			 LIMIT 1"
		);
		$effectiveFrom = $stmtEffective ? $stmtEffective->fetchColumn() : null;
	}
} catch (Throwable $e) {
	$effectiveFrom = null;
}

/**
 * Format a compensation limit similar to the screenshot (e.g., 5,249.99 -> 5,250).
 */
function formatLimit(?string $value): string
{
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
}

function formatRange(?string $lower, ?string $upper): string
{
	$lower = ($lower !== null && $lower !== '') ? (string)$lower : null;
	$upper = ($upper !== null && $upper !== '') ? (string)$upper : null;

	if ($lower === null && $upper !== null) {
		return 'BELOW ' . formatLimit($upper);
	}
	if ($lower !== null && $upper !== null) {
		return formatLimit($lower) . ' - ' . formatLimit($upper);
	}
	if ($lower !== null && $upper === null) {
		return formatLimit($lower) . ' AND ABOVE';
	}
	return '';
}

$rows = [];
try {
	if ($effectiveFrom) {
		$stmt = $conn->prepare(
			'SELECT sss_id, lower_limit, upper_limit, msc, regular_msc, mpf_msc,
					employee_contribution, employer_contribution, effective_from, effective_to
			 FROM sss_bracket
			 WHERE effective_from = :effective_from
			 ORDER BY COALESCE(lower_limit, 0) ASC, COALESCE(upper_limit, 999999999) ASC'
		);
		$stmt->execute([':effective_from' => $effectiveFrom]);
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
	}
} catch (Throwable $e) {
	$rows = [];
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
	
    /* Custom Blue Header Styling */
    .sss-table thead th { 
        vertical-align: middle; 
        white-space: nowrap; 
        background-color: #0d6efd !important; /* Bootstrap Primary Blue */
        color: #ffffff !important;
        border-color: #ffffff33 !important; /* Light white borders for the blue header */
        font-weight: 600;
        font-size: 0.85rem;
    }
    
    .sss-table .num { text-align: right; }
    
    /* Optional: subtle blue tint for MSC columns to match your existing logic */
    .bg-primary-subtle { background-color: #e7f1ff !important; }

</style>

<div class="d-flex" id="wrapper">
	<?php include __DIR__ . '/../../global/sidebar.php'; ?>

	<div id="page-content-wrapper" class="w-100">
		<div class="container-fluid px-4 py-4">
			<div class="card mb-4">
				<div class="card-header">
					<h5 class="card-title mb-0 text-center" style="font-size: 1.25rem; font-weight: bold;">
						Schedule of SSS Contributions (Effective <?php echo htmlspecialchars((string)($effectiveFrom ?: 'N/A'), ENT_QUOTES, 'UTF-8'); ?>)
					</h5>
				</div>
				<div class="card-body">
					<div class="table-responsive">
						<table class="table table-bordered table-striped sss-table mb-0">
							<thead>
								<tr class="bg-primary text-white">
									<th rowspan="3">RANGE OF<br>COMPENSATION</th>
									<th colspan="3" class="text-center">MONTHLY SALARY CREDIT</th>
									<th colspan="7" class="text-center">AMOUNT OF CONTRIBUTIONS</th>
									<th rowspan="3" class="text-center">TOTAL</th>
								</tr>
								<tr class="bg-primary text-white">
									<th class="text-center" rowspan="2">REGULAR SS<br>EC</th>
									<th class="text-center" rowspan="2">MPF</th>
									<th class="text-center" rowspan="2">TOTAL</th>
									<th colspan="4" class="text-center">EMPLOYER</th>
									<th colspan="3" class="text-center">EMPLOYEE</th>
								</tr>
								<tr class="bg-primary text-white">
									<th class="text-center">REGULAR SS</th>
									<th class="text-center">MPF</th>
									<th class="text-center">EC</th>
									<th class="text-center">TOTAL</th>
									<th class="text-center">REGULAR SS</th>
									<th class="text-center">MPF</th>
									<th class="text-center">TOTAL</th>
								</tr>
							</thead>
							<tbody>
								<?php if (!$rows): ?>
									<tr>
										<td colspan="12" class="text-center text-muted py-4">No bracket rows found<?php echo $effectiveFrom ? (' for ' . htmlspecialchars((string)$effectiveFrom, ENT_QUOTES, 'UTF-8')) : ''; ?>.</td>
									</tr>
								<?php else: ?>
									<?php foreach ($rows as $row): ?>
										<?php
											$mscRegular = (float)($row['regular_msc'] ?? 0);
											$mscMpf = (float)($row['mpf_msc'] ?? 0);
											$mscTotal = (float)($row['msc'] ?? 0);
											$employee = (float)($row['employee_contribution'] ?? 0);
											$employer = (float)($row['employer_contribution'] ?? 0);
											$total = $employee + $employer;
										?>
										<tr>
											<td><?php echo htmlspecialchars(formatRange($row['lower_limit'] ?? null, $row['upper_limit'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>

											<!-- Monthly Salary Credit -->
											<td class="num bg-primary-subtle"><?php echo number_format($mscRegular, 2); ?></td>
											<td class="num bg-primary-subtle"><?php echo number_format($mscMpf, 2); ?></td>
											<td class="num bg-primary-subtle"><?php echo number_format($mscTotal, 2); ?></td>

											<!-- Employer -->
											<td class="num"><?php echo number_format($employer, 2); ?></td>
											<td class="text-center">-</td>
											<td class="text-center">-</td>
											<td class="num"><?php echo number_format($employer, 2); ?></td>

											<!-- Employee -->
											<td class="num"><?php echo number_format($employee, 2); ?></td>
											<td class="text-center">-</td>
											<td class="num"><?php echo number_format($employee, 2); ?></td>

											<td class="num fw-semibold"><?php echo number_format($total, 2); ?></td>
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

