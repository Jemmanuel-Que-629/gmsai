<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

// Endpoint not implemented yet.
http_response_code(501);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => false, 'error' => 'Not implemented']);

