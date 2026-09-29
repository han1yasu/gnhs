-- Run this in phpMyAdmin → gnhs_guidance → SQL tab → Go
ALTER TABLE users ADD COLUMN IF NOT EXISTS advisory_class VARCHAR(60) DEFAULT NULL COMMENT 'Teacher advisory class e.g. Grade 9 - Dalton';
