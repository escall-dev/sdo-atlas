-- =====================================================
-- SGOD UNITS MIGRATION
-- SDO ATLAS - Add SGOD sub-units routing to SGOD Chief
-- Created: 2026-01-27
-- =====================================================
-- This migration adds 7 units under SGOD division
-- All SGOD units route directly to SGOD_CHIEF (role_id=5)
-- Units based on Frederick G. Byrd Jr's organizational structure
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+08:00";

-- =========================
-- ADD SGOD UNITS TO sdo_offices
-- =========================
-- All units under SGOD division (parent_office_id = 4)
-- All route to SGOD_CHIEF (approver_role_id = 5)
-- =========================

INSERT INTO sdo_offices (office_code, office_name, office_type, parent_office_id, approver_role_id, is_osds_unit, sort_order, is_active) VALUES
-- SGOD Units (all under SGOD Chief - role_id=5)
('SMME', 'School Management Monitoring and Evaluation', 'unit', 4, 5, 0, 40, 1),
('HRD', 'Human Resource Development', 'unit', 4, 5, 0, 41, 1),
('SMN', 'Social Mobilization and Networking', 'unit', 4, 5, 0, 42, 1),
('PR', 'Planning and Research', 'unit', 4, 5, 0, 43, 1),
('DRRM', 'Disaster Risk Reduction and Management', 'unit', 4, 5, 0, 44, 1),
('EF', 'Education Facilities', 'unit', 4, 5, 0, 45, 1),
('SHN', 'School Health and Nutrition', 'unit', 4, 5, 0, 46, 1);

-- =========================
-- ADD SGOD UNITS TO unit_routing_config
-- =========================
-- Map each unit to SGOD_CHIEF as recommending authority
-- =========================

INSERT INTO unit_routing_config (unit_name, unit_display_name, approver_role_id, travel_scope, is_active, sort_order, office_id) 
SELECT 
    o.office_code,
    o.office_name,
    5, -- SGOD_CHIEF role_id
    'all',
    1,
    o.sort_order,
    o.id
FROM sdo_offices o
WHERE o.office_code IN ('SMME', 'HRD', 'SMN', 'PR', 'DRRM', 'EF', 'SHN')
ON DUPLICATE KEY UPDATE 
    unit_display_name = VALUES(unit_display_name),
    approver_role_id = VALUES(approver_role_id),
    office_id = VALUES(office_id);

-- Update existing SGOD entry to have correct parent hierarchy
UPDATE sdo_offices 
SET office_type = 'division',
    office_name = 'School Governance and Operations Division (Main Office)',
    approver_role_id = 5
WHERE office_code = 'SGOD';

-- =========================
-- UPDATE EXISTING USERS
-- =========================
-- If any users already have these office names in employee_office,
-- map them to the new office_id
-- =========================

UPDATE admin_users u
JOIN sdo_offices o ON (
    u.employee_office = o.office_code 
    OR u.employee_office = o.office_name
    OR (u.employee_office = 'School Management Monitoring and Evaluation' AND o.office_code = 'SMME')
    OR (u.employee_office = 'Human Resource Development' AND o.office_code = 'HRD')
    OR (u.employee_office = 'Social Mobilization and Networking' AND o.office_code = 'SMN')
    OR (u.employee_office = 'Planning and Research' AND o.office_code = 'PR')
    OR (u.employee_office = 'Disaster Risk Reduction and Management' AND o.office_code = 'DRRM')
    OR (u.employee_office = 'Education Facilities' AND o.office_code = 'EF')
    OR (u.employee_office = 'School Health and Nutrition' AND o.office_code = 'SHN')
)
SET u.office_id = o.id
WHERE o.office_code IN ('SMME', 'HRD', 'SMN', 'PR', 'DRRM', 'EF', 'SHN');

-- =========================
-- VERIFICATION QUERIES
-- =========================
-- Uncomment to verify the changes
-- 
-- -- Show all SGOD units
-- SELECT id, office_code, office_name, office_type, parent_office_id, approver_role_id, sort_order
-- FROM sdo_offices 
-- WHERE parent_office_id = 4 OR office_code = 'SGOD'
-- ORDER BY sort_order;
-- 
-- -- Show routing configuration for SGOD units
-- SELECT urc.id, urc.unit_name, urc.unit_display_name, urc.approver_role_id, ar.role_name, urc.office_id
-- FROM unit_routing_config urc
-- JOIN admin_roles ar ON urc.approver_role_id = ar.id
-- WHERE urc.approver_role_id = 5
-- ORDER BY urc.sort_order;
-- 
-- -- Show users from SGOD units
-- SELECT u.id, u.employee_name, u.employee_office, u.office_id, o.office_code, o.office_name
-- FROM admin_users u
-- LEFT JOIN sdo_offices o ON u.office_id = o.id
-- WHERE o.parent_office_id = 4 OR o.office_code = 'SGOD';

COMMIT;

-- =========================
-- NOTES:
-- =========================
-- After running this migration:
-- 1. Users from any of the 7 SGOD units will have their requests routed to SGOD_CHIEF only
-- 2. SGOD_CHIEF must recommend before going to ASDS for final approval
-- 3. Update admin_config.php SDO_OFFICES constant to include new office codes:
--    'SMME', 'HRD', 'SMN', 'PR', 'DRRM', 'EF', 'SHN'
-- 4. Registration form should allow users to select these new units
