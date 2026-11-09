<?php
/**
 * GNHS Guidance System — Production Environment Variables
 * Copy this file to "includes/env.php" on your Hostinger server (via File Manager)
 * and update with your actual Hostinger MySQL details & Gemini API Key.
 *
 * IMPORTANT: Keep your real "env.php" private and do not commit real passwords to public GitHub!
 */

// Hostinger MySQL Database Credentials (found in hPanel -> Databases -> MySQL Databases)
putenv('DB_HOST=localhost');
putenv('DB_USER=u123456789_gnhs_user');
putenv('DB_PASS=YourStrongHostingerDbPassword');
putenv('DB_NAME=u123456789_gnhs_db');

// Google Gemini API Key for AI Summarization & Resolution Suggestions
putenv('GEMINI_API_KEY=YOUR_GEMINI_API_KEY_HERE');
