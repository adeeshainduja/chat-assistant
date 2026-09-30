-- ==============================================================================
-- GETMORE AI Assistant - Clean Production Database Schema
-- ==============================================================================
-- Target Domain: https://ai.getmore.lk
-- Charset: utf8mb4 / utf8mb4_unicode_ci (Supports English, Sinhala, Tamil)
-- Engine: InnoDB
--
-- TABLES:
-- 1. ai_assistant_institutes   (Multi-institute customer profiles & widget keys)
-- 2. ai_assistants             (Assistant configuration, chat themes, starters)
-- 3. ai_assistant_permissions  (Feature permissions per assistant)
-- 4. ai_institute_integrations (GETMORE REST API credentials & endpoint mapping)
-- ==============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- Table 1: ai_assistant_institutes
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_assistant_institutes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `public_widget_key` VARCHAR(100) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `allowed_domains` TEXT NULL,
    `short_description` VARCHAR(500) NULL,
    `about` TEXT NULL,
    `about_institute` TEXT NULL,
    `public_address` TEXT NULL,
    `public_phone` VARCHAR(100) NULL,
    `public_email` VARCHAR(255) NULL,
    `website` VARCHAR(255) NULL,
    `opening_hours` TEXT NULL,
    `registration_information` TEXT NULL,
    `registration_info` TEXT NULL,
    `facilities` TEXT NULL,
    `facilities_services` TEXT NULL,
    `public_notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_institute_widget_key` (`public_widget_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 2: ai_assistants
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_assistants` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `institute_id` INT UNSIGNED NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `purpose` TEXT NULL,
    `welcome_message` TEXT NULL,
    `enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `theme_primary_color` VARCHAR(20) NOT NULL DEFAULT '#00B957',
    `theme_secondary_color` VARCHAR(20) NOT NULL DEFAULT '#F3F4F6',
    `theme_text_color` VARCHAR(20) NOT NULL DEFAULT '#111827',
    `theme_header_text_color` VARCHAR(20) NOT NULL DEFAULT '#FFFFFF',
    `user_bubble_color` VARCHAR(20) NOT NULL DEFAULT '#ECFDF3',
    `assistant_bubble_color` VARCHAR(20) NOT NULL DEFAULT '#EAEAEA',
    `chat_background_color` VARCHAR(20) NOT NULL DEFAULT '#FFFFFF',
    `starter_messages` TEXT NULL,
    `header_subtitle` VARCHAR(255) NOT NULL DEFAULT 'AI Assistant',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_assistant_institute` (`institute_id`),
    CONSTRAINT `fk_assistant_institute`
        FOREIGN KEY (`institute_id`) REFERENCES `ai_assistant_institutes` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 3: ai_assistant_permissions
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_assistant_permissions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `assistant_id` INT UNSIGNED NOT NULL,
    `permission_key` VARCHAR(100) NOT NULL,
    `permission_name` VARCHAR(255) NOT NULL,
    `enabled` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_assistant_permission` (`assistant_id`, `permission_key`),
    KEY `idx_permission_assistant` (`assistant_id`),
    CONSTRAINT `fk_ai_permission_assistant`
        FOREIGN KEY (`assistant_id`) REFERENCES `ai_assistants` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table 4: ai_institute_integrations
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_institute_integrations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `institute_id` INT UNSIGNED NOT NULL,
    `provider` VARCHAR(50) NOT NULL DEFAULT 'getmore',
    `api_base_url` VARCHAR(255) NULL,
    `encrypted_api_key` TEXT NULL,
    `classes_endpoint` VARCHAR(255) NULL DEFAULT '/api/v1/classes',
    `lecturers_endpoint` VARCHAR(255) NULL DEFAULT '/api/v1/lecturers',
    `extra_classes_endpoint` VARCHAR(255) NULL DEFAULT '/api/v1/extra-classes',
    `attendance_endpoint` VARCHAR(255) NULL DEFAULT '/api/v1/student/attendance/today',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_institute_provider` (`institute_id`, `provider`),
    KEY `idx_integration_institute` (`institute_id`),
    CONSTRAINT `fk_integration_institute`
        FOREIGN KEY (`institute_id`) REFERENCES `ai_assistant_institutes` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
