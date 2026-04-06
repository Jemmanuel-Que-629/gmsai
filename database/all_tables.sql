CREATE TABLE `users` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `first_name` VARCHAR(255) NOT NULL,
  `middle_name` VARCHAR(255),
  `last_name` VARCHAR(255) NOT NULL,
  `profile_picture` VARCHAR(255),
  `role_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`role_id`) ON DELETE CASCADE
);

CREATE TABLE `roles` (
  `role_id` INT NOT NULL AUTO_INCREMENT,
  `role_name` enum('HR', 'Accounting') NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`)
);

CREATE TABLE `employees` (
  `employee_id` INT NOT NULL AUTO_INCREMENT,
  `position` VARCHAR(255) NOT NULL,
  `department` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`employee_id`),
);

/* To be created when I asked the HR for applicant requirements. */
CREATE TABLE `applicants` (
  `applicant_id` INT NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(255) NOT NULL,
  `middle_name` VARCHAR(255),
  `last_name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone_number` VARCHAR(20) NOT NULL,
  `position_applied` VARCHAR(255) NOT NULL,
  `resume` VARCHAR(255) NOT NULL,
  `2x2` VARCHAR(255) NOT NULL,
  `full_body_photo` VARCHAR(255) NOT NULL,
  `license_registration` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`applicant_id`)
);

CREATE TABLE `sss_bracket`{
    `bracket_id` INT NOT NULL AUTO_INCREMENT,
    `lower_limit` DECIMAL(10,2) NOT NULL,
    `upper_limit` DECIMAL(10,2) NOT NULL,
    `employee_contribution` DECIMAL(10,2) NOT NULL,
    `employer_contribution` DECIMAL(10,2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`bracket_id`)
}

CREATE TABLE `philhealth_bracket`{
    `bracket_id` INT NOT NULL AUTO_INCREMENT,
    `lower_limit` DECIMAL(10,2) NOT NULL,
    `upper_limit` DECIMAL(10,2) NOT NULL,
    `employee_contribution` DECIMAL(10,2) NOT NULL,
    `employer_contribution` DECIMAL(10,2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`bracket_id`)
}

CREATE TABLE `pagibig_bracket`{
    `bracket_id` INT NOT NULL AUTO_INCREMENT,
    `lower_limit` DECIMAL(10,2) NOT NULL,
    `upper_limit` DECIMAL(10,2) NOT NULL,
    `employee_contribution` DECIMAL(10,2) NOT NULL,
    `employer_contribution` DECIMAL(10,2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`bracket_id`)
}

CREATE TABLE `holidays` (
    `holiday_id` INT PRIMARY KEY AUTO_INCREMENT,
    `holiday_date` DATE NOT NULL,
    `holiday_name` VARCHAR(100) NOT NULL,
    `holiday_type` ENUM('Regular', 'Special Non-Working', 'Company Event') NOT NULL,
    `is_recurring` TINYINT(1) DEFAULT 0, -- 1 for fixed dates like Dec 25, 0 for moving dates like Holy Week
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `payroll` (
  `payroll_id` INT NOT NULL AUTO_INCREMENT,
  `employee_id` INT NOT NULL,

  `allowances` DECIMAL(10,2) NOT NULL,
  `sss_deduction` DECIMAL(10,2) NOT NULL,
  `philhealth_deduction` DECIMAL(10,2) NOT NULL,
  `pagibig_deduction` DECIMAL(10,2) NOT NULL,
  `cash_advances` DECIMAL(10,2) NOT NULL,
  `cash_bond` DECIMAL(10,2) NOT NULL,  
  `net_pay` DECIMAL(10,2) NOT NULL,
  `pay_date` DATE NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`payroll_id`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`employee_id`) ON DELETE CASCADE
);

CREATE TABLE `payroll_rates` (
    `rate_id` INT NOT NULL AUTO_INCREMENT,
    `rate_name` VARCHAR(255) NOT NULL,
    `rate_value` DECIMAL(10,2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`rate_id`)
)