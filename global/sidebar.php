<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

$userRole = $_SESSION['user_role'] ?? '';

$hrDashboardUrl = BASE_URL . 'system_users/hr/dashboard.php';
$accountingDashboardUrl = BASE_URL . 'system_users/accounting/dashboard.php';

$dashboardUrl = $userRole === 'ACCOUNTING' ? $accountingDashboardUrl : $hrDashboardUrl;

// Optional pages (only link if they exist)
$payrollTablePath = DOMAIN_PATH . '/system_users/accounting/payroll_table.php';
$sssContributionPath = DOMAIN_PATH . '/system_users/accounting/sss_contribution.php';

$payrollTableUrl = file_exists($payrollTablePath) ? (BASE_URL . 'system_users/accounting/payroll_table.php') : '';
$sssContributionUrl = file_exists($sssContributionPath) ? (BASE_URL . 'system_users/accounting/sss_contribution.php') : '';

$logoUrl = defined('LOGO_URL') ? LOGO_URL : (BASE_URL . 'images/logo.jpg');

$currentPath = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');

$isHrDashboardActive = str_ends_with($currentPath, '/system_users/hr/dashboard.php');
$isAccountingDashboardActive = str_ends_with($currentPath, '/system_users/accounting/dashboard.php');

$isPayrollTableActive = str_ends_with($currentPath, '/system_users/accounting/payroll_table.php');
$isSssContributionActive = str_ends_with($currentPath, '/system_users/accounting/sss_contribution.php');
$isPayrollSectionActive = $isPayrollTableActive || $isSssContributionActive;

$isDashboardActive = ($userRole === 'HR' && $isHrDashboardActive) || ($userRole === 'ACCOUNTING' && $isAccountingDashboardActive);
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
								<span>SSS Contribution</span>
							</span>
						</a>
					<?php else: ?>
						<a href="#" onclick="return false;" aria-disabled="true" style="opacity:.75;">
							<span class="nav-left">
								<span class="material-symbols-outlined nav-icon">account_balance</span>
								<span>SSS Contribution</span>
							</span>
						</a>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
	</nav>
</aside>

