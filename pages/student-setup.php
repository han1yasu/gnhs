<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin('student');

// If already complete, redirect to dashboard
if (!empty($user['is_setup_complete'])) {
    header('Location: student-dashboard.php'); exit;
}
$db = getDB();
$stmt = $db->prepare("SELECT middle_name FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$dbUser = $stmt->fetch();
$middleName = $dbUser['middle_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Profile | GNHS Guidance</title>
    <link rel="stylesheet" href="/assets/css/style.css?v=2.3">
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
    <style>
        .setup-input {
            width: 100%;
            font-size: 15px;
            padding: 10px 14px;
            background: rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            color: #fff;
            font-weight: 500;
            outline: none;
            transition: .3s;
        }
        .setup-input::placeholder { color: rgba(255,255,255,0.4); }
        .setup-input:focus {
            background: rgba(0,0,0,0.3);
            border-color: rgba(255,255,255,0.5);
        }
        .glass-card {
            padding: 30px 40px;
        }
        label {
            color: rgba(255,255,255,0.9);
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 4px;
            display: block;
        }
        .form-row {
            margin-bottom: 12px;
            text-align: left;
        }
    </style>
</head>
<body class="glass-bg">

<div class="glass-card" style="max-width: 450px;">
    <h2 style="margin-bottom: 4px; font-size: 22px;">Complete Your Profile</h2>
    <p style="margin-bottom: 16px; color: rgba(255,255,255,0.8); font-size: 13px;">
        Welcome, <?= htmlspecialchars($user['first_name']) ?>! Please confirm your details to continue.
    </p>
    
    <div class="error-msg" id="errorMsg" style="background:rgba(255,0,0,0.2); border:1px solid rgba(255,0,0,0.3); color:#fff; display:none; margin-bottom: 12px; padding: 10px; border-radius: 8px; font-size: 13px;"></div>
    
    <form id="setupForm" onsubmit="completeSetup(event)">
        <div class="form-row">
            <label>First Name</label>
            <input type="text" id="firstName" class="setup-input" value="<?= htmlspecialchars($user['first_name']) ?>" required>
        </div>
        
        <div class="form-row">
            <label>Middle Name</label>
            <input type="text" id="middleName" class="setup-input" value="<?= htmlspecialchars($middleName) ?>" placeholder="Optional">
        </div>
        
        <div class="form-row">
            <label>Last Name</label>
            <input type="text" id="lastName" class="setup-input" value="<?= htmlspecialchars($user['last_name']) ?>" required>
        </div>
        
        <div class="form-row">
            <label>LRN (Learner Reference Number)</label>
            <input type="text" id="lrn" class="setup-input" value="<?= htmlspecialchars($user['id_number']) ?>" readonly style="background: rgba(255,255,255,0.05); cursor: not-allowed; color: rgba(255,255,255,0.6);">
        </div>
        
        <div class="form-row">
            <label>Grade & Section</label>
            <input type="text" id="gradeSection" class="setup-input" value="<?= htmlspecialchars($user['grade_section'] ?? '') ?>" placeholder="e.g. Grade 10 - Diamond" required>
        </div>
        
        <div class="form-row" style="margin-bottom: 20px;">
            <label>Age</label>
            <input type="number" id="age" class="setup-input" min="10" max="100" placeholder="e.g. 15" required>
        </div>
        
        <button type="submit" class="glass-btn" id="submitBtn" style="padding: 12px; font-size: 15px;">Save and Continue</button>
    </form>
</div>

<script src="/assets/js/theme.js?v=1.1"></script>
<script>
async function completeSetup(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    const err = document.getElementById('errorMsg');
    
    btn.disabled = true;
    btn.style.opacity = '0.7';
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    err.style.display = 'none';

    const data = {
        first_name: document.getElementById('firstName').value,
        middle_name: document.getElementById('middleName').value,
        last_name: document.getElementById('lastName').value,
        grade_section: document.getElementById('gradeSection').value,
        age: document.getElementById('age').value
    };

    try {
        const res = await fetch('../api/complete_setup.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const ans = await res.json();
        
        if (ans.success) {
            btn.style.background = '#10b981';
            btn.style.color = '#fff';
            btn.innerHTML = '<i class="fas fa-check"></i> Saved successfully';
            document.body.classList.add('page-exiting');
            setTimeout(() => {
                window.location.href = 'student-dashboard.php';
            }, 300);
        } else {
            err.style.display = 'block';
            err.textContent = ans.message || 'An error occurred.';
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.innerHTML = 'Save and Continue';
        }
    } catch (error) {
        err.style.display = 'block';
        err.textContent = 'Server error. Please try again later.';
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.innerHTML = 'Save and Continue';
    }
}
</script>
</body>
</html>
