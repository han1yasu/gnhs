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
    $session = null;
    if ($sessionId > 0) {
        $stmt = $db->prepare("SELECT cs.*, 
            COALESCE(c.case_number, r.ref_number) AS case_number, 
            COALESCE(c.subject, CONCAT('Teacher Referral: ', REPLACE(r.concern_type, '_', ' '))) AS case_subject, 
            COALESCE(c.student_id, r.student_id, cs.student_id) AS case_student_id
            FROM counseling_sessions cs
            LEFT JOIN cases c ON cs.case_id = c.id
            LEFT JOIN referrals r ON cs.referral_id = r.id
            WHERE cs.id = ?");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch();
    }

    if (!$session && $caseId > 0) {
        // Fallback: fetch case directly and most recent session if any
        $stmt = $db->prepare("SELECT c.id AS case_id, c.case_number, c.subject AS case_subject, c.student_id AS case_student_id,
            cs.id AS id, cs.student_id, cs.notes, cs.session_date, cs.session_type, cs.location
            FROM cases c
            LEFT JOIN counseling_sessions cs ON cs.case_id = c.id
            WHERE c.id = ?
            ORDER BY cs.id DESC LIMIT 1");
        $stmt->execute([$caseId]);
        $session = $stmt->fetch();
        if ($session && !empty($session['id'])) {
            $sessionId = (int)$session['id'];
        }
    }

    if (!$session) {
        jsonOut(['success' => false, 'message' => 'Counseling session or Case not found.'], 404);
    }

    $caseId = (int)$session['case_id'];

    // Safely resolve student_id: NULL if empty/0 or not present in users table (prevents FK constraint violation)
    $candidateStudentId = !empty($session['student_id']) ? (int)$session['student_id'] : (!empty($session['case_student_id']) ? (int)$session['case_student_id'] : null);
    $studentId = null;
    if ($candidateStudentId && $candidateStudentId > 0) {
        $chk = $db->prepare("SELECT id FROM users WHERE id = ?");
        $chk->execute([$candidateStudentId]);
        if ($chk->fetchColumn()) {
            $studentId = $candidateStudentId;
        }
    }

    $caseNumber = $session['case_number'];
    $caseSubj   = $session['case_subject'] ?: 'Concern';
    $counselorName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Guidance Counselor';

    $endNotes = $sessionNotes ?: ($session['notes'] ?? '');

    if ($isResolved) {
        // ── BRANCH: CASE RESOLVED ──────────────────────────────────
        $resolution  = trim($raw['resolution'] ?? '');
        $sanctions   = trim($raw['sanctions'] ?? '');
        $guidanceRec = trim($raw['guidance_recommendations'] ?? '');

        if (!$resolution) {
            jsonOut(['success' => false, 'message' => 'Please provide the case resolution summary.'], 400);
        }

        // Mark current session as completed if exists
        if ($sessionId > 0) {
            $updateSessionStmt = $db->prepare("UPDATE counseling_sessions 
                SET status = 'completed', 
                    ended_at = NOW(), 
                    notes = ?,
                    recommendations = ?
                WHERE id = ?");
            $updateSessionStmt->execute([$endNotes, $guidanceRec ?: null, $sessionId]);
        }

        if ($caseId) {
            $db->prepare("UPDATE counseling_sessions SET status = 'completed' WHERE case_id = ? AND status IN ('ongoing', 'in_progress', 'scheduled')")
               ->execute([$caseId]);
        }
        if (!empty($session['referral_id'])) {
            $db->prepare("UPDATE counseling_sessions SET status = 'completed' WHERE referral_id = ? AND status IN ('ongoing', 'in_progress', 'scheduled')")
               ->execute([$session['referral_id']]);
            $db->prepare("UPDATE referrals SET status = 'resolved' WHERE id = ?")
               ->execute([$session['referral_id']]);

            // Notify referring teacher of resolution
            try {
                $rStmt = $db->prepare("SELECT * FROM referrals WHERE id = ?");
                $rStmt->execute([$session['referral_id']]);
                $refData = $rStmt->fetch();
                if ($refData && !empty($refData['teacher_id'])) {
                    $tTitle = "Referral #{$refData['ref_number']} Concluded & Resolved";
                    $tMsg   = "Counselor {$counselorName} has completed the counseling session for your referred student {$refData['student_name']} regarding \"{$refData['concern_type']}\". The referral is now officially marked as RESOLVED.\n\n• Case Resolution Summary:\n{$resolution}";
                    if (!empty($guidanceRec)) {
                        $tMsg .= "\n\n• Guidance Recommendations:\n{$guidanceRec}";
                    }
                    $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'referral', 0, NOW())")
                       ->execute([$refData['teacher_id'], $tTitle, $tMsg]);
                }
            } catch (Exception $nte) {}
        }

        // Save final session notes into case_notes if provided (internal, not visible to student)
        if ($sessionNotes && $caseId) {
            $db->prepare("INSERT INTO case_notes (case_id, author_id, note_type, content, is_visible_to_student, created_at) VALUES (?, ?, 'session_notes', ?, 0, NOW())")
               ->execute([$caseId, $user['id'], "Session Notes: " . $sessionNotes]);
        }

        // Update cases table
        if ($caseId) {
            $db->prepare("UPDATE cases 
                SET status = 'resolved', 
                    resolution = ?, 
                    guidance_recommendations = ?,
                    resolution_process = 'Settled through Counseling Session',
                    sanctions = ?, 
                    resolved_at = NOW(), 
                    updated_at = NOW() 
                WHERE id = ?")
               ->execute([$resolution, $guidanceRec ?: null, $sanctions ?: null, $caseId]);

            // Add resolution record in case_notes (visible to student)
            $noteLog = "Case officially RESOLVED by Counselor {$counselorName}.\n\n• Resolution:\n{$resolution}";
            if ($sanctions) {
                $noteLog .= "\n\n• School Rule Sanction / Action Imposed:\n{$sanctions}";
            }
            if ($guidanceRec) {
                $noteLog .= "\n\n• Guidance Recommendations:\n{$guidanceRec}";
            }
            $db->prepare("INSERT INTO case_notes (case_id, author_id, note_type, content, is_visible_to_student, created_at) VALUES (?, ?, 'resolution', ?, 1, NOW())")
               ->execute([$caseId, $user['id'], $noteLog]);
        }

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
        $nextMeetingLink = trim($raw['next_session_meeting_link'] ?? '');
        $nextRemarks  = trim($raw['next_session_remarks'] ?? '');

        if (!$nextDate) {
            jsonOut(['success' => false, 'message' => 'Please set the next session schedule date and time.'], 400);
        }
        $nextTimestamp = strtotime($nextDate);
        if (!$nextTimestamp || $nextTimestamp < (time() - 60)) {
            jsonOut(['success' => false, 'message' => 'Next counseling session cannot be scheduled in the past. Please select a present or future date and time.'], 400);
        }

        $guidanceRec = trim($raw['guidance_recommendations'] ?? '');

        // Mark current session as ongoing progress (continuous tracking)
        if ($sessionId > 0) {
            $updateSessionStmt = $db->prepare("UPDATE counseling_sessions 
                SET status = 'ongoing', 
                    ended_at = NOW(), 
                    notes = ?,
                    recommendations = ?
                WHERE id = ?");
            $updateSessionStmt->execute([$endNotes, $guidanceRec ?: null, $sessionId]);
        }

        // Save session notes into case_notes if provided (internal, not visible to student)
        if ($sessionNotes && $caseId) {
            $db->prepare("INSERT INTO case_notes (case_id, author_id, note_type, content, is_visible_to_student, created_at) VALUES (?, ?, 'session_notes', ?, 0, NOW())")
               ->execute([$caseId, $user['id'], "Session Notes: " . $sessionNotes]);
        }

        // Keep case / referral status as ongoing, update recommendations if provided
        if ($caseId) {
            if ($guidanceRec) {
                $db->prepare("UPDATE cases SET status = 'ongoing', guidance_recommendations = ?, updated_at = NOW() WHERE id = ?")
                   ->execute([$guidanceRec, $caseId]);
                $db->prepare("INSERT INTO case_notes (case_id, author_id, note_type, content, is_visible_to_student, created_at) VALUES (?, ?, 'guidance_recommendation', ?, 1, NOW())")
                   ->execute([$caseId, $user['id'], "Guidance Recommendations: " . $guidanceRec]);
            } else {
                $db->prepare("UPDATE cases SET status = 'ongoing', updated_at = NOW() WHERE id = ?")
                   ->execute([$caseId]);
            }
        }
        if (!empty($session['referral_id'])) {
            $db->prepare("UPDATE referrals SET status = 'ongoing' WHERE id = ?")->execute([$session['referral_id']]);
        }

        // Insert new counseling session
        $db->prepare("INSERT INTO counseling_sessions (case_id, referral_id, student_id, counselor_id, session_date, status, session_type, location, meeting_link, notes, created_at) VALUES (?, ?, ?, ?, ?, 'scheduled', ?, ?, ?, ?, NOW())")
           ->execute([$caseId ?: null, $session['referral_id'] ?: null, $studentId, $user['id'], $nextDate, $nextType, $nextLocation, $nextMeetingLink ?: null, $nextRemarks ?: null]);

        $formattedDate = date('F d, Y \a\t g:i A', strtotime($nextDate));
        $formattedMode = ($nextType === 'online' || $nextType === 'virtual') ? 'Online / Virtual' : 'In-Person';

        // Add record to case_notes
        if ($caseId) {
            $noteLog = "Counseling session concluded for today. Case remains ONGOING.\nFollow-up session scheduled for {$formattedDate} ({$formattedMode})" . (($nextType === 'online' || $nextType === 'virtual') && $nextMeetingLink ? " (Link: {$nextMeetingLink})" : " at {$nextLocation}") . ($nextRemarks ? "\nRemarks: {$nextRemarks}" : "");
            $db->prepare("INSERT INTO case_notes (case_id, author_id, note_type, content, is_visible_to_student, created_at) VALUES (?, ?, 'follow_up_scheduled', ?, 0, NOW())")
               ->execute([$caseId, $user['id'], $noteLog]);
        }

        // Send detailed notification to student
        if ($studentId) {
            $notifTitle = "Follow-Up Counseling Session Scheduled";
            $notifMsg   = "Your counseling session with Counselor {$counselorName} for " . ($caseNumber ? "#{$caseNumber}" : "your guidance record") . " (\"{$caseSubj}\") has concluded for today.\n\nA follow-up counseling session has been scheduled:\n\n• Next Session: {$formattedDate}\n• Session Mode: {$formattedMode}\n";
            if (($nextType === 'online' || $nextType === 'virtual') && !empty($nextMeetingLink)) {
                $notifMsg .= "• Virtual Meeting Link: {$nextMeetingLink}\n";
            } else {
                $notifMsg .= "• Location: {$nextLocation}\n";
            }
            if ($nextRemarks) {
                $notifMsg .= "• Counselor Remarks: {$nextRemarks}\n";
            }
            $notifMsg .= "\nPlease be present and prepared at the scheduled time.";

            $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'session', 0, NOW())")
               ->execute([$studentId, $notifTitle, $notifMsg]);
        }

        // Send notification to referring teacher if linked to a referral
        if (!empty($session['referral_id'])) {
            try {
                $rStmt = $db->prepare("SELECT * FROM referrals WHERE id = ?");
                $rStmt->execute([$session['referral_id']]);
                $refData = $rStmt->fetch();
                if ($refData && !empty($refData['teacher_id'])) {
                    $tTitle = "Referral #{$refData['ref_number']} Update: Follow-Up Scheduled";
                    $tMsg   = "Counselor {$counselorName} conducted a counseling session with your referred student {$refData['student_name']}.\n\nA follow-up session has been scheduled:\n\n• Next Session: {$formattedDate}\n• Session Mode: {$formattedMode}\n";
                    if (($nextType === 'online' || $nextType === 'virtual') && !empty($nextMeetingLink)) {
                        $tMsg .= "• Virtual Meeting Link: {$nextMeetingLink}\n";
                    } else {
                        $tMsg .= "• Location: {$nextLocation}\n";
                    }
                    if ($nextRemarks) {
                        $tMsg .= "• Counselor Remarks: {$nextRemarks}\n";
                    }
                    $tMsg .= "\nThe referral status remains Ongoing.";
                    $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'referral', 0, NOW())")
                       ->execute([$refData['teacher_id'], $tTitle, $tMsg]);
                }
            } catch (Exception $nte) {}
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
