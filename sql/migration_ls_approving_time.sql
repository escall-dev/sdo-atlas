-- =====================================================
-- Migration: Add approving_time column to locator_slips
-- SDO ATLAS - Stores timestamp when request is approved
-- Run this migration on existing databases
-- =====================================================

-- Add approving_time column to locator_slips table
ALTER TABLE locator_slips 
ADD COLUMN approving_time DATETIME DEFAULT NULL 
AFTER approval_date;

-- Optional: Update existing approved records to set approving_time from approval_date
-- This sets the time to midnight on the approval date for historical records
UPDATE locator_slips 
SET approving_time = CONCAT(approval_date, ' 00:00:00')
WHERE status = 'approved' 
AND approving_time IS NULL 
AND approval_date IS NOT NULL;
