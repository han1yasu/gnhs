<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin('admin');
header('Content-Type: application/json');

$raw = json_decode(file_get_contents('php://input'), true);
$sessionId    = (int)($raw['session_id'] ?? 0);
$caseId       = (int)($raw['case_id'] ?? 0);
$sessionNotes = trim($raw['session_notes'] ?? '');
$isResolved   = filter_var($raw['is_resolved'] ?? false, FILTER_VALIDATE_BOOLEAN);

if (!$sessionId && !$caseId) {
    jsonOut(['success' => false, 'message' => 'Session ID or Case ID is required.'], 400);
}

try {
    $db = getDB();

    // Fetch session and case data
    $stmt = $db->prepare("SELECT cs.*, c.case_number, c.subject AS case_subject, c.student_id AS case_student_id
        FROM counseling_sessions cs
        JOIN cases c ON cs.case_id = c.id
        WHERE cs.id = ?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();

    if (!$session) {
        jsonOut(['success' => false, 'message' => 'Counseling session not found.'], 404);
    }

    $caseId     = (int)$session['case_id'];
    $studentId  = (int)($session['student_id'] ?: $session['case_student_id']);
    $caseNumber = $session['case_number'];
    $caseSubj   = $session['case_subject'] ?: 'Concern';
    $counselorName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Guidance Counselor';

    $endNotes = $sessionNotes ?: ($session['notes'] ?? '');

    if ($isResolved) {
        // ── BRANCH: CASE RESOLVED ──────────────────────────────────
        $resolution = trim($raw['resolution'] ?? '');
        $sanctions  = trim($raw['sanctions'] ?? '');

        if (!$resolution) {
            jsonOut(['success' => false, 'message' => 'Please provide the case resolution summary.'], 400);
        }

        // Mark current session and all previous ongoing sessions for this case as completed
        $updateSessionStmt = $db->prepare("UPDATE counseling_sessions 
            SET status = 'completed', 
                ended_at = NOW(), 
                notes = ? 
            WHERE id = ?");
        $updateSessionStmt->execute([$endNotes, $sessionId]);

        $db->prepare("UPDATE counseling_sessions SET status = 'completed' WHERE case_id = ? AND status = 'ongoing'")
           ->execute([$caseId]);

        // Save final session notes into case_notes if provided
        if ($sessionNotes) {
            $db->prepare("INSERT INTO case_notes (case_id, author_id, note_type, content, is_visible_to_student, created_at) VALUES (?, ?, 'session_notes', ?, 0, NOW())")
               ->execute([$caseId, $user['id'], "Session Notes: " . $sessionNotes]);
        }

        // Update cases table
        $db->prepare("UPDATE cases 
            SET status = 'resolved', 
                resolution = ?, 
                sanctions = ?, 
                resolved_at = NOW(), 
                updated_at = NOW() 
            WHERE id = ?")
           ->execute([$resolution, $sanctions ?: null, $caseId]);

        // Add resolution record in case_notes
        $noteLog = "Case officially RESOLVED by Counselor {$counselorName}.\n\n• Resolution:\n{$resolution}";
        if ($sanctions) {
            $noteLog .= "\n\n• School Rule Sanction / Action Imposed:\n{$sanctions}";
        }
        $db->prepare("INSERT INTO case_notes (case_id, author_id, note_type, content, is_visible_to_student, created_at) VALUES (?, ?, 'resolution', ?, 1, NOW())")
           ->execute([$caseId, $user['id'], $noteLog]);

        // Send student notification
        if ($studentId) {
            $notifTitle = "Case #{$caseNumber} Officially Resolved";
            $notifMsg   = "Your counseling case #{$caseNumber} (\"{$caseSubj}\") has been officially marked as RESOLVED by Counselor {$counselorName}.\n\n• Case Resolution:\n{$resolution}\n";
            if ($sanctions) {
                $notifMsg .= "\n• Regulated School Rule Sanction / Disciplinary Action:\n{$sanctions}\n";
            }
            $notifMsg .= "\nDate Resolved: " . date('F d, Y \a\t g:i A') . "\nThank you for working cooperatively with the Guidance Office.";

            $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'case_update', 0, NOW())")
               ->execute([$studentId, $notifTitle, $notifMsg]);
        }

        jsonOut([
            'success'     => true,
            'is_resolved' => true,
            'message'     => 'Session completed and case officially resolved!'
        ]);

    } else {
        // ── BRANCH: NEEDS ANOTHER SESSION ──────────────────────────
        $nextDate     = trim($raw['next_session_date'] ?? '');
        $nextType     = trim($raw['next_session_type'] ?? 'in-person');
        $nextLocation = trim($raw['next_session_location'] ?? 'Guidance Office');
        $nextRemarks  = trim($raw['next_session_remarks'] ?? '');

        if (!$nextDate) {
            jsonOut(['success' => false, 'message' => 'Please set the next session schedule date and time.'], 400);
        }

        // Mark current session as ongoing progress (continuous tracking)
        $updateSessionStmt = $db->prepare("UPDATE counseling_sessions 
            SET status = 'ongoing', 
                ended_at = NOW(), 
                notes = ? 
            WHERE id = ?");
        $updateSessionStmt->execute([$endNotes, $sessionId]);

        // Save session notes into case_notes if provided
        if ($sessionNotes) {
            $db->prepare("INSERT INTO case_notes (case_id, author_id, note_type, content, is_visible_to_student, created_at) VALUES (?, ?, 'session_notes', ?, 0, NOW())")
               ->execute([$caseId, $user['id'], "Session Notes: " . $sessionNotes]);
        }

        // Keep case status as ongoing
        $db->prepare("UPDATE cases SET status = 'ongoing', updated_at = NOW() WHERE id = ?")
           ->execute([$caseId]);

        // Insert new counseling session
        $db->prepare("INSERT INTO counseling_sessions (case_id, student_id, counselor_id, session_date, status, session_type, location, notes, created_at) VALUES (?, ?, ?, ?, 'scheduled', ?, ?, ?, NOW())")
           ->execute([$caseId, $studentId, $user['id'], $nextDate, $nextType, $nextLocation, $nextRemarks ?: null]);

        $formattedDate = date('F d, Y \a\t g:i A', strtotime($nextDate));
        $formattedMode = ucfirst(str_replace('-', ' ', $nextType));

        // Add record to case_notes
        $noteLog = "Counseling session concluded for today. Case remains ONGOING.\nFollow-up session scheduled for {$formattedDate} ({$formattedMode}) at {$nextLocation}." . ($nextRemarks ? "\nRemarks: {$nextRemarks}" : "");
        $db->prepare("INSERT INTO case_notes (case_id, author_id, note_type, content, is_visible_to_student, created_at) VALUES (?, ?, 'follow_up_scheduled', ?, 0, NOW())")
           ->execute([$caseId, $user['id'], $noteLog]);

        // Send detailed notification to student
        if ($studentId) {
            $notifTitle = "Follow-Up Counseling Session Scheduled";
            $notifMsg   = "Your counseling session with Counselor {$counselorName} for Case #{$caseNumber} (\"{$caseSubj}\") has concluded for today.\n\nA follow-up counseling session has been scheduled:\n\n• Next Session: {$formattedDate}\n• Session Mode: {$formattedMode}\n• Location: {$nextLocation}\n";
            if ($nextRemarks) {
                $notifMsg .= "• Counselor Remarks: {$nextRemarks}\n";
            }
            $notifMsg .= "\nPlease be present at the Guidance Office at the scheduled time.";

            $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'session', 0, NOW())")
               ->execute([$studentId, $notifTitle, $notifMsg]);
        }

        jsonOut([
            'success'     => true,
            'is_resolved' => false,
            'message'     => 'Session completed. Follow-up session successfully scheduled!',
            'next_session'=> [
                'date'     => $formattedDate,
                'location' => $nextLocation,
                'type'     => $nextType
            ]
        ]);
    }

} catch (Exception $e) {
    jsonOut(['success' => false, 'message' => $e->getMessage()], 500);
}
