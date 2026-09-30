-- ====================================================================
-- Migration: Add Institute Public Information & New Courses Support
-- ====================================================================

-- 1. Add public information fields to institutes table in AI database
ALTER TABLE ai_assistant_institutes
ADD COLUMN IF NOT EXISTS short_description VARCHAR(500) NULL AFTER allowed_domains,
ADD COLUMN IF NOT EXISTS about_institute TEXT NULL AFTER short_description,
ADD COLUMN IF NOT EXISTS public_address TEXT NULL AFTER about_institute,
ADD COLUMN IF NOT EXISTS public_phone VARCHAR(100) NULL AFTER public_address,
ADD COLUMN IF NOT EXISTS public_email VARCHAR(255) NULL AFTER public_phone,
ADD COLUMN IF NOT EXISTS website VARCHAR(255) NULL AFTER public_email,
ADD COLUMN IF NOT EXISTS opening_hours TEXT NULL AFTER website,
ADD COLUMN IF NOT EXISTS registration_info TEXT NULL AFTER opening_hours,
ADD COLUMN IF NOT EXISTS facilities_services TEXT NULL AFTER registration_info,
ADD COLUMN IF NOT EXISTS public_notes TEXT NULL AFTER facilities_services;

-- 2. Populate default public institute information for Achieve Institute (id = 1)
UPDATE ai_assistant_institutes
SET short_description = COALESCE(short_description, 'Leading higher education and tuition institute for secondary and A/L students.'),
    about_institute = COALESCE(about_institute, 'Achieve Institute is dedicated to academic excellence with expert teaching, modern facilities, and comprehensive student support in science, mathematics, and commerce streams.'),
    public_address = COALESCE(public_address, 'No. 124, High Level Road, Nugegoda, Sri Lanka'),
    public_phone = COALESCE(public_phone, '+94 11 282 9900 / +94 77 712 3456'),
    public_email = COALESCE(public_email, 'info@achieveinstitute.lk'),
    website = COALESCE(website, 'https://achieveinstitute.lk'),
    opening_hours = COALESCE(opening_hours, 'Monday – Saturday: 7:30 AM – 7:00 PM\nSunday: 8:00 AM – 5:00 PM'),
    registration_info = COALESCE(registration_info, 'New students can register online via our student portal or visit the front office with parent/guardian ID. Admission is open year-round.'),
    facilities_services = COALESCE(facilities_services, 'Air-conditioned smart lecture halls, digital attendance tracking, library, study areas, cafeteria, and secure parking.')
WHERE id = 1;

-- 3. Ensure all 5 public feature permissions exist for assistant 1
INSERT INTO ai_assistant_permissions (assistant_id, permission_key, permission_name, enabled)
VALUES
    (1, 'institute_details', 'Institute Details', 1),
    (1, 'class_details', 'Classes / Courses', 1),
    (1, 'teacher_details', 'Teacher Details', 1),
    (1, 'new_courses', 'New / Upcoming Courses', 1),
    (1, 'attendance_details', 'Attendance', 1)
ON DUPLICATE KEY UPDATE
    permission_name = VALUES(permission_name);
