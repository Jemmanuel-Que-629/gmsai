<?php
declare(strict_types=1);

$pageTitle = 'Daily Time Record';
require_once __DIR__ . '/../../middleware/auth_checker.php';
checkAccess('ACCOUNTING');
require_once __DIR__ . '/../../template/header.php';
require_once __DIR__ . '/../../controller/dtr_controller.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

<style>
	body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
	#wrapper { overflow-x: hidden; }
	#page-content-wrapper {
		flex: 1;
		min-width: 0;
		width: 100%;
		overflow-x: hidden;
	}
</style>

<div class="d-flex" id="wrapper">
	<?php include __DIR__ . '/../../template/sidebar.php'; ?>

	<div id="page-content-wrapper" class="w-100">
		<div class="container-fluid py-4">
			<div class="card shadow-sm border-0">
				<div class="card-header bg-white py-3">
					<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
						<div>
							<h5 class="mb-0 fw-600" style="color: #003366;">Daily Time Record</h5>
							<div class="text-muted small">View employees and attendance by date range and location</div>
						</div>
						<input type="text" id="tabulator-search" class="form-control form-control-sm" style="max-width: 280px;" placeholder="Search all columns...">
					</div>
				</div>

				<div class="card-body">
									<div class="row g-2 mb-3">
										<div class="col-12 col-md-4">
											<div class="card text-white bg-danger h-100 dtr-summary-card" role="button" tabindex="0" data-bucket="missing">
												<div class="card-body py-2">
													<div class="small fw-semibold">Missing Logs</div>
													<div class="fs-5 fw-bold"><?php echo (int)$missingEmployees; ?></div>
													<div class="small opacity-75">Time-in without time-out</div>
												</div>
											</div>
										</div>
										<div class="col-12 col-md-4">
											<div class="card text-white bg-warning h-100 dtr-summary-card" role="button" tabindex="0" data-bucket="discrepancy">
												<div class="card-body py-2">
													<div class="small fw-semibold">Discrepancies</div>
													<div class="fs-5 fw-bold"><?php echo (int)$discrepancyEmployees; ?></div>
													<div class="small opacity-75">No record / invalid / incomplete</div>
												</div>
											</div>
										</div>
										<div class="col-12 col-md-4">
											<div class="card text-white bg-success h-100 dtr-summary-card" role="button" tabindex="0" data-bucket="ready">
												<div class="card-body py-2">
													<div class="small fw-semibold">Ready for Payroll</div>
													<div class="fs-5 fw-bold"><?php echo (int)$readyEmployees; ?></div>
													<div class="small opacity-75">Complete logs only</div>
												</div>
											</div>
										</div>
									</div>

					<form method="GET" class="row g-2 align-items-end mb-3" novalidate>
						<div class="col-12 col-md-3">
							<label class="form-label mb-1">Start Date</label>
							<input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8'); ?>" required>
						</div>
						<div class="col-12 col-md-3">
							<label class="form-label mb-1">End Date</label>
							<input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8'); ?>" required>
						</div>
						<div class="col-12 col-md-4">
							<label class="form-label mb-1">Location</label>
							<select name="location_id" class="form-select form-select-sm">
								<option value="">All Locations</option>
								<?php foreach ($locations as $loc):
									$lid = (int)($loc['location_id'] ?? 0);
									$lname = (string)($loc['location_name'] ?? '');
									$selected = ($locationId !== null && $lid === $locationId) ? 'selected' : '';
								?>
									<option value="<?php echo htmlspecialchars((string)$lid, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selected; ?>>
										<?php echo htmlspecialchars($lname, ENT_QUOTES, 'UTF-8'); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-12 col-md-2">
							<button type="submit" class="btn btn-success btn-sm w-100">Apply Filter</button>
						</div>
					</form>

					<div id="dtr-table"></div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Tabulator Dependencies -->
<link href="https://unpkg.com/tabulator-tables@5.5.0/dist/css/tabulator_bootstrap5.min.css" rel="stylesheet">
<script type="text/javascript" src="https://unpkg.com/tabulator-tables@5.5.0/dist/js/tabulator.min.js"></script>

<script nonce="<?php echo htmlspecialchars(csp_nonce(), ENT_QUOTES, 'UTF-8'); ?>">
	(function () {
		const tableData = <?php echo $tableJson ?: '[]'; ?>;

		function escapeHtml(s) {
			return String(s || '')
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#39;');
		}

		const table = new Tabulator("#dtr-table", {
			data: tableData,
			layout: "fitColumns",
			responsiveLayout: "collapse",
			groupBy: "employee_id",
			groupStartOpen: false,
			groupHeader: function (value, count, data) {
				const first = data && data.length ? data[0] : {};
				const name = escapeHtml(first.employee_name || 'Employee');
				const loc = escapeHtml(first.location || '');
				const empNo = escapeHtml(first.employee_num_id || String(value || ''));
				const total = Number(first.employee_total_hours || 0).toFixed(2);
				const bucket = String(first.employee_bucket || 'discrepancy');

				let badge = 'bg-secondary';
				let label = 'Discrepancy';
				if (bucket === 'missing') {
					badge = 'bg-danger text-white';
					label = 'Missing Logs';
				} else if (bucket === 'ready') {
					badge = 'bg-success text-white';
					label = 'Ready';
				} else {
					badge = 'bg-warning text-white';
					label = 'Discrepancy';
				}

				return `${name} <span class="text-muted">(${empNo})</span>`
					+ (loc ? ` <span class="text-muted">• ${loc}</span>` : '')
					+ ` <span class="ms-2 badge ${badge}">${escapeHtml(label)}</span>`
					+ ` <span class="ms-2 text-muted">Total Hours: ${escapeHtml(total)}</span>`
					+ ` <span class="ms-2 text-muted">(${escapeHtml(count)} row(s))</span>`;
			},
			pagination: "local",
			paginationSize: 10,
			paginationSizeSelector: [10, 25, 50, 100],
			movableColumns: true,
			placeholder: "No employees/attendance found",
			columns: [
				{ title: "Employee ID", field: "employee_id", visible: false },
				{ title: "Employee No.", field: "employee_num_id", visible: false },
				{ title: "Name", field: "employee_name", visible: false },
				{ title: "Department", field: "department", visible: false },
				{ title: "Position", field: "position", visible: false },
				{ title: "Location", field: "location", visible: false },
				{
					title: "Work Date",
					field: "work_date",
					width: 140,
					sorter: "date",
					formatter: function (cell) {
						const v = cell.getValue();
						return v ? v : '<span class="text-muted">—</span>';
					}
				},
				{
					title: "Time In",
					field: "time_in",
					width: 120,
					formatter: function (cell) {
						const v = cell.getValue();
						return v ? v : '<span class="text-muted">—</span>';
					}
				},
				{
					title: "Time Out",
					field: "time_out",
					width: 120,
					formatter: function (cell) {
						const v = cell.getValue();
						return v ? v : '<span class="text-muted">—</span>';
					}
				},
				{
					title: "Hours",
					field: "hours",
					width: 110,
					sorter: "number",
					formatter: function (cell) {
						const v = Number(cell.getValue() || 0);
						return v > 0 ? v.toFixed(2) : '<span class="text-muted">—</span>';
					}
				},
				{
					title: "Status",
					field: "status",
					width: 140,
					formatter: function (cell) {
						const v = cell.getValue();
						const map = {
							"Complete": "bg-success text-white",
							"Missing Log": "bg-danger text-white",
							"Discrepancy": "bg-warning text-white",
							"Present (No Time)": "bg-info text-white",
							"No Record": "bg-secondary text-white",
						};
						const cls = map[v] || 'bg-secondary';
						const safe = escapeHtml(v || '');
						return `<span class="badge ${cls}">${safe}</span>`;
					}
				},
			],
		});

		let activeBucket = '';
		let searchValue = '';

		function applyClientFilters() {
			table.setFilter(function (data) {
				if (activeBucket && String(data.employee_bucket || '') !== activeBucket) {
					return false;
				}
				if (!searchValue) {
					return true;
				}
				const hay = (
					String(data.employee_id || '') + ' ' +
					String(data.employee_num_id || '') + ' ' +
					String(data.employee_name || '') + ' ' +
					String(data.department || '') + ' ' +
					String(data.position || '') + ' ' +
					String(data.location || '') + ' ' +
					String(data.work_date || '') + ' ' +
					String(data.time_in || '') + ' ' +
					String(data.time_out || '') + ' ' +
					String(data.status || '')
				).toLowerCase();
				return hay.includes(searchValue);
			});
		}

		const search = document.getElementById('tabulator-search');
		if (search) {
			search.addEventListener('input', function (e) {
				searchValue = String(e.target.value || '').trim().toLowerCase();
				applyClientFilters();
			});
		}

		function setActiveCard(bucket) {
			const cards = document.querySelectorAll('.dtr-summary-card');
			cards.forEach(function (c) {
				const b = c.getAttribute('data-bucket') || '';
				if (bucket && b === bucket) {
					c.classList.add('border', 'border-2', 'border-dark');
				} else {
					c.classList.remove('border', 'border-2', 'border-dark');
				}
			});
		}

		document.querySelectorAll('.dtr-summary-card').forEach(function (card) {
			function activate() {
				const bucket = String(card.getAttribute('data-bucket') || '');
				if (!bucket) return;
				activeBucket = (activeBucket === bucket) ? '' : bucket;
				setActiveCard(activeBucket);
				applyClientFilters();
			}
			card.addEventListener('click', activate);
			card.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					activate();
				}
			});
		});

		const showToast = <?php echo $filtersAppliedByUser ? 'true' : 'false'; ?>;
		if (showToast && typeof Swal !== 'undefined') {
			const msg = <?php echo json_encode($filterToastText, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
			Swal.fire({
				toast: true,
				position: 'top-end',
				icon: 'success',
				title: 'Filter applied',
				text: msg,
				showConfirmButton: false,
				timer: 2200,
				timerProgressBar: true
			});
		}
	})();
</script>

