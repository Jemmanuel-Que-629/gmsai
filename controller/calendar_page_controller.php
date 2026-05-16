<?php

declare(strict_types=1);

require_once __DIR__ . '/../middleware/csrf.php';

csrf_init();

$csrfToken = csrf_token();
$defaultYear = (int)date('Y');
$defaultMonth = (int)date('n');
