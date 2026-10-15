-- HMO providers: full legal names + Insurance Commission license numbers.
-- Source: Insurance Commission "List of HMOs with CA", as of 31 January 2025 (27 HMOs).
-- Safe to run more than once. Your existing "Intellicare" and "Maxicare" rows are renamed (not deleted),
-- so their coverage rules are kept. Any other provider not in this list is deleted, with its coverage rules.
-- "Other" is added by the booking form itself.

-- 1) license_no column (added only if missing)
SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hmo_providers' AND COLUMN_NAME='license_no');
SET @sql := IF(@has=0, 'ALTER TABLE hmo_providers ADD COLUMN license_no VARCHAR(30) NULL AFTER name', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2) rename the two existing rows
UPDATE hmo_providers SET name = 'Asalus Corporation (Intellicare)' WHERE name = 'Intellicare';
UPDATE hmo_providers SET name = 'Maxicare Healthcare Corp.' WHERE name = 'Maxicare';

-- 3) add the missing providers
INSERT INTO hmo_providers (name, license_no, status)
SELECT t.name, t.license_no, 'Active' FROM (
  SELECT 'Asalus Corporation (Intellicare)' AS name, 'HMO-2023-06-R' AS license_no
  UNION ALL SELECT 'Asiancare Health Systems, Inc.' AS name, 'HMO-2024-01-R' AS license_no
  UNION ALL SELECT 'Avega Managed Care, Inc.' AS name, 'HMO-2023-05-R' AS license_no
  UNION ALL SELECT 'Carewell Health Systems, Inc.' AS name, 'HMO-2023-09-R' AS license_no
  UNION ALL SELECT 'Cooperative Health Management Federation' AS name, 'HMO-2024-03-R' AS license_no
  UNION ALL SELECT 'Dynamic Care Corporation' AS name, 'HMO-2023-20-R' AS license_no
  UNION ALL SELECT 'EastWest Healthcare, Inc.' AS name, 'HMO-2023-14-R' AS license_no
  UNION ALL SELECT 'Getwell Health Systems, Inc.' AS name, 'HMO-2023-13-R' AS license_no
  UNION ALL SELECT 'Health Care and Development Corporation of the Philippines' AS name, 'HMO-2024-04-R' AS license_no
  UNION ALL SELECT 'Health Delivery System, Inc.' AS name, 'HMO-2024-06-R' AS license_no
  UNION ALL SELECT 'Health Maintenance, Inc.' AS name, 'HMO-2023-02-R' AS license_no
  UNION ALL SELECT 'Health Plan Philippines, Inc.' AS name, 'HMO-2023-04-R' AS license_no
  UNION ALL SELECT 'IMS Wellth Care, Inc.' AS name, 'HMO-2023-01-R' AS license_no
  UNION ALL SELECT 'Insular Health Care, Inc.' AS name, 'HMO-2023-03-R' AS license_no
  UNION ALL SELECT 'Kaiser International Healthgroup, Inc.' AS name, 'HMO-2023-08-R' AS license_no
  UNION ALL SELECT 'Life & Health HMP, Inc.' AS name, 'HMO-2023-16-R' AS license_no
  UNION ALL SELECT 'Maxicare Healthcare Corp.' AS name, 'HMO-2023-07-R' AS license_no
  UNION ALL SELECT 'Medicard Philippines, Inc.' AS name, 'HMO-2023-12-R' AS license_no
  UNION ALL SELECT 'Medicare Plus, Inc.' AS name, 'HMO-2023-21-R' AS license_no
  UNION ALL SELECT 'Medocare Health Systems, Inc.' AS name, 'HMO-2023-17-R' AS license_no
  UNION ALL SELECT 'Metrocare Health Systems, Incorporated' AS name, 'HMO-2023-11-R' AS license_no
  UNION ALL SELECT 'MI Healthcare Inc.' AS name, 'HMO-2024-05-O' AS license_no
  UNION ALL SELECT 'Optimum Medical and Healthcare Services Inc.' AS name, 'HMO-2023-19-R' AS license_no
  UNION ALL SELECT 'Pacific Cross Health Care, Inc.' AS name, 'HMO-2023-10-R' AS license_no
  UNION ALL SELECT 'PhilhealthCare, Inc. (PhilCare)' AS name, 'HMO-2023-15-R' AS license_no
  UNION ALL SELECT 'Value Care Health Systems, Inc.' AS name, 'HMO-2023-18-R' AS license_no
  UNION ALL SELECT 'Wellcare Health Maintenance Inc.' AS name, 'HMO-2024-02-R' AS license_no
) t
WHERE NOT EXISTS (SELECT 1 FROM hmo_providers p WHERE p.name = t.name);

-- 4) license numbers + active for all 27
UPDATE hmo_providers SET license_no = 'HMO-2023-06-R', status = 'Active' WHERE name = 'Asalus Corporation (Intellicare)';
UPDATE hmo_providers SET license_no = 'HMO-2024-01-R', status = 'Active' WHERE name = 'Asiancare Health Systems, Inc.';
UPDATE hmo_providers SET license_no = 'HMO-2023-05-R', status = 'Active' WHERE name = 'Avega Managed Care, Inc.';
UPDATE hmo_providers SET license_no = 'HMO-2023-09-R', status = 'Active' WHERE name = 'Carewell Health Systems, Inc.';
UPDATE hmo_providers SET license_no = 'HMO-2024-03-R', status = 'Active' WHERE name = 'Cooperative Health Management Federation';
UPDATE hmo_providers SET license_no = 'HMO-2023-20-R', status = 'Active' WHERE name = 'Dynamic Care Corporation';
UPDATE hmo_providers SET license_no = 'HMO-2023-14-R', status = 'Active' WHERE name = 'EastWest Healthcare, Inc.';
UPDATE hmo_providers SET license_no = 'HMO-2023-13-R', status = 'Active' WHERE name = 'Getwell Health Systems, Inc.';
UPDATE hmo_providers SET license_no = 'HMO-2024-04-R', status = 'Active' WHERE name = 'Health Care and Development Corporation of the Philippines';
UPDATE hmo_providers SET license_no = 'HMO-2024-06-R', status = 'Active' WHERE name = 'Health Delivery System, Inc.';
UPDATE hmo_providers SET license_no = 'HMO-2023-02-R', status = 'Active' WHERE name = 'Health Maintenance, Inc.';
UPDATE hmo_providers SET license_no = 'HMO-2023-04-R', status = 'Active' WHERE name = 'Health Plan Philippines, Inc.';
UPDATE hmo_providers SET license_no = 'HMO-2023-01-R', status = 'Active' WHERE name = 'IMS Wellth Care, Inc.';
UPDATE hmo_providers SET license_no = 'HMO-2023-03-R', status = 'Active' WHERE name = 'Insular Health Care, Inc.';
UPDATE hmo_providers SET license_no = 'HMO-2023-08-R', status = 'Active' WHERE name = 'Kaiser International Healthgroup, Inc.';
UPDATE hmo_providers SET license_no = 'HMO-2023-16-R', status = 'Active' WHERE name = 'Life & Health HMP, Inc.';
UPDATE hmo_providers SET license_no = 'HMO-2023-07-R', status = 'Active' WHERE name = 'Maxicare Healthcare Corp.';
UPDATE hmo_providers SET license_no = 'HMO-2023-12-R', status = 'Active' WHERE name = 'Medicard Philippines, Inc.';
UPDATE hmo_providers SET license_no = 'HMO-2023-21-R', status = 'Active' WHERE name = 'Medicare Plus, Inc.';
UPDATE hmo_providers SET license_no = 'HMO-2023-17-R', status = 'Active' WHERE name = 'Medocare Health Systems, Inc.';
UPDATE hmo_providers SET license_no = 'HMO-2023-11-R', status = 'Active' WHERE name = 'Metrocare Health Systems, Incorporated';
UPDATE hmo_providers SET license_no = 'HMO-2024-05-O', status = 'Active' WHERE name = 'MI Healthcare Inc.';
UPDATE hmo_providers SET license_no = 'HMO-2023-19-R', status = 'Active' WHERE name = 'Optimum Medical and Healthcare Services Inc.';
UPDATE hmo_providers SET license_no = 'HMO-2023-10-R', status = 'Active' WHERE name = 'Pacific Cross Health Care, Inc.';
UPDATE hmo_providers SET license_no = 'HMO-2023-15-R', status = 'Active' WHERE name = 'PhilhealthCare, Inc. (PhilCare)';
UPDATE hmo_providers SET license_no = 'HMO-2023-18-R', status = 'Active' WHERE name = 'Value Care Health Systems, Inc.';
UPDATE hmo_providers SET license_no = 'HMO-2024-02-R', status = 'Active' WHERE name = 'Wellcare Health Maintenance Inc.';

-- 5) remove everything that is not on the list
DELETE FROM hmo_providers WHERE name NOT IN (
  'Asalus Corporation (Intellicare)',
  'Asiancare Health Systems, Inc.',
  'Avega Managed Care, Inc.',
  'Carewell Health Systems, Inc.',
  'Cooperative Health Management Federation',
  'Dynamic Care Corporation',
  'EastWest Healthcare, Inc.',
  'Getwell Health Systems, Inc.',
  'Health Care and Development Corporation of the Philippines',
  'Health Delivery System, Inc.',
  'Health Maintenance, Inc.',
  'Health Plan Philippines, Inc.',
  'IMS Wellth Care, Inc.',
  'Insular Health Care, Inc.',
  'Kaiser International Healthgroup, Inc.',
  'Life & Health HMP, Inc.',
  'Maxicare Healthcare Corp.',
  'Medicard Philippines, Inc.',
  'Medicare Plus, Inc.',
  'Medocare Health Systems, Inc.',
  'Metrocare Health Systems, Incorporated',
  'MI Healthcare Inc.',
  'Optimum Medical and Healthcare Services Inc.',
  'Pacific Cross Health Care, Inc.',
  'PhilhealthCare, Inc. (PhilCare)',
  'Value Care Health Systems, Inc.',
  'Wellcare Health Maintenance Inc.'
);
