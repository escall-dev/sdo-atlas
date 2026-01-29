-- =====================================================
-- SGOD SHN LEAF-ONLY (DENTAL / MEDICAL)
-- SDO ATLAS - Show only SHN sub-sections (no parent SHN)
-- Created: 2026-01-29
-- =====================================================
-- Ensures SGOD has ONLY the leaf entries:
-- - SHN_DENTAL  : School Health and Nutrition (Dental)
-- - SHN_MEDICAL : School Health and Nutrition (Medical)
-- and hides/deactivates the parent SHN entry if it exists.
-- Routes to SGOD_CHIEF (role_id=5)
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+08:00";

-- SGOD division id
SET @sgod_id := (SELECT id FROM sdo_offices WHERE office_code = 'SGOD' AND is_active = 1 LIMIT 1);

-- Deactivate parent SHN if it exists (so it won't show in dropdowns)
UPDATE sdo_offices
SET is_active = 0
WHERE office_code = 'SHN';

-- Create/ensure the leaf entries directly under SGOD
INSERT INTO sdo_offices (office_code, office_name, office_type, parent_office_id, approver_role_id, is_osds_unit, sort_order, is_active)
SELECT 'SHN_DENTAL', 'School Health and Nutrition (Dental)', 'unit', @sgod_id, 5, 0, 47, 1
WHERE @sgod_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM sdo_offices WHERE office_code = 'SHN_DENTAL');

INSERT INTO sdo_offices (office_code, office_name, office_type, parent_office_id, approver_role_id, is_osds_unit, sort_order, is_active)
SELECT 'SHN_MEDICAL', 'School Health and Nutrition (Medical)', 'unit', @sgod_id, 5, 0, 48, 1
WHERE @sgod_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM sdo_offices WHERE office_code = 'SHN_MEDICAL');

-- If they already exist, force correct parent + active + role
UPDATE sdo_offices
SET parent_office_id = @sgod_id,
    approver_role_id = 5,
    is_active = 1
WHERE office_code IN ('SHN_DENTAL', 'SHN_MEDICAL')
  AND @sgod_id IS NOT NULL;

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

