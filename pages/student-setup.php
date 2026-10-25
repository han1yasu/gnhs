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
        
        <div class="form-row" style="margin-bottom: 16px;">
            <label>Age</label>
            <input type="number" id="age" class="setup-input" min="10" max="100" value="<?= htmlspecialchars($user['age'] ?? '') ?>" placeholder="e.g. 15" required>
        </div>

        <!-- Security Verification Questions -->
        <div style="margin: 18px 0 14px 0; padding: 12px 14px; background: rgba(85,0,0,0.35); border: 1px solid rgba(255,255,255,0.25); border-radius: 10px;">
            <div style="font-weight: 700; font-size: 13.5px; color: #fff; margin-bottom: 4px; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-shield-halved" style="color: #fbbf24;"></i> Security Recovery Questions
            </div>
            <p style="font-size: 11.5px; color: rgba(255,255,255,0.85); margin: 0; line-height: 1.4;">
                Select and answer two personal security questions. These will serve as your security test whenever you request a password reset from the Guidance Office.
            </p>
        </div>

        <div class="form-row">
            <label>Security Question 1 <span style="color:#fbbf24">*</span></label>
            <select id="secQuestion1" class="setup-input" style="background: rgba(0,0,0,0.4); color:#fff;" required>
                <option value="" style="background:#222;color:#fff">Select your first security question</option>
                <option value="What was the name of your first elementary school?" style="background:#222;color:#fff">What was the name of your first elementary school?</option>
                <option value="What is your mother's maiden name?" style="background:#222;color:#fff">What is your mother's maiden name?</option>
                <option value="In what municipality or city were you born?" style="background:#222;color:#fff">In what municipality or city were you born?</option>
                <option value="What was the name of your first childhood pet?" style="background:#222;color:#fff">What was the name of your first childhood pet?</option>
                <option value="What is the name of your favorite elementary teacher?" style="background:#222;color:#fff">What is the name of your favorite elementary teacher?</option>
            </select>
        </div>

        <div class="form-row">
            <label>Answer to Question 1 <span style="color:#fbbf24">*</span></label>
            <input type="text" id="secAnswer1" class="setup-input" placeholder="Your secret answer (case-insensitive)" required autocomplete="off">
        </div>

        <div class="form-row">
            <label>Security Question 2 <span style="color:#fbbf24">*</span></label>
            <select id="secQuestion2" class="setup-input" style="background: rgba(0,0,0,0.4); color:#fff;" required>
                <option value="" style="background:#222;color:#fff">Select your second security question</option>
                <option value="What was your childhood nickname?" style="background:#222;color:#fff">What was your childhood nickname?</option>
                <option value="What is your favorite academic subject or hobby?" style="background:#222;color:#fff">What is your favorite academic subject or hobby?</option>
                <option value="What is your dream career or profession?" style="background:#222;color:#fff">What is your dream career or profession?</option>
                <option value="What street or barangay did you grow up on?" style="background:#222;color:#fff">What street or barangay did you grow up on?</option>
                <option value="What is your favorite food or Filipino dish?" style="background:#222;color:#fff">What is your favorite food or Filipino dish?</option>
            </select>
        </div>

        <div class="form-row" style="margin-bottom: 22px;">
            <label>Answer to Question 2 <span style="color:#fbbf24">*</span></label>
            <input type="text" id="secAnswer2" class="setup-input" placeholder="Your secret answer (case-insensitive)" required autocomplete="off">
        </div>
        
        <button type="submit" class="glass-btn" id="submitBtn" style="padding: 12px; font-size: 15px;">Save and Continue</button>
    </form>
</div>

<script src="../theme.js?v=1.1"></script>
<script>
async function completeSetup(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    const err = document.getElementById('errorMsg');
    
    btn.disabled = true;
    btn.style.opacity = '0.7';
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    err.style.display = 'none';

    const q1 = document.getElementById('secQuestion1').value;
    const a1 = document.getElementById('secAnswer1').value.trim();
    const q2 = document.getElementById('secQuestion2').value;
    const a2 = document.getElementById('secAnswer2').value.trim();

    if (!q1 || !a1 || !q2 || !a2) {
        err.style.display = 'block';
        err.textContent = 'Please choose both security questions and provide your answers.';
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.innerHTML = 'Save and Continue';
        return;
    }

    const data = {
        first_name: document.getElementById('firstName').value,
        middle_name: document.getElementById('middleName').value,
        last_name: document.getElementById('lastName').value,
        grade_section: document.getElementById('gradeSection').value,
        age: document.getElementById('age').value,
        security_question_1: q1,
        security_answer_1: a1,
        security_question_2: q2,
        security_answer_2: a2
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
