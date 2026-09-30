-- ==================================================
-- GETMORE AI Assistant - Multi-Institute Migration
-- ==================================================

-- 1. Create institutes table
CREATE TABLE IF NOT EXISTS ai_assistant_institutes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    public_widget_key VARCHAR(100) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    allowed_domains TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Add institute_id to assistants table if it does not already exist
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ai_assistants'
      AND COLUMN_NAME = 'institute_id'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE ai_assistants ADD COLUMN institute_id INT UNSIGNED NULL AFTER id',
    'SELECT "institute_id column already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Seed default development institute if table is empty
INSERT INTO ai_assistant_institutes (id, name, public_widget_key, is_active, allowed_domains)
VALUES (1, 'Achieve Institute', 'pk_achieve_72af8391', 1, 'localhost, 127.0.0.1, academy.lk')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    public_widget_key = VALUES(public_widget_key);

-- 4. Associate existing assistant 1 with institute 1
UPDATE ai_assistants
SET institute_id = 1
WHERE id = 1 AND (institute_id IS NULL OR institute_id = 0);

-- 5. Add foreign key constraint if not exists
SET @fk_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ai_assistants'
      AND CONSTRAINT_NAME = 'fk_assistant_institute'
);

SET @sql_fk = IF(@fk_exists = 0,
    'ALTER TABLE ai_assistants ADD CONSTRAINT fk_assistant_institute FOREIGN KEY (institute_id) REFERENCES ai_assistant_institutes(id) ON DELETE SET NULL',
    'SELECT "fk_assistant_institute already exists"'
);
PREPARE stmt_fk FROM @sql_fk;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk;
