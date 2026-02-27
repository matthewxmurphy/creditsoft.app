-- License System Tables

-- Main licenses table
CREATE TABLE IF NOT EXISTS `licenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `license_key` VARCHAR(64) NOT NULL UNIQUE,
  `customer_email` VARCHAR(255) NOT NULL,
  `customer_name` VARCHAR(255) DEFAULT NULL,
  `plan` ENUM('starter', 'professional', 'enterprise') DEFAULT 'professional',
  `status` ENUM('active', 'expired', 'suspended', 'cancelled') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME NOT NULL,
  `activated_at` DATETIME DEFAULT NULL,
  `cancelled_at` DATETIME DEFAULT NULL,
  `last_validated` DATETIME DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `domain` VARCHAR(255) DEFAULT NULL,
  INDEX `idx_license_key` (`license_key`),
  INDEX `idx_email` (`customer_email`),
  INDEX `idx_expires` (`expires_at`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Grace period tracking
CREATE TABLE IF NOT EXISTS `license_grace_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `license_id` INT NOT NULL,
  `grace_start` DATETIME NOT NULL,
  `grace_end` DATETIME NOT NULL,
  `days_used` INT DEFAULT 0,
  `auto_enabled` TINYINT(1) DEFAULT 1,
  `payment_status` ENUM('pending', 'paid', 'failed', 'waived') DEFAULT 'pending',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`license_id`) REFERENCES `licenses`(`id`) ON DELETE CASCADE,
  INDEX `idx_license` (`license_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- License validation/usage logs
CREATE TABLE IF NOT EXISTS `license_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `license_id` INT NOT NULL,
  `action` ENUM('validate', 'activate', 'deactivate', 'expire', 'grace_used', 'payment_failed', 'payment_success', 'renew') NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `details` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`license_id`) REFERENCES `licenses`(`id`) ON DELETE CASCADE,
  INDEX `idx_license` (`license_id`),
  INDEX `idx_action` (`action`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auto-renewal settings
CREATE TABLE IF NOT EXISTS `license_subscriptions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `license_id` INT NOT NULL,
  `billing_cycle` ENUM('monthly', 'yearly', 'lifetime') DEFAULT 'monthly',
  `amount` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(3) DEFAULT 'USD',
  `next_billing` DATETIME NOT NULL,
  `auto_renew` TINYINT(1) DEFAULT 1,
  `payment_method` VARCHAR(50) DEFAULT NULL,
  `payment_customer_id` VARCHAR(255) DEFAULT NULL,
  `failed_attempts` INT DEFAULT 0,
  `last_payment_at` DATETIME DEFAULT NULL,
  `last_payment_status` ENUM('success', 'failed') DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`license_id`) REFERENCES `licenses`(`id`) ON DELETE CASCADE,
  INDEX `idx_license` (`license_id`),
  INDEX `idx_next_billing` (`next_billing`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
