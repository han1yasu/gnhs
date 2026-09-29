-- Run this once in phpMyAdmin to add the avatar_photo column
-- Go to phpMyAdmin → gnhs_guidance → SQL tab → paste and click Go

ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_photo VARCHAR(255) DEFAULT NULL;

-- Also create the uploads folder structure (done automatically by PHP, but just in case)
-- C:/xampp/htdocs/gnhs-guidance/uploads/avatars/
