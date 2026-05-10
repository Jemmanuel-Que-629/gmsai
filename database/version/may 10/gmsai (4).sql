-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 10, 2026 at 10:11 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `gmsai`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `activity_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `work_date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`attendance_id`, `employee_id`, `work_date`, `time_in`, `time_out`) VALUES
(1, 1, '2026-05-06', '08:00:00', '17:00:00'),
(2, 2, '2026-05-06', '08:10:00', '17:00:00'),
(3, 3, '2026-05-06', '07:58:00', NULL),
(4, 4, '2026-05-06', '08:05:00', '17:00:00'),
(5, 5, '2026-05-06', '08:00:00', '16:00:00'),
(6, 6, '2026-05-06', NULL, NULL),
(7, 7, '2026-05-06', '08:00:00', '17:00:00'),
(8, 8, '2026-05-06', '08:00:00', '17:00:00'),
(9, 9, '2026-05-06', '09:00:00', '18:00:00'),
(10, 10, '2026-05-06', '08:00:00', '17:00:00'),
(11, 1, '2026-05-07', '08:00:00', '17:00:00'),
(12, 2, '2026-05-07', '08:00:00', '17:00:00'),
(13, 3, '2026-05-07', '08:00:00', '17:00:00'),
(14, 4, '2026-05-07', '08:00:00', '07:00:00'),
(15, 5, '2026-05-07', '08:00:00', '17:00:00'),
(16, 6, '2026-05-07', '08:00:00', NULL),
(17, 7, '2026-05-07', '08:20:00', '17:00:00'),
(18, 8, '2026-05-07', '08:00:00', '17:00:00'),
(19, 9, '2026-05-07', '08:00:00', '17:00:00'),
(20, 10, '2026-05-07', '08:00:00', '17:00:00'),
(21, 1, '2026-05-08', '08:00:00', '17:00:00'),
(22, 2, '2026-05-08', '08:00:00', '17:00:00'),
(23, 3, '2026-05-08', '08:00:00', '17:00:00'),
(24, 4, '2026-05-08', '08:00:00', '17:00:00'),
(25, 5, '2026-05-08', '08:00:00', '17:00:00'),
(26, 6, '2026-05-08', '08:00:00', '17:00:00'),
(27, 7, '2026-05-08', '08:00:00', '17:00:00'),
(28, 8, '2026-05-08', NULL, NULL),
(29, 9, '2026-05-08', '08:00:00', '17:00:00'),
(30, 10, '2026-05-08', '08:00:00', '17:00:00'),
(31, 1, '2026-05-09', '08:00:00', '17:00:00'),
(32, 2, '2026-05-09', '08:00:00', '17:00:00'),
(33, 3, '2026-05-09', '08:00:00', NULL),
(34, 4, '2026-05-09', '08:00:00', '17:00:00'),
(35, 5, '2026-05-09', '08:00:00', '17:00:00'),
(36, 6, '2026-05-09', '08:00:00', '17:00:00'),
(37, 7, '2026-05-09', '08:00:00', '17:00:00'),
(38, 8, '2026-05-09', '08:00:00', '17:00:00'),
(39, 9, '2026-05-09', '08:00:00', '17:00:00'),
(40, 10, '2026-05-09', '08:00:00', '17:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `employee_id` int(11) NOT NULL,
  `employee_num_id` varchar(50) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `position` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `location_id` int(11) NOT NULL,
  `salary_type` enum('daily','weekly','bi-weekly','semi-monthly','monthly') DEFAULT 'semi-monthly',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_archived` tinyint(1) DEFAULT 0,
  `archived_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`employee_id`, `employee_num_id`, `user_id`, `first_name`, `middle_name`, `last_name`, `position`, `department`, `location_id`, `salary_type`, `created_at`, `updated_at`, `is_archived`, `archived_at`) VALUES
(1, 'SEC-001', NULL, 'Juan', 'Pineda', 'Dela Cruz', 'Security Guard', 'Security', 10, 'semi-monthly', '2026-05-09 09:37:48', '2026-05-09 10:04:35', 0, NULL),
(2, 'ADM-002', NULL, 'Maria Clara', 'Santos', 'Reyes', 'Accounting Clerk', 'Finance', 10, 'semi-monthly', '2026-05-09 09:37:48', '2026-05-09 10:04:35', 0, NULL),
(3, 'SEC-003', NULL, 'Ricardo', 'de Sena', 'Dalisay', 'Detachment Commander', 'Security', 9, 'semi-monthly', '2026-05-09 09:37:48', '2026-05-09 10:04:35', 0, NULL),
(4, 'OPS-004', NULL, 'Stephen Ian', 'Lopez', 'Veneracion', 'IT Administrator', 'Operations', 5, 'semi-monthly', '2026-05-09 09:37:48', '2026-05-09 10:04:35', 0, NULL),
(5, 'FAC-005', NULL, 'Elena', 'Bautista', 'Mercado', 'Maintenance Staff', 'Facilities', 6, 'daily', '2026-05-09 09:37:48', '2026-05-09 10:04:35', 0, NULL),
(6, 'SEC-006', NULL, 'Antonio', 'Luna', 'Agoncillo', 'Roving Guard', 'Security', 7, 'daily', '2026-05-09 09:37:48', '2026-05-09 10:04:35', 0, NULL),
(7, 'FAC-007', NULL, 'Sisa', 'Bernardo', 'Basilio', 'Janitress', 'Facilities', 4, 'daily', '2026-05-09 09:37:48', '2026-05-09 10:04:35', 0, NULL),
(8, 'ADM-008', NULL, 'Crisostomo', 'Magsaysay', 'Ibarra', 'Payroll Specialist', 'HR', 10, 'semi-monthly', '2026-05-09 09:37:48', '2026-05-09 10:04:35', 0, NULL),
(9, 'FAC-009', NULL, 'Gabriela', 'Silang', 'Estrada', 'Head Nurse', 'Medical', 8, 'semi-monthly', '2026-05-09 09:37:48', '2026-05-09 10:04:35', 0, NULL),
(10, 'FAC-010', NULL, 'Emilio', 'Jacinto', 'Aguinaldo', 'Utility Worker', 'Facilities', 1, 'daily', '2026-05-09 09:37:48', '2026-05-09 10:04:35', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `holiday_id` int(11) NOT NULL,
  `holiday_date` date NOT NULL,
  `holiday_name` varchar(100) NOT NULL,
  `holiday_type` enum('regular','special_non_working','special_working','company') NOT NULL,
  `payroll_rate_id` int(11) DEFAULT NULL,
  `is_paid` tinyint(1) DEFAULT 1,
  `is_recurring` tinyint(1) DEFAULT 0,
  `applicable_year` year(4) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `holidays`
--

INSERT INTO `holidays` (`holiday_id`, `holiday_date`, `holiday_name`, `holiday_type`, `payroll_rate_id`, `is_paid`, `is_recurring`, `applicable_year`, `created_at`, `updated_at`) VALUES
(1, '2026-01-01', 'New Year\'s Day', 'regular', 1, 1, 1, '2026', '2026-05-10 07:51:54', '2026-05-10 07:51:54'),
(2, '2026-03-20', 'Eid\'l Fitr', 'regular', 1, 1, 0, '2026', '2026-05-10 07:51:54', '2026-05-10 07:51:54'),
(3, '2026-04-02', 'Maundy Thursday', 'regular', 1, 1, 0, '2026', '2026-05-10 07:51:54', '2026-05-10 07:51:54'),
(4, '2026-04-03', 'Good Friday', 'regular', 1, 1, 0, '2026', '2026-05-10 07:51:54', '2026-05-10 07:51:54'),
(5, '2026-04-09', 'Araw ng Kagitingan', 'regular', 1, 1, 1, '2026', '2026-05-10 07:51:54', '2026-05-10 07:51:54'),
(6, '2026-05-01', 'Labor Day', 'regular', 1, 1, 1, '2026', '2026-05-10 07:51:54', '2026-05-10 07:51:54'),
(7, '2026-06-12', 'Independence Day', 'regular', 1, 1, 1, '2026', '2026-05-10 07:51:54', '2026-05-10 07:51:54'),
(8, '2026-08-31', 'National Heroes Day', 'regular', 1, 1, 1, '2026', '2026-05-10 07:51:54', '2026-05-10 07:51:54'),
(9, '2026-11-30', 'Bonifacio Day', 'regular', 1, 1, 1, '2026', '2026-05-10 07:51:54', '2026-05-10 07:51:54'),
(10, '2026-12-25', 'Christmas Day', 'regular', 1, 1, 1, '2026', '2026-05-10 07:51:54', '2026-05-10 07:51:54'),
(11, '2026-12-30', 'Rizal Day', 'regular', 1, 1, 1, '2026', '2026-05-10 07:51:54', '2026-05-10 07:51:54'),
(12, '2026-02-17', 'Chinese New Year', 'special_non_working', 2, 0, 0, '2026', '2026-05-10 07:51:54', '2026-05-10 07:51:54'),
(13, '2026-04-04', 'Black Saturday', 'special_non_working', 2, 0, 0, '2026', '2026-05-10 07:51:54', '2026-05-10 07:51:54'),
(14, '2026-08-21', 'Ninoy Aquino Day', 'special_non_working', 2, 1, 1, '2026', '2026-05-10 07:51:54', '2026-05-10 07:51:54'),
(15, '2026-11-01', 'All Saints\' Day', 'special_non_working', 2, 0, 1, '2026', '2026-05-10 07:51:54', '2026-05-10 07:51:54'),
(16, '2026-11-02', 'All Souls\' Day', 'special_non_working', 2, 0, 0, '2026', '2026-05-10 07:51:54', '2026-05-10 07:51:54'),
(17, '2026-12-08', 'Feast of the Immaculate Conception', 'special_non_working', 2, 1, 1, '2026', '2026-05-10 07:51:54', '2026-05-10 07:51:54'),
(18, '2026-12-24', 'Christmas Eve', 'special_non_working', 2, 0, 1, '2026', '2026-05-10 07:51:54', '2026-05-10 07:51:54'),
(19, '2026-12-31', 'Last Day of the Year', 'special_non_working', 2, 0, 1, '2026', '2026-05-10 07:51:54', '2026-05-10 07:51:54'),
(20, '2026-02-25', 'EDSA People Power Revolution Anniversary', 'special_working', 3, 1, 1, '2026', '2026-05-10 07:51:54', '2026-05-10 07:51:54');

-- --------------------------------------------------------

--
-- Table structure for table `location_rate`
--

CREATE TABLE `location_rate` (
  `location_id` int(11) NOT NULL,
  `location_name` varchar(255) NOT NULL,
  `daily_rate` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `location_rate`
--

INSERT INTO `location_rate` (`location_id`, `location_name`, `daily_rate`, `created_at`, `updated_at`) VALUES
(1, 'Naga', 415.00, '2026-05-09 09:29:45', '2026-05-09 09:29:45'),
(2, 'Pangasinan', 435.00, '2026-05-09 09:29:45', '2026-05-09 09:29:45'),
(3, 'Bulacan', 525.00, '2026-05-09 09:29:45', '2026-05-09 09:29:45'),
(4, 'Pampanga', 540.00, '2026-05-09 09:29:45', '2026-05-09 09:29:45'),
(5, 'Laguna', 540.00, '2026-05-09 09:29:45', '2026-05-09 09:29:45'),
(6, 'Cavite', 540.00, '2026-05-09 09:29:45', '2026-05-09 09:29:45'),
(7, 'Biñan', 540.00, '2026-05-09 09:29:45', '2026-05-09 09:29:45'),
(8, 'Batangas', 540.00, '2026-05-09 09:29:45', '2026-05-09 09:29:45'),
(9, 'San Pedro', 560.00, '2026-05-09 09:29:45', '2026-05-09 09:29:45'),
(10, 'Manila', 645.00, '2026-05-09 09:29:45', '2026-05-09 09:29:45');

-- --------------------------------------------------------

--
-- Table structure for table `login_logs`
--

CREATE TABLE `login_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `login_time` datetime DEFAULT current_timestamp(),
  `logout_time` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `browser` varchar(100) DEFAULT NULL,
  `platform` varchar(100) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_logs`
--

INSERT INTO `login_logs` (`log_id`, `user_id`, `login_time`, `logout_time`, `ip_address`, `browser`, `platform`, `user_agent`) VALUES
(3, 1, '2026-05-03 00:58:41', NULL, '::1', 'Chrome', 'Windows', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(4, 1, '2026-05-03 12:37:37', '2026-05-03 15:53:08', '::1', 'Chrome', 'Windows', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(5, 1, '2026-05-03 15:53:14', '2026-05-03 15:54:41', '::1', 'Chrome', 'Windows', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(6, 2, '2026-05-03 15:54:46', NULL, '::1', 'Chrome', 'Windows', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(7, 2, '2026-05-09 16:55:42', NULL, '::1', 'Chrome', 'Windows', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(8, 2, '2026-05-09 17:43:33', NULL, '::1', 'Chrome', 'Windows', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36');

-- --------------------------------------------------------

--
-- Table structure for table `pagibig_contribution`
--

CREATE TABLE `pagibig_contribution` (
  `pagibig_id` int(11) NOT NULL,
  `salary_min` decimal(10,2) NOT NULL,
  `salary_max` decimal(10,2) NOT NULL,
  `employee_rate` decimal(5,4) NOT NULL,
  `employer_rate` decimal(5,4) NOT NULL,
  `salary_ceiling` decimal(10,2) NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pagibig_contribution`
--

INSERT INTO `pagibig_contribution` (`pagibig_id`, `salary_min`, `salary_max`, `employee_rate`, `employer_rate`, `salary_ceiling`, `effective_from`, `effective_to`, `created_at`) VALUES
(1, 1000.00, 1500.00, 0.0100, 0.0200, 10000.00, '2026-01-01', NULL, '2026-05-03 11:21:50'),
(2, 1500.01, 9999999.99, 0.0200, 0.0200, 10000.00, '2026-01-01', NULL, '2026-05-03 11:21:50');

-- --------------------------------------------------------

--
-- Table structure for table `payroll`
--

CREATE TABLE `payroll` (
  `payroll_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `total_hours` decimal(5,2) DEFAULT NULL,
  `gross_pay` decimal(10,2) DEFAULT NULL,
  `total_deductions` decimal(10,2) DEFAULT NULL,
  `net_pay` decimal(10,2) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_deductions`
--

CREATE TABLE `payroll_deductions` (
  `deduction_id` int(11) NOT NULL,
  `payroll_id` int(11) NOT NULL,
  `deduction_type` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_details`
--

CREATE TABLE `payroll_details` (
  `detail_id` int(11) NOT NULL,
  `payroll_id` int(11) NOT NULL,
  `work_date` date NOT NULL,
  `type` varchar(50) NOT NULL,
  `hours` decimal(5,2) NOT NULL,
  `rate_multiplier` decimal(5,2) NOT NULL,
  `amount` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_rates`
--

CREATE TABLE `payroll_rates` (
  `rate_id` int(11) NOT NULL,
  `rate_code` varchar(50) NOT NULL,
  `rate_name` varchar(100) NOT NULL,
  `worked_multiplier` decimal(5,2) NOT NULL,
  `unworked_multiplier` decimal(5,2) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_rates`
--

INSERT INTO `payroll_rates` (`rate_id`, `rate_code`, `rate_name`, `worked_multiplier`, `unworked_multiplier`, `is_active`, `effective_from`, `effective_to`, `created_at`) VALUES
(1, 'REGULAR_HOLIDAY', 'Regular Holiday', 2.00, 1.00, 1, '2026-01-01', NULL, '2026-05-10 07:51:11'),
(2, 'SPECIAL_NON_WORKING', 'Special Non-Working Holiday', 1.30, 0.00, 1, '2026-01-01', NULL, '2026-05-10 07:51:11'),
(3, 'SPECIAL_WORKING', 'Special Working Holiday', 1.00, 1.00, 1, '2026-01-01', NULL, '2026-05-10 07:51:11'),
(4, 'COMPANY_HOLIDAY', 'Company Holiday', 1.00, 1.00, 1, '2026-01-01', NULL, '2026-05-10 07:51:11'),
(5, 'NIGHT_DIFF', 'Night Differential', 1.10, 1.00, 1, '2026-01-01', NULL, '2026-05-10 07:51:11'),
(6, 'OVERTIME', 'Overtime', 1.25, 1.00, 1, '2026-01-01', NULL, '2026-05-10 07:51:11'),
(7, 'REST_DAY', 'Rest Day', 1.30, 0.00, 1, '2026-01-01', NULL, '2026-05-10 07:51:11');

-- --------------------------------------------------------

--
-- Table structure for table `philhealth_contribution`
--

CREATE TABLE `philhealth_contribution` (
  `philhealth_id` int(11) NOT NULL,
  `monthly_rate` decimal(5,4) NOT NULL,
  `employee_share` decimal(5,4) NOT NULL,
  `employer_share` decimal(5,4) NOT NULL,
  `salary_floor` decimal(10,2) NOT NULL,
  `salary_ceiling` decimal(10,2) NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `philhealth_contribution`
--

INSERT INTO `philhealth_contribution` (`philhealth_id`, `monthly_rate`, `employee_share`, `employer_share`, `salary_floor`, `salary_ceiling`, `effective_from`, `effective_to`, `created_at`) VALUES
(1, 0.0550, 0.5000, 0.5000, 10000.00, 50000.00, '2026-01-01', NULL, '2026-05-03 11:19:49');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `created_at`) VALUES
(1, 'HR', '2026-05-02 14:46:05'),
(2, 'ACCOUNTING', '2026-05-02 14:46:05'),
(3, 'HEAD ACCOUNTING', '2026-05-09 09:31:37');

-- --------------------------------------------------------

--
-- Table structure for table `sss_bracket`
--

CREATE TABLE `sss_bracket` (
  `sss_id` int(11) NOT NULL,
  `lower_limit` decimal(10,2) DEFAULT NULL,
  `upper_limit` decimal(10,2) DEFAULT NULL,
  `msc` decimal(10,2) DEFAULT NULL,
  `regular_msc` decimal(10,2) DEFAULT NULL,
  `mpf_msc` decimal(10,2) DEFAULT NULL,
  `employee_contribution` decimal(10,2) DEFAULT NULL,
  `employer_contribution` decimal(10,2) DEFAULT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sss_bracket`
--

INSERT INTO `sss_bracket` (`sss_id`, `lower_limit`, `upper_limit`, `msc`, `regular_msc`, `mpf_msc`, `employee_contribution`, `employer_contribution`, `effective_from`, `effective_to`) VALUES
(1, 0.00, 5249.99, 5000.00, 5000.00, 0.00, 250.00, 510.00, '2025-01-01', NULL),
(2, 5250.00, 5749.99, 5500.00, 5500.00, 0.00, 275.00, 560.00, '2025-01-01', NULL),
(3, 5750.00, 6249.99, 6000.00, 6000.00, 0.00, 300.00, 610.00, '2025-01-01', NULL),
(4, 6250.00, 6749.99, 6500.00, 6500.00, 0.00, 325.00, 660.00, '2025-01-01', NULL),
(5, 6750.00, 7249.99, 7000.00, 7000.00, 0.00, 350.00, 710.00, '2025-01-01', NULL),
(6, 7250.00, 7749.99, 7500.00, 7500.00, 0.00, 375.00, 760.00, '2025-01-01', NULL),
(7, 7750.00, 8249.99, 8000.00, 8000.00, 0.00, 400.00, 810.00, '2025-01-01', NULL),
(8, 8250.00, 8749.99, 8500.00, 8500.00, 0.00, 425.00, 860.00, '2025-01-01', NULL),
(9, 8750.00, 9249.99, 9000.00, 9000.00, 0.00, 450.00, 910.00, '2025-01-01', NULL),
(10, 9250.00, 9749.99, 9500.00, 9500.00, 0.00, 475.00, 960.00, '2025-01-01', NULL),
(11, 9750.00, 10249.99, 10000.00, 10000.00, 0.00, 500.00, 1010.00, '2025-01-01', NULL),
(12, 10250.00, 10749.99, 10500.00, 10500.00, 0.00, 525.00, 1060.00, '2025-01-01', NULL),
(13, 10750.00, 11249.99, 11000.00, 11000.00, 0.00, 550.00, 1110.00, '2025-01-01', NULL),
(14, 11250.00, 11749.99, 11500.00, 11500.00, 0.00, 575.00, 1160.00, '2025-01-01', NULL),
(15, 11750.00, 12249.99, 12000.00, 12000.00, 0.00, 600.00, 1210.00, '2025-01-01', NULL),
(16, 12250.00, 12749.99, 12500.00, 12500.00, 0.00, 625.00, 1260.00, '2025-01-01', NULL),
(17, 12750.00, 13249.99, 13000.00, 13000.00, 0.00, 650.00, 1310.00, '2025-01-01', NULL),
(18, 13250.00, 13749.99, 13500.00, 13500.00, 0.00, 675.00, 1360.00, '2025-01-01', NULL),
(19, 13750.00, 14249.99, 14000.00, 14000.00, 0.00, 700.00, 1410.00, '2025-01-01', NULL),
(20, 14250.00, 14749.99, 14500.00, 14500.00, 0.00, 725.00, 1460.00, '2025-01-01', NULL),
(21, 14750.00, 15249.99, 15000.00, 15000.00, 0.00, 750.00, 1530.00, '2025-01-01', NULL),
(22, 15250.00, 15749.99, 15500.00, 15500.00, 0.00, 775.00, 1580.00, '2025-01-01', NULL),
(23, 15750.00, 16249.99, 16000.00, 16000.00, 0.00, 800.00, 1630.00, '2025-01-01', NULL),
(24, 16250.00, 16749.99, 16500.00, 16500.00, 0.00, 825.00, 1680.00, '2025-01-01', NULL),
(25, 16750.00, 17249.99, 17000.00, 17000.00, 0.00, 850.00, 1730.00, '2025-01-01', NULL),
(26, 17250.00, 17749.99, 17500.00, 17500.00, 0.00, 875.00, 1780.00, '2025-01-01', NULL),
(27, 17750.00, 18249.99, 18000.00, 18000.00, 0.00, 900.00, 1830.00, '2025-01-01', NULL),
(28, 18250.00, 18749.99, 18500.00, 18500.00, 0.00, 925.00, 1880.00, '2025-01-01', NULL),
(29, 18750.00, 19249.99, 19000.00, 19000.00, 0.00, 950.00, 1930.00, '2025-01-01', NULL),
(30, 19250.00, 19749.99, 19500.00, 19500.00, 0.00, 975.00, 1980.00, '2025-01-01', NULL),
(31, 19750.00, 20249.99, 20000.00, 20000.00, 0.00, 1000.00, 2030.00, '2025-01-01', NULL),
(32, 20250.00, 20749.99, 20500.00, 20000.00, 500.00, 1025.00, 2080.00, '2025-01-01', NULL),
(33, 20750.00, 21249.99, 21000.00, 20000.00, 1000.00, 1050.00, 2130.00, '2025-01-01', NULL),
(34, 21250.00, 21749.99, 21500.00, 20000.00, 1500.00, 1075.00, 2180.00, '2025-01-01', NULL),
(35, 21750.00, 22249.99, 22000.00, 20000.00, 2000.00, 1100.00, 2230.00, '2025-01-01', NULL),
(36, 22250.00, 22749.99, 22500.00, 20000.00, 2500.00, 1125.00, 2280.00, '2025-01-01', NULL),
(37, 22750.00, 23249.99, 23000.00, 20000.00, 3000.00, 1150.00, 2330.00, '2025-01-01', NULL),
(38, 23250.00, 23749.99, 23500.00, 20000.00, 3500.00, 1175.00, 2380.00, '2025-01-01', NULL),
(39, 23750.00, 24249.99, 24000.00, 20000.00, 4000.00, 1200.00, 2430.00, '2025-01-01', NULL),
(40, 24250.00, 24749.99, 24500.00, 20000.00, 4500.00, 1225.00, 2480.00, '2025-01-01', NULL),
(41, 24750.00, 25249.99, 25000.00, 20000.00, 5000.00, 1250.00, 2530.00, '2025-01-01', NULL),
(42, 25250.00, 25749.99, 25500.00, 20000.00, 5500.00, 1275.00, 2580.00, '2025-01-01', NULL),
(43, 25750.00, 26249.99, 26000.00, 20000.00, 6000.00, 1300.00, 2630.00, '2025-01-01', NULL),
(44, 26250.00, 26749.99, 26500.00, 20000.00, 6500.00, 1325.00, 2680.00, '2025-01-01', NULL),
(45, 26750.00, 27249.99, 27000.00, 20000.00, 7000.00, 1350.00, 2730.00, '2025-01-01', NULL),
(46, 27250.00, 27749.99, 27500.00, 20000.00, 7500.00, 1375.00, 2780.00, '2025-01-01', NULL),
(47, 27750.00, 28249.99, 28000.00, 20000.00, 8000.00, 1400.00, 2830.00, '2025-01-01', NULL),
(48, 28250.00, 28749.99, 28500.00, 20000.00, 8500.00, 1425.00, 2880.00, '2025-01-01', NULL),
(49, 28750.00, 29249.99, 29000.00, 20000.00, 9000.00, 1450.00, 2930.00, '2025-01-01', NULL),
(50, 29250.00, 29749.99, 29500.00, 20000.00, 9500.00, 1475.00, 2980.00, '2025-01-01', NULL),
(51, 29750.00, 30249.99, 30000.00, 20000.00, 10000.00, 1500.00, 3030.00, '2025-01-01', NULL),
(52, 30250.00, 30749.99, 30500.00, 20000.00, 10500.00, 1525.00, 3080.00, '2025-01-01', NULL),
(53, 30750.00, 31249.99, 31000.00, 20000.00, 11000.00, 1550.00, 3130.00, '2025-01-01', NULL),
(54, 31250.00, 31749.99, 31500.00, 20000.00, 11500.00, 1575.00, 3180.00, '2025-01-01', NULL),
(55, 31750.00, 32249.99, 32000.00, 20000.00, 12000.00, 1600.00, 3230.00, '2025-01-01', NULL),
(56, 32250.00, 32749.99, 32500.00, 20000.00, 12500.00, 1625.00, 3280.00, '2025-01-01', NULL),
(57, 32750.00, 33249.99, 33000.00, 20000.00, 13000.00, 1650.00, 3330.00, '2025-01-01', NULL),
(58, 33250.00, 33749.99, 33500.00, 20000.00, 13500.00, 1675.00, 3380.00, '2025-01-01', NULL),
(59, 33750.00, 34249.99, 34000.00, 20000.00, 14000.00, 1700.00, 3430.00, '2025-01-01', NULL),
(60, 34250.00, 34749.99, 34500.00, 20000.00, 14500.00, 1725.00, 3480.00, '2025-01-01', NULL),
(61, 34750.00, 999999.99, 35000.00, 20000.00, 15000.00, 1750.00, 3530.00, '2025-01-01', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `recovery_email` varchar(255) DEFAULT NULL,
  `name_extension` varchar(10) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `role_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `email`, `password`, `first_name`, `middle_name`, `last_name`, `recovery_email`, `name_extension`, `profile_picture`, `role_id`, `created_at`, `updated_at`) VALUES
(1, 'hr@gmail.com', '$2y$10$jj6KWs1i2XorsQo8Z5Ups.EdBpFMQS.c/fNzSJXUk8Ga0QExNRfna', 'Huh', NULL, 'Yunjin', 'hr_recovery@gmail.com', 'III', 'uploads/profile_pic/u1_20260503_155232_258b2206.gif', 1, '2026-05-02 14:46:05', '2026-05-03 08:10:57'),
(2, 'accounting@gmail.com', '$2y$10$ro6HGDKkGnrWfh9ximm4GekEnHZ7JuGgWhffERrFp7eEFh9tBm6Dq', 'Gojo', NULL, 'Satoru', NULL, NULL, 'uploads/profile_pic/u2_20260503_155718_c137b591.gif', 2, '2026-05-02 14:46:05', '2026-05-03 08:10:44');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`activity_id`),
  ADD KEY `fk_activity_logs_user` (`user_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employee_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `location_id` (`location_id`);

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`holiday_id`),
  ADD UNIQUE KEY `uq_holiday_date_name_year` (`holiday_date`,`holiday_name`,`applicable_year`),
  ADD KEY `fk_holiday_payroll_rate` (`payroll_rate_id`);

--
-- Indexes for table `location_rate`
--
ALTER TABLE `location_rate`
  ADD PRIMARY KEY (`location_id`);

--
-- Indexes for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `fk_login_logs_user` (`user_id`);

--
-- Indexes for table `pagibig_contribution`
--
ALTER TABLE `pagibig_contribution`
  ADD PRIMARY KEY (`pagibig_id`);

--
-- Indexes for table `payroll`
--
ALTER TABLE `payroll`
  ADD PRIMARY KEY (`payroll_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `payroll_deductions`
--
ALTER TABLE `payroll_deductions`
  ADD PRIMARY KEY (`deduction_id`),
  ADD KEY `payroll_id` (`payroll_id`);

--
-- Indexes for table `payroll_details`
--
ALTER TABLE `payroll_details`
  ADD PRIMARY KEY (`detail_id`),
  ADD KEY `payroll_id` (`payroll_id`);

--
-- Indexes for table `payroll_rates`
--
ALTER TABLE `payroll_rates`
  ADD PRIMARY KEY (`rate_id`),
  ADD UNIQUE KEY `rate_code` (`rate_code`);

--
-- Indexes for table `philhealth_contribution`
--
ALTER TABLE `philhealth_contribution`
  ADD PRIMARY KEY (`philhealth_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`);

--
-- Indexes for table `sss_bracket`
--
ALTER TABLE `sss_bracket`
  ADD PRIMARY KEY (`sss_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `activity_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `employee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `holiday_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `location_rate`
--
ALTER TABLE `location_rate`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `pagibig_contribution`
--
ALTER TABLE `pagibig_contribution`
  MODIFY `pagibig_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `payroll_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_deductions`
--
ALTER TABLE `payroll_deductions`
  MODIFY `deduction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_details`
--
ALTER TABLE `payroll_details`
  MODIFY `detail_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_rates`
--
ALTER TABLE `payroll_rates`
  MODIFY `rate_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `philhealth_contribution`
--
ALTER TABLE `philhealth_contribution`
  MODIFY `philhealth_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sss_bracket`
--
ALTER TABLE `sss_bracket`
  MODIFY `sss_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_activity_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employees_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `location_rate` (`location_id`) ON DELETE CASCADE;

--
-- Constraints for table `holidays`
--
ALTER TABLE `holidays`
  ADD CONSTRAINT `fk_holiday_payroll_rate` FOREIGN KEY (`payroll_rate_id`) REFERENCES `payroll_rates` (`rate_id`);

--
-- Constraints for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD CONSTRAINT `fk_login_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payroll`
--
ALTER TABLE `payroll`
  ADD CONSTRAINT `payroll_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payroll_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `payroll_deductions`
--
ALTER TABLE `payroll_deductions`
  ADD CONSTRAINT `payroll_deductions_ibfk_1` FOREIGN KEY (`payroll_id`) REFERENCES `payroll` (`payroll_id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll_details`
--
ALTER TABLE `payroll_details`
  ADD CONSTRAINT `payroll_details_ibfk_1` FOREIGN KEY (`payroll_id`) REFERENCES `payroll` (`payroll_id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
