-- Add applies_to to unit_routing_config: Authority to Travel, Locator Slip, or Both
-- Run once on existing DB.

ALTER TABLE `unit_routing_config`
  ADD COLUMN `applies_to` enum('authority_to_travel','locator_slip','both') NOT NULL DEFAULT 'authority_to_travel'
  COMMENT 'Document type: Authority to Travel only, Locator Slip only, or Both'
  AFTER `travel_scope`;

-- Existing rows default to authority_to_travel (no change needed with DEFAULT above)
