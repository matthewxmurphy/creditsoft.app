-- Credit Error Identifier System - Database Schema
-- Metro2 Compliant Credit Data Management

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- Users table
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','staff','client') DEFAULT 'staff',
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Clients table
CREATE TABLE IF NOT EXISTS `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `ssn_last_4` varchar(4) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(2) DEFAULT NULL,
  `zip_code` varchar(10) DEFAULT NULL,
  `status` enum('active','inactive','prospect') DEFAULT 'prospect',
  `assigned_to` int(11) DEFAULT NULL,
  `portal_enabled` tinyint(1) DEFAULT 0,
  `portal_token` varchar(64) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `assigned_to` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Credit Reports table
CREATE TABLE IF NOT EXISTS `credit_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `bureau` enum('experian','transunion','equifax') NOT NULL,
  `credit_score` int(4) DEFAULT NULL,
  `score_factor_1` varchar(255) DEFAULT NULL,
  `score_factor_2` varchar(255) DEFAULT NULL,
  `score_factor_3` varchar(255) DEFAULT NULL,
  `score_factor_4` varchar(255) DEFAULT NULL,
  `score_factor_5` varchar(255) DEFAULT NULL,
  `total_accounts` int(5) DEFAULT 0,
  `positive_accounts` int(5) DEFAULT 0,
  `negative_accounts` int(5) DEFAULT 0,
  `derogatory_count` int(5) DEFAULT 0,
  `hard_inquiries` int(3) DEFAULT 0,
  `collections_count` int(5) DEFAULT 0,
  `bankruptcies_count` int(3) DEFAULT 0,
  `liens_count` int(3) DEFAULT 0,
  `judgments_count` int(3) DEFAULT 0,
  `raw_data` text,
  `imported_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `report_date` (`report_date`),
  KEY `bureau` (`bureau`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Report Accounts table
CREATE TABLE IF NOT EXISTS `report_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `account_number` varchar(20) DEFAULT NULL,
  `creditor_name` varchar(100) NOT NULL,
  `account_type` varchar(50) DEFAULT NULL,
  `date_opened` date DEFAULT NULL,
  `date_reported` date DEFAULT NULL,
  `balance` decimal(12,2) DEFAULT NULL,
  `credit_limit` decimal(12,2) DEFAULT NULL,
  `payment_status` varchar(50) DEFAULT NULL,
  `payment_history` text,
  `monthly_payment` decimal(10,2) DEFAULT NULL,
  `past_due_amount` decimal(10,2) DEFAULT NULL,
  `account_condition` varchar(50) DEFAULT NULL,
  `responsibility` varchar(20) DEFAULT NULL,
  `term` varchar(50) DEFAULT NULL,
  `high_balance` decimal(12,2) DEFAULT NULL,
  `low_balance` decimal(12,2) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Error Types table (Metro2 codes)
CREATE TABLE IF NOT EXISTS `error_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `metro2_field` varchar(50) DEFAULT NULL,
  `severity` enum('critical','high','medium','low') DEFAULT 'medium',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Account Errors table
CREATE TABLE IF NOT EXISTS `account_errors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_account_id` int(11) NOT NULL,
  `error_type_id` int(11) NOT NULL,
  `description` text,
  `is_disputed` tinyint(1) DEFAULT 0,
  `dispute_date` date DEFAULT NULL,
  `dispute_result` enum('pending','success','failed','partial') DEFAULT 'pending',
  `notes` text,
  `identified_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `report_account_id` (`report_account_id`),
  KEY `error_type_id` (`error_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Report Comparisons table
CREATE TABLE IF NOT EXISTS `comparisons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `report_1_id` int(11) NOT NULL,
  `report_2_id` int(11) NOT NULL,
  `score_change` int(5) DEFAULT 0,
  `accounts_added` int(5) DEFAULT 0,
  `accounts_removed` int(5) DEFAULT 0,
  `errors_fixed` int(5) DEFAULT 0,
  `errors_new` int(5) DEFAULT 0,
  `new_errors` text,
  `fixed_errors` text,
  `summary` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `report_1_id` (`report_1_id`),
  KEY `report_2_id` (`report_2_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Client Notes table
CREATE TABLE IF NOT EXISTS `client_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `note` text NOT NULL,
  `is_internal` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dispute Templates table
CREATE TABLE IF NOT EXISTS `dispute_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `error_type_id` int(11) DEFAULT NULL,
  `subject` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Disputes table
CREATE TABLE IF NOT EXISTS `disputes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `account_error_id` int(11) NOT NULL,
  `template_id` int(11) DEFAULT NULL,
  `bureau` enum('experian','transunion','equifax','all') NOT NULL,
  `subject` varchar(200) NOT NULL,
  `letter_content` text NOT NULL,
  `status` enum('draft','sent','awaiting','response','closed') DEFAULT 'draft',
  `sent_date` date DEFAULT NULL,
  `response_date` date DEFAULT NULL,
  `result` enum('pending','success','failed') DEFAULT 'pending',
  `response_notes` text,
  `pdf_path` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `account_error_id` (`account_error_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Drip Campaigns table
CREATE TABLE IF NOT EXISTS `drip_campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `trigger_type` enum('score_change','error_found','milestone','inactivity','manual') NOT NULL,
  `trigger_value` varchar(100) DEFAULT NULL,
  `delay_days` int(3) DEFAULT 0,
  `subject` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Drip Queue table
CREATE TABLE IF NOT EXISTS `drip_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `campaign_id` int(11) NOT NULL,
  `scheduled_date` datetime NOT NULL,
  `sent_date` datetime DEFAULT NULL,
  `status` enum('pending','sent','failed','cancelled') DEFAULT 'pending',
  `subject` varchar(200) DEFAULT NULL,
  `body` text,
  `error_message` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `campaign_id` (`campaign_id`),
  KEY `scheduled_date` (`scheduled_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SOPs table
CREATE TABLE IF NOT EXISTS `sops` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `content` text NOT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AI Interactions table
CREATE TABLE IF NOT EXISTS `ai_interactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `query` text NOT NULL,
  `response` text NOT NULL,
  `context_used` text,
  `tokens_used` int(5) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Knowledge Base Articles table
CREATE TABLE IF NOT EXISTS `knowledge_base` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `state` varchar(2) DEFAULT NULL,
  `content` text NOT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category` (`category`),
  KEY `state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tasks table
CREATE TABLE IF NOT EXISTS `tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `description` text,
  `client_id` int(11) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `due_date` date DEFAULT NULL,
  `completed_date` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `assigned_to` (`assigned_to`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Activity Log table
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `description` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `entity_type` (`entity_type`),
  KEY `entity_id` (`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Settings table
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL,
  `value` text,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin user (password: admin123)
INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role`, `first_name`, `last_name`, `is_active`) VALUES
(1, 'admin', 'admin@credit.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Admin', 'User', 1);

-- Insert default settings
INSERT INTO `settings` (`key`, `value`, `description`) VALUES
('company_name', 'Credit Error Identifier', 'Company name'),
('allow_registration', '0', 'Allow new user registration'),
('default_score_goal', '750', 'Default target credit score'),
('ai_enabled', '1', 'Enable AI features'),
('portal_enabled', '1', 'Enable client portal');

-- Insert Metro2 Error Codes
INSERT INTO `error_types` (`code`, `name`, `description`, `metro2_field`, `severity`) VALUES
('ACCT-001', 'Account Number Mismatch', 'Reported account number does not match consumer records', 'Base Segment', 'critical'),
('ACCT-002', 'Incorrect Account Type', 'Account type incorrectly reported (revolving vs installment)', 'Base Segment', 'high'),
('ACCT-003', 'Wrong Creditor Name', 'Creditor name does not match consumer''s records', 'Base Segment', 'medium'),
('BAL-001', 'Balance Discrepancy', 'Reported balance does not match consumer records', 'Base Segment', 'high'),
('BAL-002', 'Incorrect Credit Limit', 'Credit limit or high balance is inaccurate', 'Base Segment', 'medium'),
('DATE-001', 'Incorrect Date Opened', 'Account open date is inaccurate', 'Base Segment', 'medium'),
('DATE-002', 'Incorrect Date Reported', 'Date of last activity is inaccurate', 'Base Segment', 'low'),
('DATE-003', 'Future Date Reported', 'Date in the future appears on report', 'Base Segment', 'high'),
('PAY-001', 'Payment Status Error', 'Payment status incorrectly reported', 'Base Segment', 'critical'),
('PAY-002', 'Late Payments Not Mine', 'Late payments belong to another consumer', 'Base Segment', 'critical'),
('PAY-003', 'Incorrect Payment Amount', 'Monthly payment amount is wrong', 'Base Segment', 'medium'),
('DEL-001', 'Duplicate Delinquency', 'Same delinquency reported multiple times', 'Base Segment', 'high'),
('DEL-002', 'Delinquency Too Old', 'Old delinquency should be removed', 'Base Segment', 'medium'),
('COL-001', 'Collections Not Mine', 'Collection belongs to another person', 'Base Segment', 'critical'),
('COL-002', 'Paid Collection Showing Unpaid', 'Paid collection still showing balance', 'Base Segment', 'high'),
('COL-003', 'Duplicate Collection', 'Same collection reported multiple times', 'Base Segment', 'high'),
('BKR-001', 'Bankruptcy Not Mine', 'Bankruptcy belongs to another consumer', 'Base Segment', 'critical'),
('BKR-002', 'Bankruptcy Discharged', 'Discharged bankruptcy still showing', 'Base Segment', 'high'),
('LIEN-001', 'Lien Not Mine', 'Lien belongs to another person', 'Base Segment', 'critical'),
('LIEN-002', 'Lien Released', 'Released lien still appearing', 'Base Segment', 'high'),
('JUDG-001', 'Judgment Not Mine', 'Judgment belongs to another consumer', 'Base Segment', 'critical'),
('JUDG-002', 'Judgment Satisfied', 'Satisfied judgment still appearing', 'Base Segment', 'high'),
('INQ-001', 'Unauthorized Inquiry', 'Inquiry consumer did not authorize', 'Base Segment', 'medium'),
('INQ-002', 'Too Many Inquiries', 'Excessive inquiries affecting score', 'Base Segment', 'low'),
('NAME-001', 'Name Mismatch', 'Consumer name incorrectly reported', 'Base Segment', 'high'),
('ADDR-001', 'Address Discrepancy', 'Address information is incorrect', 'Base Segment', 'low'),
('SSN-001', 'SSN Mismatch', 'Social Security number does not match', 'Base Segment', 'critical'),
('SSN-002', 'Invalid SSN Format', 'Social Security number format is invalid', 'Base Segment', 'high'),
('DOB-001', 'Date of Birth Error', 'Date of birth is incorrect', 'Base Segment', 'medium'),
('EMP-001', 'Employment Error', 'Employment information is inaccurate', 'Base Segment', 'low');
