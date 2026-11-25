-- Apply to an existing CLONEFRINGER database once.
-- Raw scanlogs are not updated by this migration.
CREATE TABLE IF NOT EXISTS scanlog_pin_mappings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pin VARCHAR(50) NOT NULL UNIQUE,
  employee_id BIGINT UNSIGNED NOT NULL,
  mapped_by BIGINT UNSIGNED NULL,
  mapped_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  notes VARCHAR(255) NULL,
  CONSTRAINT fk_pin_mapping_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pin_mapping_user FOREIGN KEY (mapped_by) REFERENCES app_users(id) ON DELETE SET NULL,
  INDEX idx_pin_mapping_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
