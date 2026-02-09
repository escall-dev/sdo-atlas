-- Migration: Enhanced forgot password tracking
-- Adds verification access tracking, OTP input attempt limits, and OTP resend limits
-- SDO ATLAS

-- Add new tracking columns to password_reset_attempts
ALTER TABLE `password_reset_attempts`
    ADD COLUMN `verification_access_count` INT DEFAULT 0 COMMENT 'Number of times the user visited the forgot password page' AFTER `is_blocked`,
    ADD COLUMN `otp_input_attempts` INT DEFAULT 0 COMMENT 'Current OTP incorrect input attempts (resets per new OTP)' AFTER `verification_access_count`,
    ADD COLUMN `resend_count` INT DEFAULT 0 COMMENT 'OTP resend count within the current hour window' AFTER `otp_input_attempts`,
    ADD COLUMN `resend_window_start` DATETIME DEFAULT NULL COMMENT 'Start of the current resend rate limit window' AFTER `resend_count`,
    ADD COLUMN `resend_blocked` TINYINT(1) DEFAULT 0 COMMENT 'Whether resend is currently blocked (max 3/hour reached)' AFTER `resend_window_start`;
