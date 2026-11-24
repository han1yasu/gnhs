<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin('admin');
header('Content-Type: application/json');

$sessionId = (int)($_GET['id'] ?? 0);
if (!$sessionId) {
    jsonOut(['success' => false, 'message' => 'Session ID is required.'], 400);
}

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT cs.*,
        CASE 
          WHEN c.id IS NOT NULL THEN (CASE WHEN c.is_anonymous = 1 THEN 'Anonymous Student' ELSE CONCAT(s.first_name, ' ', s.last_name) END)
          ELSE COALESCE(CONCAT(s.first_name, ' ', s.last_name), r.student_name, 'Student')
        END AS student_name,
        CASE 
          WHEN c.id IS NOT NULL THEN (CASE WHEN c.is_anonymous = 1 THEN 'Confidential' ELSE s.id_number END)
          ELSE COALESCE(s.id_number, '—')
        END AS id_number,
        CASE 
          WHEN c.id IS NOT NULL THEN (CASE WHEN c.is_anonymous = 1 THEN 'Confidential' ELSE s.grade_section END)
          ELSE COALESCE(s.grade_section, r.grade_section, '—')
        END AS grade_section,
        c.is_anonymous,
        c.is_peer_report,
        c.reported_student_name,
        c.reported_student_grade,
        COALESCE(c.case_number, r.ref_number) AS case_number,
        COALESCE(c.subject, CONCAT('Teacher Referral: ', REPLACE(r.concern_type, '_', ' '))) AS case_subject,
        COALESCE(c.concern_type, r.concern_type) AS concern_type,
        COALESCE(c.description, r.observations) AS case_description,
        COALESCE(c.priority, r.urgency) AS case_priority,
        COALESCE(c.status, r.status) AS case_status,
        c.incident_date,
        c.preferred_contact,
        c.witnesses,
        c.evidence_files,
        c.resolution,
        c.sanctions,
        c.resolved_at,
        CONCAT(u.first_name, ' ', u.last_name) AS counselor_name
        FROM counseling_sessions cs
        LEFT JOIN users s ON cs.student_id = s.id
        LEFT JOIN cases c ON cs.case_id = c.id
        LEFT JOIN referrals r ON cs.referral_id = r.id
        LEFT JOIN users u ON cs.counselor_id = u.id
        WHERE cs.id = ?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();

    if (!$session) {
        jsonOut(['success' => false, 'message' => 'Counseling session not found.'], 404);
    }

    // Decode evidence files if JSON string
    $evidence = [];
    if (!empty($session['evidence_files'])) {
        try {
            $evidence = is_string($session['evidence_files']) ? json_decode($session['evidence_files'], true) : $session['evidence_files'];
        } catch (Exception $e) { $evidence = []; }
    }
    $session['parsed_evidence'] = is_array($evidence) ? $evidence : [];

    // Fetch case or referral notes
    $notes = [];
    if (!empty($session['case_id'])) {
        $notesStmt = $db->prepare("SELECT cn.*, CONCAT(u.first_name, ' ', u.last_name) AS author_name, u.role AS author_role
            FROM case_notes cn
            LEFT JOIN users u ON cn.author_id = u.id
            WHERE cn.case_id = ?
            ORDER BY cn.created_at ASC");
        $notesStmt->execute([$session['case_id']]);
        $notes = $notesStmt->fetchAll();
    } elseif (!empty($session['referral_id'])) {
        ensureReferralNotesTable($db);
        $notesStmt = $db->prepare("SELECT rn.*, CONCAT(u.first_name, ' ', u.last_name) AS author_name, u.role AS author_role
            FROM referral_notes rn
            LEFT JOIN users u ON rn.author_id = u.id
            WHERE rn.referral_id = ?
            ORDER BY rn.created_at ASC");
        $notesStmt->execute([$session['referral_id']]);
        $notes = $notesStmt->fetchAll();
    }

    // Fetch all sessions associated with this case or referral
    if (!empty($session['case_id'])) {
        $allSessionsStmt = $db->prepare("SELECT id, session_date, status, session_type, location, meeting_link, notes, started_at, ended_at
            FROM counseling_sessions
            WHERE case_id = ?
            ORDER BY session_date ASC");
        $allSessionsStmt->execute([$session['case_id']]);
    } elseif (!empty($session['referral_id'])) {
        $allSessionsStmt = $db->prepare("SELECT id, session_date, status, session_type, location, meeting_link, notes, started_at, ended_at
            FROM counseling_sessions
            WHERE referral_id = ?
            ORDER BY session_date ASC");
        $allSessionsStmt->execute([$session['referral_id']]);
    } else {
        $allSessionsStmt = null;
    }
    $allSessions = $allSessionsStmt ? $allSessionsStmt->fetchAll() : [];

    jsonOut([
        'success'      => true,
        'session'      => $session,
        'notes'        => $notes,
        'all_sessions' => $allSessions
    ]);

} catch (Exception $e) {
    jsonOut(['success' => false, 'message' => $e->getMessage()], 500);
}
