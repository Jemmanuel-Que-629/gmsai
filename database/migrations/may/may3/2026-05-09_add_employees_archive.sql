-- Add soft-archive support for employees
-- Run this once on your database.

ALTER TABLE employees
  ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER salary_type,
  ADD INDEX idx_employees_is_archived (is_archived);
