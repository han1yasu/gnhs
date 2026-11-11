<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../src/PureOTP.php';
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
$secret = trim($u['totp_secret'] ?? ($pendingUser['totp_secret'] ?? ''));
$isTotpEnabled = !empty($u['totp_enabled']);
$isResetRequested = !empty($u['totp_reset_requested']);

// Ensure valid TOTP secret exists; if missing, generate and persist immediately
if (empty($secret)) {
    $secret = PureOTP::generateSecret(16);
    $db->prepare("UPDATE users SET totp_secret = ? WHERE id = ?")->execute([$secret, $pendingUser['id']]);
}
$_SESSION['pending_user']['totp_secret'] = $secret;

$email = urlencode($pendingUser['email']);
$issuer = urlencode('GNHS Guidance System');
$otpauth = "otpauth://totp/$issuer:$email?secret=$secret&issuer=$issuer";
$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($otpauth) . "&t=" . time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Two-Factor Authentication - GNHS Guidance</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../style.css?v=3.0">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
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
    <p style="margin-bottom:8px">Please enter the 6-digit code from your authenticator app to continue securely.</p>
    <div style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:rgba(255,255,255,0.8);margin-bottom:16px;background:rgba(255,255,255,0.08);padding:4px 12px;border-radius:20px;border:1px solid rgba(255,255,255,0.12)">
      <i class="fas fa-stopwatch" style="color:#fbbf24"></i> Valid strictly while the authenticator circle timer is active
    </div>

    <div class="error-msg" id="errorMsg" style="background:rgba(255,0,0,0.2); border:1px solid rgba(255,0,0,0.3); color:#fff; display:none;"></div>

    <input type="text" id="otpCode" class="glass-input" maxlength="7" placeholder="000 000" autocomplete="off">

    <button class="glass-btn" id="btnSubmit" onclick="verifyOTP()">Verify Code</button>

    <?php if (!$isTotpEnabled): ?>
    <div style="margin-top: 16px; background: rgba(16, 185, 129, 0.18); border: 1px solid rgba(16, 185, 129, 0.35); border-radius: 10px; padding: 10px 14px; color: #d1fae5; font-size: 13px;">
        <i class="fas fa-qrcode" style="color:#34d399; margin-right:6px;"></i> Authenticator setup required. Scan the QR code or enter the key below.
    </div>
    <div class="glass-setup-box" id="setupSection" style="display:block; margin-top:14px;">
        <p style="font-size: 13px; margin-bottom: 12px; color:rgba(255,255,255,0.9)">Scan this QR code with Google Authenticator, Microsoft Authenticator, or Authy:</p>
        <div style="background:#fff; padding:10px; border-radius:12px; display:inline-block; margin-bottom:12px; box-shadow: 0 4px 16px rgba(0,0,0,0.3);">
            <div id="qrcodeJs" style="display:none; width:160px; height:160px;"></div>
            <img id="qrFallbackImg" src="<?= htmlspecialchars($qrUrl) ?>" alt="QR Code" style="width: 160px; height: 160px; display:block; border-radius:8px;">
        </div>
        
        <div style="margin-top:10px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.18); border-radius: 10px; padding: 12px 14px; text-align: center;">
            <div style="font-size: 11px; font-weight:700; color: rgba(255,255,255,0.7); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">
                <i class="fas fa-key" style="color:#fbbf24; margin-right:4px;"></i> Manual Setup Key
            </div>
            <div style="display:flex; align-items:center; justify-content:center; gap:8px; flex-wrap:wrap;">
                <code id="manualSecretKey" style="color:#fff; font-size:15px; font-weight:800; letter-spacing:2px; font-family: monospace; background: rgba(255,255,255,0.12); padding: 6px 12px; border-radius: 6px; user-select: all;"><?= htmlspecialchars(chunk_split($secret, 4, ' ')) ?></code>
                <button type="button" onclick="copySecretKey('<?= htmlspecialchars($secret) ?>')" id="btnCopyKey" style="background:#550000; color:#fff; border:1px solid rgba(255,255,255,0.25); border-radius:6px; padding:6px 12px; font-size:12px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:5px; transition:0.2s">
                    <i class="fas fa-copy"></i> Copy Key
                </button>
            </div>
            <span style="font-size: 11px; color: rgba(255,255,255,0.65); display:block; margin-top:8px;">If you can't scan: Open your Authenticator app &rarr; tap <strong>+</strong> &rarr; choose <strong>Enter setup key</strong> &rarr; paste the key.</span>
        </div>
    </div>
    <?php else: ?>
    <div style="margin-top: 16px;">
        <?php if ($isResetRequested): ?>
        <div id="resetRequestedBadge" style="background: rgba(245, 158, 11, 0.2); border: 1px solid rgba(245, 158, 11, 0.4); border-radius: 10px; padding: 12px; color: #fef08a; font-size: 13px; line-height: 1.4; text-align: left;">
            <div style="display:flex; align-items:center; gap:8px; font-weight:700; color:#fbbf24; margin-bottom:4px;">
                <i class="fas fa-clock"></i> 2FA Reset Requested
            </div>
            Your request is pending Counselor review. If you have access to your authenticator code, you can still enter it above!
            <div style="margin-top:8px">
                <button type="button" class="glass-setup-link" onclick="cancelTotpReset()" style="color:#fff;text-decoration:underline;background:none;border:none;cursor:pointer;font-size:12px;padding:0">
                    <i class="fas fa-undo"></i> Cancel 2FA reset request
                </button>
            </div>
        </div>
        <button type="button" class="glass-setup-link" id="lostAuthBtn" style="display:none" onclick="openTotpResetModal()">
            <i class="fas fa-question-circle"></i> Lost your authenticator app? Request 2FA Reset
        </button>
        <?php else: ?>
        <button type="button" class="glass-setup-link" id="lostAuthBtn" onclick="openTotpResetModal()">
            <i class="fas fa-question-circle"></i> Lost your authenticator app? Request 2FA Reset
        </button>
        <div id="resetRequestedBadge" style="display:none; background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.4); border-radius: 10px; padding: 12px; color: #a7f3d0; font-size: 13px; line-height: 1.4; text-align: left;">
            <div style="display:flex; align-items:center; gap:8px; font-weight:700; color:#34d399; margin-bottom:4px;">
                <i class="fas fa-check-circle"></i> Request Sent!
            </div>
            Your 2FA reset request was submitted to the Guidance Counselor. If you find your code, you can still enter it above.
            <div style="margin-top:8px">
                <button type="button" class="glass-setup-link" onclick="cancelTotpReset()" style="color:#fff;text-decoration:underline;background:none;border:none;cursor:pointer;font-size:12px;padding:0">
                    <i class="fas fa-undo"></i> Cancel reset request
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div style="margin-top: 24px; text-align: center;">
        <a href="../index.html" style="color: rgba(255,255,255,0.8); font-size: 13px; text-decoration: none; display: inline-block; padding: 8px;"><i class="fas fa-arrow-left"></i> Go back to login page</a>
    </div>
</div>

<!-- 2FA RESET CONFIRMATION MODAL (POPUP IN THE MIDDLE) -->
<div id="totpResetModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.72); backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px); align-items:center; justify-content:center; padding:16px;">
  <div id="totpResetModalCard" style="background:#1e1e24; border:1px solid rgba(255,255,255,0.18); border-radius:22px; max-width:440px; width:100%; padding:30px 24px; text-align:center; box-shadow:0 24px 50px rgba(0,0,0,0.6); transform:scale(0.95); transition:transform 0.2s cubic-bezier(0.16, 1, 0.3, 1); color:#fff; position:relative;">
    <button type="button" onclick="closeTotpResetModal()" style="position:absolute; top:16px; right:16px; background:none; border:none; color:rgba(255,255,255,0.6); font-size:16px; cursor:pointer; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; transition:0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.1)';this.style.color='#fff'" onmouseout="this.style.background='none';this.style.color='rgba(255,255,255,0.6)'">
      <i class="fas fa-times"></i>
    </button>
    <div style="width:64px; height:64px; border-radius:50%; background:rgba(245, 158, 11, 0.18); border:2px solid rgba(245, 158, 11, 0.4); color:#fbbf24; font-size:26px; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
      <i class="fas fa-shield-alt"></i>
    </div>
    
    <h3 style="font-size:20px; font-weight:800; margin:0 0 10px; color:#fff; letter-spacing:-0.3px;">Reset 2FA Authenticator?</h3>
    <p style="font-size:13.5px; line-height:1.55; color:rgba(255,255,255,0.75); margin:0 0 22px;">
      Did you lose access to your authenticator app? This will submit an official request to your Guidance Counselor to reset your Two-Factor Authentication so you can scan a new QR code.
    </p>

    <div id="totpResetModalMsg" style="display:none; padding:10px 14px; border-radius:10px; font-size:12.5px; margin-bottom:16px; text-align:left;"></div>

    <div style="display:flex; gap:10px;">
      <button type="button" onclick="closeTotpResetModal()" style="flex:1; padding:12px 16px; border-radius:12px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.18); color:#fff; font-size:13.5px; font-weight:700; cursor:pointer; transition:0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.15)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'">
        Cancel
      </button>
      <button type="button" id="btnConfirmTotpReset" onclick="executeTotpResetRequest()" style="flex:1.2; padding:12px 16px; border-radius:12px; background:linear-gradient(135deg, #7f1d1d, #991b1b); border:1px solid rgba(255,255,255,0.25); color:#fff; font-size:13.5px; font-weight:800; cursor:pointer; box-shadow:0 4px 14px rgba(127,29,29,0.4); display:inline-flex; align-items:center; justify-content:center; gap:8px; transition:0.2s;" onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">
        <i class="fas fa-paper-plane"></i> Submit Request
      </button>
    </div>
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
        const res = await fetch('../api/verify_totp.php', {
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

function openTotpResetModal() {
    const modal = document.getElementById('totpResetModal');
    const card = document.getElementById('totpResetModalCard');
    const msg = document.getElementById('totpResetModalMsg');
    if (msg) msg.style.display = 'none';
    if (modal) {
        modal.style.display = 'flex';
        setTimeout(() => {
            if (card) card.style.transform = 'scale(1)';
        }, 10);
    }
}

function closeTotpResetModal() {
    const modal = document.getElementById('totpResetModal');
    const card = document.getElementById('totpResetModalCard');
    if (card) card.style.transform = 'scale(0.95)';
    setTimeout(() => {
        if (modal) modal.style.display = 'none';
    }, 150);
}

// Close when clicking modal backdrop
document.getElementById('totpResetModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeTotpResetModal();
});

// Close with Escape key
window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeTotpResetModal();
});

async function executeTotpResetRequest() {
    const submitBtn = document.getElementById('btnConfirmTotpReset');
    const msg = document.getElementById('totpResetModalMsg');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting request...';
    }
    if (msg) msg.style.display = 'none';
    
    try {
        const res = await fetch('../api/request_totp_reset.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await res.json();
        if (data.success) {
            closeTotpResetModal();
            const lostBtn = document.getElementById('lostAuthBtn');
            if (lostBtn) lostBtn.style.display = 'none';
            const badge = document.getElementById('resetRequestedBadge');
            if (badge) badge.style.display = 'block';
        } else {
            if (msg) {
                msg.style.display = 'block';
                msg.style.background = 'rgba(239, 68, 68, 0.2)';
                msg.style.border = '1px solid rgba(239, 68, 68, 0.4)';
                msg.style.color = '#fca5a5';
                msg.textContent = data.message || 'Failed to submit reset request.';
            }
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Request';
            }
        }
    } catch (e) {
        if (msg) {
            msg.style.display = 'block';
            msg.style.background = 'rgba(239, 68, 68, 0.2)';
            msg.style.border = '1px solid rgba(239, 68, 68, 0.4)';
            msg.style.color = '#fca5a5';
            msg.textContent = 'Network error. Please try again.';
        }
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Request';
        }
    }
}

async function cancelTotpReset() {
    try {
        const res = await fetch('../api/cancel_totp_reset.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await res.json();
        if (data.success) {
            const badge = document.getElementById('resetRequestedBadge');
            if (badge) badge.style.display = 'none';
            const btn = document.getElementById('lostAuthBtn');
            if (btn) {
                btn.style.display = 'inline-block';
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-question-circle"></i> Lost your authenticator app? Request 2FA Reset';
            }
        }
    } catch(e) {}
}

function copySecretKey(key) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(key).then(() => {
            const btn = document.getElementById('btnCopyKey');
            if (btn) {
                btn.innerHTML = '<i class="fas fa-check" style="color:#34d399"></i> Copied!';
                btn.style.background = '#10b981';
                setTimeout(() => {
                    btn.innerHTML = '<i class="fas fa-copy"></i> Copy Key';
                    btn.style.background = '#550000';
                }, 2000);
            }
        }).catch(() => fallbackCopy(key));
    } else {
        fallbackCopy(key);
    }
}

function fallbackCopy(key) {
    const ta = document.createElement('textarea');
    ta.value = key;
    document.body.appendChild(ta);
    ta.select();
    try {
        document.execCommand('copy');
        const btn = document.getElementById('btnCopyKey');
        if (btn) {
            btn.innerHTML = '<i class="fas fa-check" style="color:#34d399"></i> Copied!';
            btn.style.background = '#10b981';
            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-copy"></i> Copy Key';
                btn.style.background = '#550000';
            }, 2000);
        }
    } catch(e) {
        prompt('Copy your manual key:', key);
    }
    document.body.removeChild(ta);
}

// Render vector QR code instantly with zero latency
(function initQrCode() {
    try {
        if (typeof QRCode !== 'undefined') {
            const qrBox = document.getElementById('qrcodeJs');
            const imgFallback = document.getElementById('qrFallbackImg');
            if (qrBox) {
                new QRCode(qrBox, {
                    text: <?= json_encode($otpauth) ?>,
                    width: 160,
                    height: 160,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });
                qrBox.style.display = 'block';
                if (imgFallback) imgFallback.style.display = 'none';
            }
        }
    } catch(e) {}
})();

window.addEventListener('load', () => input.focus());
</script>
</body>
</html>



