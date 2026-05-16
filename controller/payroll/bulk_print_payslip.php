<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../repository/payslip_repository.php';
require_once __DIR__ . '/../../template/pdf/bulk_payslips_template.php';

// Chrome's built-in PDF viewer embeds the PDF in a frame.
// Our global security headers include X-Frame-Options: DENY and CSP frame-ancestors 'none',
// which can cause the PDF to appear blank. Remove them for this PDF response.
if (PHP_SAPI !== 'cli') {
	if (!headers_sent()) {
		@header_remove('X-Frame-Options');
		@header_remove('Content-Security-Policy');
		@header_remove('Permissions-Policy');
		@header_remove('Referrer-Policy');
	}
}

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
if (!$userId) {
	header('Location: ' . BASE_URL . 'login.php', true, 303);
	exit;
}
if ($userRole !== 'ACCOUNTING') {
	header('Location: ' . BASE_URL . 'error/403.php', true, 303);
	exit;
}

require_once __DIR__ . '/../../config/db_connection.php';

function read_date(string $raw): ?string
{
	$raw = trim($raw);
	if ($raw === '') {
		return null;
	}
	$dt = DateTime::createFromFormat('Y-m-d', $raw);
	if (!$dt || $dt->format('Y-m-d') !== $raw) {
		return null;
	}
	return $raw;
}

$startDate = read_date((string)($_GET['start_date'] ?? ''));
$endDate = read_date((string)($_GET['end_date'] ?? ''));
$location = trim((string)($_GET['location'] ?? ''));

if ($startDate === null || $endDate === null) {
	http_response_code(400);
	echo 'Invalid or missing start_date/end_date.';
	exit;
}

if ($startDate > $endDate) {
	http_response_code(400);
	echo 'Invalid range: start_date must be before end_date.';
	exit;
}

$periodLabel = $startDate . ' to ' . $endDate;

$startDt = DateTime::createFromFormat('Y-m-d', $startDate);
$endDt = DateTime::createFromFormat('Y-m-d', $endDate);
$cutoffLabel = ($startDt && $endDt)
	? (strtoupper($startDt->format('M')) . ' ' . (int)$startDt->format('j') . '-' . (int)$endDt->format('j') . ', ' . $startDt->format('Y'))
	: $periodLabel;

// Fetch summary rows + bulk fetch details/deductions for all payroll IDs in this period.
$rows = payslip_list_employee_payroll_summaries($conn, $startDate, $endDate, $location !== '' ? $location : null);

$payrollIds = [];
foreach ($rows as $r) {
	$pid = (int)($r['payroll_id'] ?? 0);
	if ($pid > 0) {
		$payrollIds[] = $pid;
	}
}

$payrollIds = array_values(array_unique($payrollIds));

$detailsByPayroll = payslip_fetch_details_by_payroll_ids($conn, $payrollIds);
$deductionsByPayroll = payslip_fetch_deductions_by_payroll_ids($conn, $payrollIds);

$companyName = defined('SYSTEM_NAME') ? (string)SYSTEM_NAME : 'Company';
$companyAddress = (string)env('SYSTEM_ADDRESS', '#348 Torres Street, Brgy. Mayapa, Calamba City');

$html = render_bulk_payslips_html([
	'companyName' => $companyName,
	'companyAddress' => $companyAddress,
	'periodLabel' => $periodLabel,
	'cutoffLabel' => $cutoffLabel,
	'rows' => $rows,
	'detailsByPayroll' => $detailsByPayroll,
	'deductionsByPayroll' => $deductionsByPayroll,
	'locationFilter' => $location,
]);

try {
	$options = new Dompdf\Options();
	$options->set('defaultFont', 'DejaVu Serif');
	$options->set('isRemoteEnabled', false);
	$options->set('isHtml5ParserEnabled', true);

	$dompdf = new Dompdf\Dompdf($options);
	$dompdf->setPaper('A4', 'landscape');
	$dompdf->loadHtml($html, 'UTF-8');
	$dompdf->render();

	$filename = 'bulk_payslips_' . $startDate . '_' . $endDate . '.pdf';
	header('Content-Type: application/pdf');
	header('Content-Disposition: inline; filename="' . $filename . '"');
	echo $dompdf->output();
	exit;
} catch (Throwable $e) {
	http_response_code(500);
	echo 'Failed to generate PDF.';
	exit;
}

