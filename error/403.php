<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>403 - Forbidden</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
	<div class="container py-5">
		<div class="alert alert-danger">
			<h4 class="alert-heading mb-2">403 - Forbidden</h4>
			<div>You do not have permission to access this page.</div>
		</div>
		<a class="btn btn-secondary" href="<?php echo htmlspecialchars(BASE_URL . 'login.php', ENT_QUOTES, 'UTF-8'); ?>">Back to Login</a>
	</div>
</body>
</html>

