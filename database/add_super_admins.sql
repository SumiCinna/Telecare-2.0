-- ============================================================
-- Adds the super_admins table (mirrors the `admins` table shape)
-- and seeds ONE Super Admin account, since none exists yet.
-- Run this once against your `telecare` database.
-- ============================================================

USE `telecare`;

CREATE TABLE IF NOT EXISTS `super_admins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Seed account:
--   email:    superadmin@telecare.com
--   password: ChangeMe123!   <-- log in once, then change this via a
--                                password-reset/update feature.
-- The hash below is a standard PHP password_hash() bcrypt hash for
-- "ChangeMe123!" (compatible with password_verify()).
INSERT INTO `super_admins` (`full_name`, `email`, `password`)
VALUES (
  'Super Admin',
  'superadmin@telecare.com',
  '$2y$10$qHQuthKpG4iEn0iENp2s2e0wweLna5Oii.Y.Sb8VoykbampPFd2FG'
)
ON DUPLICATE KEY UPDATE `email` = `email`;
