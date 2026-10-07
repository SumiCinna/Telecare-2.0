-- Run once in phpMyAdmin if your DB user can't ALTER/CREATE from PHP.
-- (The booking pages also try to create all of this automatically.)

ALTER TABLE appointments
  ADD COLUMN payment_method ENUM('Regular','YAKAP','HMO') NOT NULL DEFAULT 'Regular' AFTER payment_status;

CREATE TABLE IF NOT EXISTS appointment_yakap (
  id INT NOT NULL AUTO_INCREMENT,
  appointment_id INT NOT NULL,
  philhealth_pin VARCHAR(14) NOT NULL,
  patient_name VARCHAR(150) NOT NULL,
  date_of_birth DATE NULL,
  member_type ENUM('Member','Dependent') NOT NULL,
  contact_number VARCHAR(20) NOT NULL,
  address VARCHAR(255) NOT NULL,
  yakap_clinic VARCHAR(150) NOT NULL,
  empanelment_status ENUM('Empaneled','Not Yet Empaneled') NOT NULL,
  fpe_status ENUM('Completed','Not Yet Completed') NOT NULL,
  consent TINYINT(1) NOT NULL DEFAULT 0,
  consent_at DATETIME NULL,
  verification_status ENUM('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
  diagnosis_icd10 TEXT NULL,
  consultation_notes TEXT NULL,
  prescription TEXT NULL,
  pcu_reference_no VARCHAR(60) NULL,
  provider_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  provider_confirmed_at DATETIME NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_yakap_appt (appointment_id),
  CONSTRAINT appointment_yakap_fk FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS appointment_hmo (
  id INT NOT NULL AUTO_INCREMENT,
  appointment_id INT NOT NULL,
  hmo_provider VARCHAR(100) NOT NULL,
  hmo_member_id VARCHAR(60) NOT NULL,
  member_type ENUM('Member','Dependent') NOT NULL,
  principal_member_name VARCHAR(150) NOT NULL,
  company_employer VARCHAR(150) NULL,
  hmo_plan VARCHAR(100) NULL,
  patient_name VARCHAR(150) NOT NULL,
  date_of_birth DATE NULL,
  contact_number VARCHAR(20) NOT NULL,
  service_type ENUM('Online Consultation','Follow-up Consultation') NOT NULL,
  loa_number VARCHAR(60) NULL,
  coverage_status ENUM('Pending','Approved','Not Covered') NOT NULL DEFAULT 'Pending',
  hmo_coverage_amount DECIMAL(10,2) NULL,
  patient_share DECIMAL(10,2) NULL,
  diagnosis TEXT NULL,
  consultation_notes TEXT NULL,
  prescription TEXT NULL,
  supporting_documents VARCHAR(255) NULL,
  claim_reference_no VARCHAR(60) NULL,
  claim_status ENUM('Pending','Approved','Denied','Processed') NOT NULL DEFAULT 'Pending',
  consent TINYINT(1) NOT NULL DEFAULT 0,
  consent_at DATETIME NULL,
  provider_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  provider_confirmed_at DATETIME NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_hmo_appt (appointment_id),
  CONSTRAINT appointment_hmo_fk FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
