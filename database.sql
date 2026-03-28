-- ════════════════════════════════════════════════════════
-- Heliora Consulting — Database Schema
-- Run this in Namecheap cPanel > phpMyAdmin
-- ════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS `heliora_leads`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `heliora_leads`;

-- ── Leads table ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `leads` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `first_name`      VARCHAR(100)    NOT NULL,
  `last_name`       VARCHAR(100)    NOT NULL,
  `email`           VARCHAR(255)    NOT NULL,
  `phone`           VARCHAR(50)     DEFAULT NULL,
  `company`         VARCHAR(255)    DEFAULT NULL,
  `service`         VARCHAR(100)    NOT NULL,
  `project_budget`  VARCHAR(50)     DEFAULT NULL,
  `message`         TEXT            NOT NULL,

  -- Lead metadata
  `source`          VARCHAR(100)    DEFAULT 'website_contact_form',
  `page_url`        VARCHAR(500)    DEFAULT NULL,
  `utm_source`      VARCHAR(100)    DEFAULT NULL,
  `utm_medium`      VARCHAR(100)    DEFAULT NULL,
  `utm_campaign`    VARCHAR(100)    DEFAULT NULL,
  `ip_address`      VARCHAR(45)     DEFAULT NULL,
  `user_agent`      VARCHAR(500)    DEFAULT NULL,

  -- CRM sync
  `zoho_lead_id`    VARCHAR(50)     DEFAULT NULL,
  `zoho_synced_at`  DATETIME        DEFAULT NULL,

  -- Status management
  `status`          ENUM('new','contacted','qualified','converted','lost')
                    NOT NULL DEFAULT 'new',
  `notes`           TEXT            DEFAULT NULL,

  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  INDEX `idx_email`      (`email`),
  INDEX `idx_status`     (`status`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_service`    (`service`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Email log table ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `email_log` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `lead_id`     INT UNSIGNED  NOT NULL,
  `email_to`    VARCHAR(255)  NOT NULL,
  `subject`     VARCHAR(500)  NOT NULL,
  `type`        ENUM('autorespond','notification','followup') NOT NULL,
  `status`      ENUM('sent','failed') NOT NULL,
  `error`       TEXT          DEFAULT NULL,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE,
  INDEX `idx_lead_id` (`lead_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Admin sessions table ──────────────────────────────────
CREATE TABLE IF NOT EXISTS `admin_sessions` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `session_token` VARCHAR(128) NOT NULL,
  `ip_address`  VARCHAR(45)   DEFAULT NULL,
  `expires_at`  DATETIME      NOT NULL,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_token` (`session_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Page views table (simple analytics) ──────────────────
CREATE TABLE IF NOT EXISTS `page_views` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `page`        VARCHAR(255)  NOT NULL,
  `utm_source`  VARCHAR(100)  DEFAULT NULL,
  `utm_medium`  VARCHAR(100)  DEFAULT NULL,
  `utm_campaign` VARCHAR(100) DEFAULT NULL,
  `referrer`    VARCHAR(500)  DEFAULT NULL,
  `ip_hash`     VARCHAR(64)   DEFAULT NULL,  -- hashed for privacy
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_page`       (`page`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
