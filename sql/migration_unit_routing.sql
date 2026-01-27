-- =====================================================
-- UNIT ROUTING CONFIGURATION MIGRATION
-- SDO ATLAS - Authority to Travel Routing Update
-- Created: 2026-01-26
-- =====================================================
-- This migration creates the unit_routing_config table
-- to support database-driven unit-to-approver mapping
-- with OSDS Chief as sole approving authority for all OSDS units
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+08:00";

-- =========================
-- UNIT ROUTING CONFIGURATION
-- =========================
DROP TABLE IF EXISTS unit_routing_config;
CREATE TABLE unit_routing_config (
  id INT AUTO_INCREMENT PRIMARY KEY,
  unit_name VARCHAR(100) NOT NULL UNIQUE COMMENT 'Unit/Section name matching employee_office in admin_users',
  unit_display_name VARCHAR(150) NOT NULL COMMENT 'Full display name for UI',
  approver_role_id INT NOT NULL COMMENT 'Role ID of recommending authority',
  travel_scope ENUM('all', 'local', 'international') DEFAULT 'all' COMMENT 'Travel scope this routing applies to',
  is_active TINYINT(1) DEFAULT 1,
  sort_order INT DEFAULT 0 COMMENT 'Display order in admin panel',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (approver_role_id) REFERENCES admin_roles(id),
  INDEX idx_unit_name (unit_name),
  INDEX idx_approver (approver_role_id),
  INDEX idx_active (is_active)
) COMMENT='Maps units/sections to their approving authority for AT routing';

-- =========================
-- SEED DATA: OSDS UNITS
-- All mapped to OSDS_CHIEF (role_id=3)
-- =========================
INSERT INTO unit_routing_config (unit_name, unit_display_name, approver_role_id, travel_scope, is_active, sort_order) VALUES
-- OSDS Units (all under AO V / OSDS Chief)
('Personnel', 'Personnel Section', 3, 'all', 1, 1),
('Property', 'Property and Supply Section', 3, 'all', 1, 2),
('Supply', 'Property and Supply Section', 3, 'all', 1, 3),
('Records', 'Records Section', 3, 'all', 1, 4),
('Cash', 'Cash Section', 3, 'all', 1, 5),
('Cashier', 'Cash Section', 3, 'all', 1, 6),
('Procurement', 'Procurement Section', 3, 'all', 1, 7),
('General Services', 'General Services Section', 3, 'all', 1, 8),
('Legal', 'Legal Unit', 3, 'all', 1, 9),
('ICT', 'Information and Communication Technology Unit', 3, 'all', 1, 10),
('ICTO', 'Information and Communication Technology Office', 3, 'all', 1, 11),
('Finance', 'Finance Division', 3, 'all', 1, 12),
('Accounting', 'Accounting Section (under Finance)', 3, 'all', 1, 13),
('Budget', 'Budget Section (under Finance)', 3, 'all', 1, 14),
('Admin', 'Administrative Division', 3, 'all', 1, 15),
('HR', 'Human Resource Section', 3, 'all', 1, 16),
('OSDS', 'Office of the Schools Division Superintendent', 3, 'all', 1, 17),

-- CID Unit (under CID Chief)
('CID', 'Curriculum Implementation Division', 4, 'all', 1, 18),

-- SGOD Unit (under SGOD Chief)
('SGOD', 'School Governance and Operations Division', 5, 'all', 1, 19);

-- =========================
-- UPDATE SDO_OFFICES IN DB (if using db-driven offices)
-- For reference - these values should match unit_routing_config.unit_name
-- =========================
-- Note: SDO_OFFICES constant in admin_config.php will be updated separately
-- to include new units: Procurement, General Services, Legal, ICT, etc.

COMMIT;
