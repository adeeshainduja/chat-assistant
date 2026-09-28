CREATE TABLE assistants (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    purpose TEXT NULL,
    welcome_message TEXT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE assistant_permissions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    assistant_id INT UNSIGNED NOT NULL,
    permission_key VARCHAR(100) NOT NULL,
    permission_name VARCHAR(255) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_assistant_permission (assistant_id, permission_key),
    CONSTRAINT fk_ai_permission_assistant
        FOREIGN KEY (assistant_id) REFERENCES assistants(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO assistants
(name, description, purpose, welcome_message, enabled)
VALUES
(
    'GETMORE AI',
    'AI assistant for students using the GETMORE tuition class system.',
    'Help logged-in students with approved information about their own classes, their own attendance, and their teachers.',
    'Hi! How can I help you with your classes today?',
    1
);

INSERT INTO assistant_permissions
(assistant_id, permission_key, permission_name, enabled)
VALUES
(1, 'class_details', 'Class Details', 1),
(1, 'attendance_details', 'My Attendance Details', 1),
(1, 'teacher_details', 'Teacher Details', 1);
