-- ==================================================
-- GETMORE AI Assistant - Institutes Table Migration
-- ==================================================

CREATE TABLE IF NOT EXISTS institutes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    short_description VARCHAR(500) NULL,
    about TEXT NULL,
    about_institute TEXT NULL,
    public_address TEXT NULL,
    public_phone VARCHAR(100) NULL,
    public_email VARCHAR(255) NULL,
    website VARCHAR(255) NULL,
    opening_hours TEXT NULL,
    registration_information TEXT NULL,
    registration_info TEXT NULL,
    facilities TEXT NULL,
    facilities_services TEXT NULL,
    public_notes TEXT NULL,
    public_widget_key VARCHAR(100) NOT NULL UNIQUE,
    allowed_domains TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure both column name variants exist
SET @dbname = DATABASE();

-- 1. about
SET @col1 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'institutes' AND COLUMN_NAME = 'about');
SET @sql1 = IF(@col1 = 0, 'ALTER TABLE institutes ADD COLUMN about TEXT NULL AFTER short_description', 'SELECT 1');
PREPARE s1 FROM @sql1; EXECUTE s1; DEALLOCATE PREPARE s1;

-- 2. registration_information
SET @col2 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'institutes' AND COLUMN_NAME = 'registration_information');
SET @sql2 = IF(@col2 = 0, 'ALTER TABLE institutes ADD COLUMN registration_information TEXT NULL AFTER opening_hours', 'SELECT 1');
PREPARE s2 FROM @sql2; EXECUTE s2; DEALLOCATE PREPARE s2;

-- 3. facilities
SET @col3 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'institutes' AND COLUMN_NAME = 'facilities');
SET @sql3 = IF(@col3 = 0, 'ALTER TABLE institutes ADD COLUMN facilities TEXT NULL AFTER registration_information', 'SELECT 1');
PREPARE s3 FROM @sql3; EXECUTE s3; DEALLOCATE PREPARE s3;

-- Sync values between column aliases
UPDATE institutes SET about = about_institute WHERE (about IS NULL OR about = '') AND about_institute IS NOT NULL;
UPDATE institutes SET about_institute = about WHERE (about_institute IS NULL OR about_institute = '') AND about IS NOT NULL;

UPDATE institutes SET registration_information = registration_info WHERE (registration_information IS NULL OR registration_information = '') AND registration_info IS NOT NULL;
UPDATE institutes SET registration_info = registration_information WHERE (registration_info IS NULL OR registration_info = '') AND registration_information IS NOT NULL;

UPDATE institutes SET facilities = facilities_services WHERE (facilities IS NULL OR facilities = '') AND facilities_services IS NOT NULL;
UPDATE institutes SET facilities_services = facilities WHERE (facilities_services IS NULL OR facilities_services = '') AND facilities IS NOT NULL;
