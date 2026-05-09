<?php
declare(strict_types=1);

$pageTitle = 'Daily Time Record';
require_once __DIR__ . '/../../global/header.php';
require_once __DIR__ . '/../../config/db_connection.php';

function dtr_parse_date(?string $s): ?string
{
	if ($s === null) return null;
	$s = trim($s);
	if ($s === '') return null;
	$dt = DateTime::createFromFormat('Y-m-d', $s);
	if (!$dt) return null;
	$errs = DateTime::getLastErrors();
	if (is_array($errs) && (($errs['warning_count'] ?? 0) > 0 || ($errs['error_count'] ?? 0) > 0)) {
		return null;
	}
	return $dt->format('Y-m-d');
}

function dtr_time_to_seconds(mixed $t): ?int
{
	if ($t === null) return null;
	if (!is_scalar($t)) return null;
	$s = trim((string)$t);
	if ($s === '') return null;
	$dt = DateTime::createFromFormat('H:i:s', $s) ?: DateTime::createFromFormat('H:i', $s);
	if (!$dt) return null;
	$errs = DateTime::getLastErrors();
	if (is_array($errs) && (($errs['warning_count'] ?? 0) > 0 || ($errs['error_count'] ?? 0) > 0)) {
		return null;
	}
	$h = (int)$dt->format('H');
	$m = (int)$dt->format('i');
	$sec = (int)$dt->format('s');
	return ($h * 3600) + ($m * 60) + $sec;
}

// Filters (date range is always present; default to today)
$rawStart = isset($_GET['start_date']) ? (string)$_GET['start_date'] : null;
$rawEnd = isset($_GET['end_date']) ? (string)$_GET['end_date'] : null;
$rawLocation = isset($_GET['location_id']) ? (string)$_GET['location_id'] : '';

$today = (new DateTime('now'))->format('Y-m-d');
$startDate = dtr_parse_date($rawStart) ?? $today;
$endDate = dtr_parse_date($rawEnd) ?? $today;

if ($startDate > $endDate) {
	// Swap to keep range valid.
	[$startDate, $endDate] = [$endDate, $startDate];
}

$locationId = null;
if ($rawLocation !== '' && ctype_digit($rawLocation)) {
	$tmp = (int)$rawLocation;
	if ($tmp > 0) {
		$locationId = $tmp;
	}
}

// Locations for dropdown
$locations = [];
try {
	$locStmt = $conn->query('SELECT location_id, location_name FROM location_rate ORDER BY location_name ASC');
	if ($locStmt) {
		$locations = $locStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
	}
} catch (Throwable $e) {
	$locations = [];
}

$selectedLocationName = 'All Locations';
if ($locationId !== null) {
	foreach ($locations as $loc) {
		if ((int)($loc['location_id'] ?? 0) === $locationId) {
			$selectedLocationName = (string)($loc['location_name'] ?? 'Selected Location');
			break;
		}
	}
}

// Query: show ALL employees, even if no attendance in the selected range (LEFT JOIN)
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

$rows = [];
try {
	$stmt = $conn->prepare($sql);
	$stmt->execute($params);
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
	$rows = [];
}

// Shape for Tabulator
$tableData = [];
$employeeStats = [];
foreach ($rows as $r) {
	$first = trim((string)($r['first_name'] ?? ''));
	$middle = trim((string)($r['middle_name'] ?? ''));
	$last = trim((string)($r['last_name'] ?? ''));
	$employeeNum = trim((string)($r['employee_num_id'] ?? ''));

	$mi = $middle !== '' ? (strtoupper(substr($middle, 0, 1)) . '.') : '';
	$nameParts = array_values(array_filter([
		$last !== '' ? ($last . ',') : '',
		$first,
		$mi,
	], static fn($v) => is_string($v) && trim($v) !== ''));
	$employeeName = trim(implode(' ', $nameParts));

	$workDate = $r['work_date'] ?? null;
	$timeIn = $r['time_in'] ?? null;
	$timeOut = $r['time_out'] ?? null;
	$employeeId = (int)($r['employee_id'] ?? 0);

	if (!isset($employeeStats[$employeeId])) {
		$employeeStats[$employeeId] = [
			'employee_id' => $employeeId,
			'employee_num_id' => $employeeNum,
			'employee_name' => $employeeName,
			'location' => (string)($r['location_name'] ?? ''),
			'total_seconds' => 0,
			'has_any_date' => false,
			'has_any_complete' => false,
			'missing_days' => 0,
			'discrepancy_days' => 0,
			'bucket' => 'discrepancy',
		];
	}

	$hasDate = ($workDate !== null && (string)$workDate !== '');
	if ($hasDate) {
		$employeeStats[$employeeId]['has_any_date'] = true;
	}

	$inSec = dtr_time_to_seconds($timeIn);
	$outSec = dtr_time_to_seconds($timeOut);
	$rowSeconds = 0;
	$hasTimeIn = ($inSec !== null);
	$hasTimeOut = ($outSec !== null);

	$status = 'No Record';
	if ($workDate !== null && (string)$workDate !== '') {
		if ($hasTimeIn && $hasTimeOut) {
			if ($outSec >= $inSec) {
				$status = 'Complete';
				$rowSeconds = $outSec - $inSec;
				$employeeStats[$employeeId]['total_seconds'] += $rowSeconds;
				$employeeStats[$employeeId]['has_any_complete'] = true;
			} else {
				// Invalid time range
				$status = 'Discrepancy';
				$employeeStats[$employeeId]['discrepancy_days']++;
			}
		} elseif ($hasTimeIn && !$hasTimeOut) {
			// Time-in but no time-out
			$status = 'Missing Log';
			$employeeStats[$employeeId]['missing_days']++;
		} elseif (!$hasTimeIn && $hasTimeOut) {
			// Time-out without time-in
			$status = 'Discrepancy';
			$employeeStats[$employeeId]['discrepancy_days']++;
		} else {
			$status = 'Present (No Time)';
			$employeeStats[$employeeId]['discrepancy_days']++;
		}
	}

	$tableData[] = [
		'employee_id' => $employeeId,
		'employee_num_id' => $employeeNum,
		'employee_name' => $employeeName,
		'department' => (string)($r['department'] ?? ''),
		'position' => (string)($r['position'] ?? ''),
		'location' => (string)($r['location_name'] ?? ''),
		'work_date' => $workDate,
		'time_in' => $timeIn,
		'time_out' => $timeOut,
		'status' => $status,
		'hours' => $rowSeconds > 0 ? round($rowSeconds / 3600, 2) : 0,
		'employee_bucket' => 'discrepancy',
		'employee_total_hours' => 0,
	];
}

// Employee buckets + totals (Exception-Based Grouping)
$missingEmployees = 0;
$discrepancyEmployees = 0;
$readyEmployees = 0;
foreach ($employeeStats as $eid => $s) {
	$bucket = 'discrepancy';
	if (($s['missing_days'] ?? 0) > 0) {
		$bucket = 'missing';
	} elseif (($s['discrepancy_days'] ?? 0) > 0 || !($s['has_any_date'] ?? false)) {
		$bucket = 'discrepancy';
	} elseif (($s['has_any_complete'] ?? false)) {
		$bucket = 'ready';
	} else {
		$bucket = 'discrepancy';
	}

	$employeeStats[$eid]['bucket'] = $bucket;
	$employeeStats[$eid]['total_hours'] = round(((int)($s['total_seconds'] ?? 0)) / 3600, 2);

	if ($bucket === 'missing') {
		$missingEmployees++;
	} elseif ($bucket === 'ready') {
		$readyEmployees++;
	} else {
		$discrepancyEmployees++;
	}
}

foreach ($tableData as &$row) {
	$eid = (int)($row['employee_id'] ?? 0);
	if (isset($employeeStats[$eid])) {
		$row['employee_bucket'] = (string)$employeeStats[$eid]['bucket'];
		$row['employee_total_hours'] = (float)$employeeStats[$eid]['total_hours'];
		if (!isset($row['employee_num_id']) || (string)$row['employee_num_id'] === '') {
			$row['employee_num_id'] = (string)($employeeStats[$eid]['employee_num_id'] ?? '');
		}
	}
}
unset($row);

$tableJson = json_encode(
	$tableData,
	JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE
);

// Toast: only when user explicitly set filters via query params
$filtersAppliedByUser = array_key_exists('start_date', $_GET) || array_key_exists('end_date', $_GET) || array_key_exists('location_id', $_GET);
$filterToastText = 'Date: ' . $startDate . ' to ' . $endDate . ' | ' . $selectedLocationName;
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
	<?php include __DIR__ . '/../../global/sidebar.php'; ?>

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

