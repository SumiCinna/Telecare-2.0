-- Medtech User Type Database Schema
-- For Lab Technician role in TELE-CARE system
-- Created: 2026-08-24

-- =====================================================
-- 1. MEDTECH_USERS Table
-- =====================================================
DROP TABLE IF EXISTS `medtech_users`;
CREATE TABLE `medtech_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20),
  `profile_pic` varchar(255),
  `department` varchar(100) DEFAULT 'Laboratory',
  `shift_schedule` enum('Morning','Afternoon','Evening','Night','Flexible') DEFAULT 'Flexible',
  `qualification` varchar(150),
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- 2. LAB_TESTS Table
-- =====================================================
DROP TABLE IF EXISTS `lab_tests`;
CREATE TABLE `lab_tests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `receipt_no` varchar(50) NOT NULL UNIQUE,
  `service_id` int NOT NULL,
  `patient_id` int NOT NULL,
  `requested_by` int NOT NULL,
  `medtech_id` int,
  `status` enum('Pending','In Progress','Completed','Cancelled') DEFAULT 'Pending',
  `priority` enum('Normal','Urgent','STAT') DEFAULT 'Normal',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL,
  `repeats` int DEFAULT 1,
  `notes` text,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `receipt_no` (`receipt_no`),
  KEY `service_id` (`service_id`),
  KEY `patient_id` (`patient_id`),
  KEY `medtech_id` (`medtech_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `lab_tests_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `lab_tests_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lab_tests_ibfk_3` FOREIGN KEY (`medtech_id`) REFERENCES `medtech_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- 3. LAB_TEST_RESULTS Table
-- =====================================================
DROP TABLE IF EXISTS `lab_test_results`;
CREATE TABLE `lab_test_results` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lab_test_id` int NOT NULL,
  `result_file` varchar(255),
  `result_data` longtext,
  `interpretation` text,
  `reference_range` text,
  `uploaded_by` int,
  `uploaded_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `verified_by` int,
  `verified_at` timestamp NULL,
  PRIMARY KEY (`id`),
  KEY `lab_test_id` (`lab_test_id`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `verified_by` (`verified_by`),
  CONSTRAINT `lab_test_results_ibfk_1` FOREIGN KEY (`lab_test_id`) REFERENCES `lab_tests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lab_test_results_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `medtech_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lab_test_results_ibfk_3` FOREIGN KEY (`verified_by`) REFERENCES `doctors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- 4. LAB_TEST_KITS Table (Inventory)
-- =====================================================
DROP TABLE IF EXISTS `lab_test_kits`;
CREATE TABLE `lab_test_kits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_name` varchar(150) NOT NULL,
  `category` varchar(100) NOT NULL,
  `brand` varchar(100),
  `size` varchar(50),
  `quantity` int DEFAULT 0,
  `reorder_level` int DEFAULT 5,
  `unit` varchar(20) DEFAULT 'Box',
  `supplier` varchar(150),
  `cost_per_unit` decimal(10,2),
  `status` enum('Active','Discontinued','Out of Stock') DEFAULT 'Active',
  `last_updated_by` int,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category` (`category`),
  KEY `status` (`status`),
  KEY `last_updated_by` (`last_updated_by`),
  CONSTRAINT `lab_test_kits_ibfk_1` FOREIGN KEY (`last_updated_by`) REFERENCES `medtech_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- 5. LAB_KIT_TRANSACTIONS Table (Stock movements)
-- =====================================================
DROP TABLE IF EXISTS `lab_kit_transactions`;
CREATE TABLE `lab_kit_transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kit_id` int NOT NULL,
  `transaction_type` enum('Used','Added','Adjusted','Received') DEFAULT 'Used',
  `quantity_changed` int NOT NULL,
  `notes` text,
  `recorded_by` int,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `kit_id` (`kit_id`),
  KEY `recorded_by` (`recorded_by`),
  KEY `transaction_type` (`transaction_type`),
  CONSTRAINT `lab_kit_transactions_ibfk_1` FOREIGN KEY (`kit_id`) REFERENCES `lab_test_kits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lab_kit_transactions_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `medtech_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- 6. MEDTECH_ACTIVITY_LOG Table (Audit trail)
-- =====================================================
DROP TABLE IF EXISTS `medtech_activity_log`;
CREATE TABLE `medtech_activity_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `medtech_id` int NOT NULL,
  `action` varchar(100),
  `entity_type` varchar(50),
  `entity_id` int,
  `description` text,
  `ip_address` varchar(45),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `medtech_id` (`medtech_id`),
  KEY `action` (`action`),
  KEY `entity_type` (`entity_type`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `medtech_activity_log_ibfk_1` FOREIGN KEY (`medtech_id`) REFERENCES `medtech_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================
-- Sample Data for Testing
-- =====================================================

-- Insert sample medtech user
INSERT INTO `medtech_users` (full_name, email, password, phone, department, shift_schedule, qualification, status)
VALUES ('Maria Santos', 'maria.santos@telecare.com', '$2y$10$KFzfCMLW/6YVdUWjiYG8b.g0yyUP6ZAW24jy1H7DrbopyR.btv15a', '09171234567', 'Laboratory', 'Morning', 'Medical Laboratory Scientist', 'active');

-- Insert sample lab test kits
INSERT INTO `lab_test_kits` (item_name, category, brand, size, quantity, reorder_level, unit, supplier, cost_per_unit, status)
VALUES 
('Urine Specimen Container', 'Collection Kits', 'BioPlas', '15ml', 50, 10, 'Box', 'Medical Supplies Co.', 5.00, 'Active'),
('Blood Collection Tube (EDTA)', 'Collection Kits', 'Greiner', '3ml', 100, 20, 'Box', 'Medical Supplies Co.', 2.50, 'Active'),
('Rapid Antigen Test Kit', 'Diagnostic Kits', 'Abbott', 'Individual', 30, 50, 'Box', 'Diagnostic Vendors Inc.', 150.00, 'Active'),
('Blood Glucose Strip', 'Test Strips', 'Roche', '50-strip', 40, 15, 'Box', 'Diagnostic Vendors Inc.', 200.00, 'Active'),
('COVID-19 Antigen Rapid Test', 'Diagnostic Kits', 'SD Biosensor', 'Individual', 25, 50, 'Box', 'Diagnostic Vendors Inc.', 120.00, 'Active'),
('Lancet (Sterile)', 'Sharp Instruments', 'Unistik', '100-pack', 10, 5, 'Box', 'Medical Supplies Co.', 8.00, 'Active'),
('Alcohol Swab', 'Disinfectants', 'Generic', '100-pack', 75, 20, 'Box', 'Medical Supplies Co.', 3.50, 'Active'),
('Cotton Swab (Sterile)', 'Collection Supplies', 'Puritan', '100-pack', 60, 15, 'Box', 'Medical Supplies Co.', 4.00, 'Active');

-- =====================================================
-- Create Indexes for Performance
-- =====================================================
CREATE INDEX idx_lab_tests_date ON lab_tests(created_at);
CREATE INDEX idx_lab_tests_medtech ON lab_tests(medtech_id, status);
CREATE INDEX idx_lab_test_results_lab_test ON lab_test_results(lab_test_id);
CREATE INDEX idx_lab_kits_category_status ON lab_test_kits(category, status);

-- =====================================================
-- View: Medtech Dashboard Summary
-- =====================================================
CREATE OR REPLACE VIEW v_medtech_dashboard_summary AS
SELECT 
  m.id as medtech_id,
  m.full_name as medtech_name,
  COUNT(lt.id) as total_tests_30d,
  SUM(CASE WHEN lt.status IN ('Pending', 'In Progress') THEN 1 ELSE 0 END) as pending_tests,
  SUM(CASE WHEN lt.status = 'Completed' AND lt.completed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as completed_tests_30d,
  ROUND(AVG(TIMESTAMPDIFF(HOUR, lt.created_at, lt.completed_at)), 1) as avg_turnaround_hours,
  SUM(CASE WHEN lk.quantity <= lk.reorder_level THEN 1 ELSE 0 END) as low_stock_items
FROM medtech_users m
LEFT JOIN lab_tests lt ON m.id = lt.medtech_id AND lt.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
LEFT JOIN lab_test_kits lk ON lk.status = 'Active'
WHERE m.status = 'active'
GROUP BY m.id;

-- =====================================================
-- View: Pending Lab Tests for Medtech
-- =====================================================
CREATE OR REPLACE VIEW v_pending_lab_tests AS
SELECT 
  lt.id,
  lt.receipt_no,
  lt.created_at,
  lt.priority,
  s.name as service_name,
  s.id as service_id,
  p.full_name as patient_name,
  p.id as patient_id,
  u.full_name as requested_by,
  lt.notes,
  lt.repeats
FROM lab_tests lt
JOIN services s ON s.id = lt.service_id
JOIN patients p ON p.id = lt.patient_id
JOIN users u ON u.id = lt.requested_by
WHERE lt.status = 'Pending'
ORDER BY lt.priority DESC, lt.created_at ASC;

-- =====================================================
-- View: Lab Kit Inventory Status
-- =====================================================
CREATE OR REPLACE VIEW v_lab_kit_status AS
SELECT 
  lk.id,
  lk.item_name,
  lk.category,
  lk.brand,
  lk.quantity,
  lk.reorder_level,
  CASE 
    WHEN lk.quantity <= 0 THEN 'Out of Stock'
    WHEN lk.quantity <= lk.reorder_level THEN 'Low Stock'
    ELSE 'In Stock'
  END as stock_status,
  lk.status as item_status
FROM lab_test_kits lk
WHERE lk.status = 'Active'
ORDER BY lk.category ASC, lk.item_name ASC;
