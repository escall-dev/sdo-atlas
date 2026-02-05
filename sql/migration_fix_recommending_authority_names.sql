-- =====================================================
-- Migration: Fix recommending_authority_name to include person's name
-- SDO ATLAS - Updates existing records to show "Name, Position" instead of just "Position"
-- Run this on existing databases to fix old data
-- =====================================================

-- Update authority_to_travel records to add the recommender's full name
-- This fixes records where recommending_authority_name only contains the position title
UPDATE authority_to_travel at
INNER JOIN admin_users au ON at.recommended_by = au.id
SET at.recommending_authority_name = CONCAT(au.full_name, ', ', at.recommending_authority_name)
WHERE at.recommending_authority_name IS NOT NULL
  AND at.recommending_authority_name NOT LIKE '%,%'  -- Only update if no comma (no name yet)
  AND at.recommended_by IS NOT NULL
  AND at.status IN ('recommended', 'approved');

-- Verify the changes (optional - for checking)
-- SELECT id, at_tracking_no, recommending_authority_name, recommended_by 
-- FROM authority_to_travel 
-- WHERE recommending_authority_name IS NOT NULL;
