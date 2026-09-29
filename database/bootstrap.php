<?php
require_once __DIR__ . '/../includes/config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$email = trim(getenv('BOOTSTRAP_ADMIN_EMAIL') ?: '');
$first = trim(getenv('BOOTSTRAP_ADMIN_FIRST_NAME') ?: 'Guidance');
$last = trim(getenv('BOOTSTRAP_ADMIN_LAST_NAME') ?: 'Owner');
$idNumber = trim(getenv('BOOTSTRAP_ADMIN_ID') ?: '');
$password = getenv('BOOTSTRAP_ADMIN_PASSWORD') ?: '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$idNumber || strlen($password) < 14) {
    fwrite(STDERR, "Set a valid bootstrap admin email and ID, and use a password of at least 14 characters.\n");
    exit(1);
}

$db = getDB();
$existing = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
if ($existing > 0) {
    echo "An admin account already exists; bootstrap skipped.\n";
    exit(0);
}

$stmt = $db->prepare("INSERT INTO users (first_name,last_name,email,id_number,password,role,avatar_initials,is_active,is_setup_complete) VALUES (?,?,?,?,?,'admin',?,1,1)");
$stmt->execute([$first, $last, $email, $idNumber, password_hash($password, PASSWORD_DEFAULT), strtoupper(substr($first, 0, 1) . substr($last, 0, 1))]);
echo "Owner admin account created. Sign in with the email and password from .env, then configure an authenticator app.\n";
