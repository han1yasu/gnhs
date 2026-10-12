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

    // Update case status and priority
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

    // 1. Send detailed Case Status notification to the student if status was updated
    if ($studentId && $status && ($status !== $oldStatus || empty($session))) {
        if ($status === 'under_review') {
            $statusTitle = 'Case Status Updated: Under Review';
            $statusMsg   = "Your case #{$caseNumber} (\"{$caseSubject}\") has been reviewed by Counselor {$counselorName} and its status has been updated to Under Review.\n\nThe guidance office is currently reviewing the details of your concern and preparing the appropriate guidance interventions.";
        } else if ($status === 'ongoing') {
            $statusTitle = 'Case Status Updated: Ongoing';
            $statusMsg   = "Your case #{$caseNumber} (\"{$caseSubject}\") status has been set to Ongoing by Counselor {$counselorName}.\n\nGuidance interventions and support actions are actively in progress.";
        } else if ($status === 'resolved') {
            $statusTitle = 'Case Resolved';
            $statusMsg   = "Your case #{$caseNumber} (\"{$caseSubject}\") has been marked as Resolved by Counselor {$counselorName}.\n\nIf you have further questions or require additional support, you may reach out through the chat system.";
        } else {
            $statusTitle = "Case Status Updated: {$newStatusLabel}";
            $statusMsg   = "Your case #{$caseNumber} (\"{$caseSubject}\") status has been updated from {$oldStatusLabel} to {$newStatusLabel} by Counselor {$counselorName}.";
        }

        $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read) VALUES (?, ?, ?, 'case_update', 0)")
           ->execute([$studentId, $statusTitle, $statusMsg]);
    }

    // 2. Schedule counseling session and send detailed notification
    if ($session) {
        $db->prepare("INSERT INTO counseling_sessions (case_id,student_id,counselor_id,session_date,status,session_type,location) VALUES (?,?,?,?,'scheduled',?,?)")
           ->execute([$caseId, $studentId, $user['id'], $session, $sessionType, $location]);

        if ($studentId) {
            $sessionTimestamp = strtotime($session);
            $formattedDate = date('F d, Y \a\t g:i A', $sessionTimestamp);
            $formattedMode = ucfirst(str_replace('-', ' ', $sessionType));

            $sessionTitle = 'Counseling Session Scheduled';
            $sessionMsg   = "A counseling session for Case #{$caseNumber} (\"{$caseSubject}\") has been scheduled with Counselor {$counselorName}.\n\n• Date & Time: {$formattedDate}\n• Session Mode: {$formattedMode}\n• Location / Link: {$location}\n\nPlease attend punctually at the scheduled date and time.";

            $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read) VALUES (?, ?, ?, 'session', 0)")
               ->execute([$studentId, $sessionTitle, $sessionMsg]);
        }
    }

    jsonOut(['success'=>true,'message'=>'Case updated successfully.']);
} catch (Exception $e) {
    jsonOut(['success'=>false,'message'=>$e->getMessage()], 500);
}
