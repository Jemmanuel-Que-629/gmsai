<?php

declare(strict_types=1);

$pageTitle = 'Holiday Calendar';
include '../../template/header.php';

require_once '../../middleware/csrf.php';
csrf_init();
$csrfToken = csrf_token();

$defaultYear = (int)date('Y');
$defaultMonth = (int)date('n');
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
	body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
	#wrapper { overflow-x: hidden; }
	#page-content-wrapper { min-width: 100vw; }
	@media (min-width: 768px) {
		#page-content-wrapper { min-width: 0; width: 100%; }
	}

	.gms-legend-swatch {
		width: 16px;
		height: 16px;
		display: inline-block;
		border-radius: 4px;
	}
	.gms-cal-month-title {
		letter-spacing: 0.2px;
	}
	.gms-cal-navbtn {
		width: 40px;
		height: 40px;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		border-radius: 10px;
	}
	.gms-cal-table th {
		font-size: 12px;
		color: #6c757d;
		text-transform: uppercase;
		letter-spacing: 0.6px;
		padding: 6px;
		background: #f8f9fa;
	}
	.gms-cal-table td {
		vertical-align: top;
		height: 92px;
		padding: 6px;
		background: #ffffff;
	}
	.gms-cal-table td.gms-cal-empty {
		background: #fbfbfb;
	}
	.gms-cal-daynum {
		font-size: 12px;
		font-weight: 700;
		color: #495057;
		line-height: 1;
		margin-bottom: 6px;
	}
	.gms-cal-badge {
		display: block;
		width: 100%;
		white-space: normal;
		text-align: left;
		line-height: 1.2;
		padding: 6px 8px;
		margin-bottom: 6px;
		border-radius: 8px;
		font-size: 12px;
	}
	.gms-cal-badge small {
		display: block;
		opacity: 0.9;
		font-size: 11px;
		margin-top: 2px;
	}
	.gms-cal-badge { cursor: pointer; }
</style>

<div class="d-flex" id="wrapper">
	<?php include '../../template/sidebar.php'; ?>

	<div id="page-content-wrapper" class="w-100">
		<div class="container-fluid px-4 py-4">
			<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
				<div>
					<h4 class="mb-0 fw-bold">Holiday Calendar</h4>
					<div class="text-muted small">Color-coded holiday calendar (Accounting)</div>
				</div>
				<div class="d-flex align-items-center gap-2">
					<button type="button" class="btn btn-outline-secondary gms-cal-navbtn" id="prevMonthBtn" aria-label="Previous month">
						<span class="material-symbols-outlined">chevron_left</span>
					</button>
					<div class="text-center" style="min-width: 220px;">
						<div class="fw-bold gms-cal-month-title" id="monthLabel">&nbsp;</div>
						<div class="d-flex align-items-center justify-content-center gap-2">
							<label for="yearSelect" class="form-label mb-0 small text-muted">Year</label>
							<select id="yearSelect" class="form-select form-select-sm" style="width: 110px;"></select>
						</div>
					</div>
					<button type="button" class="btn btn-outline-secondary gms-cal-navbtn" id="nextMonthBtn" aria-label="Next month">
						<span class="material-symbols-outlined">chevron_right</span>
					</button>
					<button type="button" class="btn btn-success btn-sm" id="addHolidayBtn">Add Holiday</button>
				</div>
			</div>

			<div class="card border-0 shadow-sm p-3 mb-3">
				<div class="fw-semibold mb-2">Legend</div>
				<div class="d-flex flex-wrap gap-3">
					<div class="d-flex align-items-center gap-2">
						<span class="gms-legend-swatch bg-danger"></span>
						<span class="small">Regular</span>
					</div>
					<div class="d-flex align-items-center gap-2">
						<span class="gms-legend-swatch bg-warning"></span>
						<span class="small">Special Non-Working</span>
					</div>
					<div class="d-flex align-items-center gap-2">
						<span class="gms-legend-swatch bg-primary"></span>
						<span class="small">Special Working</span>
					</div>
					<div class="d-flex align-items-center gap-2">
						<span class="gms-legend-swatch bg-success"></span>
						<span class="small">Company</span>
					</div>
				</div>
			</div>

			<div id="calendarStatus" class="text-muted small mb-2"></div>
			<div class="card border-0 shadow-sm">
				<div class="p-3">
					<table class="table table-bordered table-sm mb-0 gms-cal-table" id="monthTable">
						<thead>
							<tr>
								<th class="text-center">Sun</th>
								<th class="text-center">Mon</th>
								<th class="text-center">Tue</th>
								<th class="text-center">Wed</th>
								<th class="text-center">Thu</th>
								<th class="text-center">Fri</th>
								<th class="text-center">Sat</th>
							</tr>
						</thead>
						<tbody id="monthBody"></tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="holidayModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="holidayModalTitle">Holiday</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<input type="hidden" id="holidayId" value="">
				<div class="mb-2">
					<label class="form-label fw-semibold">Date</label>
					<input type="date" class="form-control form-control-sm" id="holidayDate" required>
				</div>
				<div class="mb-2">
					<label class="form-label fw-semibold">Name</label>
					<input type="text" class="form-control form-control-sm" id="holidayName" maxlength="100" required>
				</div>
				<div class="mb-2">
					<label class="form-label fw-semibold">Type</label>
					<select class="form-select form-select-sm" id="holidayType" required>
						<option value="regular">regular</option>
						<option value="special_non_working">special_non_working</option>
						<option value="special_working">special_working</option>
						<option value="company">company</option>
					</select>
				</div>
				<div class="form-check">
					<input class="form-check-input" type="checkbox" id="holidayRecurring">
					<label class="form-check-label" for="holidayRecurring">Recurring (fixed date)</label>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-danger btn-sm me-auto" id="deleteHolidayBtn" style="display:none;">Delete</button>
				<button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-success btn-sm" id="saveHolidayBtn">Save</button>
			</div>
		</div>
	</div>
</div>

<script nonce="<?php echo htmlspecialchars(csp_nonce(), ENT_QUOTES, 'UTF-8'); ?>">
	(function () {
		const csrfToken = <?php echo json_encode((string)$csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
		let currentYear = <?php echo (int)$defaultYear; ?>;
		let currentMonth = <?php echo (int)$defaultMonth; ?>; // 1-12
		let holidays = [];

		const yearSelect = document.getElementById('yearSelect');
		const statusEl = document.getElementById('calendarStatus');
		const monthLabel = document.getElementById('monthLabel');
		const monthBody = document.getElementById('monthBody');
		const prevBtn = document.getElementById('prevMonthBtn');
		const nextBtn = document.getElementById('nextMonthBtn');
		const addBtn = document.getElementById('addHolidayBtn');

		const modalEl = document.getElementById('holidayModal');
		const modal = new bootstrap.Modal(modalEl);
		const holidayIdEl = document.getElementById('holidayId');
		const holidayDateEl = document.getElementById('holidayDate');
		const holidayNameEl = document.getElementById('holidayName');
		const holidayTypeEl = document.getElementById('holidayType');
		const holidayRecurringEl = document.getElementById('holidayRecurring');
		const saveBtn = document.getElementById('saveHolidayBtn');
		const deleteBtn = document.getElementById('deleteHolidayBtn');
		const modalTitle = document.getElementById('holidayModalTitle');

		const monthNames = [
			'January', 'February', 'March', 'April', 'May', 'June',
			'July', 'August', 'September', 'October', 'November', 'December'
		];
		function pad2(n) {
			return String(n).padStart(2, '0');
		}

		function escapeHtml(s) {
			return String(s)
				.replaceAll('&', '&amp;')
				.replaceAll('<', '&lt;')
				.replaceAll('>', '&gt;')
				.replaceAll('"', '&quot;')
				.replaceAll("'", '&#039;');
		}

		function normalizeType(type) {
			const t = String(type || '').toLowerCase().trim();
			if (t === 'regular') return 'regular';
			if (t === 'special_non_working') return 'special_non_working';
			if (t === 'special_working') return 'special_working';
			if (t === 'company' || t === 'company_holiday' || t === 'companyholiday') return 'company';
			return t || 'regular';
		}

		function typeLabel(type) {
			switch (normalizeType(type)) {
				case 'regular': return 'regular';
				case 'special_non_working': return 'special_non_working';
				case 'special_working': return 'special_working';
				case 'company': return 'company';
				default: return String(type || 'regular');
			}
		}

		function badgeClass(type) {
			switch (normalizeType(type)) {
				case 'regular': return 'bg-danger text-white';
				case 'special_non_working': return 'bg-warning text-dark';
				case 'special_working': return 'bg-primary text-white';
				case 'company': return 'bg-success text-white';
				default: return 'bg-secondary text-white';
			}
		}

		function buildYearOptions(selectedYear) {
			yearSelect.innerHTML = '';

			const start = Math.min(2026, selectedYear);
			const end = Math.max(start + 12, selectedYear);
			for (let y = start; y <= end; y++) {
				const opt = document.createElement('option');
				opt.value = String(y);
				opt.textContent = String(y);
				if (y === selectedYear) opt.selected = true;
				yearSelect.appendChild(opt);
			}
		}

		function toDateKey(year, month, day) {
			return `${year}-${pad2(month)}-${pad2(day)}`;
		}

		function setModalMode(mode) {
			if (mode === 'create') {
				modalTitle.textContent = 'Add Holiday';
				deleteBtn.style.display = 'none';
			} else {
				modalTitle.textContent = 'Edit Holiday';
				deleteBtn.style.display = '';
			}
		}

		function openCreateModal() {
			setModalMode('create');
			holidayIdEl.value = '';
			holidayDateEl.value = `${currentYear}-${pad2(currentMonth)}-01`;
			holidayNameEl.value = '';
			holidayTypeEl.value = 'regular';
			holidayRecurringEl.checked = false;
			modal.show();
		}

		function openEditModal(holiday) {
			setModalMode('edit');
			holidayIdEl.value = String(holiday.holiday_id);
			holidayDateEl.value = String(holiday.holiday_date).slice(0, 10);
			holidayNameEl.value = String(holiday.holiday_name || '');
			holidayTypeEl.value = normalizeType(holiday.holiday_type);
			holidayRecurringEl.checked = String(holiday.is_recurring || '0') === '1' || holiday.is_recurring === 1;
			modal.show();
		}

		function getHolidayById(id) {
			return holidays.find(h => String(h.holiday_id) === String(id)) || null;
		}

		async function fetchMonth(year, month) {
			const url = `../../backend/calendar/unified_calendar_process.php?action=list&year=${encodeURIComponent(year)}&month=${encodeURIComponent(month)}`;
			const res = await fetch(url, { credentials: 'same-origin' });
			const data = await res.json();
			if (!res.ok || !data || data.success !== true) {
				const msg = (data && (data.error || data.message)) ? String(data.error || data.message) : 'Failed to load holidays.';
				throw new Error(msg);
			}
			return Array.isArray(data.holidays) ? data.holidays : [];
		}

		function renderMonthGrid() {
			monthLabel.textContent = `${monthNames[currentMonth - 1]} ${currentYear}`;
			monthBody.innerHTML = '';

			const firstDay = new Date(currentYear, currentMonth - 1, 1).getDay();
			const daysInMonth = new Date(currentYear, currentMonth, 0).getDate();

			const map = new Map();
			for (const h of holidays) {
				const date = String(h.holiday_date || '').slice(0, 10);
				if (!date) continue;
				if (!map.has(date)) map.set(date, []);
				map.get(date).push(h);
			}

			let current = 1 - firstDay;
			for (let week = 0; week < 6; week++) {
				const tr = document.createElement('tr');
				for (let dow = 0; dow < 7; dow++) {
					const td = document.createElement('td');
					if (current < 1 || current > daysInMonth) {
						td.className = 'gms-cal-empty';
						td.innerHTML = '&nbsp;';
					} else {
						const dayNum = document.createElement('div');
						dayNum.className = 'gms-cal-daynum';
						dayNum.textContent = String(current);
						td.appendChild(dayNum);

						const key = toDateKey(currentYear, currentMonth, current);
						const items = map.get(key) || [];
						for (const item of items) {
							const div = document.createElement('div');
							div.className = `gms-cal-badge ${badgeClass(item.holiday_type)}`;
							div.dataset.holidayId = String(item.holiday_id);
							div.innerHTML = `${escapeHtml(item.holiday_name)}<small>${escapeHtml(typeLabel(item.holiday_type))}</small>`;
							div.addEventListener('click', function () {
								const h = getHolidayById(div.dataset.holidayId);
								if (h) openEditModal(h);
							});
							td.appendChild(div);
						}
					}
					tr.appendChild(td);
					current++;
				}
				monthBody.appendChild(tr);
			}
		}

		async function loadAndRender() {
			statusEl.textContent = 'Loading holidays...';

			try {
				holidays = await fetchMonth(currentYear, currentMonth);
				statusEl.textContent = `${holidays.length} holiday(ies) loaded for ${monthNames[currentMonth - 1]} ${currentYear}.`;
				renderMonthGrid();
			} catch (e) {
				statusEl.textContent = `Error: ${e && e.message ? e.message : 'Unable to load holidays.'}`;
			}
		}

		async function postAction(payload) {
			const res = await fetch('../../backend/calendar/unified_calendar_process.php', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams(payload)
			});
			const data = await res.json();
			if (!res.ok || !data || data.success !== true) {
				const msg = (data && (data.error || data.message)) ? String(data.error || data.message) : 'Request failed.';
				throw new Error(msg);
			}
			return data;
		}

		async function saveHoliday() {
			const id = holidayIdEl.value.trim();
			const date = holidayDateEl.value;
			const name = holidayNameEl.value.trim();
			const type = holidayTypeEl.value;
			const isRecurring = holidayRecurringEl.checked ? '1' : '0';

			if (!date || !name || !type) {
				Swal.fire({ icon: 'warning', title: 'Missing fields', text: 'Please fill date, name, and type.' });
				return;
			}

			try {
				if (id === '') {
					await postAction({
						action: 'create',
						csrf_token: csrfToken,
						holiday_date: date,
						holiday_name: name,
						holiday_type: type,
						is_recurring: isRecurring
					});
				} else {
					await postAction({
						action: 'update',
						csrf_token: csrfToken,
						holiday_id: id,
						holiday_date: date,
						holiday_name: name,
						holiday_type: type,
						is_recurring: isRecurring
					});
				}

				modal.hide();
				await loadAndRender();
			} catch (e) {
				Swal.fire({ icon: 'error', title: 'Error', text: e && e.message ? e.message : 'Unable to save holiday.' });
			}
		}

		async function deleteHoliday() {
			const id = holidayIdEl.value.trim();
			if (!id) return;
			const confirm = await Swal.fire({
				icon: 'warning',
				title: 'Delete holiday?',
				text: 'This cannot be undone.',
				showCancelButton: true,
				confirmButtonText: 'Delete',
				confirmButtonColor: '#dc3545'
			});
			if (!confirm.isConfirmed) return;

			try {
				await postAction({
					action: 'delete',
					csrf_token: csrfToken,
					holiday_id: id
				});
				modal.hide();
				await loadAndRender();
			} catch (e) {
				Swal.fire({ icon: 'error', title: 'Error', text: e && e.message ? e.message : 'Unable to delete holiday.' });
			}
		}

		function goPrevMonth() {
			currentMonth -= 1;
			if (currentMonth < 1) {
				currentMonth = 12;
				currentYear -= 1;
				buildYearOptions(currentYear);
				yearSelect.value = String(currentYear);
			}
			loadAndRender();
		}
		function goNextMonth() {
			currentMonth += 1;
			if (currentMonth > 12) {
				currentMonth = 1;
				currentYear += 1;
				buildYearOptions(currentYear);
				yearSelect.value = String(currentYear);
			}
			loadAndRender();
		}

		buildYearOptions(currentYear);
		yearSelect.value = String(currentYear);
		yearSelect.addEventListener('change', function () {
			const y = parseInt(yearSelect.value, 10);
			if (!Number.isFinite(y)) return;
			currentYear = y;
			loadAndRender();
		});

		prevBtn.addEventListener('click', goPrevMonth);
		nextBtn.addEventListener('click', goNextMonth);
		addBtn.addEventListener('click', openCreateModal);
		saveBtn.addEventListener('click', saveHoliday);
		deleteBtn.addEventListener('click', deleteHoliday);

		loadAndRender();
	})();
</script>

