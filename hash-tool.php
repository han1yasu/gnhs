<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <title>GNHS — Password Hash Tool</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',sans-serif;background:linear-gradient(135deg,#3a0000,#550000,#7a0000);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
    .card{background:#fff;border-radius:20px;padding:40px;width:100%;max-width:520px;box-shadow:0 20px 60px rgba(0,0,0,.3)}
    .logo{text-align:center;margin-bottom:24px}
    .logo-badge{width:64px;height:64px;background:linear-gradient(135deg,#550000,#7a0000);border-radius:16px;display:inline-flex;align-items:center;justify-content:center;font-size:28px;color:#fff;margin-bottom:12px}
    h2{text-align:center;color:#550000;font-size:22px;margin-bottom:6px}
    p.sub{text-align:center;color:#71717a;font-size:14px;margin-bottom:28px}
    label{display:block;font-size:13px;font-weight:700;color:#52525b;margin-bottom:6px}
    .input-wrap{position:relative;margin-bottom:16px}
    input[type=text],input[type=password]{width:100%;padding:12px 16px;border:1.5px solid #e4e4e7;border-radius:10px;font-size:14px;font-family:inherit;transition:.2s}
    input:focus{outline:none;border-color:#550000;box-shadow:0 0 0 3px rgba(85,0,0,.09)}
    .btn{width:100%;padding:13px;background:linear-gradient(135deg,#550000,#7a0000);color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;transition:.2s;margin-top:4px}
    .btn:hover{transform:translateY(-1px);box-shadow:0 8px 20px rgba(85,0,0,.3)}
    .result{margin-top:20px;background:#fff5f5;border:1.5px solid #fce8e8;border-radius:12px;padding:18px;display:none}
    .result label{color:#550000;font-size:12px;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px}
    .hash-box{background:#f4f4f5;border-radius:8px;padding:12px;font-family:monospace;font-size:12px;word-break:break-all;color:#18181b;line-height:1.6;border:1px solid #e4e4e7;position:relative}
    .copy-btn{position:absolute;top:8px;right:8px;background:#550000;color:#fff;border:none;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer}
    .copy-btn:hover{background:#3a0000}
    .info-box{background:#fffbeb;border:1.5px solid #fde68a;border-radius:10px;padding:14px;margin-top:14px;font-size:13px;color:#78350f;line-height:1.6}
    .info-box strong{display:block;margin-bottom:4px;color:#92400e}
    .divider{border:none;border-top:1px solid #e4e4e7;margin:24px 0}
    .warn{background:#fef2f2;border:1.5px solid #fecaca;border-radius:10px;padding:12px 14px;font-size:13px;color:#991b1b;margin-top:14px;display:flex;gap:8px}
    .warn i{flex-shrink:0;font-size:16px}
  </style>
</head>
<body>
<?php
$hash = '';
$plain = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plain = trim($_POST['password'] ?? '');
    if (strlen($plain) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $hash = password_hash($plain, PASSWORD_BCRYPT);
    }
}
?>
<div class="card">
  <div class="logo">
    <div class="logo-badge">🔐</div>
    <h2>Password Hash Generator</h2>
    <p class="sub">GNHS Guidance System — Admin Tool<br>Use this to generate bcrypt hashes for phpMyAdmin.</p>
  </div>

  <form method="POST">
    <label>Plain Text Password</label>
    <div class="input-wrap">
      <input type="text" name="password" value="<?= htmlspecialchars($plain) ?>" placeholder="e.g. MyPassword@123" autocomplete="off"/>
    </div>

    <?php if ($error): ?>
      <div class="warn">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <button type="submit" class="btn">Generate Bcrypt Hash</button>
  </form>

  <?php if ($hash): ?>
  <div class="result" style="display:block">
    <label>✅ Bcrypt Hash — Copy this into phpMyAdmin</label>
    <div class="hash-box" id="hashBox">
      <?= htmlspecialchars($hash) ?>
      <button class="copy-btn" onclick="copyHash()">Copy</button>
    </div>
    <div class="info-box">
      <strong>📋 How to use this in phpMyAdmin:</strong>
      1. Go to <code>http://localhost/phpmyadmin</code><br>
      2. Open <strong>gnhs_guidance</strong> → <strong>users</strong> table<br>
      3. Find your user row → click <strong>Edit</strong><br>
      4. Paste this hash into the <strong>password</strong> column<br>
      5. Click <strong>Save</strong> — then try logging in!
    </div>
  </div>
  <?php endif; ?>

  <hr class="divider"/>

  <div class="warn">
    <span>⚠️</span>
    <span><strong>Security reminder:</strong> Delete this file (<code>hash-tool.php</code>) from your server after you're done using it!</span>
  </div>
</div>

<script>
function copyHash() {
  const text = document.getElementById('hashBox').innerText.replace('Copy','').trim();
  navigator.clipboard.writeText(text).then(() => {
    const btn = document.querySelector('.copy-btn');
    btn.textContent = 'Copied!';
    btn.style.background = '#16a34a';
    setTimeout(() => { btn.textContent = 'Copy'; btn.style.background = '#550000'; }, 2000);
  });
}
</script>
</body>
</html>



