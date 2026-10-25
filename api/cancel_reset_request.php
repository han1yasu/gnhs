<?php
// ── GNHS Guidance System — Cancel Pending Password Reset Request ──
require_once __DIR__ . '/../includes/config.php';
startSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$raw = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$identifier = trim($raw['identifier'] ?? $raw['email'] ?? $raw['id_number'] ?? '');

if (empty($identifier)) {
    jsonOut(['success' => false, 'message' => 'Email or Student ID / LRN is required.']);
}

try {
    $db = getDB();

    // Find the user
    $stmt = $db->prepare("SELECT id, first_name, last_name, email, id_number, role FROM users WHERE (email = ? OR id_number = ?) LIMIT 1");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonOut(['success' => false, 'message' => 'Account not found.']);
    }

    $userId = (int)$user['id'];
    $fullName = trim($user['first_name'] . ' ' . $user['last_name']);

    // Cancel all pending reset requests for this user
    $cancelStmt = $db->prepare("UPDATE password_reset_requests 
        SET status = 'completed', 
            admin_notes = 'Cancelled by user — remembered current password',
            reviewed_at = NOW()
        WHERE user_id = ? AND status = 'pending'");
    $cancelStmt->execute([$userId]);
    $affected = $cancelStmt->rowCount();

    // Ensure failed attempts and lockout timers are cleared so user can enter password smoothly
    $db->prepare("UPDATE users SET failed_attempts = 0, lockout_until = NULL WHERE id = ?")->execute([$userId]);

    jsonOut([
        'success'   => true,
        'cancelled' => $affected > 0,
        'email'     => $user['email'],
        'id_number' => $user['id_number'],
        'role'      => $user['role'],
        'full_name' => $fullName,
        'message'   => 'Password reset request has been cancelled. Your current password remains active. You can now log in.'
    ]);

} catch (Exception $e) {
    jsonOut(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
