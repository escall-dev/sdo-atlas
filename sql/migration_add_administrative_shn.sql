-- Migration: Add "Administrative" unit under OSDS and "School Health and Nutrition" (SHN) unit under SGOD
-- Date: 2026-02-10

-- 1. Add "Administrative" to sdo_offices under OSDS (parent_office_id = 1, approver_role_id = 3 = OSDS_CHIEF, is_osds_unit = 1)
INSERT INTO `sdo_offices` (`office_code`, `office_name`, `office_type`, `parent_office_id`, `approver_role_id`, `is_osds_unit`, `sort_order`, `is_active`, `created_at`, `updated_at`)
VALUES ('Administrative', 'Administrative', 'section', 1, 3, 1, 33, 1, NOW(), NOW());

-- 2. Add "SHN" (School Health and Nutrition) to sdo_offices under SGOD (parent_office_id = 4, approver_role_id = 5 = SGOD_CHIEF, is_osds_unit = 0)
INSERT INTO `sdo_offices` (`office_code`, `office_name`, `office_type`, `parent_office_id`, `approver_role_id`, `is_osds_unit`, `sort_order`, `is_active`, `created_at`, `updated_at`)
VALUES ('SHN', 'School Health and Nutrition', 'unit', 4, 5, 0, 46, 1, NOW(), NOW());

-- 3. Add "Administrative" to unit_routing_config (office_id will be set from the insert above)
INSERT INTO `unit_routing_config` (`unit_name`, `office_id`, `unit_display_name`, `approver_role_id`, `travel_scope`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 'Administrative', id, 'Administrative', 3, 'all', 1, 33, NOW(), NOW()
FROM `sdo_offices` WHERE `office_code` = 'Administrative' LIMIT 1;

-- 4. Add "SHN" to unit_routing_config (office_id will be set from the insert above)
INSERT INTO `unit_routing_config` (`unit_name`, `office_id`, `unit_display_name`, `approver_role_id`, `travel_scope`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT 'SHN', id, 'School Health and Nutrition', 5, 'all', 1, 46, NOW(), NOW()
FROM `sdo_offices` WHERE `office_code` = 'SHN' LIMIT 1;
