<?php
declare(strict_types=1);

require_once __DIR__ . '/../../middleware/auth_checker.php';
checkAccess('ACCOUNTING');

require_once __DIR__ . '/../../service/payroll_calculator.php';

$pageTitle = 'Payroll Table';
require_once __DIR__ . '/../../template/header.php';

// Ensure DB connection is available for filters/table.
try {
    require_once __DIR__ . '/../../config/db_connection.php';
} catch (Throwable $e) {
    // Allow page to render without DB in edge cases.
}

// Cutoff / period selection (defaults to current month and current cutoff).
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$selectedCutoff = isset($_GET['cutoff']) ? (string)$_GET['cutoff'] : ((int)date('j') <= 15 ? '1' : '2');
if ($selectedYear < 2000 || $selectedYear > 2100) {
    $selectedYear = (int)date('Y');
}
if ($selectedMonth < 1 || $selectedMonth > 12) {
    $selectedMonth = (int)date('n');
}
if ($selectedCutoff !== '1' && $selectedCutoff !== '2') {
    $selectedCutoff = ((int)date('j') <= 15 ? '1' : '2');
}

$daysInMonth = (int)cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);
$periodStartDay = $selectedCutoff === '1' ? 1 : 16;
$periodEndDay = $selectedCutoff === '1' ? 15 : $daysInMonth;

$startDate = sprintf('%04d-%02d-%02d', $selectedYear, $selectedMonth, $periodStartDay);
$endDate = sprintf('%04d-%02d-%02d', $selectedYear, $selectedMonth, $periodEndDay);

$viewingText = date('M', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear))
    . " {$periodStartDay}-{$periodEndDay}, {$selectedYear}";

$selectedLocation = isset($_GET['location']) ? trim((string)$_GET['location']) : '';
$locations = [];
if (isset($conn) && ($conn instanceof PDO)) {
    try {
        $locStmt = $conn->prepare('SELECT DISTINCT location_name FROM location_rate ORDER BY location_name ASC');
        $locStmt->execute();
        $locations = $locStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $e) {
        $locations = [];
    }
}

// $viewingText is set based on cutoff/month/year above.
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

<style>
    body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
    #wrapper { overflow-x: hidden; }
    #page-content-wrapper { min-width: 100vw; }
    @media (min-width: 768px) {
        #page-content-wrapper { min-width: 0; width: 100%; }
    }
</style>

<div class="d-flex" id="wrapper">
    <?php include __DIR__ . '/../../template/sidebar.php'; ?>

    <div id="page-content-wrapper" class="w-100">
        <div class="container-fluid px-4 py-4">
            <!-- Payroll Table Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0 text-center" style="font-size: 1.5rem; font-weight: bold;">
                        <i class="material-icons align-middle me-2">payments</i>
						Payroll For <?php echo htmlspecialchars((string)$viewingText, ENT_QUOTES, 'UTF-8'); ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form class="row g-2 align-items-end mb-3" method="get">
                        <div class="col-12 col-md-2">
                            <label class="form-label mb-1">Year</label>
                            <select name="year" class="form-select">
                                <?php
                                $currentYear = (int)date('Y');
                                for ($y = $currentYear - 3; $y <= $currentYear + 3; $y++) {
                                    $selected = ($y === $selectedYear) ? 'selected' : '';
                                    echo "<option value=\"{$y}\" {$selected}>{$y}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label mb-1">Month</label>
                            <select name="month" class="form-select">
                                <?php
                                for ($m = 1; $m <= 12; $m++) {
                                    $label = date('F', mktime(0, 0, 0, $m, 1));
                                    $selected = ($m === $selectedMonth) ? 'selected' : '';
                                    echo "<option value=\"{$m}\" {$selected}>{$label}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label mb-1">Cutoff Period</label>
                            <select name="cutoff" class="form-select">
                                <option value="1" <?php echo $selectedCutoff === '1' ? 'selected' : ''; ?>>1 - 15</option>
                                <option value="2" <?php echo $selectedCutoff === '2' ? 'selected' : ''; ?>>16 - <?php echo (int)$daysInMonth; ?></option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label mb-1">Location</label>
                            <select name="location" class="form-select">
                                <option value="" <?php echo $selectedLocation === '' ? 'selected' : ''; ?>>All Locations</option>
                                <?php
                                foreach ($locations as $locName) {
                                    $locName = (string)$locName;
                                    $selected = ($locName !== '' && $locName === $selectedLocation) ? 'selected' : '';
                                    $safe = htmlspecialchars($locName, ENT_QUOTES, 'UTF-8');
                                    echo "<option value=\"{$safe}\" {$selected}>{$safe}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary w-100">Apply</button>
                            <?php
                            $bulkUrl = '../../controller/payroll/bulk_print_payslip.php?' . http_build_query([
                                'start_date' => $startDate,
                                'end_date' => $endDate,
                                'location' => $selectedLocation,
                            ]);
                            ?>
                            <a class="btn btn-success w-100" target="_blank" href="<?php echo htmlspecialchars($bulkUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                Bulk Print Payslip
                            </a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table id="payrollTable" class="table table-striped table-bordered">
                            <thead>
                                <tr class="table-primary">
                                    <th class="align-middle" rowspan="2">Name</th>
                                    <th class="text-center align-middle bg-success text-white" colspan="10">Earnings</th>
                                    <th class="text-center align-middle bg-danger text-white" colspan="11">Deductions</th>
                                    <th class="align-middle text-center" rowspan="2">Net Salary</th>
                                    <th class="align-middle text-center" rowspan="2">Actions</th>
                                </tr>
                                <tr>
                                    <!-- Earnings sub-headers -->
                                    <th class="earnings-header">REG HRS</th>
                                    <th class="earnings-header">REG OT</th>
                                    <th class="earnings-header">SUN/RD/SPCL. HOL.</th>
                                    <th class="earnings-header">SPCL. HOL. OT</th>
                                    <th class="earnings-header">LEGAL HOLIDAY</th>
                                    <th class="earnings-header">NIGHT DIFF</th>
                                    <th class="earnings-header">UNIFORM/OTHER ALLOWANCE</th>
                                    <th class="earnings-header">CTP ALLOWANCE</th>
                                    <th class="earnings-header">RETROOACTIVE</th>
                                    <th class="earnings-header font-weight-bold">GROSS PAY</th>
									
                                    <!-- Deductions sub-headers -->
                                    <th class="deductions-header">TAX W/HELD</th>
                                    <th class="deductions-header">SSS</th>
                                    <th class="deductions-header">PHILHEALTH</th>
                                    <th class="deductions-header">PAG-IBIG</th>
                                    <th class="deductions-header">SSS LOAN</th>
                                    <th class="deductions-header">PAG-IBIG LOAN</th>
                                    <th class="deductions-header">LATE/UNDERTIME</th>
                                    <th class="deductions-header">CASH ADVANCES</th>
                                    <th class="deductions-header">CASH BOND</th>
                                    <th class="deductions-header">OTHERS</th>
                                    <th class="deductions-header font-weight-bold">TOTAL DEDUCTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            if (!isset($conn) || !($conn instanceof PDO)) {
                                // DB not available.
                            } else {
                                // New system: list ALL employees (not guards-only)
                                $sql = "SELECT e.employee_id, e.user_id, e.first_name, e.middle_name, e.last_name,
                                        CONCAT(e.first_name, ' ',
                                        CASE WHEN e.middle_name IS NOT NULL AND e.middle_name != ''
                                            THEN CONCAT(UPPER(LEFT(e.middle_name, 1)), '. ')
                                            ELSE '' END,
                                        e.last_name) AS name,
                                        e.department, e.position, lr.location_name
                                        FROM employees e
                                        JOIN location_rate lr ON e.location_id = lr.location_id";

                                // Optional location filter (by location_rate.location_name)
                                if ($selectedLocation !== '') {
                                    $sql .= " WHERE lr.location_name = :location_name";
                                }

                                $sql .= " ORDER BY e.last_name ASC, e.first_name ASC";

                                $stmt = $conn->prepare($sql);

                                // Bind location parameter if needed
                                if (!empty($selectedLocation)) {
                                    $stmt->bindParam(':location_name', $selectedLocation);
                                }

                                $stmt->execute();
                                $calculator = new PayrollCalculator($conn);

                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    $payroll_data = [];
                                    if ($calculator) {
                                        $payroll_data = $calculator->calculatePayroll(
                                            (int)$row['employee_id'],
                                            $startDate,
                                            $endDate
                                        );
                                    }
									
                                    // Cash advance (prefer payroll_deductions snapshot if payroll exists; otherwise pull from cash_advances per cutoff)
                                    $saved_cash_advance = 0.0;
                                    $payroll_id_sql = "SELECT payroll_id FROM payroll WHERE employee_id = :employee_id AND period_start = :start_date AND period_end = :end_date LIMIT 1";
                                    $payroll_id_stmt = $conn->prepare($payroll_id_sql);
                                    $payroll_id_stmt->bindParam(':employee_id', $row['employee_id'], PDO::PARAM_INT);
                                    $payroll_id_stmt->bindParam(':start_date', $startDate);
                                    $payroll_id_stmt->bindParam(':end_date', $endDate);
                                    $payroll_id_stmt->execute();
                                    $payroll_id = $payroll_id_stmt->fetchColumn();

                                    if ($payroll_id) {
                                        $cash_advance_sql = "SELECT amount FROM payroll_deductions WHERE payroll_id = :payroll_id AND deduction_type = 'CASH_ADVANCES' LIMIT 1";
                                        $cash_advance_stmt = $conn->prepare($cash_advance_sql);
                                        $cash_advance_stmt->bindParam(':payroll_id', $payroll_id, PDO::PARAM_INT);
                                        $cash_advance_stmt->execute();
                                        $saved_cash_advance = (float)($cash_advance_stmt->fetchColumn() ?: 0);
                                    } else {
                                        $periodYear = (int)date('Y', strtotime($startDate));
                                        $periodMonth = (int)date('n', strtotime($startDate));
                                        $startDay = (int)date('j', strtotime($startDate));
                                        $cutoff = $startDay <= 15 ? 1 : 2;

                                        $cash_advance_sql = "SELECT amount FROM cash_advances WHERE employee_id = :employee_id AND period_year = :py AND period_month = :pm AND cutoff = :cutoff LIMIT 1";
                                        $cash_advance_stmt = $conn->prepare($cash_advance_sql);
                                        $cash_advance_stmt->execute([
                                            ':employee_id' => (int)$row['employee_id'],
                                            ':py' => $periodYear,
                                            ':pm' => $periodMonth,
                                            ':cutoff' => $cutoff,
                                        ]);
                                        $saved_cash_advance = (float)($cash_advance_stmt->fetchColumn() ?: 0);
                                        if ($saved_cash_advance < 0) {
                                            $saved_cash_advance = 0.0;
                                        }
                                        if ($saved_cash_advance > 1000) {
                                            $saved_cash_advance = 1000.0;
                                        }
                                    }

                                    // Fallback: if no calculator, use stored payroll summary if available.
                                    if (!$calculator) {
                                        $summaryStmt = $conn->prepare('SELECT gross_pay, total_deductions, net_pay FROM payroll WHERE employee_id = :employee_id AND period_start = :start_date AND period_end = :end_date LIMIT 1');
                                        $summaryStmt->execute([
                                            ':employee_id' => (int)$row['employee_id'],
                                            ':start_date' => $startDate,
                                            ':end_date' => $endDate,
                                        ]);
                                        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                                        $payroll_data = [
                                            'gross_pay' => (float)($summary['gross_pay'] ?? 0),
                                            'total_deductions' => (float)($summary['total_deductions'] ?? 0),
                                            'net_pay' => (float)($summary['net_pay'] ?? 0),
                                        ];
                                    }
									
                                    echo "<tr>";
                                    echo "<td class='employee-name'>" . htmlspecialchars($row['name']) . "</td>";

                                    // Earnings columns
                                    if (!$calculator) {
                                        // Unknown breakdown: show zeros except gross pay.
                                        for ($i = 0; $i < 9; $i++) {
                                            echo "<td class='amount-cell'>₱0.00</td>";
                                        }
                                        echo "<td class='amount-cell gross-pay'>₱" . number_format($payroll_data['gross_pay'] ?? 0, 2) . "</td>";

                                        // Deductions columns (unknown breakdown: show zeros except cash advance + total deductions)
                                        for ($i = 0; $i < 7; $i++) {
                                            echo "<td class='amount-cell'>₱0.00</td>";
                                        }
                                        echo "<td><input type='number' class='form-control cash-advance-input' data-employee-id='" . (int)$row['employee_id'] . "' value='" . number_format($saved_cash_advance, 2, '.', '') . "' min='0' max='1000' step='0.01'></td>";
                                        echo "<td class='amount-cell'>₱0.00</td>"; // Cash bond
                                        echo "<td class='amount-cell'>₱0.00</td>"; // Others
                                        echo "<td class='amount-cell total-deductions'>₱" . number_format($payroll_data['total_deductions'] ?? 0, 2) . "</td>";

                                        echo "<td class='amount-cell net-pay fw-bold'>₱" . number_format($payroll_data['net_pay'] ?? 0, 2) . "</td>";
                                    } else {
                                        // Earnings columns
                                        echo "<td class='amount-cell'>₱" . number_format($payroll_data['regular_hours_pay'] ?? 0, 2) . "</td>";
                                        echo "<td class='amount-cell'>₱" . number_format($payroll_data['ot_pay'] ?? 0, 2) . "</td>";
                                        echo "<td class='amount-cell'>₱" . number_format($payroll_data['special_holiday_pay'] ?? 0, 2) . "</td>";
                                        echo "<td class='amount-cell'>₱" . number_format($payroll_data['special_holiday_ot_pay'] ?? 0, 2) . "</td>";
                                        echo "<td class='amount-cell'>₱" . number_format($payroll_data['legal_holiday_pay'] ?? 0, 2) . "</td>";
                                        echo "<td class='amount-cell'>₱" . number_format($payroll_data['night_diff_pay'] ?? 0, 2) . "</td>";
                                        echo "<td class='amount-cell'>₱" . number_format($payroll_data['uniform_allowance'] ?? 0, 2) . "</td>";
                                        echo "<td class='amount-cell'>₱" . number_format($payroll_data['ctp_allowance'] ?? 0, 2) . "</td>";
                                        echo "<td class='amount-cell'>₱" . number_format($payroll_data['retroactive_pay'] ?? 0, 2) . "</td>";
                                        echo "<td class='amount-cell gross-pay'>₱" . number_format($payroll_data['gross_pay'] ?? 0, 2) . "</td>";
										
                                        // Deductions columns
                                        echo "<td class='amount-cell'>₱" . number_format($payroll_data['tax'] ?? 0, 2) . "</td>";
                                        echo "<td class='amount-cell'>₱" . number_format($payroll_data['sss'] ?? 0, 2) . "</td>";
                                        echo "<td class='amount-cell'>₱" . number_format($payroll_data['philhealth'] ?? 0, 2) . "</td>";
                                        echo "<td class='amount-cell'>₱" . number_format($payroll_data['pagibig'] ?? 0, 2) . "</td>";
                                        echo "<td class='amount-cell'>₱" . number_format($payroll_data['sss_loan'] ?? 0, 2) . "</td>";
                                        echo "<td class='amount-cell'>₱" . number_format($payroll_data['pagibig_loan'] ?? 0, 2) . "</td>";
                                        echo "<td class='amount-cell'>₱" . number_format($payroll_data['late_undertime'] ?? 0, 2) . "</td>";
                                        echo "<td><input type='number' class='form-control cash-advance-input' data-employee-id='" . $row['employee_id'] . "' value='" . number_format($saved_cash_advance, 2, '.', '') . "' min='0' max='1000' step='0.01'></td>";
										
                                        if (!empty($payroll_data['cash_bond_limit_reached']) && $payroll_data['cash_bond_limit_reached']) {
                                            echo "<td class='amount-cell'>₱0.00 <span class='badge bg-success'>Limit Reached</span></td>";
                                        } else {
                                            echo "<td class='amount-cell'>₱" . number_format($payroll_data['cash_bond'] ?? 0, 2) . "</td>";
                                        }
										
                                        echo "<td class='amount-cell'>₱" . number_format($payroll_data['other_deductions'] ?? 0, 2) . "</td>";
                                        echo "<td class='amount-cell total-deductions'>₱" . number_format($payroll_data['total_deductions'] ?? 0, 2) . "</td>";
										
                                        // Total Net Salary
                                        echo "<td class='amount-cell net-pay fw-bold'>₱" . number_format($payroll_data['net_pay'] ?? 0, 2) . "</td>";
                                    }
									
                                    // Actions column with improved payslip button
                                    echo "<td class='text-center'>
                                        <button class='btn btn-success btn-sm payslip-btn' data-employee-id='{$row['employee_id']}'>
                                            <i class='material-icons align-middle fs-6'>description</i> Payslip
                                        </button>
                                    </td>";
                                    echo "</tr>";
                                }
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>