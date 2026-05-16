<?php
declare(strict_types=1);

$pageTitle = 'Philhealth Contribution';
require_once __DIR__ . '/../../middleware/auth_checker.php';
checkAccess('ACCOUNTING');
require_once __DIR__ . '/../../template/header.php';
require_once __DIR__ . '/../../controller/philhealth_contribution_controller.php';
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
	<?php include __DIR__ . '/../../template/sidebar.php'; ?>

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
								<?php if (!$displayRows): ?>
									<tr>
										<td colspan="7" class="text-center text-muted py-4">No PhilHealth rows found<?php echo $effectiveFrom ? (' for ' . htmlspecialchars((string)$effectiveFrom, ENT_QUOTES, 'UTF-8')) : ''; ?>.</td>
									</tr>
								<?php else: ?>
									<?php foreach ($displayRows as $row): ?>
										<tr>
											<td><?php echo htmlspecialchars((string)($row['monthly_rate'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
											<td><?php echo htmlspecialchars((string)($row['employee_share'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
											<td><?php echo htmlspecialchars((string)($row['employer_share'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
											<td class="num"><?php echo htmlspecialchars((string)($row['salary_floor'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
											<td class="num"><?php echo htmlspecialchars((string)($row['salary_ceiling'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
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

