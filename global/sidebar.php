<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

$userRole = $_SESSION['user_role'] ?? '';

$hrDashboardUrl = BASE_URL . 'system_users/hr/dashboard.php';
$accountingDashboardUrl = BASE_URL . 'system_users/accounting/dashboard.php';
$hrLogsUrl = BASE_URL . 'system_users/hr/activity_logs.php';
$accountingLogsUrl = BASE_URL . 'system_users/accounting/activity_logs.php';

$dashboardUrl = $userRole === 'ACCOUNTING' ? $accountingDashboardUrl : $hrDashboardUrl;
$logsUrl = $userRole === 'ACCOUNTING' ? $accountingLogsUrl : $hrLogsUrl;

// Optional pages (only link if they exist)
$payrollTablePath = DOMAIN_PATH . '/system_users/accounting/payroll_table.php';
$sssContributionPath = DOMAIN_PATH . '/system_users/accounting/sss_contribution.php';
$sssBracketPath = DOMAIN_PATH . '/system_users/accounting/sss_bracket_table.php';
$philhealthContributionPath = DOMAIN_PATH . '/system_users/accounting/philhealth_contribution.php';
$pagibigContributionPath = DOMAIN_PATH . '/system_users/accounting/pagibig_contribution.php';
$dailyTimeRecordPath = DOMAIN_PATH . '/system_users/accounting/daily_time_record.php';
$employeesPath = DOMAIN_PATH . '/system_users/accounting/employees.php';

$payrollTableUrl = file_exists($payrollTablePath) ? (BASE_URL . 'system_users/accounting/payroll_table.php') : '';
$sssContributionUrl = file_exists($sssContributionPath) ? (BASE_URL . 'system_users/accounting/sss_contribution.php') : '';
$sssBracketUrl = file_exists($sssBracketPath) ? (BASE_URL . 'system_users/accounting/sss_bracket_table.php') : '';
$philhealthContributionUrl = file_exists($philhealthContributionPath) ? (BASE_URL . 'system_users/accounting/philhealth_contribution.php') : '';
$pagibigContributionUrl = file_exists($pagibigContributionPath) ? (BASE_URL . 'system_users/accounting/pagibig_contribution.php') : '';
$dailyTimeRecordUrl = file_exists($dailyTimeRecordPath) ? (BASE_URL . 'system_users/accounting/daily_time_record.php') : '';
$employeesUrl = file_exists($employeesPath) ? (BASE_URL . 'system_users/accounting/employees.php') : '';

$logoUrl = defined('LOGO_URL') ? LOGO_URL : (BASE_URL . 'images/logo.jpg');

$currentPath = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');

$isHrDashboardActive = str_ends_with($currentPath, '/system_users/hr/dashboard.php');
$isAccountingDashboardActive = str_ends_with($currentPath, '/system_users/accounting/dashboard.php');

$isHrLogsActive = str_ends_with($currentPath, '/system_users/hr/activity_logs.php');
$isAccountingLogsActive = str_ends_with($currentPath, '/system_users/accounting/activity_logs.php');

$isPayrollTableActive = str_ends_with($currentPath, '/system_users/accounting/payroll_table.php');
$isSssContributionActive = str_ends_with($currentPath, '/system_users/accounting/sss_contribution.php');
$isPayrollSectionActive = $isPayrollTableActive || $isSssContributionActive;

$isSssBracketActive = str_ends_with($currentPath, '/system_users/accounting/sss_bracket_table.php');
$isPhilhealthContributionActive = str_ends_with($currentPath, '/system_users/accounting/philhealth_contribution.php');
$isPagibigContributionActive = str_ends_with($currentPath, '/system_users/accounting/pagibig_contribution.php');
$isContributionSectionActive = $isSssBracketActive || $isPhilhealthContributionActive || $isPagibigContributionActive;

$isDailyTimeRecordActive = str_ends_with($currentPath, '/system_users/accounting/daily_time_record.php');
$isEmployeesActive = str_ends_with($currentPath, '/system_users/accounting/employees.php');

$isDashboardActive = ($userRole === 'HR' && $isHrDashboardActive) || ($userRole === 'ACCOUNTING' && $isAccountingDashboardActive);
$isLogsActive = ($userRole === 'HR' && $isHrLogsActive) || ($userRole === 'ACCOUNTING' && $isAccountingLogsActive);
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />

<style>
	.gms-sidebar {
		font-family: 'Poppins', sans-serif;
		width: 260px;
		min-height: calc(100vh - 64px);
		background: #198754;
		color: #fff;
		padding: 18px 14px;
	}
	.gms-sidebar.is-collapsed {
		display: none;
	}
	.gms-sidebar .brand {
		display: flex;
		align-items: center;
		gap: 10px;
		margin-bottom: 18px;
		padding: 8px 10px;
	}
	.gms-sidebar .brand img {
		width: 40px;
		height: 40px;
		border-radius: 50%;
		object-fit: cover;
		background: #fff;
		padding: 2px;
	}
	.gms-sidebar .brand .name {
		font-weight: 700;
		font-size: 0.95rem;
		line-height: 1.1;
	}
	.gms-sidebar .brand .role {
		font-size: 0.78rem;
		opacity: 0.85;
	}
	.gms-nav a,
	.gms-nav button {
		color: #fff;
		text-decoration: none;
		width: 100%;
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 10px;
		padding: 10px 12px;
		border-radius: 10px;
		border: 0;
		background: transparent;
		font-weight: 500;
	}
	.gms-nav .nav-left {
		display: inline-flex;
		align-items: center;
		gap: 10px;
	}
	.gms-nav .nav-icon {
		font-size: 22px;
		line-height: 1;
		opacity: 0.95;
	}
	.gms-nav a:hover,
	.gms-nav button:hover { background: rgba(255,255,255,0.12); }
	.gms-nav a.is-active,
	.gms-nav button.is-active { background: rgba(255,255,255,0.22); }
	.gms-nav .sub a {
		padding-left: 28px;
		font-weight: 500;
		opacity: 0.95;
	}
	@media (max-width: 768px) {
		.gms-sidebar { width: 100%; min-height: auto; }
	}
</style>

<aside class="gms-sidebar">

	<nav class="gms-nav">
		<a href="<?php echo htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isDashboardActive ? 'is-active' : ''; ?>">
			<span class="nav-left">
				<span class="material-symbols-outlined nav-icon">dashboard</span>
				<span>Dashboard</span>
			</span>
		</a>
		<?php if ($userRole !== 'ACCOUNTING'): ?>
			<a href="<?php echo htmlspecialchars($logsUrl, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isLogsActive ? 'is-active' : ''; ?>">
				<span class="nav-left">
					<span class="material-symbols-outlined nav-icon">description</span>
					<span>Activity Logs</span>
				</span>
			</a>
		<?php endif; ?>


		<?php if ($userRole === 'ACCOUNTING'): ?>
			<button type="button" class="<?php echo $isPayrollSectionActive ? 'is-active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#payrollMenu" aria-expanded="<?php echo $isPayrollSectionActive ? 'true' : 'false'; ?>" aria-controls="payrollMenu">
				<span class="nav-left">
					<span class="material-symbols-outlined nav-icon">payments</span>
					<span>Payroll</span>
				</span>
				<span class="material-symbols-outlined">expand_more</span>
			</button>

			<div class="collapse <?php echo $isPayrollSectionActive ? 'show' : ''; ?>" id="payrollMenu">
				<div class="sub">
					<?php if ($payrollTableUrl): ?>
						<a href="<?php echo htmlspecialchars($payrollTableUrl, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isPayrollTableActive ? 'is-active' : ''; ?>">
							<span class="nav-left">
								<span class="material-symbols-outlined nav-icon">table_view</span>
								<span>Payroll Table</span>
							</span>
						</a>
					<?php else: ?>
						<a href="#" onclick="return false;" aria-disabled="true" style="opacity:.75;">
							<span class="nav-left">
								<span class="material-symbols-outlined nav-icon">table_view</span>
								<span>Payroll Table</span>
							</span>
						</a>
					<?php endif; ?>

					<?php if ($sssContributionUrl): ?>
						<a href="<?php echo htmlspecialchars($sssContributionUrl, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isSssContributionActive ? 'is-active' : ''; ?>">
							<span class="nav-left">
								<span class="material-symbols-outlined nav-icon">account_balance</span>
								<span>Payroll Masterlist</span>
							</span>
						</a>
					<?php else: ?>
						<a href="#" onclick="return false;" aria-disabled="true" style="opacity:.75;">
							<span class="nav-left">
								<span class="material-symbols-outlined nav-icon">account_balance</span>
								<span>Payroll Masterlist</span>
							</span>
						</a>
					<?php endif; ?>
				</div>
			</div>

			<button type="button" class="<?php echo $isContributionSectionActive ? 'is-active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#contributionMenu" aria-expanded="<?php echo $isContributionSectionActive ? 'true' : 'false'; ?>" aria-controls="contributionMenu">
				<span class="nav-left">
					<span class="material-symbols-outlined nav-icon">account_balance</span>
					<span>Contribution Table</span>
				</span>
				<span class="material-symbols-outlined">expand_more</span>
			</button>

			<div class="collapse <?php echo $isContributionSectionActive ? 'show' : ''; ?>" id="contributionMenu">
				<div class="sub">
					<?php if ($sssBracketUrl): ?>
						<a href="<?php echo htmlspecialchars($sssBracketUrl, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isSssBracketActive ? 'is-active' : ''; ?>">
							<span class="nav-left">
								<span class="material-symbols-outlined nav-icon">table_view</span>
								<span>SSS Bracket</span>
							</span>
						</a>
					<?php else: ?>
						<a href="#" onclick="return false;" aria-disabled="true" style="opacity:.75;">
							<span class="nav-left">
								<span class="material-symbols-outlined nav-icon">table_view</span>
								<span>SSS Bracket</span>
							</span>
						</a>
					<?php endif; ?>

					<?php if ($philhealthContributionUrl): ?>
						<a href="<?php echo htmlspecialchars($philhealthContributionUrl, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isPhilhealthContributionActive ? 'is-active' : ''; ?>">
							<span class="nav-left">
								<span class="material-symbols-outlined nav-icon">health_and_safety</span>
								<span>Philhealth Contribution</span>
							</span>
						</a>
					<?php else: ?>
						<a href="#" onclick="return false;" aria-disabled="true" style="opacity:.75;">
							<span class="nav-left">
								<span class="material-symbols-outlined nav-icon">health_and_safety</span>
								<span>Philhealth Contribution</span>
							</span>
						</a>
					<?php endif; ?>

					<?php if ($pagibigContributionUrl): ?>
						<a href="<?php echo htmlspecialchars($pagibigContributionUrl, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isPagibigContributionActive ? 'is-active' : ''; ?>">
							<span class="nav-left">
								<span class="material-symbols-outlined nav-icon">savings</span>
								<span>PAG-IBIG Contribution</span>
							</span>
						</a>
					<?php else: ?>
						<a href="#" onclick="return false;" aria-disabled="true" style="opacity:.75;">
							<span class="nav-left">
								<span class="material-symbols-outlined nav-icon">savings</span>
								<span>PAG-IBIG Contribution</span>
							</span>
						</a>
					<?php endif; ?>
				</div>
			</div>

			<?php if ($dailyTimeRecordUrl): ?>
				<a href="<?php echo htmlspecialchars($dailyTimeRecordUrl, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isDailyTimeRecordActive ? 'is-active' : ''; ?>">
					<span class="nav-left">
						<span class="material-symbols-outlined nav-icon">schedule</span>
						<span>Daily Time Record</span>
					</span>
				</a>
			<?php else: ?>
				<a href="#" onclick="return false;" aria-disabled="true" style="opacity:.75;">
					<span class="nav-left">
						<span class="material-symbols-outlined nav-icon">schedule</span>
						<span>Daily Time Record</span>
					</span>
				</a>
			<?php endif; ?>

			<?php if ($employeesUrl): ?>
				<a href="<?php echo htmlspecialchars($employeesUrl, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isEmployeesActive ? 'is-active' : ''; ?>">
					<span class="nav-left">
						<span class="material-symbols-outlined nav-icon">badge</span>
						<span>Employees</span>
					</span>
				</a>
			<?php else: ?>
				<a href="#" onclick="return false;" aria-disabled="true" style="opacity:.75;">
					<span class="nav-left">
						<span class="material-symbols-outlined nav-icon">badge</span>
						<span>Employees</span>
					</span>
				</a>
			<?php endif; ?>

			<a href="<?php echo htmlspecialchars($logsUrl, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isLogsActive ? 'is-active' : ''; ?>">
				<span class="nav-left">
					<span class="material-symbols-outlined nav-icon">description</span>
					<span>Activity Logs</span>
				</span>
			</a>
		<?php endif; ?>
	</nav>
</aside>

