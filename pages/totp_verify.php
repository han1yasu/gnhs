<?php
require_once __DIR__ . '/../includes/config.php';
startSession();

$pendingUser = $_SESSION['pending_user'] ?? null;
if (!$pendingUser) {
    header('Location: /gnhs-guidance/index.html');
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT totp_secret, totp_enabled, totp_reset_requested FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$pendingUser['id']]);
$u = $stmt->fetch();
$secret = $u['totp_secret'] ?? '';
$isTotpEnabled = !empty($u['totp_enabled']);
$isResetRequested = !empty($u['totp_reset_requested']);

$email = urlencode($pendingUser['email']);
$issuer = urlencode('GNHS Guidance System');
$otpauth = "otpauth://totp/$issuer:$email?secret=$secret&issuer=$issuer";
$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($otpauth);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Two-Factor Authentication - GNHS Guidance</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../style.css?v=2.3">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script>
    (function(){
      var t = localStorage.getItem('theme') || 'light';
      var c = localStorage.getItem('colorTheme') || 'maroon';
      document.documentElement.setAttribute('data-theme', t);
      document.documentElement.setAttribute('data-color', c);
    })();
  </script>
</head>
<body class="glass-bg">

<div class="glass-card">
    <i class="fas fa-fingerprint glass-icon"></i>
    <h2>Security Verification</h2>
    <p>Please enter the 6-digit code from your authenticator app to continue securely.</p>

    <div class="error-msg" id="errorMsg" style="background:rgba(255,0,0,0.2); border:1px solid rgba(255,0,0,0.3); color:#fff; display:none;"></div>

    <input type="text" id="otpCode" class="glass-input" maxlength="7" placeholder="000 000" autocomplete="off">

    <button class="glass-btn" id="btnSubmit" onclick="verifyOTP()">Verify Code</button>

    <?php if (!$isTotpEnabled): ?>
    <div style="margin-top: 16px; background: rgba(16, 185, 129, 0.18); border: 1px solid rgba(16, 185, 129, 0.35); border-radius: 10px; padding: 10px 14px; color: #d1fae5; font-size: 13px;">
        <i class="fas fa-qrcode" style="color:#34d399; margin-right:6px;"></i> Authenticator setup required. Scan the QR code below.
    </div>
    <div class="glass-setup-box" id="setupSection" style="display:block; margin-top:14px;">
        <p style="font-size: 13px; margin-bottom: 12px; color:rgba(255,255,255,0.9)">Scan this QR code with Google Authenticator or Authy to set up your account.</p>
        <div style="background:#fff; padding:10px; border-radius:12px; display:inline-block; margin-bottom:12px;">
            <img src="<?= htmlspecialchars($qrUrl) ?>" alt="QR Code" style="width: 150px; height: 150px; display:block; border-radius:8px;">
        </div>
        <p style="font-size: 12px; color: rgba(255,255,255,0.7);">Manual setup key: <br><strong style="color:#fff; font-size:14px; letter-spacing:2px;"><?= htmlspecialchars($secret) ?></strong></p>
    </div>
    <?php else: ?>
    <div style="margin-top: 16px;">
        <?php if ($isResetRequested): ?>
        <div id="resetRequestedBadge" style="background: rgba(245, 158, 11, 0.2); border: 1px solid rgba(245, 158, 11, 0.4); border-radius: 10px; padding: 12px; color: #fef08a; font-size: 13px; line-height: 1.4; text-align: left;">
            <div style="display:flex; align-items:center; gap:8px; font-weight:700; color:#fbbf24; margin-bottom:4px;">
                <i class="fas fa-clock"></i> 2FA Reset Requested
            </div>
            Your request is pending Counselor review. Once approved and reset, log in again to scan your new QR code.
        </div>
        <?php else: ?>
        <button type="button" class="glass-setup-link" id="lostAuthBtn" onclick="requestTotpReset()">
            <i class="fas fa-question-circle"></i> Lost your authenticator app? Request 2FA Reset
        </button>
        <div id="resetRequestedBadge" style="display:none; background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.4); border-radius: 10px; padding: 12px; color: #a7f3d0; font-size: 13px; line-height: 1.4; text-align: left;">
            <div style="display:flex; align-items:center; gap:8px; font-weight:700; color:#34d399; margin-bottom:4px;">
                <i class="fas fa-check-circle"></i> Request Sent!
            </div>
            Your 2FA reset request was submitted to the Guidance Counselor. Please wait for approval, then log in again.
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div style="margin-top: 24px; text-align: center;">
        <a href="/gnhs-guidance/index.html" style="color: rgba(255,255,255,0.8); font-size: 13px; text-decoration: none; display: inline-block; padding: 8px;"><i class="fas fa-arrow-left"></i> Go back to login page</a>
    </div>
</div>

<script src="../theme.js?v=1.1"></script>
<script>
const input = document.getElementById('otpCode');

input.addEventListener('input', function(e) {
    let val = this.value.replace(/[^0-9]/g, '');
    if (val.length > 3) val = val.substring(0, 3) + ' ' + val.substring(3, 6);
    this.value = val;
    if (val.replace(' ', '').length === 6) verifyOTP();
});
input.addEventListener('keydown', function(e) { if (e.key === 'Enter') verifyOTP(); });

async function verifyOTP() {
    const code = input.value.replace(/[^0-9]/g, '');
    const err = document.getElementById('errorMsg');
    const btn = document.getElementById('btnSubmit');

    if (code.length !== 6) {
        err.style.display = 'block';
        err.textContent = 'Please enter a complete 6-digit code.';
        return;
    }

    err.style.display = 'none';
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
    btn.disabled = true;
    btn.style.opacity = '0.7';

    try {
        const res = await fetch('/gnhs-guidance/api/verify_totp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code: code })
        });
        const data = await res.json();
        
        if (data.success) {
            btn.style.background = '#10b981'; 
            btn.style.color = '#fff';
            btn.innerHTML = '<i class="fas fa-check"></i> Verified';
            document.body.classList.add('page-exiting');
            setTimeout(() => window.location.href = data.redirect, 250);
        } else {
            err.style.display = 'block';
            err.textContent = data.message || 'Verification failed.';
            btn.innerHTML = 'Verify Code';
            btn.disabled = false;
            btn.style.opacity = '1';
            input.value = '';
            input.focus();
        }
    } catch (e) {
        err.style.display = 'block';
        err.textContent = 'Network error. Please try again.';
        btn.innerHTML = 'Verify Code';
        btn.disabled = false;
        btn.style.opacity = '1';
    }
}

async function requestTotpReset() {
    if (!confirm("Did you lose access to your authenticator app?\n\nThis will send an official request to your Guidance Counselor to reset your Two-Factor Authentication so you can scan a new QR code.")) {
        return;
    }
    const btn = document.getElementById('lostAuthBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting request...';
    }
    
    try {
        const res = await fetch('/gnhs-guidance/api/request_totp_reset.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await res.json();
        if (data.success) {
            if (btn) btn.style.display = 'none';
            const badge = document.getElementById('resetRequestedBadge');
            if (badge) badge.style.display = 'block';
        } else {
            alert(data.message || 'Failed to submit request.');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-question-circle"></i> Lost your authenticator app? Request 2FA Reset';
            }
        }
    } catch (e) {
        alert('Network error. Please try again.');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-question-circle"></i> Lost your authenticator app? Request 2FA Reset';
        }
    }
}

window.addEventListener('load', () => input.focus());
</script>
</body>
</html>



