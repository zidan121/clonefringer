-- CLONEFRINGER database foundation
-- Target: MySQL 8.0+ / MariaDB 10.6+
-- Run only against a NEW database or after taking a verified backup.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS units (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(150) NOT NULL,
  role ENUM('admin','operator','viewer') NOT NULL DEFAULT 'viewer',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employees (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pin VARCHAR(50) NOT NULL UNIQUE,
  full_name VARCHAR(180) NOT NULL,
  unit_id BIGINT UNSIGNED NULL,
  department VARCHAR(120) NULL,
  position_name VARCHAR(120) NULL,
  employee_status ENUM('active','inactive','resigned','terminated') NOT NULL DEFAULT 'active',
  join_date DATE NULL,
  contract_end DATE NULL,
  phone VARCHAR(40) NULL,
  email VARCHAR(160) NULL,
  gender ENUM('male','female','other') NULL,
  birth_date DATE NULL,
  religion VARCHAR(60) NULL,
  marital_status VARCHAR(40) NULL,
  education VARCHAR(80) NULL,
  blood_type VARCHAR(5) NULL,
  address TEXT NULL,
  machine_privilege VARCHAR(60) NULL,
  machine_password VARCHAR(120) NULL,
  rfid VARCHAR(120) NULL,
  fingerprint_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_employees_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL,
  INDEX idx_employees_name (full_name),
  INDEX idx_employees_status (employee_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS machines (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  model VARCHAR(120) NULL,
  serial_number VARCHAR(120) NULL,
  host VARCHAR(255) NOT NULL,
  port SMALLINT UNSIGNED NOT NULL DEFAULT 4370,
  connection_type ENUM('tcp','udp','http','sdk','file') NOT NULL DEFAULT 'tcp',
  location VARCHAR(180) NULL,
  status ENUM('active','inactive','maintenance','unknown') NOT NULL DEFAULT 'unknown',
  last_sync_at DATETIME NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_machine_serial (serial_number),
  INDEX idx_machine_host_port (host,port)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_schedules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  break_start TIME NULL,
  break_end TIME NULL,
  tolerance_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  crosses_midnight TINYINT(1) NOT NULL DEFAULT 0,
  active_days JSON NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_schedule_assignments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id BIGINT UNSIGNED NOT NULL,
  schedule_id BIGINT UNSIGNED NOT NULL,
  effective_from DATE NOT NULL,
  effective_to DATE NULL,
  custom_start TIME NULL,
  custom_end TIME NULL,
  custom_tolerance SMALLINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_esa_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  CONSTRAINT fk_esa_schedule FOREIGN KEY (schedule_id) REFERENCES work_schedules(id) ON DELETE RESTRICT,
  INDEX idx_esa_effective (employee_id,effective_from,effective_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS unit_schedule_defaults (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  unit_id BIGINT UNSIGNED NOT NULL,
  schedule_id BIGINT UNSIGNED NOT NULL,
  effective_from DATE NOT NULL,
  effective_to DATE NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_usd_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
  CONSTRAINT fk_usd_schedule FOREIGN KEY (schedule_id) REFERENCES work_schedules(id) ON DELETE RESTRICT,
  INDEX idx_usd_effective (unit_id,effective_from,effective_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendar_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  event_type ENUM('national_holiday','foundation_holiday','unit_holiday','special_activity') NOT NULL,
  unit_id BIGINT UNSIGNED NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  impact ENUM('non_working','working') NOT NULL DEFAULT 'non_working',
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_calendar_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL,
  CONSTRAINT fk_calendar_user FOREIGN KEY (created_by) REFERENCES app_users(id) ON DELETE SET NULL,
  INDEX idx_calendar_dates (start_date,end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_exceptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id BIGINT UNSIGNED NOT NULL,
  exception_type ENUM('leave','sick','permission','annual_leave','collective_leave','schedule_change','hours_change','shift_swap','overtime','extra_work') NOT NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  status ENUM('draft','pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  reason TEXT NULL,
  replacement_schedule_id BIGINT UNSIGNED NULL,
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_exception_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE RESTRICT,
  CONSTRAINT fk_exception_schedule FOREIGN KEY (replacement_schedule_id) REFERENCES work_schedules(id) ON DELETE SET NULL,
  CONSTRAINT fk_exception_approver FOREIGN KEY (approved_by) REFERENCES app_users(id) ON DELETE SET NULL,
  CONSTRAINT fk_exception_creator FOREIGN KEY (created_by) REFERENCES app_users(id) ON DELETE SET NULL,
  INDEX idx_exception_employee_dates (employee_id,start_at,end_at,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS raw_scanlogs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  machine_id BIGINT UNSIGNED NULL,
  employee_id BIGINT UNSIGNED NULL,
  pin VARCHAR(50) NOT NULL,
  scanned_at DATETIME NOT NULL,
  verify_method VARCHAR(50) NULL,
  device_event_code VARCHAR(80) NULL,
  device_transaction_id VARCHAR(160) NULL,
  source ENUM('device','file_import','manual_import') NOT NULL DEFAULT 'device',
  raw_payload JSON NULL,
  imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_scan_machine FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE SET NULL,
  CONSTRAINT fk_scan_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL,
  UNIQUE KEY uq_scan_device_transaction (machine_id,device_transaction_id),
  INDEX idx_scan_pin_time (pin,scanned_at),
  INDEX idx_scan_employee_time (employee_id,scanned_at),
  INDEX idx_scan_time (scanned_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS processing_batches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  status ENUM('queued','running','completed','failed') NOT NULL DEFAULT 'queued',
  ruleset_version VARCHAR(40) NOT NULL,
  started_by BIGINT UNSIGNED NULL,
  started_at DATETIME NULL,
  finished_at DATETIME NULL,
  row_count INT UNSIGNED NOT NULL DEFAULT 0,
  error_message TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_batch_user FOREIGN KEY (started_by) REFERENCES app_users(id) ON DELETE SET NULL,
  INDEX idx_batch_period (period_start,period_end,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_daily_results (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  attendance_date DATE NOT NULL,
  schedule_id BIGINT UNSIGNED NULL,
  first_scan_at DATETIME NULL,
  last_scan_at DATETIME NULL,
  scan_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  attendance_status VARCHAR(50) NOT NULL,
  late_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  early_leave_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  source_scan_ids JSON NOT NULL,
  calculation_detail JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_result_batch FOREIGN KEY (batch_id) REFERENCES processing_batches(id) ON DELETE RESTRICT,
  CONSTRAINT fk_result_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE RESTRICT,
  CONSTRAINT fk_result_schedule FOREIGN KEY (schedule_id) REFERENCES work_schedules(id) ON DELETE SET NULL,
  UNIQUE KEY uq_batch_employee_day (batch_id,employee_id,attendance_date),
  INDEX idx_result_day_employee (attendance_date,employee_id),
  INDEX idx_result_status (attendance_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  before_data JSON NULL,
  after_data JSON NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES app_users(id) ON DELETE SET NULL,
  INDEX idx_audit_entity (entity_type,entity_id),
  INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO units (code,name) VALUES
('KBIT','KBIT'),('RAIT','RAIT'),('SDIT','SDIT'),('SMPIT','SMPIT'),('YAYASAN','Yayasan');

SET FOREIGN_KEY_CHECKS=1;
