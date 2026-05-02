<?php
// Legacy endpoint kept for backwards compatibility.
$_GET['action'] = $_GET['action'] ?? 'logout';
require_once __DIR__ . '/unified_users_process.php';
