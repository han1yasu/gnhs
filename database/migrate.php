<?php
// Run once after configuring DB_HOST, DB_PORT, DB_USER, DB_PASS, and DB_NAME.
require_once __DIR__ . '/../includes/config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run migrations from the command line.');
}

$db = getDB();
$columns = [
    'users' => [
        'middle_name' => 'VARCHAR(50) DEFAULT NULL',
        'suffix' => 'VARCHAR(10) DEFAULT NULL',
        'age' => 'INT DEFAULT NULL',
        'is_setup_complete' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'totp_secret' => 'VARCHAR(64) DEFAULT NULL',
        'totp_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0',
    ],
    'cases' => [
        'anon_token' => 'VARCHAR(64) DEFAULT NULL',
        'assigned_to' => 'INT DEFAULT NULL',
        'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        'is_follow_up' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'follow_up_msg' => 'TEXT DEFAULT NULL',
        'follow_up_date' => 'DATETIME DEFAULT NULL',
    ],
    'referrals' => ['ai_summary' => 'TEXT DEFAULT NULL'],
    'counseling_sessions' => ['session_type' => "VARCHAR(50) DEFAULT 'in-person'"],
    'notifications' => ['type' => "VARCHAR(50) DEFAULT 'case_update'"],
];

foreach ($columns as $table => $definitions) {
    $existing = $db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($definitions as $name => $definition) {
        if (!in_array($name, $existing, true)) {
            $db->exec("ALTER TABLE `$table` ADD COLUMN `$name` $definition");
            echo "Added $table.$name\n";
        }
    }
}

$db->exec('CREATE TABLE IF NOT EXISTS case_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    author_id INT NOT NULL,
    note TEXT NOT NULL,
    is_visible_to_student TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(case_id)
)');
$db->exec('CREATE TABLE IF NOT EXISTS case_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(case_id), INDEX(sender_id)
)');
$db->exec('CREATE TABLE IF NOT EXISTS referral_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referral_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(referral_id), INDEX(user_id)
)');
$db->exec('CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(email)
)');
$resetColumns = $db->query('SHOW COLUMNS FROM password_resets')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('token_hash', $resetColumns, true)) {
    $db->exec('ALTER TABLE password_resets ADD COLUMN token_hash VARCHAR(255) DEFAULT NULL');
}
if (in_array('token', $resetColumns, true)) {
    $db->exec('ALTER TABLE password_resets DROP COLUMN token');
}

echo "Database migration complete.\n";
