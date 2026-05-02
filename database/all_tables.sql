🔵 1. ROLES (system access only)
CREATE TABLE roles (
  role_id INT AUTO_INCREMENT PRIMARY KEY,
  role_name VARCHAR(50) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

🔵 2. USERS (system users only)
CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  middle_name VARCHAR(100),
  last_name VARCHAR(100) NOT NULL,
  recovery_email VARCHAR(255),
  name_extension VARCHAR(10),
  profile_picture VARCHAR(255),
  role_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE
);

CREATE TABLE login_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    login_time DATETIME,
    logout_time DATETIME,
    ip_address VARCHAR(45),
    browser	varchar(100),
    platform	varchar(100),
    user_agent TEXT,

    CONSTRAINT fk_login_logs_user
    FOREIGN KEY (user_id)
    REFERENCES users(user_id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE TABLE activity_logs (
    activity_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255),
    description TEXT,
    created_at DATETIME,

    CONSTRAINT fk_activity_logs_user
    FOREIGN KEY (user_id)
    REFERENCES users(user_id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

🔵 4. LOCATIONS (salary basis)
CREATE TABLE location_rate (
  location_id INT AUTO_INCREMENT PRIMARY KEY,
  location_name VARCHAR(255) NOT NULL,
  daily_rate DECIMAL(10,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

🔵 5. EMPLOYEES (FIXED)
CREATE TABLE employees (
  employee_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  first_name VARCHAR(100) NOT NULL,
  middle_name VARCHAR(100),
  last_name VARCHAR(100) NOT NULL,
  position VARCHAR(100) NOT NULL,
  department VARCHAR(100) NOT NULL,
  location_id INT NOT NULL,
  salary_type ENUM('daily','monthly') DEFAULT 'daily',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
  FOREIGN KEY (location_id) REFERENCES location_rate(location_id) ON DELETE CASCADE
);

🔵 6. APPLICANTS (fixed naming)
CREATE TABLE applicants (
  applicant_id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) NOT NULL,
  middle_name VARCHAR(100),
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL,
  phone_number VARCHAR(20) NOT NULL,
  position_applied VARCHAR(100) NOT NULL,
  resume VARCHAR(255) NOT NULL,
  photo_2x2 VARCHAR(255) NOT NULL,
  full_body_photo VARCHAR(255) NOT NULL,
  license_registration VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

🔵 7. ATTENDANCE (ADDED – REQUIRED)
CREATE TABLE attendance (
  attendance_id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  work_date DATE NOT NULL,
  time_in TIME,
  time_out TIME,

  FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
);

🔵 8. HOLIDAYS
CREATE TABLE holidays (
  holiday_id INT AUTO_INCREMENT PRIMARY KEY,
  holiday_date DATE NOT NULL,
  holiday_name VARCHAR(100) NOT NULL,
  holiday_type ENUM('regular','special','company') NOT NULL,
  is_recurring TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

🔵 9. PAYROLL RATES (flexible)
CREATE TABLE payroll_rates (
  rate_id INT AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(50) NOT NULL,
  rate_multiplier DECIMAL(5,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

🔵 10. PAYROLL (CLEANED)
CREATE TABLE payroll (
  payroll_id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  total_hours DECIMAL(5,2),
  gross_pay DECIMAL(10,2),
  total_deductions DECIMAL(10,2),
  net_pay DECIMAL(10,2),
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(user_id)
);

🔴 11. PAYROLL DETAILS (CORE ENGINE)
CREATE TABLE payroll_details (
  detail_id INT AUTO_INCREMENT PRIMARY KEY,
  payroll_id INT NOT NULL,
  work_date DATE NOT NULL,
  type VARCHAR(50) NOT NULL,
  hours DECIMAL(5,2) NOT NULL,
  rate_multiplier DECIMAL(5,2) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,

  FOREIGN KEY (payroll_id) REFERENCES payroll(payroll_id) ON DELETE CASCADE
);

🔵 12. PAYROLL DEDUCTIONS (normalized)
CREATE TABLE payroll_deductions (
  deduction_id INT AUTO_INCREMENT PRIMARY KEY,
  payroll_id INT NOT NULL,
  deduction_type VARCHAR(50) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  FOREIGN KEY (payroll_id) REFERENCES payroll(payroll_id) ON DELETE CASCADE
);

🔵 13. GOVERNMENT TABLES (cleaned)

SSS
CREATE TABLE sss_bracket (
  sss_id INT AUTO_INCREMENT PRIMARY KEY,
  lower_limit DECIMAL(10,2),
  upper_limit DECIMAL(10,2),
  employee_contribution DECIMAL(10,2),
  employer_contribution DECIMAL(10,2)
);

PhilHealth
CREATE TABLE philhealth_bracket (
  philhealth_id INT AUTO_INCREMENT PRIMARY KEY,
  lower_limit DECIMAL(10,2),
  upper_limit DECIMAL(10,2),
  employee_contribution DECIMAL(10,2),
  employer_contribution DECIMAL(10,2)
);

Pag-IBIG
CREATE TABLE pagibig_bracket (
  pagibig_id INT AUTO_INCREMENT PRIMARY KEY,
  lower_limit DECIMAL(10,2),
  upper_limit DECIMAL(10,2),
  employee_contribution DECIMAL(10,2),
  employer_contribution DECIMAL(10,2)
);