-- Run this once against your database before using the Templates page.
-- Stores the letterhead / signature-block details for the 3 document types.

CREATE TABLE IF NOT EXISTS document_templates (
  doc_type        VARCHAR(30) NOT NULL PRIMARY KEY,   -- 'prescription' | 'lab_request' | 'med_cert'
  clinic_name     VARCHAR(255) NOT NULL DEFAULT '',
  address         VARCHAR(255) NOT NULL DEFAULT '',
  contact_no      VARCHAR(100) NOT NULL DEFAULT '',
  physician_name  VARCHAR(255) NOT NULL DEFAULT '',
  credentials     VARCHAR(100) NOT NULL DEFAULT '',
  license_no      VARCHAR(100) NOT NULL DEFAULT '',
  ptr_no          VARCHAR(100) NOT NULL DEFAULT '',
  footer_note     TEXT NULL,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
