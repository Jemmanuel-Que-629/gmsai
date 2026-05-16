<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../service/sidebar_service.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

$userRole = $_SESSION['user_role'] ?? '';

$logoUrl = defined('LOGO_URL') ? LOGO_URL : (BASE_URL . 'images/logo.jpg');
$currentPath = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');

$menus = getSidebarMenus((string)$userRole);
$menus = filterExistingMenus($menus);
$menus = markActiveMenus($menus, $currentPath);

function sidebar_slug(string $s): string
{
	$s = strtolower(trim($s));
	$s = preg_replace('/[^a-z0-9]+/i', '_', $s) ?: 'menu';
	return trim($s, '_');
}
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
		<?php foreach ($menus as $menu): ?>
			<?php
				$title = (string)($menu['title'] ?? '');
				$icon = (string)($menu['icon'] ?? '');
				$url = isset($menu['url']) ? (string)$menu['url'] : '';
				$isActive = !empty($menu['active']);
				$children = (isset($menu['children']) && is_array($menu['children'])) ? $menu['children'] : [];
				$hasChildren = count($children) > 0;
				$collapseId = 'menu_' . sidebar_slug($title);
			?>

			<?php if ($hasChildren): ?>
				<button type="button" class="<?php echo $isActive ? 'is-active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#<?php echo htmlspecialchars($collapseId, ENT_QUOTES, 'UTF-8'); ?>" aria-expanded="<?php echo $isActive ? 'true' : 'false'; ?>" aria-controls="<?php echo htmlspecialchars($collapseId, ENT_QUOTES, 'UTF-8'); ?>">
					<span class="nav-left">
						<span class="material-symbols-outlined nav-icon"><?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?></span>
						<span><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></span>
					</span>
					<span class="material-symbols-outlined">expand_more</span>
				</button>

				<div class="collapse <?php echo $isActive ? 'show' : ''; ?>" id="<?php echo htmlspecialchars($collapseId, ENT_QUOTES, 'UTF-8'); ?>">
					<div class="sub">
						<?php foreach ($children as $child): ?>
							<?php
								$ctitle = (string)($child['title'] ?? '');
								$cicon = (string)($child['icon'] ?? '');
								$curl = (string)($child['url'] ?? '#');
								$cactive = !empty($child['active']);
							?>
							<a href="<?php echo htmlspecialchars($curl, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $cactive ? 'is-active' : ''; ?>">
								<span class="nav-left">
									<span class="material-symbols-outlined nav-icon"><?php echo htmlspecialchars($cicon, ENT_QUOTES, 'UTF-8'); ?></span>
									<span><?php echo htmlspecialchars($ctitle, ENT_QUOTES, 'UTF-8'); ?></span>
								</span>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php else: ?>
				<a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isActive ? 'is-active' : ''; ?>">
					<span class="nav-left">
						<span class="material-symbols-outlined nav-icon"><?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?></span>
						<span><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></span>
					</span>
				</a>
			<?php endif; ?>
		<?php endforeach; ?>
	</nav>
</aside>

