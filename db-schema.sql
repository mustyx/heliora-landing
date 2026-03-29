-- ═══════════════════════════════════════════════════════════
--  Heliora Consulting Limited — MySQL Database Schema
--  Run this once in cPanel > phpMyAdmin after creating the DB
-- ═══════════════════════════════════════════════════════════

-- Create leads table
CREATE TABLE IF NOT EXISTS `leads` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `first_name`    VARCHAR(100)    NOT NULL,
  `last_name`     VARCHAR(100)    NOT NULL,
  `email`         VARCHAR(255)    NOT NULL,
  `phone`         VARCHAR(50)     DEFAULT NULL,
  `company`       VARCHAR(255)    DEFAULT NULL,
  `service`       VARCHAR(100)    NOT NULL,
  `project_scale` VARCHAR(50)     DEFAULT NULL,
  `client_type`   VARCHAR(50)     DEFAULT NULL,
  `message`       TEXT            NOT NULL,
  `source`        VARCHAR(100)    DEFAULT 'website_contact_form',
  `page_url`      VARCHAR(500)    DEFAULT NULL,
  `utm_source`    VARCHAR(100)    DEFAULT NULL,
  `utm_medium`    VARCHAR(100)    DEFAULT NULL,
  `utm_campaign`  VARCHAR(100)    DEFAULT NULL,
  `ip_address`    VARCHAR(45)     DEFAULT NULL,
  `user_agent`    VARCHAR(500)    DEFAULT NULL,
  `status`        ENUM('new','contacted','qualified','closed','lost') NOT NULL DEFAULT 'new',
  `notes`         TEXT            DEFAULT NULL,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_email`      (`email`),
  INDEX `idx_status`     (`status`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_service`    (`service`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Create email log table
CREATE TABLE IF NOT EXISTS `email_log` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lead_id`    INT UNSIGNED NOT NULL,
  `email_to`   VARCHAR(255) NOT NULL,
  `subject`    VARCHAR(500) DEFAULT NULL,
  `type`       ENUM('autorespond','notification','followup') NOT NULL,
  `status`     ENUM('sent','failed','pending') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_lead_id` (`lead_id`),
  FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Create admin sessions table (for future admin dashboard login)
CREATE TABLE IF NOT EXISTS `admin_sessions` (
  `id`         VARCHAR(128)  NOT NULL,
  `data`       TEXT          DEFAULT NULL,
  `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME      NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
