<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

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

function normalize_key(string $v): string
{
	$v = strtoupper(trim($v));
	$v = preg_replace('/[^A-Z0-9]+/', '_', $v) ?? $v;
	return trim($v, '_');
}

function money(float $v): string
{
	return number_format($v, 2);
}

function hours_fmt(float $v): string
{
	if (abs($v) < 0.00001) {
		return '';
	}
	return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
}

function peso_html(float $v): string
{
	// Use HTML entity to avoid missing glyphs when fonts differ.
	return '&#8369; ' . money($v);
}

// Fetch employees (optionally filtered by location), and join stored payroll summary for the selected period.
$sql = "SELECT e.employee_id,
			CONCAT(e.first_name, ' ',
				CASE WHEN e.middle_name IS NOT NULL AND e.middle_name != ''
					THEN CONCAT(UPPER(LEFT(e.middle_name, 1)), '. ')
					ELSE '' END,
				e.last_name) AS name,
			lr.location_name,
			p.payroll_id,
			p.gross_pay,
			p.total_deductions,
			p.net_pay
		FROM employees e
		JOIN location_rate lr ON e.location_id = lr.location_id
		LEFT JOIN payroll p
			ON p.employee_id = e.employee_id
			AND p.period_start = :start_date
			AND p.period_end = :end_date";

if ($location !== '') {
	$sql .= " WHERE lr.location_name = :location_name";
}

$sql .= " ORDER BY e.last_name ASC, e.first_name ASC";

$stmt = $conn->prepare($sql);
$params = [
	':start_date' => $startDate,
	':end_date' => $endDate,
];
if ($location !== '') {
	$params[':location_name'] = $location;
}
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Prepare detail fetch statements
$detailsStmt = $conn->prepare('SELECT type, SUM(hours) AS total_hours, SUM(amount) AS total_amount FROM payroll_details WHERE payroll_id = :payroll_id GROUP BY type');
$deductionsStmt = $conn->prepare('SELECT deduction_type, SUM(amount) AS total_amount FROM payroll_deductions WHERE payroll_id = :payroll_id GROUP BY deduction_type');

$earningsRows = [
	'REG_HOURS' => ['label' => 'REG. HOURS', 'keys' => ['REG_HOURS', 'REG_HRS', 'REGULAR_HOURS', 'REGULAR'] ],
	'REG_OT' => ['label' => 'REG. OT', 'keys' => ['REG_OT', 'OT', 'OVERTIME'] ],
	'SUN_RD_SPCL_HOL' => ['label' => 'SUN/RD/SPCL. HOL.', 'keys' => ['SUN_RD_SPCL_HOL', 'SUN_RD_SPECIAL_HOLIDAY', 'REST_DAY_SPECIAL', 'REST_DAY'] ],
	'SPCL_HOL_OT' => ['label' => 'SPCL. HOL. OT', 'keys' => ['SPCL_HOL_OT', 'SPECIAL_HOLIDAY_OT', 'SPECIAL_OT'] ],
	'LEGAL_HOLIDAY' => ['label' => 'LEGAL HOLIDAY', 'keys' => ['LEGAL_HOLIDAY', 'REGULAR_HOLIDAY', 'REGULAR_HOLIDAY_PAY'] ],
	'LEGAL_HOL_OT' => ['label' => 'LEGAL HOL. OT', 'keys' => ['LEGAL_HOL_OT', 'LEGAL_HOLIDAY_OT', 'HOLIDAY_OT', 'REGULAR_HOLIDAY_OT'] ],
	'NIGHT_DIFF' => ['label' => 'NIGHT DIFF', 'keys' => ['NIGHT_DIFF', 'NIGHT_DIFFERENTIAL'] ],
	'UNIFORM_ALLOWANCE' => ['label' => 'UNIFORM/OTHER ALLOW', 'keys' => ['UNIFORM_ALLOWANCE', 'UNIFORM_OTHER_ALLOWANCE', 'ALLOWANCE'] ],
	'CTP_ALLOWANCE' => ['label' => 'CTP ALLOWANCE', 'keys' => ['CTP_ALLOWANCE', 'CTP'] ],
	'RETROACTIVE' => ['label' => 'RETROACTIVE', 'keys' => ['RETROACTIVE', 'RETROACTIVE_PAY'] ],
];

$deductionRows = [
	'TAX_WHELD' => ['label' => 'TAX W/HELD', 'keys' => ['TAX_WHELD', 'TAX_WITHHELD', 'TAX', 'WITHHOLDING_TAX'] ],
	'SSS' => ['label' => 'SSS', 'keys' => ['SSS'] ],
	'PHILHEALTH' => ['label' => 'PHILHEALTH', 'keys' => ['PHILHEALTH'] ],
	'PAGIBIG' => ['label' => 'PAG-IBIG', 'keys' => ['PAGIBIG', 'PAG_IBIG'] ],
	'SSS_LOAN' => ['label' => 'SSS LOAN', 'keys' => ['SSS_LOAN'] ],
	'PAGIBIG_LOAN' => ['label' => 'PAG-IBIG LOAN', 'keys' => ['PAGIBIG_LOAN', 'PAG_IBIG_LOAN'] ],
	'LATE_UNDERTIME' => ['label' => 'LATE/UNDERTIME', 'keys' => ['LATE_UNDERTIME', 'LATE', 'UNDERTIME'] ],
	'CASH_ADVANCES' => ['label' => 'CASH ADVANCES', 'keys' => ['CASH_ADVANCES', 'CASH_ADVANCE'] ],
	'CASH_BOND' => ['label' => 'CASH BOND', 'keys' => ['CASH_BOND'] ],
	'OTHERS' => ['label' => 'OTHERS', 'keys' => ['OTHERS', 'OTHER_DEDUCTIONS'] ],
];

$companyName = defined('SYSTEM_NAME') ? (string)SYSTEM_NAME : 'Company';
$companyAddress = (string)env('SYSTEM_ADDRESS', '#348 Torres Street, Brgy. Mayapa, Calamba City');

$html = '<!doctype html><html><head><meta charset="utf-8">';
$html .= '<style>';
$html .= '@page { size: A4 landscape; margin: 10mm 10mm; }';
$html .= 'body { font-family: DejaVu Serif, serif; font-size: 9px; color: #000; margin: 0; padding: 0; }';
$html .= '.page { page-break-after: always; }';
$html .= '.company { font-weight: 700; font-size: 10px; text-transform: uppercase; }';
$html .= '.addr { font-size: 9px; }';
$html .= '.meta { width: 100%; border-collapse: collapse; margin-top: 2px; }';
$html .= '.meta td { font-size: 9px; padding: 0; vertical-align: top; }';
$html .= '.meta .label { width: 84px; font-weight: 700; text-transform: uppercase; }';
$html .= '.meta .value { font-weight: 700; text-transform: uppercase; }';
$html .= '.sheet { width: 100%; border-collapse: collapse; margin-top: 6px; }';
$html .= '.sheet td { vertical-align: top; }';
$html .= '.section { font-weight: 700; text-transform: uppercase; margin: 2px 0; }';
$html .= '.earn, .ded { width: 100%; border-collapse: collapse; }';
$html .= '.earn th, .earn td, .ded th, .ded td { font-size: 9px; padding: 1px 2px; }';
$html .= '.earn th, .ded th { font-weight: 700; border-bottom: 1px solid #000; }';
$html .= '.earn .col-label { width: 62%; }';
$html .= '.earn .col-hrs { width: 10%; text-align: center; }';
$html .= '.earn .col-amt { width: 28%; text-align: right; }';
$html .= '.ded .col-label { width: 72%; }';
$html .= '.ded .col-amt { width: 28%; text-align: right; }';
$html .= '.line { border-bottom: 1px solid #000; }';
$html .= '.total { font-weight: 700; }';
$html .= '.notice { text-align:center; font-size: 11px; margin-top: 30mm; }';
$html .= '</style></head><body>';
$html .= '</style></head><body>';

$renderedCount = 0;

foreach ($rows as $r) {
	$name = (string)($r['name'] ?? '');
	$locName = (string)($r['location_name'] ?? '');
	$payrollId = (int)($r['payroll_id'] ?? 0);
	$hasPayroll = $payrollId > 0;

	// Only print employees that have payroll records for this period.
	if (!$hasPayroll) {
		continue;
	}

	$gross = (float)($r['gross_pay'] ?? 0);
	$dedTotal = (float)($r['total_deductions'] ?? 0);
	$net = (float)($r['net_pay'] ?? 0);

	$detailsByKey = [];
	$dedByKey = [];

	if ($hasPayroll) {
		$detailsStmt->execute([':payroll_id' => $payrollId]);
		$detailsRows = $detailsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
		foreach ($detailsRows as $d) {
			$key = normalize_key((string)($d['type'] ?? ''));
			if ($key === '') {
				continue;
			}
			$detailsByKey[$key] = [
				'hours' => (float)($d['total_hours'] ?? 0),
				'amount' => (float)($d['total_amount'] ?? 0),
			];
		}

		$deductionsStmt->execute([':payroll_id' => $payrollId]);
		$dedRows = $deductionsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
		foreach ($dedRows as $d) {
			$key = normalize_key((string)($d['deduction_type'] ?? ''));
			if ($key === '') {
				continue;
			}
			$dedByKey[$key] = (float)($d['total_amount'] ?? 0);
		}
	}

	// If stored totals are empty, compute from line items when available.
	if ($gross <= 0.00001 && !empty($detailsByKey)) {
		$sum = 0.0;
		foreach ($detailsByKey as $it) {
			$sum += (float)($it['amount'] ?? 0);
		}
		$gross = $sum;
	}
	if ($dedTotal <= 0.00001 && !empty($dedByKey)) {
		$sum = 0.0;
		foreach ($dedByKey as $v) {
			$sum += (float)$v;
		}
		$dedTotal = $sum;
	}
	if ($net <= 0.00001 && ($gross > 0.00001 || $dedTotal > 0.00001)) {
		$net = $gross - $dedTotal;
	}

	$html .= '<div class="page">';
	$html .= '<div class="company">' . htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') . '</div>';
	$html .= '<div class="addr">' . htmlspecialchars($companyAddress, ENT_QUOTES, 'UTF-8') . '</div>';
	$html .= '<table class="meta">'
		. '<tr>'
		. '<td><span class="label">PAY PERIOD:</span> <span class="value">' . htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') . '</span></td>'
		. '<td style="text-align:right;"><span class="label">CUT OFF PERIOD:</span> <span class="value">' . htmlspecialchars($cutoffLabel, ENT_QUOTES, 'UTF-8') . '</span></td>'
		. '</tr>'
		. '<tr>'
		. '<td><span class="label">EMPLOYEE NAME:</span> <span class="value">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</span></td>'
		. '<td style="text-align:right;"><span class="label">LOCATION:</span> <span class="value">' . htmlspecialchars($locName, ENT_QUOTES, 'UTF-8') . '</span></td>'
		. '</tr>'
		. '</table>';

	$html .= '<table class="sheet"><tr>';
	$html .= '<td style="width:55%; padding-right:8px;">';

	$html .= '<div class="section">I. EARNINGS</div>';
	$html .= '<table class="earn">';
	$html .= '<tr><th class="col-label"></th><th class="col-hrs">HRS</th><th class="col-amt">EARNINGS</th></tr>';
	foreach ($earningsRows as $def) {
		$label = (string)$def['label'];
		$keys = (array)$def['keys'];
		$hrs = 0.0;
		$amt = 0.0;
		foreach ($keys as $k) {
			$k = normalize_key((string)$k);
			if (isset($detailsByKey[$k])) {
				$hrs += (float)$detailsByKey[$k]['hours'];
				$amt += (float)$detailsByKey[$k]['amount'];
			}
		}
		$html .= '<tr>';
		$html .= '<td class="col-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</td>';
		$html .= '<td class="col-hrs">' . htmlspecialchars(hours_fmt($hrs), ENT_QUOTES, 'UTF-8') . '</td>';
		$html .= '<td class="col-amt line">' . ($amt > 0.00001 ? peso_html($amt) : '&nbsp;') . '</td>';
		$html .= '</tr>';
	}
	$html .= '<tr class="total"><td class="col-label">GROSS PAY</td><td class="col-hrs"></td><td class="col-amt">' . peso_html($gross) . '</td></tr>';
	$html .= '</table>';

	$html .= '<div class="section" style="margin-top:6px;">II. DEDUCTIONS</div>';
	$html .= '<table class="ded">';
	$html .= '<tr><th class="col-label"></th><th class="col-amt">AMOUNT</th></tr>';
	foreach ($deductionRows as $def) {
		$label = (string)$def['label'];
		$keys = (array)$def['keys'];
		$amt = 0.0;
		foreach ($keys as $k) {
			$k = normalize_key((string)$k);
			if (isset($dedByKey[$k])) {
				$amt += (float)$dedByKey[$k];
			}
		}
		$html .= '<tr>';
		$html .= '<td class="col-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</td>';
		$html .= '<td class="col-amt line">' . ($amt > 0.00001 ? peso_html($amt) : '&nbsp;') . '</td>';
		$html .= '</tr>';
	}
	$html .= '<tr class="total"><td class="col-label">TOTAL DEDUCTIONS</td><td class="col-amt">' . peso_html($dedTotal) . '</td></tr>';
	$html .= '</table>';

	$html .= '<div class="total" style="margin-top:6px;">NET PAY&nbsp;&nbsp;<span class="line" style="display:inline-block; width:170px; text-align:right;">' . peso_html($net) . '</span></div>';
	$html .= '</td>';

	// Right copy (summary) like in the photo
	$html .= '<td style="width:45%; padding-left:8px;">';
	$html .= '<div class="company" style="text-align:center;">' . htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') . '</div>';
	$html .= '<div class="addr" style="text-align:center;">' . htmlspecialchars($companyAddress, ENT_QUOTES, 'UTF-8') . '</div>';
	$html .= '<table class="meta" style="margin-top:10px;">'
		. '<tr><td class="label">NAME</td><td class="line value">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</td></tr>'
		. '<tr><td class="label">Period Covered</td><td class="line">' . htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') . '</td></tr>'
		. '<tr><td class="label">GROSS SALARY</td><td class="line">' . peso_html($gross) . '</td></tr>'
		. '<tr><td class="label">LESS: Other Deductions</td><td class="line">' . peso_html($dedTotal) . '</td></tr>'
		. '</table>';
	$html .= '<div class="total" style="margin-top:12px; text-align:center;">TOTAL NET SALARY&nbsp;&nbsp;<span class="line" style="display:inline-block; width:140px; text-align:right;">' . peso_html($net) . '</span></div>';
	$html .= '</td>';
	$html .= '</tr></table>';
	$html .= '</div>';
	$renderedCount++;
}

if ($renderedCount === 0) {
	$html .= '<div class="payslip-wrapper">';
	$html .= '<div class="header">'
		. htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') . '<br>'
		. htmlspecialchars($companyAddress, ENT_QUOTES, 'UTF-8')
		. '</div>';
	$html .= '<div class="notice">No payroll records found for<br><strong>' . htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') . '</strong>'
		. ($location !== '' ? '<br>Location: <strong>' . htmlspecialchars($location, ENT_QUOTES, 'UTF-8') . '</strong>' : '')
		. '</div>';
	$html .= '</div>';
}

$html .= '</body></html>';

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

