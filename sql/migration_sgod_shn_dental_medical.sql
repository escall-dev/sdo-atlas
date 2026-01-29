-- =====================================================
-- SGOD SHN SUB-SECTIONS (DENTAL / MEDICAL)
-- SDO ATLAS - Add SHN sub-sections under School Health and Nutrition
-- Created: 2026-01-29
-- =====================================================
-- Adds two sub-sections under SGOD -> SHN:
-- - SHN_DENTAL  : School Health and Nutrition (Dental)
-- - SHN_MEDICAL : School Health and Nutrition (Medical)
-- Routes to SGOD_CHIEF (role_id=5)
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+08:00";

-- Find parent ids
-- SGOD division id
SET @sgod_id := (SELECT id FROM sdo_offices WHERE office_code = 'SGOD' AND is_active = 1 LIMIT 1);
-- SHN unit id (child of SGOD)
SET @shn_id := (SELECT id FROM sdo_offices WHERE office_code = 'SHN' AND is_active = 1 LIMIT 1);

-- Add Dental/Medical as children of SHN (if SHN exists)
INSERT INTO sdo_offices (office_code, office_name, office_type, parent_office_id, approver_role_id, is_osds_unit, sort_order, is_active)
SELECT 'SHN_DENTAL', 'School Health and Nutrition (Dental)', 'section', @shn_id, 5, 0, 47, 1
WHERE @shn_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM sdo_offices WHERE office_code = 'SHN_DENTAL');

INSERT INTO sdo_offices (office_code, office_name, office_type, parent_office_id, approver_role_id, is_osds_unit, sort_order, is_active)
SELECT 'SHN_MEDICAL', 'School Health and Nutrition (Medical)', 'section', @shn_id, 5, 0, 48, 1
WHERE @shn_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM sdo_offices WHERE office_code = 'SHN_MEDICAL');

-- Ensure routing config exists (idempotent)
INSERT INTO unit_routing_config (unit_name, unit_display_name, approver_role_id, travel_scope, is_active, sort_order, office_id) 
SELECT o.office_code, o.office_name, 5, 'all', 1, o.sort_order, o.id
FROM sdo_offices o
WHERE o.office_code IN ('SHN_DENTAL', 'SHN_MEDICAL')
ON DUPLICATE KEY UPDATE 
  unit_display_name = VALUES(unit_display_name),
  approver_role_id = VALUES(approver_role_id),
  office_id = VALUES(office_id),
  travel_scope = VALUES(travel_scope),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

COMMIT;

