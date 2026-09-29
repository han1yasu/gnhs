<?php
function renderLayout(array $user, string $pageTitle, string $activeNav, string $content): void {
    $role     = $user['role'];
    $initials = htmlspecialchars($user['avatar_initials'] ?? strtoupper($user['first_name'][0]));
    $fullName = htmlspecialchars($user['first_name'].' '.$user['last_name']);
    $roleLabel = ['student'=>'Student','teacher'=>'Teacher','admin'=>'Guidance Counselor'][$role] ?? $role;
    $email    = htmlspecialchars($user['email'] ?? '');
    $idNumber = htmlspecialchars($user['id_number'] ?? '');
    $grade    = htmlspecialchars($user['grade_section'] ?? '');
    $avatarPhoto = $user['avatar_photo'] ?? null;

    // Sidebar links per role
    $navLinks = [
        'student' => [
            ['icon'=>'fa-home',         'label'=>'Dashboard',       'href'=>'student-dashboard.php', 'key'=>'dashboard'],
            ['icon'=>'fa-file-medical', 'label'=>'Submit Concern',  'href'=>'student-submit.php',    'key'=>'submit'],
            ['icon'=>'fa-folder-open',  'label'=>'My Cases',        'href'=>'student-cases.php',     'key'=>'cases'],
            ['icon'=>'fa-comments',     'label'=>'Cases Chat',      'href'=>'student-chats.php',     'key'=>'chats'],
            ['icon'=>'fa-archive',      'label'=>'Case Archive',    'href'=>'student-archive.php',   'key'=>'archive'],
            ['icon'=>'fa-history',      'label'=>'Chat Archive',    'href'=>'student-chat-archive.php','key'=>'chat_archive'],
            ['icon'=>'fa-calendar-alt', 'label'=>'My Sessions',     'href'=>'student-sessions.php',  'key'=>'sessions'],
        ],
        'teacher' => [
            ['icon'=>'fa-home',         'label'=>'Dashboard',       'href'=>'teacher-dashboard.php', 'key'=>'dashboard'],
            ['icon'=>'fa-users',        'label'=>'Advisory Class',  'href'=>'teacher-advisory.php',  'key'=>'advisory'],
            ['icon'=>'fa-user-plus',    'label'=>'Submit Referral', 'href'=>'teacher-referral.php',  'key'=>'referral'],
            ['icon'=>'fa-list-alt',     'label'=>'My Referrals',    'href'=>'teacher-referrals.php', 'key'=>'referrals'],
            ['icon'=>'fa-comments',     'label'=>'Referrals Chat',  'href'=>'teacher-referrals-chat.php', 'key'=>'referrals_chat'],
        ],
        'admin' => [
            ['icon'=>'fa-home',         'label'=>'Dashboard',       'href'=>'admin-dashboard.php',   'key'=>'dashboard'],
            ['icon'=>'fa-folder-open',  'label'=>'All Cases',       'href'=>'admin-cases.php',       'key'=>'cases'],
            ['icon'=>'fa-comments',     'label'=>'Cases Chat',      'href'=>'admin-chats.php',       'key'=>'chats'],
            ['icon'=>'fa-reply-all',    'label'=>'Follow Ups',      'href'=>'admin-followups.php',   'key'=>'followups'],
            ['icon'=>'fa-archive',      'label'=>'Archive',         'href'=>'admin-archive.php',     'key'=>'archive'],
            ['icon'=>'fa-history',      'label'=>'Chat Archive',    'href'=>'admin-chat-archive.php','key'=>'chat_archive'],
            ['icon'=>'fa-exchange-alt', 'label'=>'Referrals',       'href'=>'admin-referrals.php',   'key'=>'referrals'],
            ['icon'=>'fa-comments',     'label'=>'Referrals Chat',  'href'=>'admin-referrals-chat.php', 'key'=>'referrals_chat'],
            ['icon'=>'fa-calendar-alt', 'label'=>'Sessions',        'href'=>'admin-sessions.php',    'key'=>'sessions'],
            ['icon'=>'fa-chart-bar',    'label'=>'Analytics',       'href'=>'admin-analytics.php',   'key'=>'analytics'],
            ['icon'=>'fa-users',        'label'=>'Users',           'href'=>'admin-users.php',       'key'=>'users'],
        ],
    ];

    $links = $navLinks[$role] ?? [];
    $sidebarLinks = '';
    foreach ($links as $l) {
        $active = $l['key']===$activeNav ? 'active' : '';
        $sidebarLinks .= "<a href='{$l['href']}' class='sb-link $active'><i class='fas {$l['icon']}'></i><span>{$l['label']}</span></a>";
    }

    // Split grade_section into grade and section display
    $gradeRow = '';
    if ($grade) {
        // Expected format: "Grade 9 - Mabini"
        $parts = explode(' - ', $grade, 2);
        $gradeDisplay   = htmlspecialchars(trim($parts[0] ?? $grade));
        $sectionDisplay = htmlspecialchars(trim($parts[1] ?? ''));
        $gradeRow = "
        <div class='profile-info-item'>
          <i class='fas fa-layer-group'></i>
          <div><label>Grade Level</label><span>$gradeDisplay</span></div>
        </div>";
        if ($sectionDisplay) {
            $gradeRow .= "
        <div class='profile-info-item'>
          <i class='fas fa-users'></i>
          <div><label>Section</label><span>$sectionDisplay</span></div>
        </div>";
        }
    }

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title><?= htmlspecialchars($pageTitle) ?> — GNHS Guidance</title>
  <link rel="stylesheet" href="/assets/css/style.css?v=2.4"/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&family=Lora:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" rel="stylesheet"/>
  <script>
    (function(){
      var t = localStorage.getItem('theme') || 'light';
      var c = localStorage.getItem('colorTheme') || 'maroon';
      document.documentElement.setAttribute('data-theme', t);
      document.documentElement.setAttribute('data-color', c);
    })();
  </script>
</head>
<body class="dashboard-page">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sb-brand">
    <img src="/assets/images/gnhs.jpg" alt="GNHS Logo" class="sb-logo-img"/>
    <div>
      <div class="sb-school">GNHS</div>
      <div class="sb-sub">Guidance System</div>
    </div>
  </div>
  <nav class="sb-nav">
    <?= $sidebarLinks ?>
  </nav>
  <div class="sb-user">
    <div style="width:36px;height:36px;min-width:36px;min-height:36px;border-radius:10px;overflow:hidden;flex-shrink:0;border:2px solid rgba(255,255,255,.25);">
      <?php if ($avatarPhoto): ?>
        <img src="<?= htmlspecialchars($avatarPhoto) ?>" alt="Avatar" style="width:36px;height:36px;object-fit:cover;object-position:center top;display:block;"/>
      <?php else: ?>
        <div class="sb-avatar"><?= $initials ?></div>
      <?php endif; ?>
    </div>
    <div class="sb-user-info">
      <div class="sb-user-name"><?= $fullName ?></div>
      <div class="sb-user-role"><?= $roleLabel ?></div>
    </div>
    <a href="#" class="sb-logout" title="Logout" onclick="event.preventDefault();confirmLogout()">
      <i class="fas fa-sign-out-alt"></i>
    </a>
  </div>
</aside>

<!-- MAIN -->
<div class="main-content">
  <header class="topbar">
    <div class="topbar-left">
      <button class="topbar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
        <i class="fas fa-bars"></i>
      </button>
      <div class="topbar-brand">
        <img src="/assets/images/gnhs.jpg" alt="GNHS Logo" class="topbar-logo-img"/>
        <div class="topbar-school-info">
          <span class="topbar-school-name">Guinayang National High School</span>
          <span class="topbar-page-title"><?= htmlspecialchars($pageTitle) ?></span>
        </div>
      </div>
    </div>
    <div class="topbar-right">
      <div class="theme-container" style="position:relative; display:inline-flex; align-items:center; margin-right:8px;">
        <button class="nav-link" style="background:none;border:none;cursor:pointer;font-size:16px;color:var(--text-2);padding:8px;border-radius:50%;" onclick="toggleThemeMenu()"><i class="fas fa-palette"></i></button>
        <div class="theme-panel" id="themePanel">
          <button class="tp-mode-toggle" id="tpModeToggle" onclick="setTheme(document.documentElement.getAttribute('data-theme')==='dark'?'light':'dark')">
            <i class="fas fa-moon"></i> Dark Mode
          </button>
          <div style="font-size:12px;font-weight:800;color:var(--text-3);text-align:center;margin-top:4px">COLOR THEME</div>
          <div class="tp-row">
            <div class="tp-color active" style="background:#550000" data-color="maroon" onclick="setColorMode('maroon')"></div>
            <div class="tp-color" style="background:#2563eb" data-color="blue" onclick="setColorMode('blue')"></div>
            <div class="tp-color" style="background:#059669" data-color="emerald" onclick="setColorMode('emerald')"></div>
            <div class="tp-color" style="background:#7c3aed" data-color="violet" onclick="setColorMode('violet')"></div>
          </div>
        </div>
      </div>
      <button class="notif-btn" onclick="toggleNotifs()">
        <i class="fas fa-bell"></i>
        <span class="notif-dot hidden" id="notifDot"></span>
      </button>
      <div class="topbar-user" onclick="toggleProfileMenu()" style="cursor:pointer;position:relative">
        <div style="width:32px;height:32px;min-width:32px;min-height:32px;border-radius:8px;overflow:hidden;flex-shrink:0;">
          <?php if ($avatarPhoto): ?>
            <img src="<?= htmlspecialchars($avatarPhoto) ?>" alt="Avatar" style="width:32px;height:32px;object-fit:cover;object-position:center top;display:block;"/>
          <?php else: ?>
            <div class="tb-avatar"><?= $initials ?></div>
          <?php endif; ?>
        </div>
        <span><?= $fullName ?></span>
        <i class="fas fa-chevron-down" style="font-size:11px;color:var(--text-3);margin-left:4px"></i>
        <div class="profile-dropdown hidden" id="profileDropdown">
          <div class="pd-header">
            <div style="width:40px;height:40px;min-width:40px;min-height:40px;border-radius:10px;overflow:hidden;flex-shrink:0;border:2px solid rgba(255,255,255,.3);">
              <?php if ($avatarPhoto): ?>
                <img src="<?= htmlspecialchars($avatarPhoto) ?>" alt="Avatar" style="width:40px;height:40px;object-fit:cover;object-position:center top;display:block;"/>
              <?php else: ?>
                <div class="pd-avatar"><?= $initials ?></div>
              <?php endif; ?>
            </div>
            <div>
              <div class="pd-name"><?= $fullName ?></div>
              <div class="pd-role"><?= $roleLabel ?></div>
            </div>
          </div>
          <div class="pd-divider"></div>
          <button class="pd-item" onclick="openProfileModal()"><i class="fas fa-user"></i> My Profile</button>
          <button class="pd-item pd-logout" onclick="confirmLogout()"><i class="fas fa-sign-out-alt"></i> Logout</button>
        </div>
      </div>
    </div>
  </header>

  <!-- Notification Dropdown -->
  <div class="notif-dropdown hidden" id="notifDropdown">
    <div class="notif-header"><strong>Notifications</strong><button onclick="markAllRead()">Mark all read</button></div>
    <div id="notifList"><div style="padding:20px;text-align:center;color:var(--text-3)">Loading…</div></div>
  </div>

  <!-- Logout Confirmation Modal -->
  <div class="modal-overlay hidden" id="logoutModal">
    <div class="modal-box" style="max-width:380px;text-align:center">
      <div style="width:64px;height:64px;border-radius:50%;background:#fef2f2;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:28px;color:#dc2626">
        <i class="fas fa-sign-out-alt"></i>
      </div>
      <h2 class="modal-title" style="font-size:20px">Confirm Logout</h2>
      <p style="color:var(--text-2);font-size:14px;margin-bottom:24px">Are you sure you want to log out of your account?</p>
      <div style="display:flex;gap:12px">
        <button class="btn-secondary" style="flex:1" onclick="document.getElementById('logoutModal').classList.add('hidden')">
          <i class="fas fa-times"></i> Cancel
        </button>
        <a href="/api/logout.php" class="btn-primary" style="flex:1;justify-content:center;text-decoration:none;display:inline-flex;align-items:center;gap:8px">
          <i class="fas fa-sign-out-alt"></i> Yes, Logout
        </a>
      </div>
    </div>
  </div>

  <!-- Profile Modal -->
  <div class="modal-overlay hidden" id="profileModal" onclick="if(event.target.id==='profileModal')this.classList.add('hidden')">
    <div class="modal-box" style="max-width:460px">
      <button class="modal-close" onclick="document.getElementById('profileModal').classList.add('hidden')"><i class="fas fa-times"></i></button>
      <div style="text-align:center;margin-bottom:24px">
        <!-- Avatar circle — click overlay to change -->
        <div class="profile-avatar-wrap" onclick="document.getElementById('avatarFileInput').click()" title="Click to change photo">
          <?php if ($avatarPhoto): ?>
            <img src="<?= htmlspecialchars($avatarPhoto) ?>" alt="Avatar" class="profile-big-avatar-img" id="profileAvatarPreview"/>
          <?php else: ?>
            <div class="profile-big-avatar" id="profileAvatarPreview"><?= $initials ?></div>
          <?php endif; ?>
          <div class="profile-avatar-overlay">
            <i class="fas fa-camera"></i>
            <span>Change</span>
          </div>
        </div>
        <input type="file" id="avatarFileInput" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="uploadAvatar(this)"/>
        <!-- Buttons row -->
        <div class="profile-avatar-actions">
          <button class="profile-change-photo" onclick="document.getElementById('avatarFileInput').click()">
            <i class="fas fa-camera"></i> Change Photo
          </button>
          <?php if ($avatarPhoto): ?>
            <button class="profile-remove-photo" id="removePhotoBtn" onclick="removeAvatar()">
              <i class="fas fa-trash-alt"></i> Remove
            </button>
          <?php else: ?>
            <button class="profile-remove-photo" id="removePhotoBtn" onclick="removeAvatar()" style="display:none">
              <i class="fas fa-trash-alt"></i> Remove
            </button>
          <?php endif; ?>
        </div>
        <h2 style="font-size:20px;font-weight:900;margin-top:12px"><?= $fullName ?></h2>
        <span class="profile-role-badge"><?= $roleLabel ?></span>
      </div>
      <div class="profile-info-grid">
        <div class="profile-info-item">
          <i class="fas fa-envelope"></i>
          <div><label>Email Address</label><span><?= $email ?></span></div>
        </div>
        <div class="profile-info-item">
          <i class="fas fa-id-card"></i>
          <div><label>ID Number</label><span><?= $idNumber ?></span></div>
        </div>
        <div class="profile-info-item">
          <i class="fas fa-user-tag"></i>
          <div><label>Role</label><span><?= $roleLabel ?></span></div>
        </div>
        <?= $gradeRow ?>
      </div>
      <div style="margin-top:20px">
        <button class="btn-primary" style="width:100%" onclick="confirmLogout()">
          <i class="fas fa-sign-out-alt"></i> Logout
        </button>
      </div>
    </div>
  </div>

  <main class="page-content">
    <?= $content ?>
  </main>
  <!-- Cropper Modal -->
  <div class="modal-overlay hidden" id="cropperModal" style="z-index: 2000;">
    <div class="modal-box" style="max-width:500px;text-align:center;">
      <h2 class="modal-title" style="margin-bottom: 16px;">Crop Profile Picture</h2>
      <div style="max-height:400px;overflow:hidden;margin-bottom:20px;background:#f0f0f2;border-radius:8px;">
        <img id="cropperImage" src="" style="max-width:100%; display:block;" />
      </div>
      <div style="display:flex;gap:12px">
        <button class="btn-secondary" style="flex:1" onclick="cancelCrop()">
          <i class="fas fa-times"></i> Cancel
        </button>
        <button class="btn-primary" style="flex:1;justify-content:center" onclick="confirmCrop()">
          <i class="fas fa-crop"></i> Crop & Save
        </button>
      </div>
    </div>
  </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
<script src="/assets/js/theme.js?v=1.1"></script>
<script src="/assets/js/app.js?v=2.4"></script>
<script>
async function toggleNotifs() {
  const dd = document.getElementById('notifDropdown');
  dd.classList.toggle('hidden');
  if (!dd.classList.contains('hidden')) await loadNotifs();
}
async function loadNotifs() {
  try {
    const r = await fetch('/api/get_notifications.php');
    const d = await r.json();
    const dot = document.getElementById('notifDot');
    if (d.unread > 0 && dot) dot.classList.remove('hidden');
    else if (dot) dot.classList.add('hidden');
    const list = document.getElementById('notifList');
    if (!d.notifications || !d.notifications.length) {
      list.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-3)">No notifications</div>';
      return;
    }
    list.innerHTML = d.notifications.map(function(n) {
      // Determine redirection URL based on notification type and user role
      var url = '#';
      var role = "<?= $role ?>";
      if (n.type === 'chat') {
        url = role === 'admin' ? '/pages/admin-chats.php' : '/pages/student-chats.php';
      } else if (n.type === 'case_update') {
        url = role === 'admin' ? '/pages/admin-cases.php' : '/pages/student-cases.php';
      } else if (n.type === 'referral') {
        url = role === 'admin' ? '/pages/admin-referrals.php' : '/pages/teacher-referrals.php';
      } else if (n.type === 'session') {
        url = role === 'admin' ? '/pages/admin-sessions.php' : '/pages/student-sessions.php';
      } else if (n.type === 'appeal') {
        url = role === 'admin' ? '/pages/admin-followups.php' : '#';
      }

      return '<div class="notif-item' + (n.is_read ? '' : ' unread') + '" onclick="window.location.href=\'' + url + '\'" style="cursor:pointer">'
        + '<div class="notif-icon"><i class="fas fa-bell"></i></div>'
        + '<div><div class="notif-title">' + n.title + '</div>'
        + '<div class="notif-msg">' + n.message + '</div></div>'
        + '</div>';
    }).join('');
  } catch(e) {}
}
async function markAllRead() {
  await fetch('/api/get_notifications.php?mark_read=1');
  document.getElementById('notifDot')?.classList.add('hidden');
  document.querySelectorAll('.notif-item.unread').forEach(function(el){ el.classList.remove('unread'); });
}
function toggleProfileMenu() {
  const dd = document.getElementById('profileDropdown');
  if (dd) dd.classList.toggle('hidden');
}
function openProfileModal() {
  document.getElementById('profileDropdown')?.classList.add('hidden');
  document.getElementById('profileModal')?.classList.remove('hidden');
}
function confirmLogout() {
  document.getElementById('profileDropdown')?.classList.add('hidden');
  document.getElementById('profileModal')?.classList.add('hidden');
  document.getElementById('logoutModal')?.classList.remove('hidden');
}
document.addEventListener('click', function(e) {
  var dd = document.getElementById('notifDropdown');
  if (dd && !dd.classList.contains('hidden') && !e.target.closest('.notif-btn') && !e.target.closest('#notifDropdown')) {
    dd.classList.add('hidden');
  }
  var pd = document.getElementById('profileDropdown');
  if (pd && !pd.classList.contains('hidden') && !e.target.closest('.topbar-user')) {
    pd.classList.add('hidden');
  }
});
loadNotifs();

// ── Avatar Upload & Cropping ─────────────────────────────────────────────
let cropperInstance = null;
let currentAvatarFile = null;

function uploadAvatar(input) {
  const file = input.files[0];
  if (!file) return;

  if (file.size > 3 * 1024 * 1024) {
    showToast('Image must be under 3MB.', 'error');
    input.value = '';
    return;
  }
  
  currentAvatarFile = file;
  const reader = new FileReader();
  reader.onload = function(e) {
    const cropperImage = document.getElementById('cropperImage');
    cropperImage.src = e.target.result;
    
    // Hide profile modal temporarily
    document.getElementById('profileModal').classList.add('hidden');
    document.getElementById('cropperModal').classList.remove('hidden');
    
    if (cropperInstance) cropperInstance.destroy();
    
    cropperInstance = new Cropper(cropperImage, {
      aspectRatio: 1,
      viewMode: 1,
      dragMode: 'move',
      autoCropArea: 1,
      restore: false,
      guides: true,
      center: true,
      highlight: false,
      cropBoxMovable: true,
      cropBoxResizable: true,
      toggleDragModeOnDblclick: false,
    });
  };
  reader.readAsDataURL(file);
}

function cancelCrop() {
  document.getElementById('cropperModal').classList.add('hidden');
  document.getElementById('profileModal').classList.remove('hidden');
  document.getElementById('avatarFileInput').value = '';
  if (cropperInstance) {
    cropperInstance.destroy();
    cropperInstance = null;
  }
}

async function confirmCrop() {
  if (!cropperInstance) return;
  
  const canvas = cropperInstance.getCroppedCanvas({
    width: 300,
    height: 300
  });
  
  if (!canvas) {
    showToast('Could not crop image.', 'error');
    return;
  }
  
  const src = canvas.toDataURL('image/jpeg', 0.9);
  
  // Preview immediately
  const preview = document.getElementById('profileAvatarPreview');
  if (preview) {
    let img;
    if (preview.tagName === 'IMG') {
      preview.src = src;
    } else {
      img = document.createElement('img');
      img.src = src;
      img.className = 'profile-big-avatar-img';
      img.id = 'profileAvatarPreview';
      preview.parentNode.replaceChild(img, preview);
    }
  }

  document.querySelectorAll('.sb-user > div[style*="overflow:hidden"]').forEach(box => {
    box.innerHTML = '<img src="'+src+'" alt="Avatar" style="width:36px;height:36px;object-fit:cover;object-position:center top;display:block;"/>';
  });
  document.querySelectorAll('.topbar-user > div[style*="overflow:hidden"]').forEach(box => {
    box.innerHTML = '<img src="'+src+'" alt="Avatar" style="width:32px;height:32px;object-fit:cover;object-position:center top;display:block;"/>';
  });
  document.querySelectorAll('.pd-header > div[style*="overflow:hidden"]').forEach(box => {
    box.innerHTML = '<img src="'+src+'" alt="Avatar" style="width:40px;height:40px;object-fit:cover;object-position:center top;display:block;"/>';
  });

  // Close cropper modal, show profile modal
  document.getElementById('cropperModal').classList.add('hidden');
  document.getElementById('profileModal').classList.remove('hidden');
  
  // Upload Blob
  canvas.toBlob(async (blob) => {
    const formData = new FormData();
    formData.append('avatar', blob, currentAvatarFile.name);

    try {
      const res = await fetch('/api/upload_avatar.php', { method:'POST', body:formData });
      const data = await res.json();
      if (data.success) {
        showToast('Profile picture updated! ✅', 'success');
        const removeBtn = document.getElementById('removePhotoBtn');
        if (removeBtn) removeBtn.style.display = 'inline-flex';
      } else {
        showToast(data.message || 'Upload failed.', 'error');
      }
    } catch(e) {
      showToast('Upload error. Please try again.', 'error');
    }
  }, 'image/jpeg', 0.9);
  
  cropperInstance.destroy();
  cropperInstance = null;
  document.getElementById('avatarFileInput').value = '';
}

async function removeAvatar() {
  if (!confirm('Remove your profile picture?')) return;
  try {
    const res  = await fetch('/api/remove_avatar.php');
    const data = await res.json();
    if (data.success) {
      showToast('Profile picture removed.', 'info');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(data.message || 'Failed to remove.', 'error');
    }
  } catch(e) {
    showToast('Error. Please try again.', 'error');
  }
}
</script>
</body>
</html>
<?php
} // end renderLayout








