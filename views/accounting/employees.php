<?php
declare(strict_types=1);

$pageTitle = 'Employees';

require_once __DIR__ . '/../../middleware/auth_checker.php';
checkAccess('ACCOUNTING');

require_once __DIR__ . '/../../template/header.php';
require_once __DIR__ . '/../../controller/employee_controller.php';
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
				<div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
					<h5 class="mb-0 fw-600" style="color: #003366;">Employees</h5>
					<input type="text" id="tabulator-search" class="form-control form-control-sm w-25" placeholder="Search all columns...">
				</div>
				<div class="card-body">
					<div id="employees-table"></div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Tabulator Dependencies (match existing version) -->
<link href="https://unpkg.com/tabulator-tables@5.5.0/dist/css/tabulator_bootstrap5.min.css" rel="stylesheet">
<script type="text/javascript" src="https://unpkg.com/tabulator-tables@5.5.0/dist/js/tabulator.min.js"></script>

<script nonce="<?php echo htmlspecialchars(csp_nonce(), ENT_QUOTES, 'UTF-8'); ?>">
	const tableData = <?php echo $employeesJson; ?>;
	const locations = <?php echo $locationsJson; ?>;
	const csrfToken = <?php echo json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
	const employeeBackendUrl = <?php echo json_encode($employeeBackendUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

	const locationsById = new Map(locations.map(l => [String(l.location_id), l.location_name]));

	function escapeHtml(s) {
		return String(s)
			.replaceAll('&', '&amp;')
			.replaceAll('<', '&lt;')
			.replaceAll('>', '&gt;')
			.replaceAll('"', '&quot;')
			.replaceAll("'", '&#039;');
	}

	async function postEmployeeAction(action, payload) {
		const fd = new FormData();
		fd.append('action', action);
		fd.append('csrf_token', csrfToken);
		Object.entries(payload).forEach(([k, v]) => fd.append(k, String(v ?? '')));

		const res = await fetch(employeeBackendUrl, {
			method: 'POST',
			body: fd,
			credentials: 'same-origin'
		});
		let data;
		try {
			data = await res.json();
		} catch (e) {
			data = { success: false, error: 'Invalid server response' };
		}
		if (!res.ok || !data || data.success !== true) {
			throw new Error((data && data.error) ? data.error : 'Request failed');
		}
		return data;
	}

	function actionsFormatter(cell) {
		const d = cell.getRow().getData();
		const archived = Number(d.is_archived || 0) === 1;
		if (archived) {
			return '<span class="badge text-bg-secondary">Archived</span>';
		}
		return `
			<div class="d-flex gap-1">
				<button type="button" class="btn btn-sm btn-outline-primary" data-action="edit">Edit</button>
				<button type="button" class="btn btn-sm btn-outline-danger" data-action="archive">Archive</button>
			</div>
		`;
	}

	async function handleEdit(row) {
		const d = row.getData();
		const locationOptions = locations.map(l => `<option value="${escapeHtml(l.location_id)}">${escapeHtml(l.location_name)}</option>`).join('');

		const salaryOptions = ['daily', 'weekly', 'bi-weekly', 'semi-monthly', 'monthly']
			.map(v => `<option value="${escapeHtml(v)}">${escapeHtml(v)}</option>`)
			.join('');

		const html = `
			<div class="text-start">
				<label class="form-label">First Name</label>
				<input id="sw-first" class="form-control" value="${escapeHtml(d.first_name || '')}">
				<label class="form-label mt-2">Middle Name</label>
				<input id="sw-middle" class="form-control" value="${escapeHtml(d.middle_name || '')}">
				<label class="form-label mt-2">Last Name</label>
				<input id="sw-last" class="form-control" value="${escapeHtml(d.last_name || '')}">
				<label class="form-label mt-2">Department</label>
				<input id="sw-dept" class="form-control" value="${escapeHtml(d.department || '')}">
				<label class="form-label mt-2">Position</label>
				<input id="sw-pos" class="form-control" value="${escapeHtml(d.position || '')}">
				<label class="form-label mt-2">Location</label>
				<select id="sw-loc" class="form-select">${locationOptions}</select>
				<label class="form-label mt-2">Salary Type</label>
				<select id="sw-salary" class="form-select">${salaryOptions}</select>
			</div>
		`;

		const result = await Swal.fire({
			title: 'Edit Employee',
			html,
			focusConfirm: false,
			showCancelButton: true,
			confirmButtonText: 'Save',
			preConfirm: () => {
				const first = document.getElementById('sw-first').value.trim();
				const middle = document.getElementById('sw-middle').value.trim();
				const last = document.getElementById('sw-last').value.trim();
				const dept = document.getElementById('sw-dept').value.trim();
				const pos = document.getElementById('sw-pos').value.trim();
				const loc = document.getElementById('sw-loc').value;
				const salary = document.getElementById('sw-salary').value;

				if (!first || !last || !dept || !pos || !loc || !salary) {
					Swal.showValidationMessage('Please fill in all required fields');
					return false;
				}
				return { first, middle, last, dept, pos, loc, salary };
			}
		});

		if (!result.isConfirmed) return;
		const v = result.value;

		const payload = {
			employee_id: d.employee_id,
			first_name: v.first,
			middle_name: v.middle,
			last_name: v.last,
			department: v.dept,
			position: v.pos,
			location_id: v.loc,
			salary_type: v.salary,
		};

		try {
			const res = await postEmployeeAction('update', payload);
			const updated = res.employee;
			const middle = (updated.middle_name || '').trim();
			const mi = middle ? (middle.substring(0, 1).toUpperCase() + '.') : '';
			const fullName = (updated.first_name + ' ' + (mi ? (mi + ' ') : '') + updated.last_name).trim();

			row.update({
				...updated,
				full_name: fullName,
				location_name: updated.location_name || locationsById.get(String(updated.location_id)) || '',
			});

			Swal.fire({
				icon: 'success',
				title: 'Saved',
				text: 'Employee updated successfully',
				timer: 1500,
				showConfirmButton: false,
			});
		} catch (err) {
			Swal.fire({
				icon: 'error',
				title: 'Error',
				text: err.message || 'Failed to update employee',
			});
		}
	}

	async function handleArchive(row) {
		const d = row.getData();
		const confirm = await Swal.fire({
			icon: 'warning',
			title: 'Archive employee?',
			text: 'This will hide the employee from active use, but keep records in the database.',
			showCancelButton: true,
			confirmButtonText: 'Archive',
			confirmButtonColor: '#dc3545'
		});
		if (!confirm.isConfirmed) return;

		try {
			await postEmployeeAction('archive', { employee_id: d.employee_id });
			row.update({ is_archived: 1 });
			Swal.fire({
				icon: 'success',
				title: 'Archived',
				text: 'Employee archived successfully',
				timer: 1500,
				showConfirmButton: false,
			});
		} catch (err) {
			Swal.fire({
				icon: 'error',
				title: 'Error',
				text: err.message || 'Failed to archive employee',
			});
		}
	}

	const table = new Tabulator("#employees-table", {
		data: tableData,
		layout: "fitColumns",
		responsiveLayout: "collapse",
		pagination: "local",
		paginationSize: 10,
		paginationSizeSelector: [10, 25, 50, 100],
		movableColumns: true,
		placeholder: "No employees found",
		rowFormatter: function(row) {
			const d = row.getData();
			if (Number(d.is_archived || 0) === 1) {
				row.getElement().style.opacity = '0.6';
			}
		},
		columns: [
			{title: "Actions", field: "actions", width: 160, headerSort: false, formatter: actionsFormatter, cellClick: function(e, cell) {
				const btn = e.target && e.target.closest ? e.target.closest('button[data-action]') : null;
				if (!btn) return;
				const action = btn.getAttribute('data-action');
				const row = cell.getRow();
				if (action === 'edit') return handleEdit(row);
				if (action === 'archive') return handleArchive(row);
			}},
			{title: "Employee ID", field: "employee_num_id", width: 90, sorter: "number", headerFilter: "input"},
			{title: "Name", field: "full_name", minWidth: 220, headerFilter: "input"},
			{title: "Department", field: "department", minWidth: 160, headerFilter: "input"},
			{title: "Position", field: "position", minWidth: 180, headerFilter: "input"},
			{title: "Location", field: "location_name", minWidth: 160, headerFilter: "input"},
			{
				title: "Salary Type",
				field: "salary_type",
				width: 140,
				headerFilter: "list",
				headerFilterParams: {
					values: {"": "All", "daily": "daily", "weekly": "weekly", "bi-weekly": "bi-weekly", "semi-monthly": "semi-monthly", "monthly": "monthly"}
				},
			},
			{title: "Archived", field: "is_archived", width: 110, sorter: "number", headerFilter: "list", headerFilterParams: {values: {"": "All", "0": "No", "1": "Yes"}}, formatter: function(cell) {
				return Number(cell.getValue() || 0) === 1
					? '<span class="badge text-bg-secondary">Yes</span>'
					: '<span class="badge text-bg-success">No</span>';
			}},
			{
				title: "Last Updated",
				field: "updated_at",
				width: 200,
				sorter: "datetime",
				headerFilter: "input",
				formatter: function(cell) {
					const val = cell.getValue();
					return val ? new Date(val).toLocaleString() : '';
				}
			},
		],
	});

	document.getElementById("tabulator-search").addEventListener("keyup", function(e) {
		const value = e.target.value;
		table.setFilter([
			[
				{field: "employee_num_id", type: "like", value: value},
				{field: "full_name", type: "like", value: value},
				{field: "department", type: "like", value: value},
				{field: "position", type: "like", value: value},
				{field: "location_name", type: "like", value: value},
				{field: "salary_type", type: "like", value: value},
				{field: "created_at", type: "like", value: value},
			]
		]);
	});
</script>

