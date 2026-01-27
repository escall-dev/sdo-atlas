-- =====================================================
-- MASTER OFFICES TABLE MIGRATION
-- SDO ATLAS - Single Source of Truth for Offices/Units
-- Created: 2026-01-26
-- =====================================================
-- This migration creates the sdo_offices table as the 
-- master reference for all offices and units, fixing
-- data inconsistency between registration and user management
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+08:00";

-- =========================
-- SDO OFFICES MASTER TABLE
-- =========================
DROP TABLE IF EXISTS sdo_offices;
CREATE TABLE sdo_offices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  office_code VARCHAR(50) NOT NULL UNIQUE COMMENT 'Short code for the office (e.g., ICT, CID)',
  office_name VARCHAR(150) NOT NULL COMMENT 'Full display name',
  office_type ENUM('executive', 'division', 'section', 'unit') NOT NULL DEFAULT 'section',
  parent_office_id INT DEFAULT NULL COMMENT 'Parent office for hierarchical structure',
  approver_role_id INT DEFAULT NULL COMMENT 'Role ID of recommending authority for this office',
  is_osds_unit TINYINT(1) DEFAULT 0 COMMENT 'Flag for OSDS units under AO V',
  sort_order INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (parent_office_id) REFERENCES sdo_offices(id) ON DELETE SET NULL,
  FOREIGN KEY (approver_role_id) REFERENCES admin_roles(id),
  INDEX idx_code (office_code),
  INDEX idx_type (office_type),
  INDEX idx_osds (is_osds_unit),
  INDEX idx_approver (approver_role_id),
  INDEX idx_active (is_active)
) COMMENT='Master table for all SDO offices and units';

-- =========================
-- SEED DATA: ALL OFFICES
-- =========================
INSERT INTO sdo_offices (id, office_code, office_name, office_type, parent_office_id, approver_role_id, is_osds_unit, sort_order) VALUES
-- Executive Offices (no recommending authority - go direct to ASDS)
(1, 'SDS', 'Office of the Schools Division Superintendent', 'executive', NULL, NULL, 0, 1),
(2, 'ASDS', 'Office of the Assistant Schools Division Superintendent', 'executive', NULL, NULL, 0, 2),

-- Divisions
(3, 'CID', 'Curriculum Implementation Division', 'division', NULL, 4, 0, 10),
(4, 'SGOD', 'School Governance and Operations Division', 'division', NULL, 5, 0, 11),

-- OSDS Units (all under AO V / OSDS Chief - role_id=3)
(10, 'OSDS', 'Office of the Schools Division Superintendent Staff', 'unit', 1, 3, 1, 20),
(11, 'Personnel', 'Personnel Section', 'section', NULL, 3, 1, 21),
(12, 'Property and Supply', 'Property and Supply Section', 'section', NULL, 3, 1, 22),
(13, 'Records', 'Records Section', 'section', NULL, 3, 1, 23),
(14, 'Procurement', 'Procurement Section', 'section', NULL, 3, 1, 24),
(15, 'General Services', 'General Services Section', 'section', NULL, 3, 1, 25),
(16, 'Legal', 'Legal Unit', 'unit', NULL, 3, 1, 26),
(17, 'ICT', 'Information and Communication Technology Unit', 'unit', NULL, 3, 1, 27),
(18, 'Finance', 'Finance Division', 'division', NULL, 3, 1, 30),
(19, 'Accounting', 'Accounting Section', 'section', 18, 3, 1, 31),
(20, 'Budget', 'Budget Section', 'section', 18, 3, 1, 32);

-- =========================
-- ADD office_id TO admin_users
-- =========================
ALTER TABLE admin_users 
ADD COLUMN office_id INT DEFAULT NULL AFTER employee_office,
ADD CONSTRAINT fk_user_office FOREIGN KEY (office_id) REFERENCES sdo_offices(id) ON DELETE SET NULL;

-- Create index for office_id
CREATE INDEX idx_user_office_id ON admin_users(office_id);

-- =========================
-- MIGRATE EXISTING DATA
-- Update office_id based on existing employee_office text
-- =========================
UPDATE admin_users u
JOIN sdo_offices o ON (
    u.employee_office = o.office_code 
    OR u.employee_office = o.office_name
    OR (u.employee_office = 'Supply' AND o.office_code = 'Property and Supply')
    OR (u.employee_office = 'Property' AND o.office_code = 'Property and Supply')
    -- ICTO alias removed per latest office list
)
SET u.office_id = o.id
WHERE u.office_id IS NULL;

-- =========================
-- UPDATE unit_routing_config TO USE office_id
-- =========================
ALTER TABLE unit_routing_config 
ADD COLUMN office_id INT DEFAULT NULL AFTER unit_name,
ADD CONSTRAINT fk_routing_office FOREIGN KEY (office_id) REFERENCES sdo_offices(id) ON DELETE CASCADE;

-- Link existing routing configs to office IDs
UPDATE unit_routing_config urc
JOIN sdo_offices o ON urc.unit_name = o.office_code
SET urc.office_id = o.id;

-- =========================
-- UPDATE authority_to_travel TO USE office_id
-- =========================
ALTER TABLE authority_to_travel 
ADD COLUMN requester_office_id INT DEFAULT NULL AFTER requester_office,
ADD CONSTRAINT fk_at_office FOREIGN KEY (requester_office_id) REFERENCES sdo_offices(id) ON DELETE SET NULL;

-- Create index
CREATE INDEX idx_at_office_id ON authority_to_travel(requester_office_id);

-- Migrate existing data
UPDATE authority_to_travel at
JOIN sdo_offices o ON (
    at.requester_office = o.office_code 
    OR at.requester_office = o.office_name
    OR (at.requester_office = 'Supply' AND o.office_code = 'Property and Supply')
    OR (at.requester_office = 'Property' AND o.office_code = 'Property and Supply')
    -- ICTO alias removed per latest office list
)
SET at.requester_office_id = o.id
WHERE at.requester_office_id IS NULL;

COMMIT;
