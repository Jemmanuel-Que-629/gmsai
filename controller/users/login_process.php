<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connection.php';
require_once __DIR__ . '/../../middleware/csrf.php';
require_once __DIR__ . '/../../middleware/rate_limiter.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

csrf_init();

function requirePostAndValidCsrf(string $redirectUrl): void
{
	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
		setFlashToast('error', 'Error', 'Invalid request');
		redirectTo($redirectUrl);
	}
	$token = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : null;
	if (!csrf_validate($token)) {
		setFlashToast('error', 'Error', 'Security check failed. Please try again.');
		redirectTo($redirectUrl);
	}
}

function enforceAuthRateLimit(string $scope, string $identifier, string $redirectUrl): void
{
	// Per instruction: max 5 attempts per 10-15 min for auth routes.
	$res = rate_limit('auth:' . $scope, 5, 15 * 60, $identifier);
	if ($res['allowed']) {
		return;
	}
	$retryAfter = max(1, $res['reset'] - time());
	$mins = (int)ceil($retryAfter / 60);
	setFlashToast('error', 'Error', 'Too many attempts. Please try again in ' . $mins . ' minute(s).');
	redirectTo($redirectUrl);
}

function cleanString(mixed $val, int $maxLen): string
{
	if (!is_scalar($val)) return '';
	$s = trim((string)$val);
	$s = str_replace("\0", '', $s);
	if ($maxLen > 0 && strlen($s) > $maxLen) {
		$s = substr($s, 0, $maxLen);
	}
	return $s;
}

function setFlashToast(string $icon, string $title, string $text = ''): void
{
	$_SESSION['flash_toast'] = [
		'icon' => $icon,
		'title' => $title,
		'text' => $text,
	];
}

function redirectTo(string $url): never
{
	header('Location: ' . $url, true, 303);
	exit;
}

function getBrowser(string $ua): string
{
	if (stripos($ua, 'Edg') !== false || stripos($ua, 'Edge') !== false) return 'Edge';
	if (stripos($ua, 'Chrome') !== false) return 'Chrome';
	if (stripos($ua, 'Firefox') !== false) return 'Firefox';
	if (stripos($ua, 'Safari') !== false && stripos($ua, 'Chrome') === false) return 'Safari';
	return 'Unknown';
}

function getOS(string $ua): string
{
	if (stripos($ua, 'Android') !== false) return 'Android';
	if (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) return 'iOS';
	if (stripos($ua, 'Windows') !== false) return 'Windows';
	if (stripos($ua, 'Mac') !== false) return 'MacOS';
	if (stripos($ua, 'Linux') !== false) return 'Linux';
	return 'Unknown';
}

function ensureLoggedIn(): int
{
	$userId = (int)($_SESSION['user_id'] ?? 0);
	if ($userId <= 0) {
		setFlashToast('error', 'Error', 'Please login again.');
		redirectTo(BASE_URL . 'login.php');
	}
	return $userId;
}

function validatePasswordPolicy(string $password): bool
{
	if (strlen($password) < 8) return false;
	if (!preg_match('/[a-z]/', $password)) return false;
	if (!preg_match('/[A-Z]/', $password)) return false;
	if (!preg_match('/[0-9]/', $password)) return false;
	if (!preg_match('/[^a-zA-Z0-9]/', $password)) return false;
	return true;
}

function handleLogin(PDO $conn): never
{
	requirePostAndValidCsrf(BASE_URL . 'login.php');

	$email = cleanString($_POST['email'] ?? '', 254);
	$password = cleanString($_POST['password'] ?? '', 255);

	$ip = rl_client_ip();
	// Rate limit by IP first to slow down brute force even without an email.
	enforceAuthRateLimit('login_ip', $ip, BASE_URL . 'login.php');

	if ($email === '' || $password === '') {
		setFlashToast('error', 'Error', 'All fields are required');
		redirectTo(BASE_URL . 'login.php');
	}
	if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		setFlashToast('error', 'Error', 'Invalid email address');
		redirectTo(BASE_URL . 'login.php');
	}

	// Also rate limit per IP+email.
	enforceAuthRateLimit('login_ip_email', $ip . '|' . strtolower($email), BASE_URL . 'login.php');

	try {
		$stmt = $conn->prepare('
			SELECT users.*, roles.role_name
			FROM users
			INNER JOIN roles ON users.role_id = roles.role_id
			WHERE users.email = :email
			LIMIT 1
		');
		$stmt->execute([':email' => $email]);
		$user = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$user || !isset($user['password']) || !password_verify($password, (string)$user['password'])) {
			setFlashToast('error', 'Error', 'Invalid email or password');
			redirectTo(BASE_URL . 'login.php');
		}

		session_regenerate_id(true);
		$_SESSION['user_id'] = $user['user_id'];
		$_SESSION['user_email'] = $user['email'] ?? '';
		$_SESSION['user_role'] = $user['role_name'] ?? '';
		$_SESSION['first_name'] = $user['first_name'] ?? '';
		$_SESSION['last_name'] = $user['last_name'] ?? '';
		$_SESSION['name_extension'] = $user['name_extension'] ?? '';
		$_SESSION['profile_picture'] = $user['profile_picture'] ?? '';

		// Save login activity
		try {
			$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
			$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
			$browser = getBrowser($userAgent);
			$platform = getOS($userAgent);

			$logStmt = $conn->prepare('
				INSERT INTO login_logs (user_id, login_time, logout_time, ip_address, browser, platform, user_agent)
				VALUES (:user_id, NOW(), NULL, :ip_address, :browser, :platform, :user_agent)
			');
			$logStmt->execute([
				':user_id' => (int)$user['user_id'],
				':ip_address' => $ipAddress,
				':browser' => $browser,
				':platform' => $platform,
				':user_agent' => $userAgent,
			]);
			$_SESSION['login_log_id'] = (int)$conn->lastInsertId();
		} catch (Throwable $e) {
			// Don't block login if logging fails.
		}

		$role = (string)($_SESSION['user_role'] ?? '');
		$redirectMap = [
			'HR' => BASE_URL . 'views/hr/dashboard.php',
			'ACCOUNTING' => BASE_URL . 'views/accounting/dashboard.php',
		];

		if (!isset($redirectMap[$role])) {
			setFlashToast('error', 'Error', 'Role not assigned');
			redirectTo(BASE_URL . 'login.php');
		}

		setFlashToast('success', 'Login successful!');
		redirectTo($redirectMap[$role]);
	} catch (Throwable $e) {
		setFlashToast('error', 'Error', 'Database error');
		redirectTo(BASE_URL . 'login.php');
	}
}

function handleLogout(PDO $conn): never
{
	$userId = (int)($_SESSION['user_id'] ?? 0);
	$loginLogId = (int)($_SESSION['login_log_id'] ?? 0);

	if ($userId > 0) {
		try {
			if ($loginLogId > 0) {
				$stmt = $conn->prepare('
					UPDATE login_logs
					SET logout_time = NOW()
					WHERE log_id = :log_id AND user_id = :user_id
					LIMIT 1
				');
				$stmt->execute([
					':log_id' => $loginLogId,
					':user_id' => $userId,
				]);
			} else {
				$stmt = $conn->prepare('
					UPDATE login_logs
					SET logout_time = NOW()
					WHERE user_id = :user_id AND logout_time IS NULL
					ORDER BY login_time DESC
					LIMIT 1
				');
				$stmt->execute([':user_id' => $userId]);
			}
		} catch (Throwable $e) {
			// Don't block logout if logging fails.
		}
	}

	$_SESSION = [];
	if (ini_get('session.use_cookies')) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
	}
	session_destroy();
	redirectTo(BASE_URL . 'login.php');
}

function handleChangePassword(PDO $conn): never
{
	requirePostAndValidCsrf(BASE_URL . 'system_users/profile.php');
	$userId = ensureLoggedIn();
	enforceAuthRateLimit('change_password', rl_client_ip() . '|' . (string)$userId, BASE_URL . 'system_users/profile.php');

	$current = cleanString($_POST['current_password'] ?? '', 255);
	$new = cleanString($_POST['new_password'] ?? '', 255);
	$confirm = cleanString($_POST['confirm_password'] ?? '', 255);

	if ($new === '' || $confirm === '' || $current === '') {
		setFlashToast('error', 'Error', 'All password fields are required.');
		redirectTo(BASE_URL . 'system_users/profile.php');
	}
	if ($new !== $confirm) {
		setFlashToast('error', 'Error', 'Passwords do not match.');
		redirectTo(BASE_URL . 'system_users/profile.php');
	}
	if (!validatePasswordPolicy($new)) {
		setFlashToast('error', 'Error', 'Password does not meet the requirements.');
		redirectTo(BASE_URL . 'system_users/profile.php');
	}

	try {
		$stmt = $conn->prepare('SELECT password FROM users WHERE user_id = :id LIMIT 1');
		$stmt->execute([':id' => $userId]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		$hash = (string)($row['password'] ?? '');
		if ($hash === '' || !password_verify($current, $hash)) {
			setFlashToast('error', 'Error', 'Current password is incorrect.');
			redirectTo(BASE_URL . 'system_users/profile.php');
		}

		$newHash = password_hash($new, PASSWORD_DEFAULT);
		$upd = $conn->prepare('UPDATE users SET password = :pw WHERE user_id = :id LIMIT 1');
		$upd->execute([':pw' => $newHash, ':id' => $userId]);
		setFlashToast('success', 'Success', 'Password updated!');
		redirectTo(BASE_URL . 'system_users/profile.php');
	} catch (Throwable $e) {
		setFlashToast('error', 'Error', 'An error occurred.');
		redirectTo(BASE_URL . 'system_users/profile.php');
	}
}

function handleUpdateProfile(PDO $conn): never
{
	requirePostAndValidCsrf(BASE_URL . 'system_users/profile.php');
	$userId = ensureLoggedIn();
	enforceAuthRateLimit('update_profile', rl_client_ip() . '|' . (string)$userId, BASE_URL . 'system_users/profile.php');

	$recoveryEmail = cleanString($_POST['recovery_email'] ?? '', 254);
	$hasRecoveryEmail = $recoveryEmail !== '';
	if ($hasRecoveryEmail && !filter_var($recoveryEmail, FILTER_VALIDATE_EMAIL)) {
		setFlashToast('error', 'Error', 'Please enter a valid recovery email.');
		redirectTo(BASE_URL . 'system_users/profile.php');
	}

	$profilePath = null;
	$pictureChanged = false;
	if (isset($_FILES['profile_pic']) && is_array($_FILES['profile_pic']) && ($_FILES['profile_pic']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
		if (($_FILES['profile_pic']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
			setFlashToast('error', 'Error', 'Photo upload failed.');
			redirectTo(BASE_URL . 'system_users/profile.php');
		}

		$tmpName = (string)($_FILES['profile_pic']['tmp_name'] ?? '');
		$originalName = (string)($_FILES['profile_pic']['name'] ?? '');
		$size = (int)($_FILES['profile_pic']['size'] ?? 0);

		if ($tmpName === '' || $size <= 0 || $size > UPLOAD_MAX_BYTES) {
			setFlashToast('error', 'Error', 'Invalid image file (or too large).');
			redirectTo(BASE_URL . 'system_users/profile.php');
		}

		$imgInfo = @getimagesize($tmpName);
		if ($imgInfo === false) {
			setFlashToast('error', 'Error', 'Uploaded file is not an image.');
			redirectTo(BASE_URL . 'system_users/profile.php');
		}

		$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
		$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
		if (!in_array($ext, $allowed, true)) {
			setFlashToast('error', 'Error', 'Unsupported image type. Allowed: jpg, jpeg, png, gif, webp.');
			redirectTo(BASE_URL . 'system_users/profile.php');
		}

		$dir = rtrim(UPLOADS_PATH, '/\\') . DIRECTORY_SEPARATOR . 'profile_pic' . DIRECTORY_SEPARATOR;
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}

		$filename = 'u' . $userId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
		$dest = $dir . $filename;
		if (!move_uploaded_file($tmpName, $dest)) {
			setFlashToast('error', 'Error', 'Failed to save the uploaded image.');
			redirectTo(BASE_URL . 'system_users/profile.php');
		}

		$profilePath = 'uploads/profile_pic/' . $filename;
		$pictureChanged = true;
	}

	try {
		$sets = [];
		$params = [':id' => $userId];
		if ($hasRecoveryEmail) {
			$sets[] = 'recovery_email = :recovery_email';
			$params[':recovery_email'] = $recoveryEmail;
		}
		if ($profilePath !== null) {
			$sets[] = 'profile_picture = :profile_picture';
			$params[':profile_picture'] = $profilePath;
			$_SESSION['profile_picture'] = $profilePath;
		}

		if (empty($sets)) {
			setFlashToast('success', 'Success', 'Profile updated!');
			redirectTo(BASE_URL . 'system_users/profile.php');
		}

		$sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE user_id = :id LIMIT 1';
		$stmt = $conn->prepare($sql);
		$stmt->execute($params);
		setFlashToast('success', 'Success', $pictureChanged ? 'Picture updated!' : 'Profile updated!');
		redirectTo(BASE_URL . 'system_users/profile.php');
	} catch (Throwable $e) {
		setFlashToast('error', 'Error', 'An error occurred.');
		redirectTo(BASE_URL . 'system_users/profile.php');
	}
}

$action = cleanString($_POST['action'] ?? $_GET['action'] ?? '', 40);
if ($action === '' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['email'], $_POST['password'])) {
	$action = 'login';
}

switch ($action) {
	case 'login':
		handleLogin($conn);
	case 'logout':
		handleLogout($conn);
	case 'change_password':
		handleChangePassword($conn);
	case 'update_profile':
		handleUpdateProfile($conn);
	default:
		setFlashToast('error', 'Error', 'Invalid request');
		redirectTo(BASE_URL . 'login.php');
}
