-- Migration: Email Verification OTP for Registration
-- SDO ATLAS - Email verification during self-registration

-- Table to store temporary registration data and OTP codes
CREATE TABLE IF NOT EXISTS `email_verifications` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(255) NOT NULL,
    `employee_no` VARCHAR(50) DEFAULT NULL,
    `employee_position` VARCHAR(100) DEFAULT NULL,
    `employee_office` VARCHAR(100) DEFAULT NULL,
    `office_id` INT(11) DEFAULT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `otp_hash` VARCHAR(255) NOT NULL,
    `otp_expires_at` DATETIME NOT NULL,
    `otp_attempts` INT(11) NOT NULL DEFAULT 0 COMMENT 'Failed OTP input attempts',
    `otp_request_count` INT(11) NOT NULL DEFAULT 1 COMMENT 'Number of OTP requests for this email in current window',
    `otp_request_window_start` DATETIME DEFAULT NULL COMMENT 'Start of the rate-limit window',
    `status` ENUM('pending','verified','expired','invalidated') NOT NULL DEFAULT 'pending',
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_email_status` (`email`, `status`),
    INDEX `idx_otp_expires` (`otp_expires_at`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
