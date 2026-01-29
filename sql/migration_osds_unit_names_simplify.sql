-- =====================================================
-- OSDS UNIT NAMES SIMPLIFY
-- SDO ATLAS - Remove 'Section/Unit/Division' suffixes from OSDS unit names
-- Created: 2026-01-29
-- =====================================================
-- This migration updates sdo_offices.office_name so OSDS units show only
-- the bare unit name in dropdowns (no 'Section', 'Unit', 'Division').
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+08:00";

-- Personnel -> Personnel
UPDATE sdo_offices
SET office_name = 'Personnel'
WHERE office_code = 'Personnel';

-- Property and Supply -> Property and Supply
UPDATE sdo_offices
SET office_name = 'Property and Supply'
WHERE office_code = 'Property and Supply';

-- Records -> Records
UPDATE sdo_offices
SET office_name = 'Records'
WHERE office_code = 'Records';

-- Procurement -> Procurement
UPDATE sdo_offices
SET office_name = 'Procurement'
WHERE office_code = 'Procurement';

-- General Services -> General Services
UPDATE sdo_offices
SET office_name = 'General Services'
WHERE office_code = 'General Services';

-- Legal -> Legal
UPDATE sdo_offices
SET office_name = 'Legal'
WHERE office_code = 'Legal';

-- Information and Communication Technology -> Information and Communication Technology
UPDATE sdo_offices
SET office_name = 'Information and Communication Technology'
WHERE office_code = 'ICT';

-- Finance -> Finance
UPDATE sdo_offices
SET office_name = 'Finance'
WHERE office_code = 'Finance';

-- Accounting -> Accounting
UPDATE sdo_offices
SET office_name = 'Accounting'
WHERE office_code = 'Accounting';

-- Budget -> Budget
UPDATE sdo_offices
SET office_name = 'Budget'
WHERE office_code = 'Budget';

COMMIT;

-- Notes:
-- 1. Safe to run multiple times (idempotent) since each UPDATE just re-applies
--    the desired simple name.
-- 2. UI dropdowns that use office_name will now show names without extensions.

