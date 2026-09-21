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

$studentId = null;
if (!$isAnon) {
    if (empty($_SESSION['user'])) jsonOut(['success'=>false,'message'=>'Please log in first.']);
    $studentId = $_SESSION['user']['id'];
}

// â”€â”€ AI Summarization via Anthropic API â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function getAiSummary(string $concernType, string $subject, string $description): string {
    $apiKey = 'AQ.Ab8RN6KnxxiItAmADJTCOpW5golOyfCXwYGZ6IAVBceSpHqZkQ';

    if (empty($apiKey) || !function_exists('curl_init')) return '';

    $typeLabel = ucfirst(str_replace('_', ' ', $concernType));
    $prompt = "You are a school guidance counselor assistant at Guinayang National High School. "
            . "A student has submitted a concern. Provide a concise, professional summary of the student's concern in exactly 1 to 3 short sentences. "
            . "CRITICAL INSTRUCTION: Your summary MUST be significantly shorter than the student's original description. Extract only the most essential information. "
            . "Be objective. Do not give advice, do not use Markdown formatting, and do not use introductory text like \"Summary:\" — only summarize what the student reported.\n\n"
            . "Concern Type: {$typeLabel}\n"
            . "Subject: {$subject}\n"
            . "Student's Description:\n{$description}";

    $payload = json_encode([
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ],
        'generationConfig' => [
            'maxOutputTokens' => 1500,
            'temperature' => 0.4
        ]
    ]);

    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.7-flash:generateContent?key='.$apiKey);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json'
        ],
    ]);

    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err || !$response) return '';

    $data = json_decode($response, true);
    if (!is_array($data)) return '';

    // API returned an error object
    if (isset($data['error'])) return '';

    // Success - extract text
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    return trim($text);
}

try {
    $db        = getDB();
    $caseNum   = generateCaseNumber();
    $anonToken = $isAnon ? bin2hex(random_bytes(24)) : null;

    // Generate AI summary (fails silently if API unavailable)
    $aiSummary = getAiSummary($concernType, $subject, $description);

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
        $db->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,?)")
           ->execute([$assignedTo, "New Case: $caseNum",
               "A new ".strtoupper($priority)." priority ".str_replace('_',' ',$concernType)." case has been submitted.",
               'case_update']);
    }
    // Notify student
    if ($studentId) {
        $db->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,?)")
           ->execute([$studentId, "Case Submitted",
               "Your case $caseNum has been submitted and is pending review.", 'case_update']);
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





