CREATE TABLE institutes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    public_widget_key VARCHAR(100) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    allowed_domains TEXT NULL,
    short_description VARCHAR(500) NULL,
    about_institute TEXT NULL,
    public_address TEXT NULL,
    public_phone VARCHAR(100) NULL,
    public_email VARCHAR(255) NULL,
    website VARCHAR(255) NULL,
    opening_hours TEXT NULL,
    registration_info TEXT NULL,
    facilities_services TEXT NULL,
    public_notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE assistants (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    institute_id INT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    purpose TEXT NULL,
    welcome_message TEXT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    theme_primary_color VARCHAR(20) DEFAULT '#00B957',
    theme_secondary_color VARCHAR(20) DEFAULT '#F3F4F6',
    theme_text_color VARCHAR(20) DEFAULT '#111827',
    theme_header_text_color VARCHAR(20) DEFAULT '#FFFFFF',
    user_bubble_color VARCHAR(20) DEFAULT '#ECFDF3',
    assistant_bubble_color VARCHAR(20) DEFAULT '#EAEAEA',
    chat_background_color VARCHAR(20) DEFAULT '#FFFFFF',
    starter_messages TEXT NULL,
    header_subtitle VARCHAR(255) DEFAULT 'AI Assistant',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_assistant_institute
        FOREIGN KEY (institute_id) REFERENCES institutes(id)
        ON DELETE SET NULL
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

INSERT INTO institutes
(id, name, public_widget_key, is_active, allowed_domains, short_description, about_institute, public_address, public_phone, public_email, website, opening_hours, registration_info, facilities_services, public_notes)
VALUES
(
    1,
    'Achieve Institute',
    'pk_achieve_72af8391',
    1,
    'localhost, 127.0.0.1, academy.lk',
    'Leading higher education and tuition institute for secondary and A/L students.',
    'Achieve Institute is dedicated to academic excellence with expert teaching, modern facilities, and comprehensive student support in science, mathematics, and commerce streams.',
    'No. 124, High Level Road, Nugegoda, Sri Lanka',
    '+94 11 282 9900 / +94 77 712 3456',
    'info@achieveinstitute.lk',
    'https://achieveinstitute.lk',
    'Monday – Saturday: 7:30 AM – 7:00 PM\nSunday: 8:00 AM – 5:00 PM',
    'New students can register online via our student portal or visit the front office with parent/guardian ID. Admission is open year-round.',
    'Air-conditioned smart lecture halls, digital attendance tracking, library, study areas, cafeteria, and secure parking.',
    NULL
);

INSERT INTO assistants
(id, institute_id, name, description, purpose, welcome_message, enabled, theme_primary_color, theme_secondary_color, theme_text_color, theme_header_text_color, user_bubble_color, assistant_bubble_color, chat_background_color, starter_messages, header_subtitle)
VALUES
(
    1,
    1,
    'Achieve AI',
    'Public AI assistant for Achieve Institute tuition classes, schedules, and verified attendance.',
    'Help visitors and parents with approved information about classes, schedules, teachers, and verified student attendance.',
    'Hello! How can I help you with Achieve Institute today?',
    1,
    '#00B957',
    '#F3F4F6',
    '#111827',
    '#FFFFFF',
    '#ECFDF3',
    '#EAEAEA',
    '#FFFFFF',
    '["What classes do you offer?","Tell me about this institute","Who are your teachers?","Do you have any new courses?","Check my attendance"]',
    'AI Assistant'
);

INSERT INTO assistant_permissions
(assistant_id, permission_key, permission_name, enabled)
VALUES
(1, 'institute_details', 'Institute Details', 1),
(1, 'class_details', 'Classes / Courses', 1),
(1, 'teacher_details', 'Teacher Details', 1),
(1, 'new_courses', 'New / Upcoming Courses', 1),
(1, 'attendance_details', 'Attendance', 1);
