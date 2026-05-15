<?php
/**
 * Unified Payroll Calculator
 * 
 * A comprehensive payroll calculation system that handles:
 * - Day and night shift calculations
 * - Holiday pay (based on actual database holidays)
 * - Rest day calculations
 * - Night differential
 * - Overtime calculations
 * - Location-specific rates
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
     * Calculate day shift (6am to 6pm)
     * - Regular time: 6am to 2pm (8 hours)
     * - Overtime: 2pm to 6pm (4 hours with OT)
     */
    private function calculateDayShift($timeIn, $timeOut, $hourlyRate, $isRestDay, $holidayType, &$result) {
        $date = $timeIn->format('Y-m-d');

        // Company rule: Straight 12-hour morning shift pays
        // Regular 8 hours = Daily Rate
        // Overtime 4 hours = (Hourly × 1.25) × 4
        // No night differential for day shift segment

        // Holiday override: Special Non-Working — fixed multipliers regardless of shift
        if ($holidayType === 'Special Non-Working') {
            $dailyRate = $hourlyRate * 8;
            // Special holiday regular: Daily × 1.30
            $result['special_holiday_hours'] += 8;
            $result['special_holiday_pay'] += $dailyRate * 1.30;
            // Special holiday OT: Hourly × 1.30 (holiday) × 1.30 (holiday OT) × 4 hours
            $result['special_holiday_ot_hours'] += 4;
            $result['special_holiday_ot_pay'] += ($hourlyRate * 1.30 * 1.30) * 4;
            return; // Do not add regular/OT when special holiday applies
        }

        // Holiday override: Legal Holiday — fixed multipliers regardless of shift
        if ($holidayType === 'Legal Holiday' || $holidayType === 'Regular') {
            $dailyRate = $hourlyRate * 8;
            // Legal holiday regular: Daily × 2.0
            $result['legal_holiday_hours'] += 8;
            $result['legal_holiday_pay'] += $dailyRate * 2.0;
            // Legal holiday OT: Hourly × 2.0 (holiday) × 1.3 (holiday OT) × 4 hours
            $result['holiday_ot_hours'] += 4;
            $result['holiday_ot_pay'] += ($hourlyRate * 2.0 * 1.3) * 4;
            return; // Do not add regular/OT when legal holiday applies
        }

        $dailyRate = $hourlyRate * 8;
        // Regular pay: full daily rate
        $result['regular_hours'] += 8;
        $result['regular_hours_pay'] += $dailyRate;

        // OT pay: 4 hours at 1.25x hourly
        $result['ot_hours'] += 4;
        $result['ot_pay'] += ($hourlyRate * 1.25) * 4;
    }
    
    /**
     * Calculate night shift (6pm to 6am)
     * - Regular hours: 6pm to 10pm (4 hours)
     * - Night differential: 10pm to 2am (4 hours with night diff)
     * - Night differential + OT: 2am to 6am (4 hours with night diff and OT)
     */
    private function calculateNightShift($timeIn, $timeOut, $hourlyRate, $isRestDay, $holidayType, &$result) {
        $date = $timeIn->format('Y-m-d');
        
        // Debug for July 22-23 night shift
        if ($date == '2025-07-22') {
            error_log("=== NIGHT SHIFT DEBUG July 22-23 ===");
            error_log("Time In: " . $timeIn->format('Y-m-d H:i:s'));
            error_log("Time Out: " . $timeOut->format('Y-m-d H:i:s'));
            error_log("Hourly Rate: " . $hourlyRate);
            error_log("Holiday Type: " . ($holidayType ?: 'None'));
        }
        
        // Holiday override: Special Non-Working — fixed multipliers regardless of shift
        if ($holidayType === 'Special Non-Working') {
            $dailyRate = $hourlyRate * 8;
            // Special holiday regular: Daily × 1.30
            $result['special_holiday_hours'] += 8;
            $result['special_holiday_pay'] += $dailyRate * 1.30;
            // Special holiday OT: Hourly × 1.30 (holiday) × 1.30 (holiday OT) × 4 hours
            $result['special_holiday_ot_hours'] += 4;
            $result['special_holiday_ot_pay'] += ($hourlyRate * 1.30 * 1.30) * 4;
            return; // Do not add regular/ND/OT when special holiday applies
        }

        // Holiday override: Legal Holiday — fixed multipliers regardless of shift
        if ($holidayType === 'Legal Holiday' || $holidayType === 'Regular') {
            $dailyRate = $hourlyRate * 8;
            // Legal holiday regular: Daily × 2.0
            $result['legal_holiday_hours'] += 8;
            $result['legal_holiday_pay'] += $dailyRate * 2.0;
            // Legal holiday OT: Hourly × 2.0 (holiday) × 1.3 (holiday OT) × 4 hours
            $result['holiday_ot_hours'] += 4;
            $result['holiday_ot_pay'] += ($hourlyRate * 2.0 * 1.3) * 4;
            return; // Do not add regular/ND/OT when legal holiday applies
        }

        // Company rule: Straight 12-hour night shift pays (non-holiday)
        // NS = Daily Rate + (10% of Daily Rate) + 4 hrs OT
        $dailyRate = $hourlyRate * 8;

        // Regular pay: always 8 hours
        $result['regular_hours'] += 8;
        $result['regular_hours_pay'] += $dailyRate;

        // Night differential: flat 10% of daily rate (no stacking with OT)
        $result['night_diff_hours'] += 8; // represent ND segment hours (10pm–6am)
        $result['night_diff_pay'] += $dailyRate * 0.10;

        // OT: fixed 4 hours at 1.25x hourly
        $result['ot_hours'] += 4;
        $result['ot_pay'] += ($hourlyRate * 1.25) * 4;
    }
    
    /**
     * Apply holiday pay with proper multipliers
     */
    private function applyHolidayPay($holidayType, $hours, $hourlyRate, $isRestDay, $isNightShift, &$result) {
        $amount = $hours * $hourlyRate;
        $multiplier = 1.0;
        
        switch ($holidayType) {
            case 'Regular':
                if ($isRestDay && $isNightShift) {
                    $multiplier = 2.86; // Regular holiday, rest day, night shift = 2.6 × 1.1 = 2.86
                } elseif ($isRestDay) {
                    $multiplier = 2.6;  // Regular holiday on rest day = 2.6
                } elseif ($isNightShift) {
                    $multiplier = 2.2;  // Regular holiday, night shift = 2 × 1.1 = 2.2
                } else {
                    $multiplier = 2.0;  // Regular holiday = 2.0
                }
                $result['legal_holiday_pay'] += $amount * $multiplier;
                $result['legal_holiday_hours'] += $hours;
                break;
                
            case 'Special Non-Working':
                if ($isRestDay && $isNightShift) {
                    $multiplier = 1.65; // Special non-working, rest day, night shift = 1.5 × 1.1 = 1.65
                } elseif ($isRestDay) {
                    $multiplier = 1.5;  // Special non-working on rest day = 1.5
                } elseif ($isNightShift) {
                    $multiplier = 1.43; // Special non-working, night shift = 1.3 × 1.1 = 1.43
                } else {
                    $multiplier = 1.3;  // Special non-working = 1.3
                }
                $result['special_holiday_pay'] += $amount * $multiplier;
                $result['special_holiday_hours'] += $hours;
                break;
                
            case 'Special Working':
                if ($isRestDay && $isNightShift) {
                    $multiplier = 1.43; // Rest day, night shift = 1.3 × 1.1 = 1.43
                } elseif ($isRestDay) {
                    $multiplier = 1.3;  // Rest day = 1.3
                } elseif ($isNightShift) {
                    $multiplier = 1.1;  // Night shift = 1.1
                } else {
                    $multiplier = 1.0;  // Regular day = 1.0
                }
                $result['regular_hours_pay'] += $amount * $multiplier; // Special working days use regular pay category
                $result['regular_hours'] += $hours;
                break;
                
            case 'Double Holiday':
                if ($isRestDay && $isNightShift) {
                    $multiplier = 4.29; // Double holiday, rest day, night shift = 3.9 × 1.1 = 4.29
                } elseif ($isRestDay) {
                    $multiplier = 3.9;  // Double holiday on rest day = 3.9
                } elseif ($isNightShift) {
                    $multiplier = 3.3;  // Double holiday, night shift = 3 × 1.1 = 3.3
                } else {
                    $multiplier = 3.0;  // Double holiday = 3.0
                }
                $result['legal_holiday_pay'] += $amount * $multiplier; // Double holidays go into legal holidays category
                $result['legal_holiday_hours'] += $hours;
                break;
                
            case 'Double Special Non-Working':
                if ($isRestDay && $isNightShift) {
                    $multiplier = 2.145; // Double special, rest day, night shift = 1.95 × 1.1 = 2.145
                } elseif ($isRestDay) {
                    $multiplier = 1.95;  // Double special on rest day = 1.95
                } elseif ($isNightShift) {
                    $multiplier = 1.65;  // Double special, night shift = 1.5 × 1.1 = 1.65
                } else {
                    $multiplier = 1.5;   // Double special = 1.5
                }
                $result['special_holiday_pay'] += $amount * $multiplier;
                $result['special_holiday_hours'] += $hours;
                break;
        }
            if ($isNightShift) { $result['night_diff_hours'] += $hours; }
    }
    
    /**
     * Apply holiday overtime pay with proper multipliers
     */
    private function applyHolidayOvertimePay($holidayType, $hours, $hourlyRate, $isRestDay, $isNightShift, &$result) {
        $amount = $hours * $hourlyRate;
        $multiplier = 1.0;
        
        switch ($holidayType) {
            case 'Regular':
                if ($isRestDay && $isNightShift) {
                    $multiplier = 3.575; // Regular holiday, rest day, night shift, OT = 2.6 × 1.1 × 1.25 = 3.575
                } elseif ($isRestDay) {
                    $multiplier = 3.25; // Regular holiday, rest day, OT = 2.6 × 1.25 = 3.25
                } elseif ($isNightShift) {
                    $multiplier = 2.75; // Regular holiday, night shift, OT = 2 × 1.1 × 1.25 = 2.75
                } else {
                    $multiplier = 2.5;  // Regular holiday, OT = 2 × 1.25 = 2.5
                }
                $result['holiday_ot_pay'] += $amount * $multiplier;
                $result['holiday_ot_hours'] += $hours;
                break;
                
            case 'Special Non-Working':
                if ($isRestDay && $isNightShift) {
                    $multiplier = 2.0625; // Special, rest day, night shift, OT = 1.5 × 1.1 × 1.25 = 2.0625
                } elseif ($isRestDay) {
                    $multiplier = 1.875; // Special, rest day, OT = 1.5 × 1.25 = 1.875
                } elseif ($isNightShift) {
                    $multiplier = 1.7875; // Special, night shift, OT = 1.3 × 1.1 × 1.25 = 1.7875
                } else {
                    $multiplier = 1.625; // Special, OT = 1.3 × 1.25 = 1.625
                }
                $result['special_holiday_ot_pay'] += $amount * $multiplier;
                $result['special_holiday_ot_hours'] += $hours;
                break;
                
            case 'Special Working':
                if ($isRestDay && $isNightShift) {
                    $multiplier = 1.7875; // Rest day, night shift, OT = 1.3 × 1.1 × 1.25 = 1.7875
                } elseif ($isRestDay) {
                    $multiplier = 1.625; // Rest day, OT = 1.3 × 1.25 = 1.625
                } elseif ($isNightShift) {
                    $multiplier = 1.375; // Night shift, OT = 1.0 × 1.1 × 1.25 = 1.375
                } else {
                    $multiplier = 1.25;  // OT = 1.25
                }
                $result['ot_pay'] += $amount * $multiplier;
                $result['ot_hours'] += $hours;
                break;
                
            case 'Double Holiday':
                if ($isRestDay && $isNightShift) {
                    $multiplier = 5.3625; // Double, rest day, night shift, OT = 3.9 × 1.1 × 1.25 = 5.3625
                } elseif ($isRestDay) {
                    $multiplier = 4.875; // Double, rest day, OT = 3.9 × 1.25 = 4.875
                } elseif ($isNightShift) {
                    $multiplier = 4.125; // Double, night shift, OT = 3 × 1.1 × 1.25 = 4.125
                } else {
                    $multiplier = 3.75;  // Double, OT = 3 × 1.25 = 3.75
                }
                $result['holiday_ot_pay'] += $amount * $multiplier;
                $result['holiday_ot_hours'] += $hours;
                break;
                
            case 'Double Special Non-Working':
                if ($isRestDay && $isNightShift) {
                    $multiplier = 2.6875; // Double special, rest day, night shift, OT = 1.95 × 1.1 × 1.25 = 2.6875
                } elseif ($isRestDay) {
                    $multiplier = 2.4375; // Double special, rest day, OT = 1.95 × 1.25 = 2.4375
                } elseif ($isNightShift) {
                    $multiplier = 2.0625; // Double special, night shift, OT = 1.5 × 1.1 × 1.25 = 2.0625
                } else {
                    $multiplier = 1.875; // Double special, OT = 1.5 × 1.25 = 1.875
                }
                $result['special_holiday_ot_pay'] += $amount * $multiplier;
                $result['special_holiday_ot_hours'] += $hours;
                break;
        }
            if ($isNightShift) { $result['night_diff_hours'] += $hours; }
    }
    
    /**
     * Get guard's location and rate
     */
    private function getEmployeeLocationRate($employeeId) {
        try {
            $sql = "SELECT lr.location_name, lr.daily_rate
                    FROM employees e
                    JOIN location_rate lr ON lr.location_id = e.location_id
                    WHERE e.employee_id = ?
                    LIMIT 1";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$employeeId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: ['daily_rate' => $this->defaultRate];
        } catch (Throwable $e) {
            return ['daily_rate' => $this->defaultRate];
        }
    }
    
    /**
     * Get attendance records for a user in a date range
     */
    private function getAttendanceRecords($employeeId, $startDate, $endDate) {
        $sql = "SELECT work_date, time_in, time_out
                FROM attendance
                WHERE employee_id = ?
                AND work_date BETWEEN ? AND ?
                AND time_in IS NOT NULL
                AND time_out IS NOT NULL
                ORDER BY work_date ASC, time_in ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$employeeId, $startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get the holiday type for a specific date
     */
    private function getHolidayClass($date) {
        if (isset($this->holidayCache[$date])) {
            return $this->holidayCache[$date];
        }

        $sql = "SELECT holiday_type FROM holidays WHERE holiday_date = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$date]);
        $types = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $types = array_values(array_filter(array_map('strtolower', array_map('strval', $types))));
        $holidayClass = null;

        if (!empty($types)) {
            $hasRegular = in_array('regular', $types, true);
            $hasSpecial = in_array('special', $types, true);
            $hasCompany = in_array('company', $types, true);

            if ($hasRegular && $hasSpecial) {
                $holidayClass = 'double_holiday';
            } elseif (count($types) > 1 && !$hasRegular && $hasSpecial) {
                $holidayClass = 'double_special';
            } elseif ($hasRegular) {
                $holidayClass = 'regular';
            } elseif ($hasSpecial) {
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

        // Saved/entered deductions (stored in payroll_deductions once a payroll row exists)
        $payrollId = $this->getPayrollId($employeeId, $startDate, $endDate);
        if ($payrollId) {
            $result['cash_advance'] = $this->getDeductionAmount($payrollId, 'CASH_ADVANCES');
            $result['cash_bond'] = $this->getDeductionAmount($payrollId, 'CASH_BOND');
            $result['sss_loan'] = $this->getDeductionAmount($payrollId, 'SSS_LOAN');
            $result['pagibig_loan'] = $this->getDeductionAmount($payrollId, 'PAGIBIG_LOAN');
            $result['other_deductions'] = $this->getDeductionAmount($payrollId, 'OTHERS');
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
     * Get cash advance for a period
     */
    private function getPayrollId(int $employeeId, string $startDate, string $endDate): ?int
    {
        $sql = "SELECT payroll_id FROM payroll WHERE employee_id = ? AND period_start = ? AND period_end = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$employeeId, $startDate, $endDate]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    private function getDeductionAmount(int $payrollId, string $type): float
    {
        $sql = "SELECT COALESCE(SUM(amount), 0) FROM payroll_deductions WHERE payroll_id = ? AND deduction_type = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$payrollId, $type]);
        return (float)$stmt->fetchColumn();
    }
    
    /**
     * Calculate cash bond with limits
     */
    // Legacy cash bond limit logic relied on guard_settings/payroll columns that aren't in the current schema.
    // Cash bond is now read from payroll_deductions when available.
    
    /**
     * Calculate SSS deduction based on monthly compensation table
     */
    private function calculateSSSFromTable(float $monthlyCompensation, string $asOf, bool $isHalf): float {
        try {
            $sql = "SELECT employee_contribution
                    FROM sss_bracket
                    WHERE ? BETWEEN lower_limit AND upper_limit
                    AND effective_from <= ?
                    AND (effective_to IS NULL OR effective_to >= ?)
                    ORDER BY effective_from DESC
                    LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$monthlyCompensation, $asOf, $asOf]);
            $val = $stmt->fetchColumn();
            $monthly = $val !== false ? (float)$val : 0.0;
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
            $sql = "SELECT monthly_rate, employee_share, salary_floor, salary_ceiling
                    FROM philhealth_contribution
                    WHERE effective_from <= ?
                    AND (effective_to IS NULL OR effective_to >= ?)
                    ORDER BY effective_from DESC
                    LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$asOf, $asOf]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
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
            $sql = "SELECT employee_rate, salary_ceiling
                    FROM pagibig_contribution
                    WHERE ? BETWEEN salary_min AND salary_max
                    AND effective_from <= ?
                    AND (effective_to IS NULL OR effective_to >= ?)
                    ORDER BY effective_from DESC
                    LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$monthlyGross, $asOf, $asOf]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
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
    
    // Add this method to the PayrollCalculator class

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

// Usage example:
// require_once '../db_connection.php';
// $calculator = new PayrollCalculator($conn);
// $result = $calculator->calculatePayroll(123, '2025-06-01', '2025-06-15');