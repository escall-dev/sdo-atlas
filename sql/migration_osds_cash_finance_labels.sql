-- =====================================================
-- OSDS CASH + FINANCE LABELS
-- SDO ATLAS - Add missing Cash unit and label Finance sub-sections
-- Created: 2026-01-29
-- =====================================================
-- Adds OSDS "Cash" unit (if missing) and updates OSDS office_name values
-- to remove "Section/Unit/Division" extensions, including:
-- - Accounting -> Finance (Accounting)
-- - Budget -> Finance (Budget)
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+08:00";

-- Insert Cash unit if missing (OSDS unit -> is_osds_unit=1, routes to AO V / role_id=3)
INSERT INTO sdo_offices (office_code, office_name, office_type, parent_office_id, approver_role_id, is_osds_unit, sort_order, is_active)
SELECT 'Cash', 'Cash', 'section',
       (SELECT id FROM sdo_offices WHERE office_code = 'SDS' LIMIT 1),
       3, 1, 28, 1
WHERE NOT EXISTS (SELECT 1 FROM sdo_offices WHERE office_code = 'Cash');

-- Normalize OSDS unit display names (no "Section/Unit/Division")
UPDATE sdo_offices SET office_name = 'Personnel' WHERE office_code = 'Personnel';
UPDATE sdo_offices SET office_name = 'Property and Supply' WHERE office_code = 'Property and Supply';
UPDATE sdo_offices SET office_name = 'Records' WHERE office_code = 'Records';
UPDATE sdo_offices SET office_name = 'Cash' WHERE office_code = 'Cash';
UPDATE sdo_offices SET office_name = 'Procurement' WHERE office_code = 'Procurement';
UPDATE sdo_offices SET office_name = 'General Services' WHERE office_code = 'General Services';
UPDATE sdo_offices SET office_name = 'Legal' WHERE office_code = 'Legal';
UPDATE sdo_offices SET office_name = 'Information and Communication Technology' WHERE office_code = 'ICT';
UPDATE sdo_offices SET office_name = 'Finance' WHERE office_code = 'Finance';
UPDATE sdo_offices SET office_name = 'Finance (Accounting)' WHERE office_code = 'Accounting';
UPDATE sdo_offices SET office_name = 'Finance (Budget)' WHERE office_code = 'Budget';

-- Ensure routing config exists for Cash (idempotent)
INSERT INTO unit_routing_config (unit_name, unit_display_name, approver_role_id, travel_scope, is_active, sort_order, office_id)
SELECT o.office_code, o.office_name, 3, 'all', 1, o.sort_order, o.id
FROM sdo_offices o
WHERE o.office_code = 'Cash'
ON DUPLICATE KEY UPDATE
  unit_display_name = VALUES(unit_display_name),
  approver_role_id = VALUES(approver_role_id),
  office_id = VALUES(office_id),
  travel_scope = VALUES(travel_scope),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

COMMIT;

