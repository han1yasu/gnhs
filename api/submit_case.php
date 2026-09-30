<?php
require_once __DIR__ . '/../includes/config.php';
startSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonOut(['success'=>false,'message'=>'Method not allowed.'],405);

$raw         = json_decode(file_get_contents('php://input'), true);
$isAnon      = (bool)($raw['is_anonymous']   ?? false);
$concernType = trim($raw['concern_type']      ?? '');
$subject     = trim($raw['subject']           ?? '');
$description = trim($raw['description']       ?? '');
$priority    = trim($raw['priority']          ?? 'medium');
$incidentDate= trim($raw['incident_date']     ?? '');
$contact     = trim($raw['preferred_contact'] ?? 'in_person');

if (!$concernType || !$subject || !$description) {
    jsonOut(['success'=>false,'message'=>'Concern type, subject and description are required.']);
}

require_once __DIR__ . '/../includes/summarizer.php';

// Word limit: max 400 words
if (strlen($description) < 30) {
    jsonOut(['success'=>false,'message'=>'Description must be at least 30 characters long to provide sufficient detail.']);
}
if (str_word_count($description) > 400) {
    jsonOut(['success'=>false,'message'=>'Description must not exceed 400 words.']);
}

// Profanity filter
if (containsProfanity($subject) || containsProfanity($description)) {
    jsonOut(['success'=>false,'message'=>'Please remove inappropriate language or profanity from your submission.']);
}

// Always associate case with the submitting student's account if logged in
$studentId = $_SESSION['user']['id'] ?? null;
if (!$studentId && empty($_SESSION['user'])) {
    jsonOut(['success'=>false,'message'=>'Please log in to submit a concern.']);
}

try {
    $db        = getDB();
    $caseNum   = generateCaseNumber();
    $anonToken = $isAnon ? bin2hex(random_bytes(24)) : null;

    // Generate summary via Gemini AI or intelligent guidance extractor
    $aiSummary = generateCaseSummary($concernType, $subject, $description, $priority);

    // Get counselor to assign
    $counselor  = $db->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetch();
    $assignedTo = $counselor ? $counselor['id'] : null;

    $stmt = $db->prepare("INSERT INTO cases
        (case_number,student_id,is_anonymous,anon_token,concern_type,subject,description,ai_summary,priority,status,incident_date,preferred_contact,assigned_to)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $caseNum, $studentId, (int)$isAnon, $anonToken,
        $concernType, $subject, $description, $aiSummary ?: null,
        $priority, 'pending', $incidentDate ?: null, $contact, $assignedTo
    ]);
    $caseId = $db->lastInsertId();

    // Notify counselor
    if ($assignedTo) {
        $counselorNotifTitle = "New " . ($isAnon ? "Anonymous " : "") . "Case: $caseNum";
        $db->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,?)")
           ->execute([$assignedTo, $counselorNotifTitle,
               "A new ".strtoupper($priority)." priority ".str_replace('_',' ',$concernType)." case has been submitted.",
               'case_update']);
    }
    // Notify student
    if ($studentId) {
        $studentNotifMsg = $isAnon 
            ? "Your anonymous case $caseNum has been submitted. Your identity is kept confidential from the counselor."
            : "Your case $caseNum has been submitted and is pending review.";
        $db->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,?)")
           ->execute([$studentId, "Case Submitted", $studentNotifMsg, 'case_update']);
    }

    jsonOut([
        'success'    => true,
        'case_number'=> $caseNum,
        'case_id'    => $caseId,
        'anon_token' => $anonToken,
        'ai_summary' => $aiSummary,
    ]);

} catch (Exception $e) {
    jsonOut(['success'=>false,'message'=>'Server error: '.$e->getMessage()], 500);
}





