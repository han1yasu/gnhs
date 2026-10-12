<?php
require_once __DIR__ . '/../includes/config.php';
startSession();
header('Content-Type: application/json');

if (empty($_SESSION['user'])) {
    jsonOut(['success' => false, 'message' => 'Unauthorized. Please log in.'], 401);
}

$user = $_SESSION['user'];
$caseId = (int)($_POST['case_id'] ?? 0);
$caseNum = trim($_POST['case_number'] ?? '');

if (!$caseId && empty($caseNum)) {
    jsonOut(['success' => false, 'message' => 'Case ID or Case Number is required.']);
}

$db = getDB();

if ($caseId) {
    $stmt = $db->prepare("SELECT * FROM cases WHERE id = ?");
    $stmt->execute([$caseId]);
} else {
    $stmt = $db->prepare("SELECT * FROM cases WHERE case_number = ?");
    $stmt->execute([$caseNum]);
}
$case = $stmt->fetch();

if (!$case) {
    jsonOut(['success' => false, 'message' => 'Case not found.']);
}

// Access check: only the student who owns the case or counselors/admins
if ($user['role'] === 'student' && $case['student_id'] != $user['id']) {
    jsonOut(['success' => false, 'message' => 'Access denied.'], 403);
}

if (empty($_FILES['evidence'])) {
    jsonOut(['success' => false, 'message' => 'No files were received.']);
}

$rootUploadDir   = __DIR__ . '/../../uploads/evidence/';
$nestedUploadDir = __DIR__ . '/../uploads/evidence/';

$primaryUploadDir = is_dir($nestedUploadDir) ? $nestedUploadDir : $rootUploadDir;

if (!is_dir($rootUploadDir))   @mkdir($rootUploadDir, 0755, true);
if (!is_dir($nestedUploadDir)) @mkdir($nestedUploadDir, 0755, true);

$existingEvidence = [];
if (!empty($case['evidence_files'])) {
    $decoded = json_decode($case['evidence_files'], true);
    if (is_array($decoded)) {
        $existingEvidence = $decoded;
    }
}

$files = $_FILES['evidence'];
$fileCount = is_array($files['name']) ? count($files['name']) : 1;

$allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif', 'jfif', 'mp4', 'webm', 'mov', 'mkv', 'ogg', '3gp', 'avi', 'm4v'];
$imageExts   = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif', 'jfif'];
$videoExts   = ['mp4', 'webm', 'mov', 'mkv', 'ogg', '3gp', 'avi', 'm4v'];
$maxFileSize = 100 * 1024 * 1024; // 100MB

$newlyAdded = [];

for ($i = 0; $i < $fileCount; $i++) {
    $origName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
    if (empty($origName)) continue;

    $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
    if ($error !== UPLOAD_ERR_OK) {
        if ($error === UPLOAD_ERR_NO_FILE) continue;
        jsonOut(['success' => false, 'message' => "Upload error for '{$origName}' (code {$error})."]);
    }

    $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
    $size    = is_array($files['size']) ? $files['size'][$i] : $files['size'];

    if ($size > $maxFileSize) {
        jsonOut(['success' => false, 'message' => "File '{$origName}' exceeds the 100MB limit."]);
    }
    if ($size <= 0) continue;

    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts)) {
        jsonOut(['success' => false, 'message' => "Unsupported format (.{$ext}) for '{$origName}'."]);
    }

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
        jsonOut(['success' => false, 'message' => "File '{$origName}' is not a valid image or video."]);
    }

    $newFileName = 'ev_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest1 = $rootUploadDir . $newFileName;
    $dest2 = $nestedUploadDir . $newFileName;

    $saved = move_uploaded_file($tmpName, $dest2);
    if (!$saved && php_sapi_name() === 'cli') {
        $saved = copy($tmpName, $dest2);
    }

    if ($saved) {
        if (is_dir($rootUploadDir)) {
            @copy($dest2, $dest1);
        }

        $fileEntry = [
            'name'        => basename($origName),
            'file_name'   => $newFileName,
            'file_path'   => '/gnhs-guidance/uploads/evidence/' . $newFileName,
            'file_type'   => $isVideo ? 'video' : 'image',
            'mime_type'   => $mime ?: ($isVideo ? 'video/mp4' : 'image/jpeg'),
            'size'        => $size,
            'uploaded_at' => date('Y-m-d H:i:s')
        ];
        $existingEvidence[] = $fileEntry;
        $newlyAdded[]       = $fileEntry;
    }
}

if (!empty($newlyAdded)) {
    $updatedJson = json_encode($existingEvidence, JSON_UNESCAPED_SLASHES);
    $uStmt = $db->prepare("UPDATE cases SET evidence_files = ?, updated_at = NOW() WHERE id = ?");
    $uStmt->execute([$updatedJson, $case['id']]);

    jsonOut([
        'success'        => true,
        'message'        => count($newlyAdded) . ' evidence file(s) attached successfully.',
        'evidence_files' => $existingEvidence,
        'count'          => count($existingEvidence),
        'added_count'    => count($newlyAdded)
    ]);
} else {
    jsonOut(['success' => false, 'message' => 'No valid evidence files were attached.']);
}
