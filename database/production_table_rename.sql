-- ==============================================================================
-- GETMORE AI Assistant - Production Database Table Rename Migration
-- ==============================================================================
-- Target Domain: https://ai.getmore.lk
--
-- PURPOSE:
-- Renames all tables to EXACT lowercase names for production Linux / cPanel MySQL.
-- On Linux servers, MySQL table names can be strictly case-sensitive.
--
-- FINAL REQUIRED TABLE NAMES:
-- 1. ai_assistants
-- 2. ai_assistant_institutes
-- 3. ai_assistant_permissions
-- 4. ai_institute_integrations
--
-- INSTRUCTIONS:
-- - Run this script manually in phpMyAdmin or MySQL client ONLY if your database
--   already contains existing tables with mixed-case (Ai_*) or legacy names.
-- - If you are deploying on a fresh database, use database/production_schema.sql instead.
-- - NO DATA IS DELETED. NO DROP TABLE COMMANDS ARE USED.
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- OPTION A: DIRECT RENAME STATEMENTS (Run if tables use mixed-case 'Ai_*')
-- Expected source names:
--   - Ai_assistant_institutes
--   - Ai_assistants
--   - Ai_assistant_permissions
--   - Ai_institute_integrations
-- ------------------------------------------------------------------------------

-- RENAME TABLE `Ai_assistant_institutes`   TO `ai_assistant_institutes`;
-- RENAME TABLE `Ai_assistants`             TO `ai_assistants`;
-- RENAME TABLE `Ai_assistant_permissions`  TO `ai_assistant_permissions`;
-- RENAME TABLE `Ai_institute_integrations` TO `ai_institute_integrations`;

-- ------------------------------------------------------------------------------
-- OPTION B: DIRECT RENAME STATEMENTS (Run if tables use legacy prefixless names)
-- Expected source names:
--   - institutes
--   - assistants
--   - assistant_permissions
--   - institute_integrations
-- ------------------------------------------------------------------------------

-- RENAME TABLE `institutes`              TO `ai_assistant_institutes`;
-- RENAME TABLE `assistants`              TO `ai_assistants`;
-- RENAME TABLE `assistant_permissions`   TO `ai_assistant_permissions`;
-- RENAME TABLE `institute_integrations`  TO `ai_institute_integrations`;

-- ------------------------------------------------------------------------------
-- OPTION C: SAFE DYNAMIC RENAME PROCEDURE (Recommended)
-- Checks current database schema and safely renames whatever variant is present.
-- If the table is already lowercase, it is safely skipped without error.
-- ------------------------------------------------------------------------------

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_rename_ai_tables_to_lowercase$$

CREATE PROCEDURE sp_rename_ai_tables_to_lowercase()
BEGIN
    DECLARE v_dbname VARCHAR(64);
    SET v_dbname = DATABASE();

    -- 1. Rename Institutes table -> ai_assistant_institutes
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = v_dbname AND TABLE_NAME = 'ai_assistant_institutes') THEN
        IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = v_dbname AND TABLE_NAME = 'Ai_assistant_institutes') THEN
            RENAME TABLE `Ai_assistant_institutes` TO `ai_assistant_institutes`;
        ELSEIF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = v_dbname AND TABLE_NAME = 'institutes') THEN
            RENAME TABLE `institutes` TO `ai_assistant_institutes`;
        ELSEIF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = v_dbname AND TABLE_NAME = 'assistant_institutes') THEN
            RENAME TABLE `assistant_institutes` TO `ai_assistant_institutes`;
        END IF;
    END IF;

    -- 2. Rename Assistants table -> ai_assistants
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = v_dbname AND TABLE_NAME = 'ai_assistants') THEN
        IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = v_dbname AND TABLE_NAME = 'Ai_assistants') THEN
            RENAME TABLE `Ai_assistants` TO `ai_assistants`;
        ELSEIF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = v_dbname AND TABLE_NAME = 'assistants') THEN
            RENAME TABLE `assistants` TO `ai_assistants`;
        END IF;
    END IF;

    -- 3. Rename Permissions table -> ai_assistant_permissions
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = v_dbname AND TABLE_NAME = 'ai_assistant_permissions') THEN
        IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = v_dbname AND TABLE_NAME = 'Ai_assistant_permissions') THEN
            RENAME TABLE `Ai_assistant_permissions` TO `ai_assistant_permissions`;
        ELSEIF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = v_dbname AND TABLE_NAME = 'assistant_permissions') THEN
            RENAME TABLE `assistant_permissions` TO `ai_assistant_permissions`;
        END IF;
    END IF;

    -- 4. Rename Integrations table -> ai_institute_integrations
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = v_dbname AND TABLE_NAME = 'ai_institute_integrations') THEN
        IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = v_dbname AND TABLE_NAME = 'Ai_institute_integrations') THEN
            RENAME TABLE `Ai_institute_integrations` TO `ai_institute_integrations`;
        ELSEIF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = v_dbname AND TABLE_NAME = 'institute_integrations') THEN
            RENAME TABLE `institute_integrations` TO `ai_institute_integrations`;
        END IF;
    END IF;

END$$

DELIMITER ;

-- Execute the safe renaming procedure
CALL sp_rename_ai_tables_to_lowercase();

-- Clean up temporary procedure
DROP PROCEDURE IF EXISTS sp_rename_ai_tables_to_lowercase;

-- Verify final table names
SHOW TABLES LIKE 'ai_%';
