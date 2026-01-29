-- =====================================================
-- CID UNIT DIS MIGRATION
-- SDO ATLAS - Add District Instructional Supervision unit under CID
-- Created: 2026-01-29
-- =====================================================
-- This migration adds the DIS unit under CID division
-- Routes directly to CID_CHIEF (role_id=4)
-- Unit: District Instructional Supervision
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+08:00";

-- =========================
-- ADD DIS UNIT TO sdo_offices
-- =========================
-- Unit under CID division (parent_office_id = 3)
-- Routes to CID_CHIEF (approver_role_id = 4)
-- =========================

INSERT INTO sdo_offices (office_code, office_name, office_type, parent_office_id, approver_role_id, is_osds_unit, sort_order, is_active) VALUES
('DIS', 'District Instructional Supervision', 'unit', 3, 4, 0, 53, 1)
ON DUPLICATE KEY UPDATE
    office_name = VALUES(office_name),
    office_type = VALUES(office_type),
    parent_office_id = VALUES(parent_office_id),
    approver_role_id = VALUES(approver_role_id),
    is_osds_unit = VALUES(is_osds_unit),
    sort_order = VALUES(sort_order),
    is_active = VALUES(is_active);

-- =========================
-- ADD DIS UNIT TO unit_routing_config
-- =========================
-- Map DIS unit to CID_CHIEF as recommending authority
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
WHERE o.office_code = 'DIS'
ON DUPLICATE KEY UPDATE 
    unit_display_name = VALUES(unit_display_name),
    approver_role_id = VALUES(approver_role_id),
    office_id = VALUES(office_id),
    travel_scope = VALUES(travel_scope),
    is_active = VALUES(is_active),
    sort_order = VALUES(sort_order);

-- =========================
-- UPDATE EXISTING USERS
-- =========================
-- If any users already have this office name in employee_office,
-- map them to the new office_id
-- =========================

UPDATE admin_users u
JOIN sdo_offices o ON (
    u.employee_office = o.office_code 
    OR u.employee_office = o.office_name
    OR (u.employee_office = 'District Instructional Supervision' AND o.office_code = 'DIS')
)
SET u.office_id = o.id
WHERE o.office_code = 'DIS' AND (u.office_id IS NULL OR u.office_id != o.id);

-- =========================
-- VERIFICATION QUERIES
-- =========================
-- Uncomment to verify the changes
-- 
-- -- Show DIS unit
-- SELECT id, office_code, office_name, office_type, parent_office_id, approver_role_id, sort_order
-- FROM sdo_offices 
-- WHERE office_code = 'DIS';
-- 
-- -- Show routing configuration for DIS unit
-- SELECT urc.id, urc.unit_name, urc.unit_display_name, urc.approver_role_id, ar.role_name, urc.office_id
-- FROM unit_routing_config urc
-- JOIN admin_roles ar ON urc.approver_role_id = ar.id
-- WHERE urc.unit_name = 'DIS';
-- 
-- -- Show users from DIS unit
-- SELECT u.id, u.employee_name, u.employee_office, u.office_id, o.office_code, o.office_name
-- FROM admin_users u
-- LEFT JOIN sdo_offices o ON u.office_id = o.id
-- WHERE o.office_code = 'DIS';

COMMIT;

-- =========================
-- NOTES:
-- =========================
-- After running this migration:
-- 1. Users from DIS unit will have their requests routed to CID_CHIEF only
-- 2. CID_CHIEF must recommend before going to ASDS for final approval
-- 3. This script is safe to run multiple times (idempotent)
-- 4. Existing users with matching office names will be automatically mapped
