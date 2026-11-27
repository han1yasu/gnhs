<?php
require_once __DIR__ . '/../includes/config.php';
$adminUser = requireLogin('admin');
header('Content-Type: application/json');

$raw = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $_GET['action'] ?? ($raw['action'] ?? '');
$id     = (int)($_GET['id'] ?? ($raw['id'] ?? ($raw['user_id'] ?? 0)));

if (!$id) jsonOut(['success'=>false, 'message'=>'User ID required.']);

try {
    $db = getDB();
    $chkTarget = $db->prepare("SELECT id, email, first_name, last_name, role FROM users WHERE id=? LIMIT 1");
    $chkTarget->execute([$id]);
    $targetUser = $chkTarget->fetch();
    if (!$targetUser) {
        jsonOut(['success'=>false, 'message'=>'User account not found.'], 404);
    }

    $isActorTop = isTopAdminRole($adminUser['role'] ?? '');
    if (in_array($targetUser['role'], ['super_admin', 'admin'], true) && !$isActorTop) {
        jsonOut(['success'=>false, 'message'=>'Only Top Administrators (Principal / Developer) can modify or delete admin/guidance accounts.'], 403);
    }

    if ($action === 'deactivate') {
        if ($id === (int)$adminUser['id']) {
            jsonOut(['success'=>false, 'message'=>'You cannot deactivate your own active account while signed in.'], 400);
        }
        $db->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$id]);
        jsonOut(['success'=>true, 'message'=>'User deactivated.']);
    } elseif ($action === 'activate') {
        $db->prepare("UPDATE users SET is_active=1 WHERE id=?")->execute([$id]);
        jsonOut(['success'=>true, 'message'=>'User activated.']);
    } elseif ($action === 'delete') {
        if ($id === (int)$adminUser['id']) {
            jsonOut(['success'=>false, 'message'=>'You cannot delete your own active administrator account while signed in.'], 400);
        }

        $db->beginTransaction();
        try {
            // Reassign/clear counselor or assigned references
            $db->prepare("UPDATE cases SET assigned_to=NULL WHERE assigned_to=?")->execute([$id]);
            $db->prepare("UPDATE counseling_sessions SET counselor_id=NULL WHERE counselor_id=?")->execute([$id]);
            $db->prepare("UPDATE referrals SET student_id=NULL WHERE student_id=?")->execute([$id]);

            // Remove messages, notes, notifications, and reset requests linked directly to user
            $db->prepare("DELETE FROM notifications WHERE user_id=?")->execute([$id]);
            try { $db->prepare("DELETE FROM password_reset_requests WHERE user_id=?")->execute([$id]); } catch (Exception $e) {}
            try { $db->prepare("DELETE FROM password_resets WHERE email=?")->execute([$targetUser['email']]); } catch (Exception $e) {}
            try { $db->prepare("DELETE FROM case_messages WHERE sender_id=?")->execute([$id]); } catch (Exception $e) {}
            try { $db->prepare("DELETE FROM case_notes WHERE author_id=?")->execute([$id]); } catch (Exception $e) {}
            try { $db->prepare("DELETE FROM referral_messages WHERE sender_id=?")->execute([$id]); } catch (Exception $e) {}
            try { $db->prepare("DELETE FROM referral_notes WHERE author_id=?")->execute([$id]); } catch (Exception $e) {}
            try { $db->prepare("DELETE FROM counseling_sessions WHERE student_id=?")->execute([$id]); } catch (Exception $e) {}

            // Delete student cases & child rows
            $caseIds = $db->prepare("SELECT id FROM cases WHERE student_id=?");
            $caseIds->execute([$id]);
            $cList = $caseIds->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($cList)) {
                $ph = implode(',', array_fill(0, count($cList), '?'));
                $db->prepare("DELETE FROM case_messages WHERE case_id IN ($ph)")->execute($cList);
                $db->prepare("DELETE FROM case_notes WHERE case_id IN ($ph)")->execute($cList);
                $db->prepare("DELETE FROM counseling_sessions WHERE case_id IN ($ph)")->execute($cList);
                $db->prepare("DELETE FROM cases WHERE id IN ($ph)")->execute($cList);
            }

            // Delete teacher referrals & child rows
            $refIds = $db->prepare("SELECT id FROM referrals WHERE teacher_id=?");
            $refIds->execute([$id]);
            $rList = $refIds->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($rList)) {
                $ph = implode(',', array_fill(0, count($rList), '?'));
                try { $db->prepare("DELETE FROM referral_messages WHERE referral_id IN ($ph)")->execute($rList); } catch (Exception $e) {}
                try { $db->prepare("DELETE FROM referral_notes WHERE referral_id IN ($ph)")->execute($rList); } catch (Exception $e) {}
                try { $db->prepare("DELETE FROM counseling_sessions WHERE referral_id IN ($ph)")->execute($rList); } catch (Exception $e) {}
                $db->prepare("DELETE FROM referrals WHERE id IN ($ph)")->execute($rList);
            }

            $db->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
            $db->commit();
            jsonOut(['success'=>true, 'message'=>'User account permanently deleted.']);
        } catch (Exception $inner) {
            if ($db->inTransaction()) $db->rollBack();
            throw $inner;
        }
    } elseif ($action === 'reset_password') {
        $password = trim($raw['password'] ?? $_GET['password'] ?? '');
        if (strlen($password) < 8) {
            jsonOut(['success'=>false, 'message'=>'Password must be at least 8 characters long.']);
        }
        $stmt = $db->prepare("SELECT id, first_name, last_name, email FROM users WHERE id=? LIMIT 1");
        $stmt->execute([$id]);
        $targetUser = $stmt->fetch();
        if (!$targetUser) {
            jsonOut(['success'=>false, 'message'=>'User not found.']);
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $db->prepare("UPDATE users SET password=?, failed_attempts=0, lockout_until=NULL, is_active=1 WHERE id=?")->execute([$hash, $id]);
        try {
            $db->prepare("DELETE FROM password_resets WHERE email=?")->execute([$targetUser['email']]);
        } catch (Exception $e) {}
        try {
            $notif = $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read) VALUES (?, ?, ?, 'system', 0)");
            $notif->execute([
                $id,
                'Password Reset by School IT',
                'Your account password was updated by School IT Administrator ' . htmlspecialchars($adminUser['first_name'] . ' ' . $adminUser['last_name']) . '.'
            ]);
        } catch (Exception $e) {}
        jsonOut(['success'=>true, 'message'=>'Password reset successfully.']);
    } else {
        jsonOut(['success'=>false, 'message'=>'Unknown action.']);
    }
} catch (Exception $e) {
    jsonOut(['success'=>false, 'message'=>$e->getMessage()], 500);
}
