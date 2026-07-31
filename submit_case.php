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

$studentId = null;
if (!$isAnon) {
    if (empty($_SESSION['user'])) jsonOut(['success'=>false,'message'=>'Please log in first.']);
    $studentId = $_SESSION['user']['id'];
}

// ── AI Summarization via Anthropic API ────────────────────────
function getAiSummary(string $concernType, string $subject, string $description): string {
    // ✏️ PUT YOUR API KEY BELOW
    $apiKey = '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';

    if (empty($apiKey) || !function_exists('curl_init')) return '';

    $typeLabel = ucfirst(str_replace('_', ' ', $concernType));
    $prompt = "You are a school guidance counselor assistant at Guinayang National High School. "
            . "A student has submitted a concern. Write a concise 2-3 sentence professional summary "
            . "of the key issue so the counselor can quickly understand the situation. "
            . "Be objective. Do not give advice — only summarize what the student reported.\n\n"
            . "Concern Type: {$typeLabel}\n"
            . "Subject: {$subject}\n"
            . "Student's Description:\n{$description}";

    $payload = json_encode([
        'model'      => 'claude-haiku-4-5',
        'max_tokens' => 250,
        'messages'   => [['role'=>'user','content'=>$prompt]]
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: '.$apiKey,
            'anthropic-version: 2023-06-01',
        ],
    ]);

    $response = curl_exec($ch);
    $err      = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err || !$response) return '';

    $data = json_decode($response, true);
    if (!is_array($data)) return '';

    // API returned an error object
    if (isset($data['error'])) return '';

    // Success - extract text
    $text = trim($data['content'][0]['text'] ?? '');
    return $text;
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
