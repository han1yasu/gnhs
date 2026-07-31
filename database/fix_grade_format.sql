-- ============================================================
-- Fix inconsistent grade_section format in users table
-- Converts em dash (–) to regular hyphen (-) for consistency
-- Run this once in phpMyAdmin → gnhs_guidance → SQL tab → Go
-- ============================================================

-- Fix users table
UPDATE users SET grade_section = REPLACE(grade_section, ' – ', ' - ') WHERE grade_section LIKE '%–%';

-- Fix referrals table  
UPDATE referrals SET grade_section = REPLACE(grade_section, ' – ', ' - ') WHERE grade_section LIKE '%–%';

-- Verify the fix
SELECT grade_section, COUNT(*) as cnt FROM users WHERE grade_section IS NOT NULL GROUP BY grade_section ORDER BY grade_section;
