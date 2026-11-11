<?php
require_once __DIR__ . '/../includes/config.php';
startSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success'=>false, 'message'=>'Method not allowed.'], 405);
}

$raw = json_decode(file_get_contents('php://input'), true);
$identifier = trim($raw['email'] ?? $raw['identifier'] ?? '');

if (!$identifier) {
    jsonOut(['success'=>false, 'message'=>'Please enter your registered Email address or Student ID / LRN.']);
}

function maskUserEmail($email) {
    $parts = explode('@', $email);
    if (count($parts) !== 2) return $email;
    $name = $parts[0];
    $domain = $parts[1];
    $len = strlen($name);
    if ($len <= 2) {
        $masked = $name[0] . '*';
    } else {
        $masked = substr($name, 0, 2) . str_repeat('*', max(1, $len - 3)) . substr($name, -1);
    }
    return $masked . '@' . $domain;
}

try {
    $db = getDB();

    // Check if user exists by email or id_number
    $stmt = $db->prepare("SELECT id, first_name, last_name, email, role, is_active FROM users WHERE (LOWER(email) = LOWER(?) OR id_number = ?) LIMIT 1");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonOut(['success'=>false, 'message'=>'No account found matching "' . htmlspecialchars($identifier) . '". Please check your email or contact the Guidance Office.']);
    }

    if (isset($user['is_active']) && $user['is_active'] == 0) {
        jsonOut(['success'=>false, 'message'=>'This account has been deactivated. Please contact the Guidance Office for assistance.']);
    }

    $targetEmail = strtolower($user['email']);
    if (!filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
        jsonOut(['success'=>false, 'message'=>'Your account does not have a valid email on file. Please visit the Guidance Office for manual assistance.']);
    }

    // Generate secure 6-digit confirmation code
    $code = sprintf("%06d", random_int(100000, 999999));
    $expiresAt = date('Y-m-d H:i:s', time() + (10 * 60)); // 10 minutes

    // Delete existing unused reset codes for this email
    $db->prepare("DELETE FROM password_resets WHERE LOWER(email) = ?")->execute([$targetEmail]);

    // Insert new reset record
    $ins = $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
    $ins->execute([$targetEmail, $code, $expiresAt]);

    // Store in session for extra resiliency
    $_SESSION['pwd_reset'] = [
        'email'      => $targetEmail,
        'user_id'    => $user['id'],
        'code'       => $code,
        'expires_at' => time() + 600
    ];

    // Build Email
    $fullName = trim($user['first_name'] . ' ' . $user['last_name']);
    $subject = "GNHS Guidance — Password Reset Code: $code";

    $messageHtml = '
    <!DOCTYPE html>
    <html>
    <head>
    <meta charset="utf-8">
    <style>
      body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #fdf8f8; margin: 0; padding: 24px; color: #222; }
      .email-card { max-width: 480px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #f0dede; padding: 32px 28px; box-shadow: 0 4px 18px rgba(85,0,0,0.06); }
      .header { text-align: center; margin-bottom: 24px; }
      .badge { display: inline-block; background: #550000; color: #ffffff; padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 800; letter-spacing: 0.5px; }
      .code-box { background: #fff5f5; border: 2px dashed #550000; border-radius: 12px; text-align: center; padding: 18px; margin: 24px 0; font-size: 36px; font-weight: 900; letter-spacing: 8px; color: #550000; font-family: monospace; }
      .footer { margin-top: 26px; border-top: 1px solid #f0dede; padding-top: 16px; font-size: 11.5px; color: #888; text-align: center; line-height: 1.5; }
    </style>
    </head>
    <body>
      <div class="email-card">
        <div class="header">
          <span class="badge">GNHS GUIDANCE</span>
          <h2 style="color:#550000;margin:12px 0 4px;font-size:20px;">Password Reset Verification</h2>
          <p style="margin:0;color:#666;font-size:13px;">Guinayang National High School</p>
        </div>
        <p style="font-size:14px;line-height:1.5;">Hello <strong>' . htmlspecialchars($fullName) . '</strong>,</p>
        <p style="font-size:14px;line-height:1.5;">We received a request to reset your password for the <strong>GNHS Guidance Case Management System</strong>.</p>
        
        <div style="background:#fef2f2;border:1px solid #fecaca;padding:10px 14px;border-radius:8px;font-size:13px;color:#991b1b;margin:12px 0;">
          <strong>Registered Account:</strong> <span style="font-weight:700;color:#550000;">' . htmlspecialchars($targetEmail) . '</span>
        </div>

        <p style="font-size:13px;color:#555;margin:8px 0 0;">Enter the 6-digit confirmation code below on the reset screen:</p>
        <div class="code-box">' . $code . '</div>
        <p style="font-size:13px;color:#555;line-height:1.5;">This confirmation code expires in <strong>10 minutes</strong>. If you did not request a password reset, you can safely ignore this email—your account remains safe and your current password has not been changed.</p>
        <div class="footer">
          Guidance Office &bull; Guinayang National High School<br>
          Confidential school guidance communications.
        </div>
      </div>
    </body>
    </html>';

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: GNHS Guidance Office <no-reply@gnhsguidance.com>\r\n";
    $headers .= "Reply-To: no-reply@gnhsguidance.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    $mailSent = false;
    try {
        if (function_exists('mail')) {
            $mailSent = @mail($targetEmail, $subject, $messageHtml, $headers);
        }
    } catch (Exception $e) {}

    $masked = maskUserEmail($targetEmail);

    jsonOut([
        'success'      => true,
        'email'        => $targetEmail,
        'masked_email' => $masked,
        'mail_sent'    => $mailSent,
        'message'      => 'A 6-digit confirmation code has been sent to ' . $masked . '. Please check your inbox or spam folder.'
    ]);

} catch (Exception $e) {
    jsonOut(['success'=>false, 'message'=>'Server error: ' . $e->getMessage()], 500);
}
