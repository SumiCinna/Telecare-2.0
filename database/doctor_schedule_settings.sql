-- Optional migration for persisted doctor scheduling preferences.
-- The availability page also creates this table defensively when needed.
CREATE TABLE IF NOT EXISTS doctor_schedule_settings (
  doctor_id INT NOT NULL,
  consultation_duration SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  appointment_interval SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  break_start TIME NULL,
  break_end TIME NULL,
  consultation_types VARCHAR(255) NOT NULL DEFAULT 'In-person',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (doctor_id),
  CONSTRAINT doctor_schedule_settings_doctor_fk
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
