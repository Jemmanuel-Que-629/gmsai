<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
$userEmail = $_SESSION['user_email'] ?? '';

$firstName = (string)($_SESSION['first_name'] ?? '');
$lastName = (string)($_SESSION['last_name'] ?? '');
$nameExtension = (string)($_SESSION['name_extension'] ?? '');
$profilePicture = (string)($_SESSION['profile_picture'] ?? '');

$userName = trim($firstName . ' ' . $lastName);
if ($nameExtension !== '') {
	$userName = trim($userName . ' ' . $nameExtension);
}

// If session doesn't have name yet (older session), fetch once from DB and cache.
if ($userId && $userName === '') {
	try {
		require_once __DIR__ . '/../config/db_connection.php';
		$stmt = $conn->prepare('SELECT first_name, last_name, name_extension, profile_picture, email FROM users WHERE user_id = :id LIMIT 1');
		$stmt->execute([':id' => $userId]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row) {
			$_SESSION['first_name'] = $row['first_name'] ?? '';
			$_SESSION['last_name'] = $row['last_name'] ?? '';
			$_SESSION['name_extension'] = $row['name_extension'] ?? '';
			$_SESSION['profile_picture'] = $row['profile_picture'] ?? '';
			// Keep email in sync too
			$_SESSION['user_email'] = $row['email'] ?? $userEmail;

			$firstName = (string)$_SESSION['first_name'];
			$lastName = (string)$_SESSION['last_name'];
			$nameExtension = (string)$_SESSION['name_extension'];
			$profilePicture = (string)$_SESSION['profile_picture'];
			$userEmail = (string)$_SESSION['user_email'];

			$userName = trim($firstName . ' ' . $lastName);
			if ($nameExtension !== '') {
				$userName = trim($userName . ' ' . $nameExtension);
			}
		}
	} catch (Throwable $e) {
		// Fail silently: header should still render.
	}
}

if (!$userId) {
	header('Location: ' . BASE_URL . 'login.php', true, 303);
	exit;
}

// Optional role enforcement for /system_users/* pages
$self = $_SERVER['PHP_SELF'] ?? '';
if (str_contains($self, '/system_users/hr/') && $userRole !== 'HR') {
	header('Location: ' . BASE_URL . 'error/403.php', true, 303);
	exit;
}
if (str_contains($self, '/system_users/accounting/') && $userRole !== 'ACCOUNTING') {
	header('Location: ' . BASE_URL . 'error/403.php', true, 303);
	exit;
}

$flashToast = null;
if (isset($_SESSION['flash_toast']) && is_array($_SESSION['flash_toast'])) {
	$flashToast = $_SESSION['flash_toast'];
	unset($_SESSION['flash_toast']);
}

$logoUrl = defined('LOGO_URL') ? LOGO_URL : (BASE_URL . 'images/logo.jpg');

// Page title handling
$currentPath = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
$currentFile = basename($currentPath);

// Allow any page to override by setting $pageTitle before including this header.
$pageName = null;
if (isset($pageTitle) && is_string($pageTitle) && trim($pageTitle) !== '') {
	$pageName = trim($pageTitle);
} else {
	$pageMap = [
		'dashboard.php' => 'Dashboard',
		'payroll_table.php' => 'Payroll Table',
		'sss_contribution.php' => 'SSS Contribution',
	];
	$pageName = $pageMap[$currentFile] ?? (defined('ACTIVE_PAGE') ? ucwords(str_replace('_', ' ', ACTIVE_PAGE)) : '');
}

$docTitle = 'Payroll System' . ($pageName !== '' ? ' - ' . $pageName : '');
$docTitleJs = json_encode($docTitle, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>

<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />

<style>
	.gms-topbar {
		background: #ffffff;
		border-bottom: 1px solid rgba(0,0,0,0.08);
		height: 64px;
		padding: 0 18px;
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 16px;
	}
	.gms-menu-btn {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 40px;
		height: 40px;
		border: 0;
		background: transparent;
		border-radius: 10px;
		color: #111;
		font-size: 20px;
		line-height: 1;
	}
	.gms-menu-btn:hover { background: rgba(0,0,0,0.04); }
	.gms-topbar .gms-brand {
		display: flex;
		align-items: center;
		gap: 10px;
		min-width: 200px;
	}
	.gms-topbar .gms-brand img {
		width: 38px;
		height: 38px;
		border-radius: 50%;
		object-fit: cover;
	}
	.gms-topbar .gms-title {
		font-weight: 600;
		color: #111;
		margin: 0;
		font-size: 1rem;
		line-height: 1.2;
	}
	.gms-topbar .gms-datetime {
		flex: 1;
		text-align: center;
		font-weight: 500;
		color: #444;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}
	.gms-topbar .gms-profile {
		min-width: 200px;
		display: flex;
		justify-content: flex-end;
	}
	.gms-profile-btn {
		display: inline-flex;
		align-items: center;
		gap: 10px;
		border: 0;
		background: transparent;
		padding: 6px 8px;
		border-radius: 10px;
	}
	.gms-profile-btn:hover { background: rgba(0,0,0,0.04); }
	.gms-avatar {
		width: 36px;
		height: 36px;
		border-radius: 50%;
		background: #e9ecef;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		overflow: hidden;
		flex: 0 0 auto;
	}
	.gms-avatar img {
		width: 100%;
		height: 100%;
		object-fit: cover;
	}
	.gms-user-meta {
		text-align: left;
		line-height: 1.1;
	}
	.gms-user-meta .role { font-size: 0.75rem; color: #6c757d; }
	.gms-user-meta .email { font-size: 0.85rem; color: #212529; max-width: 170px; overflow: hidden; text-overflow: ellipsis; }
</style>

<div class="gms-topbar">
	<div class="gms-brand">
		<button type="button" class="gms-menu-btn" id="gmsSidebarToggle" aria-label="Toggle sidebar" aria-expanded="true">&#9776;</button>
		<img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo">
		<p class="gms-title">Payroll System</p>
	</div>

	<div class="gms-datetime" id="gmsDateTime">&nbsp;</div>

	<div class="gms-profile">
		<div class="dropdown">
			<button class="gms-profile-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
				<span class="gms-avatar" aria-hidden="true">
					<?php if ($profilePicture !== ''): ?>
						<img src="<?php echo htmlspecialchars(BASE_URL . ltrim($profilePicture, '/'), ENT_QUOTES, 'UTF-8'); ?>" alt="Profile">
					<?php else: ?>
						<span class="material-symbols-outlined">account_circle</span>
					<?php endif; ?>
				</span>
				<span class="gms-user-meta d-none d-md-inline">
					<div class="email"><?php echo htmlspecialchars($userName !== '' ? $userName : $userEmail, ENT_QUOTES, 'UTF-8'); ?></div>
					<div class="role"><?php echo htmlspecialchars((string)$userRole, ENT_QUOTES, 'UTF-8'); ?></div>
				</span>
			</button>
			<ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
				<li>
					<a class="dropdown-item d-flex align-items-center" href="<?php echo htmlspecialchars(BASE_URL . 'system_users/profile.php', ENT_QUOTES, 'UTF-8'); ?>">
						<span class="material-symbols-outlined me-2" style="font-size: 20px;">account_circle</span> Profile
					</a>
				</li>
				<li>
					<a class="dropdown-item d-flex align-items-center" href="<?php echo htmlspecialchars(BASE_URL . 'system_users/settings.php', ENT_QUOTES, 'UTF-8'); ?>">
						<span class="material-symbols-outlined me-2" style="font-size: 20px;">settings</span> Settings
					</a>
				</li>
				<li>
					<a class="dropdown-item d-flex align-items-center" href="<?php echo htmlspecialchars(BASE_URL . 'system_users/my_activity_logs.php', ENT_QUOTES, 'UTF-8'); ?>">
						<span class="material-symbols-outlined me-2" style="font-size: 20px;">history</span> My Activity Logs
					</a>
				</li>
				<li><hr class="dropdown-divider"></li>
				<li>
					<a class="dropdown-item d-flex align-items-center text-danger" href="<?php echo htmlspecialchars(BASE_URL . 'backend/users/unified_users_process.php?action=logout', ENT_QUOTES, 'UTF-8'); ?>">
						<span class="material-symbols-outlined me-2" style="font-size: 20px; color: #dc3545;">logout</span> Logout
					</a>
				</li>
			</ul>
		</div>
	</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
	(function () {
		// Always set browser tab title
		document.title = <?php echo $docTitleJs; ?>;

		const el = document.getElementById('gmsDateTime');
		function render() {
			const now = new Date();
			const weekday = now.toLocaleString('en-US', { weekday: 'short' });
			const month = now.toLocaleString('en-US', { month: 'short' });
			const day = now.getDate();
			const year = now.getFullYear();
			const time = now.toLocaleString('en-US', { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true });
			el.textContent = `${weekday}, ${month} ${day}, ${year}, ${time}`;
		}
		render();
		setInterval(render, 100);

		document.addEventListener('DOMContentLoaded', function () {
			const toggleBtn = document.getElementById('gmsSidebarToggle');
			const sidebar = document.querySelector('.gms-sidebar');
			if (!toggleBtn || !sidebar) return;

			const storageKey = 'gms_sidebar_collapsed';
			const applyState = (collapsed) => {
				sidebar.classList.toggle('is-collapsed', collapsed);
				toggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
			};

			applyState(localStorage.getItem(storageKey) === '1');

			toggleBtn.addEventListener('click', function () {
				const nextCollapsed = !sidebar.classList.contains('is-collapsed');
				applyState(nextCollapsed);
				localStorage.setItem(storageKey, nextCollapsed ? '1' : '0');
			});
		});

		const flashToast = <?php echo json_encode($flashToast, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
		if (flashToast && (flashToast.icon || flashToast.title || flashToast.text)) {
			Swal.fire({
				toast: true,
				position: 'top-end',
				icon: flashToast.icon || 'info',
				title: flashToast.title || '',
				text: flashToast.text || '',
				showConfirmButton: false,
				timer: 2500,
				timerProgressBar: true
			});
		}
	})();
</script>

