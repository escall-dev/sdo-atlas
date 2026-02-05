-- =====================================================
-- Migration: Add approving_time column to authority_to_travel
-- SDO ATLAS - Stores timestamp when AT request is approved
-- Run this migration on existing databases
-- =====================================================

-- Add approving_time column to authority_to_travel table
ALTER TABLE authority_to_travel 
ADD COLUMN approving_time DATETIME DEFAULT NULL 
AFTER approval_date;

-- Optional: Update existing approved records to set approving_time from approval_date
-- This sets the time to midnight on the approval date for historical records
UPDATE authority_to_travel 
SET approving_time = CONCAT(approval_date, ' 00:00:00')
WHERE status = 'approved' 
AND approving_time IS NULL 
AND approval_date IS NOT NULL;
