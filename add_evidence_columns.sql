-- Run this in phpMyAdmin → gnhs_guidance → SQL tab → Go
ALTER TABLE cases ADD COLUMN IF NOT EXISTS evidence_files LONGTEXT DEFAULT NULL COMMENT 'JSON array of uploaded evidence photos/videos';
ALTER TABLE cases ADD COLUMN IF NOT EXISTS witnesses TEXT DEFAULT NULL COMMENT 'Names and details of witnesses';
