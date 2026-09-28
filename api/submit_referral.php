<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin('staff');
header('Content-Type: application/json');

$raw          = json_decode(file_get_contents('php://input'), true);
$studentName  = trim($raw['student_name']  ?? '');
$gradeSection = trim($raw['grade_section'] ?? '');
$concernType  = trim($raw['concern_type']  ?? '');
$urgency      = trim($raw['urgency']       ?? 'moderate');
$observations = trim($raw['observations']  ?? '');

if (!$studentName || !$gradeSection || !$concernType || !$observations) {
    jsonOut(['success'=>false,'message'=>'All required fields must be filled.']);
}

// Word limit: max 400 words
if (strlen($observations) < 30) {
    jsonOut(['success'=>false,'message'=>'Observations must be at least 30 characters long to provide sufficient detail.']);
}
if (str_word_count($observations) > 400) {
    jsonOut(['success'=>false,'message'=>'Observations must not exceed 400 words.']);
}

// Profanity filter
if (containsProfanity($studentName) || containsProfanity($observations)) {
    jsonOut(['success'=>false,'message'=>'Please remove inappropriate language or profanity from your submission.']);
}

// ── AI Summarization via Gemini API ──────────────────────────────────────────────────────────
function getAiSummary(string $concernType, string $studentName, string $observations): string {
    $apiKey = 'AQ.Ab8RN6KnxxiItAmADJTCOpW5golOyfCXwYGZ6IAVBceSpHqZkQ';
    if (empty($apiKey) || !function_exists('curl_init')) return '';

    $typeLabel = ucfirst(str_replace('_', ' ', $concernType));
    $wordCount = str_word_count($observations);
    $maxWords = max(10, (int)($wordCount * 0.5)); // ensure it's significantly shorter

    $prompt = "You are a school guidance counselor assistant at Guinayang National High School. "
            . "A teacher has submitted a referral for a student. Provide a concise, professional summary of the teacher's observation.\n"
            . "CRITICAL INSTRUCTION: Your summary MUST be significantly shorter than the teacher's original observation. "
            . "Limit your summary to a MAXIMUM of {$maxWords} words. Extract only the most essential information.\n"
            . "Be objective. Do not give advice, do not use Markdown formatting, and do not use introductory text like \"Summary:\".\n\n"
            . "Concern Type: {$typeLabel}\n"
            . "Student Name: {$studentName}\n"
            . "Teacher's Observation:\n{$observations}";

    $payload = json_encode([
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['maxOutputTokens' => 1500, 'temperature' => 0.4]
    ]);

    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key='.$apiKey);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);

    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err || !$response) return '';
    $data = json_decode($response, true);
    if (!is_array($data) || isset($data['error'])) return '';

    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    return trim($text);
}

try {
    $db      = getDB();
    $refNum  = generateRefNumber();
    
    // Generate AI summary (fails silently if API unavailable)
    $aiSummary = getAiSummary($concernType, $studentName, $observations);
    
    $stmt    = $db->prepare("INSERT INTO referrals (ref_number,teacher_id,student_name,grade_section,concern_type,urgency,observations,ai_summary) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$refNum,$user['id'],$studentName,$gradeSection,$concernType,$urgency,$observations,$aiSummary ?: null]);

    // Notify admins
    $admins = $db->query("SELECT id FROM users WHERE role='admin'")->fetchAll();
    $ns = $db->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,'referral')");
    foreach ($admins as $a) {
        $ns->execute([$a['id'],"New Referral: $refNum","Teacher {$user['first_name']} {$user['last_name']} submitted a ".strtoupper($urgency)." urgency referral."]);
    }

    jsonOut(['success'=>true,'ref_number'=>$refNum]);
} catch (Exception $e) {
    jsonOut(['success'=>false,'message'=>$e->getMessage()],500);
}





