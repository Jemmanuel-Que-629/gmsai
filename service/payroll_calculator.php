<?php

declare(strict_types=1);

require_once __DIR__ . '/../repository/payroll_repository.php';

/**
 * Unified Payroll Calculator (Service-layer)
 *
 * This file intentionally contains the payroll computation engine.
 * Controllers/views should only call this class, not re-implement payroll logic.
 */
class PayrollCalculator {
	private $conn;
	private $uniformAllowance = 50.00; // Default allowance amount
	private $defaultRate = 540.00;     // Default daily rate if no location is found

	// Cache for holidays
	private $holidayCache = [];
	public function __construct($conn) {
		$this->conn = $conn;
	}

	/**
	 * Main calculation function
	 *
	 * @param int $employeeId Employee ID
	 * @param string $startDate Start date of the payroll period (Y-m-d)
	 * @param string $endDate End date of the payroll period (Y-m-d)
	 * @return array Complete payroll calculation results
	 */
	public function calculatePayroll($employeeId, $startDate, $endDate) {
		// Initialize result array with zeros
		$result = $this->getEmptyPayrollArray();

		// Get employee location and rate
		$locationData = $this->getEmployeeLocationRate($employeeId);
		$dailyRate = $locationData['daily_rate'] ?? $this->defaultRate;
		$hourlyRate = $dailyRate / 8; // Convert daily rate to hourly rate

		// Store the rates in the result for reference
		$result['hourly_rate'] = $hourlyRate;
		$result['daily_rate'] = $dailyRate;

		// Get attendance records for this period
		$attendance = $this->getAttendanceRecords($employeeId, $startDate, $endDate);
		if (empty($attendance)) {
			return $result; // Return zeros if no attendance
		}

		// Process each attendance record
		foreach ($attendance as $record) {
			$this->processAttendanceRecord($record, $hourlyRate, $result);
		}

		// Add uniform allowance
		$result['uniform_allowance'] = $this->uniformAllowance;

		// Calculate gross pay (subtract late/undertime from earnings)
		$result['gross_pay'] =
			$result['regular_hours_pay'] +
			$result['ot_pay'] +
			$result['night_diff_pay'] +
			$result['legal_holiday_pay'] +
			$result['holiday_ot_pay'] +
			$result['special_holiday_pay'] +
			$result['special_holiday_ot_pay'] +
			$result['uniform_allowance'] -
			$result['late_undertime']; // Subtract late deduction from gross pay

		// Calculate deductions
		$this->calculateDeductions($employeeId, $result, $startDate, $endDate, $dailyRate);

		// Calculate net pay (gross minus deductions)
		$result['net_pay'] = $result['gross_pay'] - $result['total_deductions'];

		return $result;
	}

	/**
	 * Process a single attendance record
	 */
	private function processAttendanceRecord($record, $hourlyRate, &$result) {
		$workDate = isset($record['work_date']) ? (string)$record['work_date'] : '';
		$timeInRaw = isset($record['time_in']) ? (string)$record['time_in'] : '';
		$timeOutRaw = isset($record['time_out']) ? (string)$record['time_out'] : '';

		if ($workDate === '' || $timeInRaw === '' || $timeOutRaw === '') {
			return;
		}

		$timeIn = new DateTime($workDate . ' ' . $timeInRaw);
		$timeOut = new DateTime($workDate . ' ' . $timeOutRaw);

		// Handle overnight shift (if time_out is earlier than time_in)
		if ($timeOut < $timeIn) {
			$timeOut->modify('+1 day');
		}

		// Accumulate total hours worked (raw duration regardless of categorization)
		$workedHours = ($timeOut->getTimestamp() - $timeIn->getTimestamp()) / 3600;
		if ($workedHours > 0) {
			$result['total_hours_worked'] += $workedHours;
		}

		// Get day of the week (1=Monday, 7=Sunday)
		$dayOfWeek = (int)(new DateTime($workDate))->format('N');
		$isRestDay = ($dayOfWeek == 6 || $dayOfWeek == 7); // Saturday or Sunday

		// Get holiday classification from database for this date
		$holidayClass = $this->getHolidayClass($workDate);

		// Calculate late deduction based on shift
		$this->calculateLateDeduction($timeIn, $hourlyRate, $result);

		// Compute pay based on actual worked hours, with OT + night diff + holiday/rest day multipliers.
		$this->applyWorkedIntervalPay($timeIn, $timeOut, $hourlyRate, $isRestDay, $holidayClass, $result);
	}

	/**
	 * Calculate late deduction based on shift time
	 * Late formula: Late Minutes × (Location Rate ÷ 8 ÷ 60)
	 */
	private function calculateLateDeduction($timeIn, $hourlyRate, &$result) {
		$hourOfDay = (int)$timeIn->format('H');

		// Determine expected start time based on shift
		// Current attendance seed data uses 08:00–17:00 for day shift.
		// Without a schedule table, we use a practical heuristic.
		if ($hourOfDay < 15) {
			$expectedStartTime = new DateTime($timeIn->format('Y-m-d') . ' 08:00:00');
		} else {
			$expectedStartTime = new DateTime($timeIn->format('Y-m-d') . ' 18:00:00');
		}

		// If employee is late (clock in after expected start time)
		if ($timeIn > $expectedStartTime) {
			// Calculate late minutes
			$lateSeconds = $timeIn->getTimestamp() - $expectedStartTime->getTimestamp();
			$lateMinutes = ceil($lateSeconds / 60); // Round up to the next minute

			// Calculate late deduction using the formula: Late Minutes × (Location Rate ÷ 8 ÷ 60)
			// hourlyRate is already Daily Rate ÷ 8, so we just divide by 60 for per minute rate
			$perMinuteRate = $hourlyRate / 60;
			$lateDeduction = $lateMinutes * $perMinuteRate;

			// Add late deduction to the result
			$result['late_undertime'] += $lateDeduction;
		}
	}

	/**
	 * Apply pay for a worked interval using:
	 * - First 8 hours = regular
	 * - Excess hours = overtime
	 * - Night differential = +10% for hours between 22:00 and 06:00
	 * - Premium multipliers for rest days and holidays (based on provided DOLE-style tables)
	 */
	private function applyWorkedIntervalPay(DateTime $timeIn, DateTime $timeOut, float $hourlyRate, bool $isRestDay, ?string $holidayClass, array &$result): void
	{
		$startTs = $timeIn->getTimestamp();
		$endTs = $timeOut->getTimestamp();

		if ($endTs <= $startTs) {
			return;
		}

		$regularEndTs = min($endTs, $startTs + (8 * 3600));

		$cutPoints = [$startTs, $endTs, $regularEndTs];

		// Add night-diff boundaries (22:00 and 06:00) for each day spanned.
		$cursor = new DateTime($timeIn->format('Y-m-d') . ' 00:00:00');
		$endDay = new DateTime($timeOut->format('Y-m-d') . ' 00:00:00');
		$endDay->modify('+1 day');
		while ($cursor < $endDay) {
			$d = $cursor->format('Y-m-d');
			$cutPoints[] = (new DateTime($d . ' 22:00:00'))->getTimestamp();
			$cutPoints[] = (new DateTime($d . ' 06:00:00'))->getTimestamp();
			$cursor->modify('+1 day');
		}

		// Keep only cutpoints strictly inside the interval.
		$cutPoints = array_values(array_filter($cutPoints, static function ($ts) use ($startTs, $endTs) {
			return is_int($ts) && $ts > $startTs && $ts < $endTs;
		}));
		$cutPoints[] = $startTs;
		$cutPoints[] = $endTs;
		$cutPoints[] = $regularEndTs;
		$cutPoints = array_values(array_unique($cutPoints));
		sort($cutPoints);

		$baseMultiplier = $this->getBaseMultiplier($holidayClass, $isRestDay);
		$otFactor = $this->getOvertimeFactor($holidayClass, $isRestDay);

		for ($i = 0; $i < count($cutPoints) - 1; $i++) {
			$a = (int)$cutPoints[$i];
			$b = (int)$cutPoints[$i + 1];
			if ($b <= $a) {
				continue;
			}

			$hours = ($b - $a) / 3600;
			if ($hours <= 0) {
				continue;
			}

			$isOt = $a >= $regularEndTs;
			$segmentMultiplier = $baseMultiplier * ($isOt ? $otFactor : 1.0);
			$segmentBasePay = $hourlyRate * $hours * $segmentMultiplier;

			$this->addEarnings($holidayClass, $isRestDay, $isOt, $hours, $segmentBasePay, $result);

			if ($this->isNightDiffTimestamp($a)) {
				$result['night_diff_hours'] += $hours;
				$result['night_diff_pay'] += ($segmentBasePay * 0.10);
			}
		}
	}

	private function isNightDiffTimestamp(int $ts): bool
	{
		$h = (int)date('G', $ts);
		return ($h >= 22 || $h < 6);
	}

	/**
	 * holidayClass values:
	 * - null (no holiday)
	 * - company
	 * - special
	 * - regular
	 * - double_special
	 * - double_holiday
	 */
	private function getBaseMultiplier(?string $holidayClass, bool $isRestDay): float
	{
		$holidayClass = $holidayClass ? strtolower($holidayClass) : null;
		switch ($holidayClass) {
			case 'regular':
				return $isRestDay ? 2.6 : 2.0;
			case 'special':
				return $isRestDay ? 1.5 : 1.3;
			case 'double_holiday':
				return $isRestDay ? 3.9 : 3.0;
			case 'double_special':
				return $isRestDay ? 1.95 : 1.5;
			case 'company':
			default:
				return $isRestDay ? 1.3 : 1.0;
		}
	}

	private function getOvertimeFactor(?string $holidayClass, bool $isRestDay): float
	{
		$holidayClass = $holidayClass ? strtolower($holidayClass) : null;
		if ($holidayClass === null || $holidayClass === 'company') {
			// Ordinary day OT = 1.25; Rest day OT uses 1.30 premium (per provided table)
			return $isRestDay ? 1.3 : 1.25;
		}
		// Holiday OT uses 1.30 premium (per provided tables)
		return 1.3;
	}

	private function addEarnings(?string $holidayClass, bool $isRestDay, bool $isOt, float $hours, float $amount, array &$result): void
	{
		$holidayClass = $holidayClass ? strtolower($holidayClass) : null;

		// UI column combines SUN/RD + Special holidays, so route rest-day earnings there.
		if ($holidayClass === null || $holidayClass === 'company') {
			if ($isRestDay) {
				if ($isOt) {
					$result['special_holiday_ot_hours'] += $hours;
					$result['special_holiday_ot_pay'] += $amount;
				} else {
					$result['special_holiday_hours'] += $hours;
					$result['special_holiday_pay'] += $amount;
				}
				return;
			}

			if ($isOt) {
				$result['ot_hours'] += $hours;
				$result['ot_pay'] += $amount;
			} else {
				$result['regular_hours'] += $hours;
				$result['regular_hours_pay'] += $amount;
			}
			return;
		}

		if ($holidayClass === 'special' || $holidayClass === 'double_special') {
			if ($isOt) {
				$result['special_holiday_ot_hours'] += $hours;
				$result['special_holiday_ot_pay'] += $amount;
			} else {
				$result['special_holiday_hours'] += $hours;
				$result['special_holiday_pay'] += $amount;
			}
			return;
		}

		// regular or double_holiday
		if ($isOt) {
			$result['holiday_ot_hours'] += $hours;
			$result['holiday_ot_pay'] += $amount;
		} else {
			$result['legal_holiday_hours'] += $hours;
			$result['legal_holiday_pay'] += $amount;
		}
	}

	/**
	 * Get guard's location and rate
	 */
	private function getEmployeeLocationRate($employeeId) {
		try {
			$row = payroll_get_employee_location_rate($this->conn, (int)$employeeId);
			if (!$row) {
				return ['daily_rate' => $this->defaultRate];
			}
			if (array_key_exists('daily_rate', $row)) {
				$row['daily_rate'] = (float)$row['daily_rate'];
			}
			return $row;
		} catch (Throwable $e) {
			return ['daily_rate' => $this->defaultRate];
		}
	}

	/**
	 * Get attendance records for a user in a date range
	 */
	private function getAttendanceRecords($employeeId, $startDate, $endDate) {
		return payroll_get_attendance_records($this->conn, (int)$employeeId, (string)$startDate, (string)$endDate);
	}

	/**
	 * Get the holiday type for a specific date
	 */
	private function getHolidayClass($date) {
		if (isset($this->holidayCache[$date])) {
			return $this->holidayCache[$date];
		}

		$types = payroll_get_holiday_types_for_date($this->conn, (string)$date);

		$holidayClass = null;
		if (!empty($types)) {
			$hasRegular = false;
			$hasSpecialNonWorking = false;
			$hasCompany = false;

			foreach ($types as $t) {
				$t = strtolower((string)$t);
				if ($t === 'regular') {
					$hasRegular = true;
					continue;
				}
				if ($t === 'special' || $t === 'special_non_working') {
					$hasSpecialNonWorking = true;
					continue;
				}
				// special_working: treat as ordinary (no premium)
				if ($t === 'company') {
					$hasCompany = true;
					continue;
				}
			}

			if ($hasRegular && $hasSpecialNonWorking) {
				$holidayClass = 'double_holiday';
			} elseif (count($types) > 1 && !$hasRegular && $hasSpecialNonWorking) {
				$holidayClass = 'double_special';
			} elseif ($hasRegular) {
				$holidayClass = 'regular';
			} elseif ($hasSpecialNonWorking) {
				$holidayClass = 'special';
			} elseif ($hasCompany) {
				$holidayClass = 'company';
			}
		}

		$this->holidayCache[$date] = $holidayClass;
		return $holidayClass;
	}

	/**
	 * Calculate deductions based on gross pay and settings
	 */
	private function calculateDeductions($employeeId, &$result, $startDate, $endDate, $dailyRate) {
		// Determine if this is a half month period to derive gross monthly pay
		$isHalf = $this->isHalfMonthPeriod($startDate, $endDate);
		$monthlyGrossPay = $isHalf ? ($result['gross_pay'] * 2) : $result['gross_pay'];

		// Statutory deductions from current tables
		$asOf = $endDate;
		$result['sss'] = $this->calculateSSSFromTable($monthlyGrossPay, $asOf, $isHalf);
		$result['philhealth'] = $this->calculatePhilhealthFromTable($monthlyGrossPay, $asOf, $isHalf);
		$result['pagibig'] = $this->calculatePagibigFromTable($monthlyGrossPay, $asOf, $isHalf);

		$payrollId = $this->getPayrollId($employeeId, $startDate, $endDate);
		if ($payrollId) {
			// Saved/entered deductions (stored in payroll_deductions once a payroll row exists)
			$result['cash_advance'] = $this->getDeductionAmount($payrollId, 'CASH_ADVANCES');
			$result['cash_bond'] = $this->getDeductionAmount($payrollId, 'CASH_BOND');
			$result['sss_loan'] = $this->getDeductionAmount($payrollId, 'SSS_LOAN');
			$result['pagibig_loan'] = $this->getDeductionAmount($payrollId, 'PAGIBIG_LOAN');
			$result['other_deductions'] = $this->getDeductionAmount($payrollId, 'OTHERS');
		} else {
			// Live deductions prior to payroll save/finalize
			[$periodYear, $periodMonth, $cutoff] = $this->getCutoffYearMonth($startDate, $endDate);
			$result['cash_advance'] = $this->calculateCashAdvanceForCutoff($employeeId, $periodYear, $periodMonth, $cutoff);
			$cashBond = $this->calculateCashBondForCutoff($employeeId, $periodYear, $periodMonth, $cutoff);
			$result['cash_bond'] = $cashBond['amount'];
			$result['cash_bond_limit_reached'] = $cashBond['limit_reached'];
		}

		// Always compute the "limit reached" indicator for display
		if (empty($result['cash_bond_limit_reached'])) {
			$limit = $this->isCashBondLimitReached($employeeId);
			$result['cash_bond_limit_reached'] = $limit;
			if ($limit) {
				$result['cash_bond'] = 0.0;
			}
		}

		// Calculate total deductions (excluding late_undertime as it's already subtracted from gross pay)
		$result['total_deductions'] =
			$result['tax'] +
			$result['sss'] +
			$result['philhealth'] +
			$result['pagibig'] +
			$result['sss_loan'] +
			$result['pagibig_loan'] +
			$result['cash_advance'] +
			$result['cash_bond'] +
			$result['other_deductions'];
			// Note: late_undertime is now subtracted from gross pay instead of being a deduction
	}

	/**
	 * @return array{0:int,1:int,2:int} periodYear, periodMonth, cutoff(1|2)
	 */
	private function getCutoffYearMonth(string $startDate, string $endDate): array
	{
		$start = new DateTime($startDate);
		$day = (int)$start->format('j');
		$cutoff = $day <= 15 ? 1 : 2;
		return [(int)$start->format('Y'), (int)$start->format('n'), $cutoff];
	}

	private function calculateCashAdvanceForCutoff(int $employeeId, int $periodYear, int $periodMonth, int $cutoff): float
	{
		$amount = payroll_get_cash_advance_amount($this->conn, $employeeId, $periodYear, $periodMonth, $cutoff);
		if ($amount < 0) {
			$amount = 0.0;
		}
		if ($amount > 1000) {
			$amount = 1000.0;
		}
		return round($amount, 2);
	}

	/**
	 * @return array{amount:float, limit_reached:bool}
	 */
	private function calculateCashBondForCutoff(int $employeeId, int $periodYear, int $periodMonth, int $cutoff): array
	{
		if (!$this->isEmployeeGuard($employeeId)) {
			return ['amount' => 0.0, 'limit_reached' => false];
		}

		$account = payroll_get_cash_bond_account($this->conn, $employeeId);
		$targetAmount = (float)($account['target_amount'] ?? 10000.0);
		$perCutoffAmount = (float)($account['per_cutoff_amount'] ?? 100.0);
		$isActive = (int)($account['is_active'] ?? 1);
		if ($isActive !== 1) {
			return ['amount' => 0.0, 'limit_reached' => true];
		}

		$totalPaid = payroll_get_cash_bond_total_paid($this->conn, $employeeId);
		$remaining = max(0.0, $targetAmount - $totalPaid);
		if ($remaining <= 0.0) {
			return ['amount' => 0.0, 'limit_reached' => true];
		}

		$recordedPayment = payroll_get_cash_bond_payment_amount($this->conn, $employeeId, $periodYear, $periodMonth, $cutoff);
		if ($recordedPayment !== null) {
			$amt = max(0.0, min((float)$recordedPayment, $remaining));
			return ['amount' => round($amt, 2), 'limit_reached' => ($remaining <= 0.0)];
		}

		$due = min($perCutoffAmount, $remaining);
		return ['amount' => round($due, 2), 'limit_reached' => false];
	}

	private function isCashBondLimitReached(int $employeeId): bool
	{
		if (!$this->isEmployeeGuard($employeeId)) {
			return false;
		}
		$account = payroll_get_cash_bond_account($this->conn, $employeeId);
		$targetAmount = (float)($account['target_amount'] ?? 10000.0);
		$isActive = (int)($account['is_active'] ?? 1);
		if ($isActive !== 1) {
			return true;
		}
		$totalPaid = payroll_get_cash_bond_total_paid($this->conn, $employeeId);
		return $totalPaid >= $targetAmount;
	}

	private function isEmployeeGuard(int $employeeId): bool
	{
		$info = payroll_get_employee_department_position($this->conn, $employeeId);
		if (!$info) {
			return false;
		}
		$dept = strtolower(trim((string)($info['department'] ?? '')));
		$pos = strtolower(trim((string)($info['position'] ?? '')));
		if ($dept === 'security') {
			return true;
		}
		return strpos($pos, 'guard') !== false;
	}

	/**
	 * Get cash advance for a period
	 */
	private function getPayrollId(int $employeeId, string $startDate, string $endDate): ?int
	{
		return payroll_get_payroll_id($this->conn, $employeeId, $startDate, $endDate);
	}

	private function getDeductionAmount(int $payrollId, string $type): float
	{
		return payroll_sum_deductions($this->conn, $payrollId, $type);
	}

	/**
	 * Calculate SSS deduction based on monthly compensation table
	 */
	private function calculateSSSFromTable(float $monthlyCompensation, string $asOf, bool $isHalf): float {
		try {
			$val = payroll_get_sss_employee_contribution($this->conn, $monthlyCompensation, $asOf);
			$monthly = $val !== null ? (float)$val : 0.0;
			return $isHalf ? round($monthly / 2, 2) : round($monthly, 2);
		} catch (Throwable $e) {
			// Fallback to a small, safe default bracket list if table isn't available.
			$monthly = $this->calculateSSSDeductionFallback($monthlyCompensation);
			return $isHalf ? round($monthly / 2, 2) : round($monthly, 2);
		}
	}

	private function calculateSSSDeductionFallback($monthlyCompensation): float {
		$sssTable = [
			['min' => 0.00,      'max' => 5249.99,  'contribution' => 250.00],
			['min' => 5250.00,   'max' => 5749.99,  'contribution' => 275.00],
			['min' => 5750.00,   'max' => 6249.99,  'contribution' => 300.00],
			['min' => 6250.00,   'max' => 6749.99,  'contribution' => 325.00],
			['min' => 6750.00,   'max' => 7249.99,  'contribution' => 350.00],
			['min' => 7250.00,   'max' => 7749.99,  'contribution' => 375.00],
			['min' => 7750.00,   'max' => 8249.99,  'contribution' => 400.00],
			['min' => 8250.00,   'max' => 8749.99,  'contribution' => 425.00],
			['min' => 8750.00,   'max' => 9249.99,  'contribution' => 450.00],
			['min' => 9250.00,   'max' => 9749.99,  'contribution' => 475.00],
			['min' => 9750.00,   'max' => 10249.99, 'contribution' => 500.00],
			['min' => 10250.00,  'max' => 10749.99, 'contribution' => 525.00],
			['min' => 10750.00,  'max' => 11249.99, 'contribution' => 550.00],
			['min' => 11250.00,  'max' => 11749.99, 'contribution' => 575.00],
			['min' => 11750.00,  'max' => 12249.99, 'contribution' => 600.00],
			['min' => 12250.00,  'max' => 12749.99, 'contribution' => 625.00],
			['min' => 12750.00,  'max' => 13249.99, 'contribution' => 650.00],
			['min' => 13250.00,  'max' => 13749.99, 'contribution' => 675.00],
			['min' => 13750.00,  'max' => 14249.99, 'contribution' => 700.00],
			['min' => 14250.00,  'max' => 14749.99, 'contribution' => 725.00],
			['min' => 14750.00,  'max' => 15249.99, 'contribution' => 750.00],
			['min' => 15250.00,  'max' => 15749.99, 'contribution' => 775.00],
			['min' => 15750.00,  'max' => 16249.99, 'contribution' => 800.00],
			['min' => 16250.00,  'max' => 16749.99, 'contribution' => 825.00],
			['min' => 16750.00,  'max' => 17249.99, 'contribution' => 850.00],
			['min' => 17250.00,  'max' => 17749.99, 'contribution' => 875.00],
			['min' => 17750.00,  'max' => 18249.99, 'contribution' => 900.00],
			['min' => 18250.00,  'max' => 18749.99, 'contribution' => 925.00],
			['min' => 18750.00,  'max' => 19249.99, 'contribution' => 950.00],
			['min' => 19250.00,  'max' => 19749.99, 'contribution' => 975.00],
			['min' => 19750.00,  'max' => PHP_FLOAT_MAX, 'contribution' => 1000.00]
		];

		foreach ($sssTable as $bracket) {
			if ($monthlyCompensation >= $bracket['min'] && $monthlyCompensation <= $bracket['max']) {
				return $bracket['contribution'];
			}
		}

		return 2000.00; // Maximum contribution
	}

	private function calculatePhilhealthFromTable(float $monthlyGross, string $asOf, bool $isHalf): float
	{
		try {
			$row = payroll_get_philhealth_row($this->conn, $asOf);
			if (!$row) {
				return 0.0;
			}

			$rate = (float)$row['monthly_rate'];
			$employeeShare = (float)$row['employee_share'];
			$floor = (float)$row['salary_floor'];
			$ceiling = (float)$row['salary_ceiling'];

			$basis = max($floor, min($monthlyGross, $ceiling));
			$monthlyTotal = $basis * $rate;
			$monthlyEmployee = $monthlyTotal * $employeeShare;
			return $isHalf ? round($monthlyEmployee / 2, 2) : round($monthlyEmployee, 2);
		} catch (Throwable $e) {
			return 0.0;
		}
	}

	private function calculatePagibigFromTable(float $monthlyGross, string $asOf, bool $isHalf): float
	{
		try {
			$row = payroll_get_pagibig_row($this->conn, $monthlyGross, $asOf);
			if (!$row) {
				return 0.0;
			}
			$rate = (float)$row['employee_rate'];
			$ceiling = (float)$row['salary_ceiling'];

			$basis = min($monthlyGross, $ceiling);
			$monthlyEmployee = $basis * $rate;
			return $isHalf ? round($monthlyEmployee / 2, 2) : round($monthlyEmployee, 2);
		} catch (Throwable $e) {
			return 0.0;
		}
	}

	/**
	 * Check if a date range is a half-month period
	 */
	private function isHalfMonthPeriod($startDate, $endDate) {
		$start = new DateTime($startDate);
		$end = new DateTime($endDate);

		// Check if range is 1st-15th or 16th-end
		$firstDay = $start->format('j');
		$lastDay = $end->format('j');

		return ($firstDay == 1 && $lastDay == 15) ||
			   ($firstDay == 16 && $lastDay > 25);
	}

	/**
	 * Get empty payroll array with zeros
	 */
	private function getEmptyPayrollArray() {
		return [
			'regular_hours_pay' => 0,
			'regular_hours' => 0,
			'ot_pay' => 0,
			'ot_hours' => 0,
			'night_diff_pay' => 0,
			'night_diff_hours' => 0,
			'legal_holiday_pay' => 0,
			'legal_holiday_hours' => 0,
			'holiday_ot_pay' => 0,
			'holiday_ot_hours' => 0,
			'special_holiday_pay' => 0,
			'special_holiday_hours' => 0,
			'special_holiday_ot_pay' => 0,
			'special_holiday_ot_hours' => 0,
			'uniform_allowance' => 0,
			'ctp_allowance' => 0,
			'retroactive_pay' => 0,
			'gross_pay' => 0,
			'tax' => 0,
			'sss' => 0,
			'philhealth' => 0,
			'pagibig' => 0,
			'sss_loan' => 0,
			'pagibig_loan' => 0,
			'late_undertime' => 0,
			'cash_advance' => 0,
			'cash_bond' => 0,
			'cash_bond_limit_reached' => false,
			'other_deductions' => 0,
			'total_deductions' => 0,
			'net_pay' => 0,
			'hourly_rate' => 0,
			'daily_rate' => 0,
			'total_hours_worked' => 0
		];
	}

	/**
	 * Compatibility method to support existing code
	 * @deprecated Use calculatePayroll() instead
	 */
	public function calculatePayrollForGuard($userId, $month = null, $year = null, $startDate = null, $endDate = null) {
		// Ignore month/year parameters and use start/end dates
		if ($startDate && $endDate) {
			return $this->calculatePayroll($userId, $startDate, $endDate);
		}

		// If no start/end dates but month/year provided, calculate dates
		if ($month && $year) {
			// Format dates for the period
			$startDate = "$year-$month-01";
			$endDate = date('Y-m-t', strtotime("$year-$month-01"));
			return $this->calculatePayroll($userId, $startDate, $endDate);
		}

		// Default to current month
		$currentMonth = date('m');
		$currentYear = date('Y');
		$startDate = "$currentYear-$currentMonth-01";
		$endDate = date('Y-m-t', strtotime($startDate));
		return $this->calculatePayroll($userId, $startDate, $endDate);
	}
}
