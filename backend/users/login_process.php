<?php
// Legacy endpoint kept for backwards compatibility.
$_POST['action'] = $_POST['action'] ?? 'login';
require_once __DIR__ . '/unified_users_process.php';