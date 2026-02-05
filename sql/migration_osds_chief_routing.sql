-- =====================================================
-- Migration: Seed OSDS Chief and Routing Configuration
-- SDO ATLAS - Paul Jeremy I. Aguja as OSDS Chief (Locator Slip Approver)
-- Run this migration on existing databases
-- =====================================================

-- Add SDS role if not exists
INSERT IGNORE INTO admin_roles (id, role_name, description, permissions) VALUES
(7, 'SDS', 'Schools Division Superintendent - Final approver for all travel requests', '{"requests.view": true, "requests.final_approve": true, "logs.view": true, "analytics.view": true}');

-- Add/Update OSDS Chief (Paul Jeremy I. Aguja) - Locator Slip Approver
-- Password: sdoescall (same hash as System Administrator)
INSERT INTO admin_users (role_id, full_name, employee_position, employee_office, email, password_hash, status, is_active) 
VALUES (3, 'Paul Jeremy I. Aguja', 'Administrative Officer V', 'OSDS', 'aov@deped.gov.ph', '$2y$10$i9CNq.Jmk./rwMFDdjmyLeCfp6xaYmHyczadTu5Ppo8p6ZJrwxQDm', 'active', 1)
ON DUPLICATE KEY UPDATE 
    full_name = VALUES(full_name),
    employee_position = VALUES(employee_position),
    employee_office = VALUES(employee_office),
    role_id = VALUES(role_id);

-- =====================================================
-- Routing Configuration for OSDS units
-- All OSDS units route to OSDS_CHIEF (role_id=3) for Locator Slips and AT
-- =====================================================

-- Insert routing config entries for OSDS units
INSERT INTO unit_routing_config (unit_name, unit_display_name, office_id, approver_role_id, travel_scope, is_active, sort_order) VALUES
('OSDS', 'Office of the Schools Division Superintendent', NULL, 3, 'all', 1, 1),
('Personnel', 'Personnel Services', NULL, 3, 'all', 1, 2),
('Property and Supply', 'Property and Supply Unit', NULL, 3, 'all', 1, 3),
('Records', 'Records Management Unit', NULL, 3, 'all', 1, 4),
('Cash', 'Cash Unit', NULL, 3, 'all', 1, 5),
('Procurement', 'Procurement Division', NULL, 3, 'all', 1, 6),
('General Services', 'General Services Unit', NULL, 3, 'all', 1, 7),
('Legal', 'Legal Division', NULL, 3, 'all', 1, 8),
('ICT', 'Information and Communications Technology', NULL, 3, 'all', 1, 9),
('Accounting', 'Accounting Unit', NULL, 3, 'all', 1, 10),
('Budget', 'Budget Unit', NULL, 3, 'all', 1, 11)
ON DUPLICATE KEY UPDATE 
    approver_role_id = VALUES(approver_role_id),
    is_active = VALUES(is_active);
