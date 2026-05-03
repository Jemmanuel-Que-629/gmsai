<?php
declare(strict_types=1);

$pageTitle = 'Payroll Table';
require_once __DIR__ . '/../../global/header.php';

// Frontend-first defaults (backend wiring can override these later)
if (!isset($viewingText) || !is_string($viewingText) || trim($viewingText) === '') {
    $viewingText = 'Selected Period';
}
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
    <?php include __DIR__ . '/../../global/sidebar.php'; ?>

    <div id="page-content-wrapper" class="w-100">
        <div class="container-fluid px-4 py-4">
            <!-- Payroll Table Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0 text-center" style="font-size: 1.5rem; font-weight: bold;">
                        <i class="material-icons align-middle me-2">payments</i>
                        Payroll For <?php echo $viewingText; ?>
                    </h5>
                </div>
                <div class="card-body">
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
                            if (!isset($conn) || !($conn instanceof PDO) || !class_exists('PayrollCalculator')) {
                                // Backend wiring pending: keep table layout, render no rows.
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
                                $selectedLocation = isset($_GET['location']) ? (string)$_GET['location'] : '';
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
                                    // Check for attendance in the selected period
                                    $attendance_check_sql = "SELECT COUNT(*) FROM attendance WHERE employee_id = :employee_id AND work_date BETWEEN :start_date AND :end_date";
                                    $attendance_check_stmt = $conn->prepare($attendance_check_sql);
                                    $attendance_check_stmt->bindParam(':employee_id', $row['employee_id'], PDO::PARAM_INT);
                                    $attendance_check_stmt->bindParam(':start_date', $startDate);
                                    $attendance_check_stmt->bindParam(':end_date', $endDate);
                                    $attendance_check_stmt->execute();
                                    $attendance_count = $attendance_check_stmt->fetchColumn();

                                    $payroll_data = $calculator->calculatePayroll(
                                        $row['employee_id'], 
                                        $startDate, 
                                        $endDate
                                    );
									
                                    // Saved cash advance (schema-normalized via payroll + payroll_deductions)
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
                                    }
									
                                    echo "<tr>";
                                    echo "<td class='employee-name'>" . htmlspecialchars($row['name']) . "</td>";
									
                                    // If no attendance, display all columns as ₱0.00 (and 0.00 for input)
                                    if ($attendance_count == 0) {
                                        for ($i = 0; $i < 10; $i++) {
                                            echo "<td class='amount-cell'>₱0.00</td>";
                                        }
                                        for ($i = 0; $i < 7; $i++) {
                                            echo "<td class='amount-cell'>₱0.00</td>";
                                        }
                                        echo "<td><input type='number' class='form-control cash-advance-input' data-employee-id='" . $row['employee_id'] . "' value='" . number_format($saved_cash_advance, 2, '.', '') . "' min='0' max='1000' step='0.01'></td>";
                                        if (!empty($payroll_data['cash_bond_limit_reached']) && $payroll_data['cash_bond_limit_reached']) {
                                            echo "<td class='amount-cell'>₱0.00 <span class='badge bg-success'>Limit Reached</span></td>";
                                        } else {
                                            echo "<td class='amount-cell'>₱0.00</td>";
                                        }
                                        echo "<td class='amount-cell'>₱0.00</td>"; // Others
                                        echo "<td class='amount-cell total-deductions'>₱0.00</td>";
                                        echo "<td class='amount-cell net-pay'>₱0.00</td>";
                                    } else if (empty($payroll_data['gross_pay']) || $payroll_data['gross_pay'] == 0 || empty($payroll_data['net_pay']) || $payroll_data['net_pay'] == 0) {
                                        // If gross pay or net pay is empty or zero, display all columns as ₱0.00 (and 0.00 for input)
                                        for ($i = 0; $i < 10; $i++) {
                                            echo "<td class='amount-cell'>₱0.00</td>";
                                        }
                                        for ($i = 0; $i < 7; $i++) {
                                            echo "<td class='amount-cell'>₱0.00</td>";
                                        }
                                        echo "<td><input type='number' class='form-control cash-advance-input' data-employee-id='" . $row['employee_id'] . "' value='" . number_format($saved_cash_advance, 2, '.', '') . "' min='0' max='1000' step='0.01'></td>";
                                        if (!empty($payroll_data['cash_bond_limit_reached']) && $payroll_data['cash_bond_limit_reached']) {
                                            echo "<td class='amount-cell'>₱0.00 <span class='badge bg-success'>Limit Reached</span></td>";
                                        } else {
                                            echo "<td class='amount-cell'>₱0.00</td>";
                                        }
                                        echo "<td class='amount-cell'>₱0.00</td>"; // Others
                                        echo "<td class='amount-cell total-deductions'>₱0.00</td>";
                                        echo "<td class='amount-cell net-pay'>₱0.00</td>";
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