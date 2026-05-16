<?php
declare(strict_types=1);

$pageTitle = 'SSS Bracket Table';
require_once __DIR__ . '/../../middleware/auth_checker.php';
checkAccess('ACCOUNTING');
require_once __DIR__ . '/../../template/header.php';
require_once __DIR__ . '/../../controller/sss_bracket_controller.php';
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
	<?php include __DIR__ . '/../../template/sidebar.php'; ?>

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
								<?php if (!$displayRows): ?>
									<tr>
										<td colspan="12" class="text-center text-muted py-4">No bracket rows found<?php echo $effectiveFrom ? (' for ' . htmlspecialchars((string)$effectiveFrom, ENT_QUOTES, 'UTF-8')) : ''; ?>.</td>
									</tr>
								<?php else: ?>
									<?php foreach ($displayRows as $row): ?>
										<tr>
											<td><?php echo htmlspecialchars((string)($row['range'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>

											<!-- Monthly Salary Credit -->
											<td class="num bg-primary-subtle"><?php echo htmlspecialchars((string)($row['msc_regular'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
											<td class="num bg-primary-subtle"><?php echo htmlspecialchars((string)($row['msc_mpf'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
											<td class="num bg-primary-subtle"><?php echo htmlspecialchars((string)($row['msc_total'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>

											<!-- Employer -->
											<td class="num"><?php echo htmlspecialchars((string)($row['employer'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
											<td class="text-center">-</td>
											<td class="text-center">-</td>
											<td class="num"><?php echo htmlspecialchars((string)($row['employer'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>

											<!-- Employee -->
											<td class="num"><?php echo htmlspecialchars((string)($row['employee'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
											<td class="text-center">-</td>
											<td class="num"><?php echo htmlspecialchars((string)($row['employee'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>

											<td class="num fw-semibold"><?php echo htmlspecialchars((string)($row['total'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
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

