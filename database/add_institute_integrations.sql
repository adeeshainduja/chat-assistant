-- ==================================================
-- GETMORE AI Assistant - Institute Integrations Table
-- ==================================================

CREATE TABLE IF NOT EXISTS ai_institute_integrations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    institute_id INT UNSIGNED NOT NULL,
    provider VARCHAR(50) NOT NULL DEFAULT 'getmore',
    api_base_url VARCHAR(255) NULL,
    encrypted_api_key TEXT NULL,
    classes_endpoint VARCHAR(255) NULL DEFAULT '/api/v1/classes',
    lecturers_endpoint VARCHAR(255) NULL DEFAULT '/api/v1/lecturers',
    extra_classes_endpoint VARCHAR(255) NULL DEFAULT '/api/v1/extra-classes',
    attendance_endpoint VARCHAR(255) NULL DEFAULT '/api/v1/student/attendance/today',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_institute_provider (institute_id, provider),
    CONSTRAINT fk_integration_institute FOREIGN KEY (institute_id) REFERENCES ai_assistant_institutes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
