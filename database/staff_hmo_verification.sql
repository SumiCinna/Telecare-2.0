-- Staff HMO verification: extra columns + case tables.
-- Only needed if your DB user cannot ALTER/CREATE: the staff pages do all of this automatically.
-- Run booking_payment_methods.sql FIRST (it creates appointment_hmo). Safe to run more than once.

ALTER TABLE appointment_hmo MODIFY COLUMN coverage_status
  ENUM('Pending','In Verification','Action Required','Approved','Not Approved','Not Covered') NOT NULL DEFAULT 'Pending';

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='appointment_hmo' AND COLUMN_NAME='staff_instructions');
SET @sql := IF(@has=0, 'ALTER TABLE appointment_hmo ADD COLUMN staff_instructions TEXT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='appointment_hmo' AND COLUMN_NAME='approval_loa_no');
SET @sql := IF(@has=0, 'ALTER TABLE appointment_hmo ADD COLUMN approval_loa_no VARCHAR(60) NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='appointment_hmo' AND COLUMN_NAME='valid_from');
SET @sql := IF(@has=0, 'ALTER TABLE appointment_hmo ADD COLUMN valid_from DATE NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='appointment_hmo' AND COLUMN_NAME='valid_until');
SET @sql := IF(@has=0, 'ALTER TABLE appointment_hmo ADD COLUMN valid_until DATE NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='appointment_hmo' AND COLUMN_NAME='approved_service');
SET @sql := IF(@has=0, 'ALTER TABLE appointment_hmo ADD COLUMN approved_service VARCHAR(150) NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='appointment_hmo' AND COLUMN_NAME='benefit_note');
SET @sql := IF(@has=0, 'ALTER TABLE appointment_hmo ADD COLUMN benefit_note VARCHAR(255) NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='appointment_hmo' AND COLUMN_NAME='verification_method');
SET @sql := IF(@has=0, 'ALTER TABLE appointment_hmo ADD COLUMN verification_method VARCHAR(60) NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='appointment_hmo' AND COLUMN_NAME='decision_reason');
SET @sql := IF(@has=0, 'ALTER TABLE appointment_hmo ADD COLUMN decision_reason VARCHAR(255) NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='appointment_hmo' AND COLUMN_NAME='decided_by');
SET @sql := IF(@has=0, 'ALTER TABLE appointment_hmo ADD COLUMN decided_by INT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='appointment_hmo' AND COLUMN_NAME='decided_at');
SET @sql := IF(@has=0, 'ALTER TABLE appointment_hmo ADD COLUMN decided_at DATETIME NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='appointment_hmo' AND COLUMN_NAME='status_updated_at');
SET @sql := IF(@has=0, 'ALTER TABLE appointment_hmo ADD COLUMN status_updated_at DATETIME NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS hmo_contact_log (
            id INT NOT NULL AUTO_INCREMENT,
            appointment_id INT NOT NULL,
            staff_id INT NULL,
            channel VARCHAR(40) NOT NULL,
            contact_detail VARCHAR(150) NULL,
            contacted_at DATETIME NOT NULL,
            hmo_reference_no VARCHAR(80) NULL,
            response TEXT NOT NULL,
            follow_up_date DATE NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_hmo_contact_appt (appointment_id),
            CONSTRAINT hmo_contact_log_fk FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS hmo_case_events (
            id INT NOT NULL AUTO_INCREMENT,
            appointment_id INT NOT NULL,
            staff_id INT NULL,
            event_type VARCHAR(30) NOT NULL,
            status_from VARCHAR(30) NULL,
            status_to VARCHAR(30) NULL,
            summary VARCHAR(255) NOT NULL,
            detail TEXT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_hmo_events_appt (appointment_id, id),
            CONSTRAINT hmo_case_events_fk FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===== Patient HMO pages + provider hold (added in the one-bundle update) =====

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='appointment_hmo' AND COLUMN_NAME='patient_submitted_at');
SET @sql := IF(@has=0, 'ALTER TABLE appointment_hmo ADD COLUMN patient_submitted_at DATETIME NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='appointment_hmo' AND COLUMN_NAME='assistance_requested');
SET @sql := IF(@has=0, 'ALTER TABLE appointment_hmo ADD COLUMN assistance_requested TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='appointment_hmo' AND COLUMN_NAME='assistance_note');
SET @sql := IF(@has=0, 'ALTER TABLE appointment_hmo ADD COLUMN assistance_note VARCHAR(255) NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='appointment_hmo' AND COLUMN_NAME='patient_updated_at');
SET @sql := IF(@has=0, 'ALTER TABLE appointment_hmo ADD COLUMN patient_updated_at DATETIME NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hmo_providers' AND COLUMN_NAME='on_hold');
SET @sql := IF(@has=0, 'ALTER TABLE hmo_providers ADD COLUMN on_hold TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hmo_providers' AND COLUMN_NAME='hold_reason');
SET @sql := IF(@has=0, 'ALTER TABLE hmo_providers ADD COLUMN hold_reason VARCHAR(255) NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hmo_providers' AND COLUMN_NAME='hold_since');
SET @sql := IF(@has=0, 'ALTER TABLE hmo_providers ADD COLUMN hold_since DATETIME NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS appointment_hmo_docs (
            id INT NOT NULL AUTO_INCREMENT,
            appointment_id INT NOT NULL,
            doc_type VARCHAR(20) NOT NULL,
            stored_name VARCHAR(80) NOT NULL,
            original_name VARCHAR(150) NOT NULL,
            mime VARCHAR(60) NOT NULL,
            size_bytes INT NOT NULL DEFAULT 0,
            uploaded_by ENUM('patient','staff') NOT NULL DEFAULT 'patient',
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_hmo_docs_appt (appointment_id),
            CONSTRAINT appointment_hmo_docs_fk FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ===== Admin HMO module (dashboard, provider management, rules, staff permissions, access log) =====
-- The admin pages create all of this by themselves; run this only if your DB user cannot ALTER/CREATE. Safe to run more than once.

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hmo_providers' AND COLUMN_NAME='provider_code');
SET @sql := IF(@has=0, 'ALTER TABLE hmo_providers ADD COLUMN provider_code VARCHAR(20) NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hmo_providers' AND COLUMN_NAME='support_channels');
SET @sql := IF(@has=0, 'ALTER TABLE hmo_providers ADD COLUMN support_channels VARCHAR(255) NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hmo_providers' AND COLUMN_NAME='teleconsult_available');
SET @sql := IF(@has=0, 'ALTER TABLE hmo_providers ADD COLUMN teleconsult_available TINYINT(1) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hmo_providers' AND COLUMN_NAME='effective_from');
SET @sql := IF(@has=0, 'ALTER TABLE hmo_providers ADD COLUMN effective_from DATE NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS hmo_provider_contacts (
  id INT NOT NULL AUTO_INCREMENT, provider_id INT NOT NULL, agent_name VARCHAR(120) NOT NULL, role_title VARCHAR(80) NULL,
  phone VARCHAR(40) NULL, email VARCHAR(120) NULL, channel VARCHAR(60) NULL, notes VARCHAR(255) NULL, is_primary TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_hpc_provider (provider_id),
  CONSTRAINT hmo_provider_contacts_fk FOREIGN KEY (provider_id) REFERENCES hmo_providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS hmo_provider_doctors (
  provider_id INT NOT NULL, doctor_id INT NOT NULL, PRIMARY KEY (provider_id, doctor_id),
  CONSTRAINT hmo_provider_doctors_fk FOREIGN KEY (provider_id) REFERENCES hmo_providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS hmo_rules (
  provider_id INT NOT NULL, loa_requirement ENUM('required','optional','not_applicable') NOT NULL DEFAULT 'optional',
  member_fields VARCHAR(255) NOT NULL DEFAULT '', required_docs VARCHAR(255) NOT NULL DEFAULT 'card',
  verification_method ENUM('portal','api','phone','email') NOT NULL DEFAULT 'phone', validity_days INT NULL,
  consult_types VARCHAR(255) NOT NULL DEFAULT 'Online Consultation,Follow-up Consultation', copay_note VARCHAR(255) NULL,
  effective_from DATE NULL, updated_by INT NULL, updated_at DATETIME NULL, PRIMARY KEY (provider_id),
  CONSTRAINT hmo_rules_fk FOREIGN KEY (provider_id) REFERENCES hmo_providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS hmo_staff_permissions (
  staff_id INT NOT NULL, can_verify TINYINT(1) NOT NULL DEFAULT 1, can_update_status TINYINT(1) NOT NULL DEFAULT 1,
  can_view_documents TINYINT(1) NOT NULL DEFAULT 1, updated_by INT NULL, updated_at DATETIME NULL, PRIMARY KEY (staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS hmo_access_log (
  id INT NOT NULL AUTO_INCREMENT, actor_type ENUM('admin','staff') NOT NULL, actor_id INT NOT NULL, action VARCHAR(40) NOT NULL,
  entity VARCHAR(30) NOT NULL, entity_id INT NULL, appointment_id INT NULL, detail VARCHAR(255) NULL, ip VARCHAR(45) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_hal_actor (actor_type, actor_id), KEY idx_hal_appt (appointment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
