-- =====================================================
-- OSDS FINANCE LEAF-ONLY (ACCOUNTING / BUDGET)
-- SDO ATLAS - Hide Finance parent, keep Finance (Accounting/Budget) only
-- Created: 2026-01-29
-- =====================================================
-- Ensures:
-- - Finance (parent) is not shown/used (deactivated if exists)
-- - Accounting and Budget remain as OSDS units with display names:
--     Finance (Accounting)
--     Finance (Budget)
-- Routes to AO V / OSDS Chief (role_id=3)
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+08:00";

-- Deactivate Finance parent if it exists
UPDATE sdo_offices
SET is_active = 0
WHERE office_code = 'Finance';

-- Ensure Accounting and Budget are active OSDS units and labeled correctly
UPDATE sdo_offices
SET office_name = 'Finance (Accounting)',
    approver_role_id = 3,
    is_osds_unit = 1,
    is_active = 1
WHERE office_code = 'Accounting';

UPDATE sdo_offices
SET office_name = 'Finance (Budget)',
    approver_role_id = 3,
    is_osds_unit = 1,
    is_active = 1
WHERE office_code = 'Budget';

-- Ensure routing config exists (idempotent)
INSERT INTO unit_routing_config (unit_name, unit_display_name, approver_role_id, travel_scope, is_active, sort_order, office_id)
SELECT o.office_code, o.office_name, 3, 'all', 1, o.sort_order, o.id
FROM sdo_offices o
WHERE o.office_code IN ('Accounting', 'Budget')
ON DUPLICATE KEY UPDATE
  unit_display_name = VALUES(unit_display_name),
  approver_role_id = VALUES(approver_role_id),
  office_id = VALUES(office_id),
  travel_scope = VALUES(travel_scope),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

COMMIT;

