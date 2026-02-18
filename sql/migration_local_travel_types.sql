-- Migration: Consolidate travel_scope to local + international
-- Within Region and Outside Region are now both "local" scope with a travel_type differentiator
-- travel_type: 'within_region' or 'outside_region'

-- 1. Add travel_type column to authority_to_travel
ALTER TABLE authority_to_travel
    ADD COLUMN travel_type VARCHAR(30) DEFAULT NULL
    COMMENT 'Local travel type: within_region or outside_region'
    AFTER travel_scope;

-- 2. Backfill travel_type for existing records
-- Existing 'local' records become within_region
UPDATE authority_to_travel
    SET travel_type = 'within_region'
    WHERE travel_scope = 'local';

-- Existing 'national' records become local + outside_region
UPDATE authority_to_travel
    SET travel_type = 'outside_region',
        travel_scope = 'local'
    WHERE travel_scope = 'national';

-- 3. Update travel_scope ENUM to remove 'national'
ALTER TABLE authority_to_travel
    MODIFY COLUMN travel_scope ENUM('local', 'international') DEFAULT NULL;

-- 4. Update unit_routing_config travel_scope ENUM (remove 'national' if present)
ALTER TABLE unit_routing_config
    MODIFY COLUMN travel_scope ENUM('all', 'local', 'international') DEFAULT 'all'
    COMMENT 'Travel scope this routing applies to';
