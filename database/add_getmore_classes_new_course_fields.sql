-- ====================================================================
-- Migration: Add safe new/upcoming course fields to GETMORE classes table
-- ====================================================================

ALTER TABLE classes
ADD COLUMN IF NOT EXISTS is_new TINYINT(1) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS enrollment_open TINYINT(1) NOT NULL DEFAULT 1,
ADD COLUMN IF NOT EXISTS start_date DATE NULL,
ADD COLUMN IF NOT EXISTS public_status VARCHAR(30) NOT NULL DEFAULT 'published';

-- Also ensure courses table has is_new and enrollment_open if not exists
ALTER TABLE courses
ADD COLUMN IF NOT EXISTS is_new TINYINT(1) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS enrollment_open TINYINT(1) NOT NULL DEFAULT 1;

-- Mark course 1 as an active upcoming course with start date for testing
UPDATE courses
SET is_new = 1,
    enrollment_open = 1,
    start_date = COALESCE(start_date, '2026-10-15')
WHERE id = 1;
