<?php
require_once __DIR__ . '/../includes/config.php';
startSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonOut(['success'=>false,'message'=>'Method not allowed.'],405);

// Check if request is multipart/form-data or json
$isFormData = !empty($_POST) || !empty($_FILES);
$raw = $isFormData ? $_POST : json_decode(file_get_contents('php://input'), true);
if (!is_array($raw)) $raw = [];

$isAnon = false;
if (isset($raw['is_anonymous'])) {
    $val = $raw['is_anonymous'];
    $isAnon = ($val === true || $val === 'true' || $val === 1 || $val === '1' || $val === 'on');
}

$concernType = trim($raw['concern_type']      ?? '');
$subject     = trim($raw['subject']           ?? '');
$description = trim($raw['description']       ?? '');
$priority    = trim($raw['priority']          ?? 'medium');
$incidentDate= trim($raw['incident_date']     ?? '');
$contact     = trim($raw['preferred_contact'] ?? 'in_person');
$witnesses   = trim($raw['witnesses']         ?? '');
$isPeerReport = isset($raw['is_peer_report']) ? (int)($raw['is_peer_report'] === '1' || $raw['is_peer_report'] === true || $raw['is_peer_report'] === 'true' || $raw['is_peer_report'] === 1) : 0;
$reportedStudentName = trim($raw['reported_student_name'] ?? '');
$reportedStudentGrade = trim($raw['reported_student_grade'] ?? '');

if ($isPeerReport && empty($reportedStudentName)) {
    jsonOut(['success'=>false, 'message'=>'Please provide the name of the student you are reporting for.']);
}

if (!empty($incidentDate)) {
    $today = date('Y-m-d');
    if ($incidentDate > $today) {
        jsonOut(['success'=>false, 'message'=>"Incident date cannot be in the future. Today is {$today}."]);
    }
}

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
if (containsProfanity($subject) || containsProfanity($description) || ($witnesses && containsProfanity($witnesses))) {
    jsonOut(['success'=>false,'message'=>'Please remove inappropriate language or profanity from your submission.']);
}

// Always associate case with the submitting student's account if logged in
$studentId = $_SESSION['user']['id'] ?? null;
if (!$studentId && empty($_SESSION['user'])) {
    jsonOut(['success'=>false,'message'=>'Please log in to submit a concern.']);
}

// Process optional evidence files (pictures and videos)
$evidenceFiles = [];
$primaryUploadDir = __DIR__ . '/../uploads/evidence/';
$rootUploadDir    = 'C:/xampp/htdocs/gnhs-guidance/uploads/evidence/';
$nestedUploadDir  = 'C:/xampp/htdocs/gnhs-guidance/gnhs-guidance/uploads/evidence/';

if (!is_dir($rootUploadDir))   @mkdir($rootUploadDir, 0755, true);
if (!is_dir($nestedUploadDir)) @mkdir($nestedUploadDir, 0755, true);
if (!is_dir($primaryUploadDir)) @mkdir($primaryUploadDir, 0755, true);

if (!empty($_FILES['evidence'])) {
    $files = $_FILES['evidence'];
    $fileCount = is_array($files['name']) ? count($files['name']) : 1;

    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif', 'jfif', 'mp4', 'webm', 'mov', 'mkv', 'ogg', '3gp', 'avi', 'm4v'];
    $imageExts   = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif', 'jfif'];
    $videoExts   = ['mp4', 'webm', 'mov', 'mkv', 'ogg', '3gp', 'avi', 'm4v'];
    $maxFileSize = 100 * 1024 * 1024; // 100MB per file

    for ($i = 0; $i < $fileCount; $i++) {
        $origName = is_array($files['name'])     ? $files['name'][$i]     : $files['name'];
        if (empty($origName)) continue;

        $error    = is_array($files['error'])    ? $files['error'][$i]    : $files['error'];
        if ($error === UPLOAD_ERR_NO_FILE) continue;

        if ($error !== UPLOAD_ERR_OK) {
            if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
                jsonOut(['success'=>false, 'message'=>"File '{$origName}' exceeds the maximum allowed upload size (100MB)."]);
            }
            jsonOut(['success'=>false, 'message'=>"Upload failed for '{$origName}' (code {$error}). Please try again."]);
        }

        $tmpName  = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $size     = is_array($files['size'])     ? $files['size'][$i]     : $files['size'];

        if ($size > $maxFileSize) {
            jsonOut(['success'=>false, 'message'=>"File '{$origName}' exceeds the 100MB limit."]);
        }
        if ($size <= 0) continue;

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts)) {
            jsonOut(['success'=>false, 'message'=>"File format (.{$ext}) for '{$origName}' is not supported. Please upload JPG, PNG, WEBP, GIF, MP4, MOV, or WEBM."]);
        }

        // Verify MIME type using Fileinfo if available
        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $tmpName);
            finfo_close($finfo);
        } else if (function_exists('mime_content_type')) {
            $mime = mime_content_type($tmpName);
        }

        $isImage = in_array($ext, $imageExts) || (strpos($mime, 'image/') === 0);
        $isVideo = in_array($ext, $videoExts) || (strpos($mime, 'video/') === 0);

        if (!$isImage && !$isVideo) {
            jsonOut(['success'=>false, 'message'=>"File '{$origName}' could not be verified as an image or video."]);
        }

        $newFileName = 'ev_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $destPath    = $primaryUploadDir . $newFileName;

        $saved = move_uploaded_file($tmpName, $destPath);
        if (!$saved && php_sapi_name() === 'cli') {
            $saved = copy($tmpName, $destPath);
        }

        if ($saved) {
            // Mirror to both directories so files resolve regardless of base path
            if (file_exists($destPath)) {
                if ($destPath !== $rootUploadDir . $newFileName && is_dir($rootUploadDir)) {
                    @copy($destPath, $rootUploadDir . $newFileName);
                }
                if ($destPath !== $nestedUploadDir . $newFileName && is_dir($nestedUploadDir)) {
                    @copy($destPath, $nestedUploadDir . $newFileName);
                }
            }

            $evidenceFiles[] = [
                'name'        => basename($origName),
                'file_name'   => $newFileName,
                'file_path'   => '/gnhs-guidance/uploads/evidence/' . $newFileName,
                'file_type'   => $isVideo ? 'video' : 'image',
                'mime_type'   => $mime ?: ($isVideo ? 'video/mp4' : 'image/jpeg'),
                'size'        => $size,
                'uploaded_at' => date('Y-m-d H:i:s')
            ];
        } else {
            jsonOut(['success'=>false, 'message'=>"Failed to save file '{$origName}' on the server. Please check folder permissions."]);
        }
    }
}
$evidenceJson = !empty($evidenceFiles) ? json_encode($evidenceFiles, JSON_UNESCAPED_SLASHES) : null;

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
        (case_number,student_id,is_anonymous,anon_token,concern_type,subject,description,ai_summary,priority,status,incident_date,preferred_contact,witnesses,evidence_files,assigned_to,is_peer_report,reported_student_name,reported_student_grade)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $caseNum, $studentId, (int)$isAnon, $anonToken,
        $concernType, $subject, $description, $aiSummary ?: null,
        $priority, 'pending', $incidentDate ?: null, $contact,
        $witnesses ?: null, $evidenceJson, $assignedTo,
        $isPeerReport, $reportedStudentName ?: null, $reportedStudentGrade ?: null
    ]);
    $caseId = $db->lastInsertId();

    // Notify counselor
    if ($assignedTo) {
        $peerTitle = $isPeerReport ? "Peer Report " : "";
        $counselorNotifTitle = "New " . ($isAnon ? "Anonymous " : "") . $peerTitle . "Case: $caseNum";
        $extraDetails = [];
        if ($isPeerReport && $reportedStudentName) $extraDetails[] = "Reported for: $reportedStudentName";
        if (!empty($evidenceFiles)) $extraDetails[] = count($evidenceFiles) . ' media evidence file(s)';
        if (!empty($witnesses)) $extraDetails[] = 'witness information';
        $extraStr = !empty($extraDetails) ? ' (' . implode(', ', $extraDetails) . ')' : '';

        $db->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,?)")
           ->execute([$assignedTo, $counselorNotifTitle,
               "A new ".strtoupper($priority)." priority ".str_replace('_',' ',$concernType)." case has been submitted$extraStr.",
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
        'success'        => true,
        'case_number'    => $caseNum,
        'case_id'        => $caseId,
        'anon_token'     => $anonToken,
        'ai_summary'     => $aiSummary,
        'evidence_count' => count($evidenceFiles),
        'evidence_files' => $evidenceFiles,
        'has_witnesses'  => !empty($witnesses)
    ]);

} catch (Exception $e) {
    jsonOut(['success'=>false,'message'=>'Server error: '.$e->getMessage()], 500);
}






