-- =====================================================
-- CID UNITS MIGRATION
-- SDO ATLAS - Add CID sub-units routing to CID Chief
-- Created: 2026-01-29
-- =====================================================
-- This migration adds 4 units under CID division
-- All CID units route directly to CID_CHIEF (role_id=4)
-- Units: Instructional Management, Learning Resource Management, Alternative Learning System, District Instructional Supervision
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+08:00";

-- =========================
-- ADD CID UNITS TO sdo_offices
-- =========================
-- All units under CID division (parent_office_id = 3)
-- All route to CID_CHIEF (approver_role_id = 4)
-- =========================

INSERT INTO sdo_offices (office_code, office_name, office_type, parent_office_id, approver_role_id, is_osds_unit, sort_order, is_active) VALUES
-- CID Units (all under CID Chief - role_id=4)
('IM', 'Instructional Management', 'unit', 3, 4, 0, 50, 1),
('LRM', 'Learning Resource Management', 'unit', 3, 4, 0, 51, 1),
('ALS', 'Alternative Learning System', 'unit', 3, 4, 0, 52, 1),
('DIS', 'District Instructional Supervision', 'unit', 3, 4, 0, 53, 1);

-- =========================
-- ADD CID UNITS TO unit_routing_config
-- =========================
-- Map each unit to CID_CHIEF as recommending authority
-- =========================

INSERT INTO unit_routing_config (unit_name, unit_display_name, approver_role_id, travel_scope, is_active, sort_order, office_id) 
SELECT 
    o.office_code,
    o.office_name,
    4, -- CID_CHIEF role_id
    'all',
    1,
    o.sort_order,
    o.id
FROM sdo_offices o
WHERE o.office_code IN ('IM', 'LRM', 'ALS', 'DIS')
ON DUPLICATE KEY UPDATE 
    unit_display_name = VALUES(unit_display_name),
    approver_role_id = VALUES(approver_role_id),
    office_id = VALUES(office_id);

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
    OR (u.employee_office = 'Instructional Management' AND o.office_code = 'IM')
    OR (u.employee_office = 'Learning Resource Management' AND o.office_code = 'LRM')
    OR (u.employee_office = 'Alternative Learning System' AND o.office_code = 'ALS')
    OR (u.employee_office = 'District Instructional Supervision' AND o.office_code = 'DIS')
)
SET u.office_id = o.id
WHERE o.office_code IN ('IM', 'LRM', 'ALS', 'DIS');

-- =========================
-- VERIFICATION QUERIES
-- =========================
-- Uncomment to verify the changes
-- 
-- -- Show all CID units
-- SELECT id, office_code, office_name, office_type, parent_office_id, approver_role_id, sort_order
-- FROM sdo_offices 
-- WHERE parent_office_id = 3 OR office_code = 'CID'
-- ORDER BY sort_order;
-- 
-- -- Show routing configuration for CID units
-- SELECT urc.id, urc.unit_name, urc.unit_display_name, urc.approver_role_id, ar.role_name, urc.office_id
-- FROM unit_routing_config urc
-- JOIN admin_roles ar ON urc.approver_role_id = ar.id
-- WHERE urc.approver_role_id = 4
-- ORDER BY urc.sort_order;
-- 
-- -- Show users from CID units
-- SELECT u.id, u.employee_name, u.employee_office, u.office_id, o.office_code, o.office_name
-- FROM admin_users u
-- LEFT JOIN sdo_offices o ON u.office_id = o.id
-- WHERE o.parent_office_id = 3 OR o.office_code = 'CID';

COMMIT;

-- =========================
-- NOTES:
-- =========================
-- After running this migration:
-- 1. Users from any of the 4 CID units will have their requests routed to CID_CHIEF only
-- 2. CID_CHIEF must recommend before going to ASDS for final approval
-- 3. Update admin_config.php SDO_OFFICES constant to include new office codes:
--    'IM', 'LRM', 'ALS', 'DIS'
-- 4. Update admin_config.php ROLE_OFFICE_MAP to map these units to ROLE_CID_CHIEF
-- 5. Update admin_config.php UNIT_HEAD_OFFICES to include these units under ROLE_CID_CHIEF
-- 6. Registration form should allow users to select these new units
