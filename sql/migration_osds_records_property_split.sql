-- =====================================================
-- OSDS RECORDS / PROPERTY & SUPPLY FIX
-- SDO ATLAS - Align OSDS units with correct structure
-- Created: 2026-01-29
-- =====================================================
-- This migration ensures that:
-- 1) Property and Supply is a single section
-- 2) Records is a separate section
-- 3) Any legacy references to "Records and Supply" are mapped correctly
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+08:00";

-- =========================
-- ENSURE MASTER OFFICES ROWS EXIST
-- =========================

-- Insert Property and Supply if missing
INSERT INTO sdo_offices (office_code, office_name, office_type, parent_office_id, approver_role_id, is_osds_unit, sort_order, is_active)
SELECT 'Property and Supply', 'Property and Supply Section', 'section',
       (SELECT id FROM sdo_offices WHERE office_code = 'SDS' LIMIT 1),
       3, 1, 22, 1
WHERE NOT EXISTS (
    SELECT 1 FROM sdo_offices WHERE office_code = 'Property and Supply'
);

-- Insert Records if missing
INSERT INTO sdo_offices (office_code, office_name, office_type, parent_office_id, approver_role_id, is_osds_unit, sort_order, is_active)
SELECT 'Records', 'Records Section', 'section',
       (SELECT id FROM sdo_offices WHERE office_code = 'SDS' LIMIT 1),
       3, 1, 23, 1
WHERE NOT EXISTS (
    SELECT 1 FROM sdo_offices WHERE office_code = 'Records'
);

-- =========================
-- HANDLE LEGACY "RECORDS AND SUPPLY" TEXT
-- =========================
-- Business rule: legacy \"Records and Supply\" entries are treated as
-- Property and Supply section, while Records is a separate unit.
-- =========================

-- Map admin_users.employee_office = 'Records and Supply' to Property and Supply office_id
UPDATE admin_users u
JOIN sdo_offices o ON o.office_code = 'Property and Supply'
SET u.office_id = o.id
WHERE u.employee_office = 'Records and Supply'
  AND (u.office_id IS NULL OR u.office_id != o.id);

-- Map authority_to_travel.requester_office = 'Records and Supply' to Property and Supply office_id
UPDATE authority_to_travel at
JOIN sdo_offices o ON o.office_code = 'Property and Supply'
SET at.requester_office_id = o.id
WHERE at.requester_office = 'Records and Supply'
  AND (at.requester_office_id IS NULL OR at.requester_office_id != o.id);

-- =========================
-- CLEAN UP OPTIONAL LEGACY ROW
-- =========================
-- If a combined "Records and Supply" office row exists, deactivate it
-- so only the separate Property and Supply + Records units are used.
-- =========================

UPDATE sdo_offices
SET is_active = 0
WHERE office_code = 'Records and Supply';

COMMIT;

-- =========================
-- NOTES:
-- =========================
-- 1. Config constants in config/admin_config.php have been updated to use
--    "Property and Supply" and "Records" separately, not "Records and Supply".
-- 2. This script is safe to run multiple times (idempotent).
-- 3. Existing users and AT records using "Records and Supply" will now be
--    associated with the Property and Supply section.

