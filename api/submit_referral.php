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

require_once __DIR__ . '/../includes/summarizer.php';

try {
    $db      = getDB();
    $refNum  = generateRefNumber();
    
    // Generate AI summary via Gemini API or intelligent guidance fallback
    $aiSummary = generateReferralSummary($concernType, $studentName, $observations, $urgency);
    
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





