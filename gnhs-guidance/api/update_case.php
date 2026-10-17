<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin('admin');
header('Content-Type: application/json');

$raw      = json_decode(file_get_contents('php://input'), true);
$caseId   = (int)($raw['case_id']     ?? 0);
$priority = trim($raw['priority']     ?? '');
$status   = trim($raw['status']       ?? '');
$session  = trim($raw['session_date'] ?? '');
$sessionType = trim($raw['session_type'] ?? 'in-person');
$location = trim($raw['location'] ?? 'Guidance Office');

$resolution        = trim($raw['resolution'] ?? '');
$resolutionProcess = trim($raw['resolution_process'] ?? '');
$sanctions         = trim($raw['sanctions'] ?? '');
$completeSessions  = filter_var($raw['complete_sessions'] ?? true, FILTER_VALIDATE_BOOLEAN);

if (!$caseId) jsonOut(['success'=>false,'message'=>'Case ID required.']);

try {
    $db = getDB();

    // Retrieve existing case details first
    $caseStmt = $db->prepare("SELECT * FROM cases WHERE id=?");
    $caseStmt->execute([$caseId]);
    $caseData = $caseStmt->fetch();
    if (!$caseData) jsonOut(['success'=>false,'message'=>'Case not found.']);

    $oldStatus   = $caseData['status'];
    $oldPriority = $caseData['priority'];
    $studentId   = $caseData['student_id'];
    $caseNumber  = $caseData['case_number'];
    $caseSubject = $caseData['subject'] ?: 'Concern';

    $counselorName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    if (!$counselorName) $counselorName = 'Guidance Counselor';

    // ── IF RESOLVED: Validate and process resolution, process, and sanctions ──
    if ($status === 'resolved') {
        if (!$resolution && !empty($caseData['resolution'])) {
            $resolution = $caseData['resolution'];
        }
        if (!$resolution) {
            jsonOut(['success' => false, 'message' => 'Please provide the resolution summary to mark this case as resolved.'], 400);
        }

        if (!$resolutionProcess) {
            $resolutionProcess = !empty($caseData['resolution_process']) ? $caseData['resolution_process'] : 'Settled through Counseling Session';
        }

        // Update cases table
        $updates = ['status=?', 'resolution=?', 'resolution_process=?', 'sanctions=?', 'resolved_at=NOW()', 'updated_at=NOW()'];
        $params  = ['resolved', $resolution, $resolutionProcess, $sanctions ?: null];
        if ($priority) {
            $updates[] = 'priority=?';
            $params[]  = $priority;
        }
        $params[] = $caseId;
        $db->prepare("UPDATE cases SET " . implode(',', $updates) . " WHERE id=?")->execute($params);

        // Conclude and archive any open counseling sessions for this case
        if ($completeSessions || stripos($resolutionProcess, 'counseling') !== false) {
            $completionNote = "Session concluded upon case resolution via {$resolutionProcess}.";
            $db->prepare("UPDATE counseling_sessions 
                SET status = 'completed', 
                    ended_at = NOW(), 
                    notes = CASE WHEN notes IS NULL OR notes = '' THEN ? ELSE CONCAT(notes, '\n\n', ?) END 
                WHERE case_id = ? AND status IN ('scheduled', 'in_progress', 'ongoing')")
               ->execute([$completionNote, $completionNote, $caseId]);
        }

        // Add resolution record in case_notes
        $noteLog = "Case officially RESOLVED by Counselor {$counselorName}.\n\n• Guidance Settlement Process: {$resolutionProcess}\n• Resolution Summary:\n{$resolution}";
        if ($sanctions) {
            $noteLog .= "\n\n• Regulated School Rule Sanction / Action Imposed:\n{$sanctions}";
        }
        $db->prepare("INSERT INTO case_notes (case_id, author_id, note_type, content, is_visible_to_student, created_at) VALUES (?, ?, 'resolution', ?, 1, NOW())")
           ->execute([$caseId, $user['id'], $noteLog]);

        // Send student notification
        if ($studentId) {
            $statusTitle = "Case #{$caseNumber} Officially Resolved";
            $statusMsg   = "Your counseling case #{$caseNumber} (\"{$caseSubject}\") has been officially marked as RESOLVED by Counselor {$counselorName}.\n\n• Settlement Process:\n{$resolutionProcess}\n\n• Case Resolution:\n{$resolution}\n";
            if ($sanctions) {
                $statusMsg .= "\n• Regulated School Rule Sanction / Action Imposed:\n{$sanctions}\n";
            }
            $statusMsg .= "\nDate Resolved: " . date('F d, Y \a\t g:i A') . "\nThank you for working cooperatively with the Guidance Office.";

            $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'case_update', 0, NOW())")
               ->execute([$studentId, $statusTitle, $statusMsg]);
        }

        jsonOut([
            'success'            => true,
            'is_resolved'        => true,
            'resolution_process' => $resolutionProcess,
            'message'            => 'Case officially resolved, settlement process recorded, and sessions archived!'
        ]);
    }

    // ── IF OTHER STATUS: Normal priority & status update ──
    $updates = []; $params = [];
    if ($priority) { $updates[]='priority=?'; $params[]=$priority; }
    if ($status)   { $updates[]='status=?';   $params[]=$status;   }
    if ($updates) {
        $updates[] = 'updated_at=NOW()';
        $params[] = $caseId;
        $db->prepare("UPDATE cases SET ".implode(',',$updates)." WHERE id=?")->execute($params);
    }

    $statusLabels = [
        'pending'      => 'Pending',
        'under_review' => 'Under Review',
        'ongoing'      => 'Ongoing',
        'resolved'     => 'Resolved',
        'closed'       => 'Closed'
    ];
    $newStatusLabel = $statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status));
    $oldStatusLabel = $statusLabels[$oldStatus] ?? ucfirst(str_replace('_', ' ', $oldStatus));

    // Send status notification to student
    if ($studentId && $status && ($status !== $oldStatus || empty($session))) {
        if ($status === 'under_review') {
            $statusTitle = 'Case Status Updated: Under Review';
            $statusMsg   = "Your case #{$caseNumber} (\"{$caseSubject}\") has been reviewed by Counselor {$counselorName} and its status has been updated to Under Review.\n\nThe guidance office is currently reviewing the details of your concern and preparing the appropriate guidance interventions.";
        } else if ($status === 'ongoing') {
            $statusTitle = 'Case Status Updated: Ongoing';
            $statusMsg   = "Your case #{$caseNumber} (\"{$caseSubject}\") status has been set to Ongoing by Counselor {$counselorName}.\n\nGuidance interventions and support actions are actively in progress.";
        } else {
            $statusTitle = "Case Status Updated: {$newStatusLabel}";
            $statusMsg   = "Your case #{$caseNumber} (\"{$caseSubject}\") status has been updated from {$oldStatusLabel} to {$newStatusLabel} by Counselor {$counselorName}.";
        }

        $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'case_update', 0, NOW())")
           ->execute([$studentId, $statusTitle, $statusMsg]);
    }

    // Schedule counseling session if provided
    if ($session) {
        $sessionTimestamp = strtotime($session);
        if (!$sessionTimestamp || $sessionTimestamp < (time() - 60)) {
            jsonOut(['success'=>false, 'message'=>'Counseling session cannot be scheduled in the past. Please select a present or future date and time.']);
        }

        $db->prepare("INSERT INTO counseling_sessions (case_id,student_id,counselor_id,session_date,status,session_type,location,created_at) VALUES (?,?,?,?,'scheduled',?,?,NOW())")
           ->execute([$caseId, $studentId, $user['id'], $session, $sessionType, $location]);

        if ($studentId) {
            $formattedDate = date('F d, Y \a\t g:i A', $sessionTimestamp);
            $formattedMode = ucfirst(str_replace('-', ' ', $sessionType));

            $sessionTitle = 'Counseling Session Scheduled';
            $sessionMsg   = "A counseling session for Case #{$caseNumber} (\"{$caseSubject}\") has been scheduled with Counselor {$counselorName}.\n\n• Date & Time: {$formattedDate}\n• Session Mode: {$formattedMode}\n• Location / Link: {$location}\n\nPlease attend punctually at the scheduled date and time.";

            $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'session', 0, NOW())")
               ->execute([$studentId, $sessionTitle, $sessionMsg]);
        }
    }

    jsonOut(['success'=>true,'message'=>'Case updated successfully.']);
} catch (Exception $e) {
    jsonOut(['success'=>false,'message'=>$e->getMessage()], 500);
}
