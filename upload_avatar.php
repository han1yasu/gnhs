<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success'=>false,'message'=>'Method not allowed.'], 405);
}

$file = $_FILES['avatar'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    $errors = [1=>'File too large',2=>'File too large',3=>'Partial upload',4=>'No file',6=>'No temp dir',7=>'Cannot write'];
    jsonOut(['success'=>false,'message'=>'Upload error: '.($errors[$file['error']]??'Unknown')]);
}

// Validate type
$allowed = ['image/jpeg','image/png','image/gif','image/webp'];
$finfo   = finfo_open(FILEINFO_MIME_TYPE);
$mime    = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
if (!in_array($mime, $allowed)) {
    jsonOut(['success'=>false,'message'=>'Only JPG, PNG or WEBP images are allowed.']);
}
if ($file['size'] > 5 * 1024 * 1024) {
    jsonOut(['success'=>false,'message'=>'Image must be under 5MB.']);
}

// Create folder
$uploadDir = __DIR__ . '/../uploads/avatars/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        jsonOut(['success'=>false,'message'=>'Cannot create uploads folder. Check permissions.']);
    }
}

// Ensure DB column exists
$db = getDB();
try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_photo VARCHAR(255) DEFAULT NULL"); } catch(Exception $e){}

// Delete old avatar file
try {
    $oldRow = $db->query("SELECT avatar_photo FROM users WHERE id={$user['id']}")->fetch();
    if ($oldRow && $oldRow['avatar_photo']) {
        $oldFile = __DIR__ . '/../uploads/avatars/' . basename($oldRow['avatar_photo']);
        if (file_exists($oldFile)) @unlink($oldFile);
    }
} catch(Exception $e){}

$filename = 'avatar_' . $user['id'] . '_' . time() . '.jpg';
$destPath = $uploadDir . $filename;
$webPath  = '/gnhs-guidance/uploads/avatars/' . $filename;

// ── Try GD resize (best quality, always 200×200) ────────────
$gdAvailable = extension_loaded('gd') && function_exists('imagecreatefromjpeg');
$saved = false;

if ($gdAvailable) {
    $src = match($mime) {
        'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
        'image/png'  => @imagecreatefrompng($file['tmp_name']),
        'image/gif'  => @imagecreatefromgif($file['tmp_name']),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : false,
        default      => false
    };

    if ($src) {
        $origW = imagesx($src);
        $origH = imagesy($src);
        $SIZE  = 200;

        // Square crop from center-top (keeps face)
        $cropSize = min($origW, $origH);
        $cropX    = (int)(($origW - $cropSize) / 2);
        $cropY    = 0;

        $out = imagecreatetruecolor($SIZE, $SIZE);
        // White background for transparency
        $white = imagecolorallocate($out, 255, 255, 255);
        imagefill($out, 0, 0, $white);

        imagecopyresampled($out, $src, 0, 0, $cropX, $cropY, $SIZE, $SIZE, $cropSize, $cropSize);
        imagedestroy($src);

        $saved = imagejpeg($out, $destPath, 92);
        imagedestroy($out);
    }
}

// ── Fallback: save original file as-is if GD unavailable ────
if (!$saved) {
    $saved = move_uploaded_file($file['tmp_name'], $destPath);
    // Use original extension if GD not available
    if ($saved && $mime !== 'image/jpeg') {
        $ext = match($mime) {
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            default      => 'jpg'
        };
        $newFilename = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
        $newPath     = $uploadDir . $newFilename;
        rename($destPath, $newPath);
        $destPath = $newPath;
        $webPath  = '/gnhs-guidance/uploads/avatars/' . $newFilename;
    }
}

if (!$saved) {
    jsonOut(['success'=>false,'message'=>'Failed to save image. Check folder permissions at: '.$uploadDir]);
}

// Save to DB + session
try {
    $db->prepare("UPDATE users SET avatar_photo=? WHERE id=?")->execute([$webPath, $user['id']]);
    $_SESSION['user']['avatar_photo'] = $webPath;
    jsonOut(['success'=>true,'avatar_photo'=>$webPath,'message'=>'Profile picture updated!','gd'=>$gdAvailable]);
} catch(Exception $e) {
    jsonOut(['success'=>false,'message'=>'DB error: '.$e->getMessage()], 500);
}
