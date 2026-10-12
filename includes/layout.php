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
            ['icon'=>'fa-archive',      'label'=>'Referrals Archive','href'=>'teacher-referrals-archive.php','key'=>'referrals_archive'],
        ],
        'admin' => [
            ['icon'=>'fa-home',         'label'=>'Dashboard',       'href'=>'admin-dashboard.php',   'key'=>'dashboard'],
            ['icon'=>'fa-folder-open',  'label'=>'All Cases',       'href'=>'admin-cases.php',       'key'=>'cases'],
            ['icon'=>'fa-comments',     'label'=>'Cases Chat',      'href'=>'admin-chats.php',       'key'=>'chats'],
            ['icon'=>'fa-reply-all',    'label'=>'Follow Ups',      'href'=>'admin-followups.php',   'key'=>'followups'],
            ['icon'=>'fa-exchange-alt', 'label'=>'Referrals',       'href'=>'admin-referrals.php',   'key'=>'referrals'],
            ['icon'=>'fa-comments',     'label'=>'Referrals Chat',  'href'=>'admin-referrals-chat.php', 'key'=>'referrals_chat'],
            [
                'icon'=>'fa-archive',
                'label'=>'Archive',
                'key'=>'archive_group',
                'children'=>[
                    ['icon'=>'fa-folder',       'label'=>'Cases Archive',     'href'=>'admin-archive.php',          'key'=>'archive'],
                    ['icon'=>'fa-history',      'label'=>'Chat Archive',      'href'=>'admin-chat-archive.php',     'key'=>'chat_archive'],
                    ['icon'=>'fa-exchange-alt', 'label'=>'Referrals Archive', 'href'=>'admin-referrals-archive.php','key'=>'referrals_archive'],
                ]
            ],
            ['icon'=>'fa-calendar-alt', 'label'=>'Sessions',        'href'=>'admin-sessions.php',    'key'=>'sessions'],
            ['icon'=>'fa-chart-bar',    'label'=>'Analytics',       'href'=>'admin-analytics.php',   'key'=>'analytics'],
            ['icon'=>'fa-users',        'label'=>'Users',           'href'=>'admin-users.php',       'key'=>'users'],
            ['icon'=>'fa-shield-alt',   'label'=>'2FA Requests',    'href'=>'admin-2fa-requests.php','key'=>'2fa_requests'],
        ],
    ];

    $pending2faCount = 0;
    if ($role === 'admin') {
        try {
            $db = getDB();
            $pending2faCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE totp_reset_requested = 1")->fetchColumn();
        } catch(Exception $e){}
    }

    $links = $navLinks[$role] ?? [];
    $sidebarLinks = '';
    foreach ($links as $l) {
        if (!empty($l['children'])) {
            $childKeys = array_column($l['children'], 'key');
            $isGroupActive = in_array($activeNav, $childKeys);
            $openClass = $isGroupActive ? 'open' : '';
            $showClass = $isGroupActive ? 'show' : '';
            $activeClass = $isGroupActive ? 'active active-parent' : '';
            $ariaExpanded = $isGroupActive ? 'true' : 'false';
            $groupId = 'sb-group-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $l['key']);

            $sublinksHtml = '';
            foreach ($l['children'] as $child) {
                $cActive = ($child['key'] === $activeNav) ? 'active' : '';
                $sublinksHtml .= "<a href='{$child['href']}' class='sb-sublink $cActive'><i class='fas {$child['icon']}'></i><span>{$child['label']}</span></a>";
            }

            $sidebarLinks .= "
            <div class='sb-dropdown-group $openClass' id='{$groupId}-wrap'>
              <button type='button' class='sb-link sb-dropdown-btn $activeClass $openClass' onclick='toggleSbDropdown(\"{$groupId}\")' aria-expanded='$ariaExpanded' id='{$groupId}-btn'>
                <i class='fas {$l['icon']}'></i>
                <span>{$l['label']}</span>
                <i class='fas fa-chevron-down sb-chevron'></i>
              </button>
              <div class='sb-submenu $showClass' id='{$groupId}-menu'>
                $sublinksHtml
              </div>
            </div>";
        } else {
            $active = $l['key']===$activeNav ? 'active' : '';
            $badge = '';
            if ($l['key'] === '2fa_requests' && $pending2faCount > 0) {
                $badge = "<span class='badge-notif' style='margin-left:auto;background:var(--danger);color:#fff;font-size:10px;font-weight:800;padding:2px 7px;border-radius:50px;min-width:20px;text-align:center'>$pending2faCount</span>";
            }
            $sidebarLinks .= "<a href='{$l['href']}' class='sb-link $active'><i class='fas {$l['icon']}'></i><span>{$l['label']}</span>$badge</a>";
        }
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
  <link rel="stylesheet" href="/gnhs-guidance/style.css?v=3.2"/>
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
    <img src="/gnhs-guidance/gnhs.jpg" alt="GNHS Logo" class="sb-logo-img"/>
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
        <img src="/gnhs-guidance/gnhs.jpg" alt="GNHS Logo" class="topbar-logo-img"/>
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
    <div id="notifList" style="max-height:420px;overflow-y:auto"><div style="padding:20px;text-align:center;color:var(--text-3)">Loading…</div></div>
  </div>

  <!-- Notification Overview Modal -->
  <div class="modal-overlay hidden" id="notifOverviewModal" onclick="closeNotifOverviewOnBackdrop(event)" style="z-index: 2500;">
    <div class="modal-box" style="max-width: 580px; width: 95%; border-radius: 16px; padding: 0; overflow: hidden; box-shadow: var(--shadow-lg);">
      <div style="background: var(--bg2); padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: flex-start; justify-content: space-between; gap: 16px;">
        <div style="display: flex; gap: 14px; align-items: center;">
          <div id="notifModalIconBox" style="width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; background: var(--maroon-tint); color: var(--maroon);">
            <i class="fas fa-bell"></i>
          </div>
          <div>
            <div id="notifModalBadge" style="display: inline-block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 2px 8px; border-radius: 6px; margin-bottom: 4px; background: #fff0f0; color: var(--maroon);">Notification</div>
            <h3 id="notifModalTitle" style="font-size: 17px; font-weight: 800; color: var(--text); margin: 0; line-height: 1.3;">Notification Overview</h3>
          </div>
        </div>
        <button type="button" class="modal-close" onclick="closeNotifOverview()" style="background: none; border: none; font-size: 18px; color: var(--text-3); cursor: pointer; padding: 6px; border-radius: 6px;" title="Close">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div style="padding: 22px 24px; max-height: calc(85vh - 160px); overflow-y: auto;">
        <!-- Action Date & Time -->
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; background: var(--bg2); border: 1px solid var(--border); border-radius: 10px; padding: 12px 16px; margin-bottom: 18px;">
          <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-2);">
            <i class="fas fa-clock" style="color: var(--maroon); font-size: 15px;"></i>
            <strong>Action Date &amp; Time:</strong>
            <span id="notifModalDateTime" style="font-weight: 600; color: var(--text);"></span>
          </div>
          <div id="notifModalTimeAgo" style="font-size: 12px; font-weight: 600; color: var(--text-3); background: var(--bg); padding: 3px 10px; border-radius: 20px; border: 1px solid var(--border);"></div>
        </div>

        <!-- Overview Content -->
        <div style="margin-bottom: 18px;">
          <label style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-3); display: block; margin-bottom: 8px;">
            Overview &amp; Details
          </label>
          <div id="notifModalMessage" style="font-size: 14.5px; line-height: 1.65; color: var(--text); background: var(--bg); border: 1px solid var(--border); border-radius: 10px; padding: 16px; white-space: pre-line;"></div>
        </div>

        <!-- Structured Details Grid -->
        <div id="notifModalDetailsCard" class="hidden" style="background: var(--bg2); border: 1px solid var(--border); border-radius: 10px; padding: 16px; margin-bottom: 8px;">
          <div style="font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.6px; color: var(--text-2); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
            <i class="fas fa-info-circle" style="color: #2563eb;"></i> Quick Summary
          </div>
          <div id="notifModalDetailsList" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; font-size: 13px;"></div>
        </div>
      </div>

      <div style="background: var(--bg2); padding: 16px 24px; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: flex-end; gap: 12px;">
        <button type="button" class="btn-secondary" onclick="closeNotifOverview()" style="padding: 8px 18px; font-size: 13px;">Close</button>
        <a id="notifModalActionBtn" href="#" class="btn-primary" style="padding: 8px 20px; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
          <span>View Details</span> <i class="fas fa-arrow-right"></i>
        </a>
      </div>
    </div>
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
        <a href="/gnhs-guidance/api/logout.php" class="btn-primary" style="flex:1;justify-content:center;text-decoration:none;display:inline-flex;align-items:center;gap:8px">
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
<script src="/gnhs-guidance/theme.js?v=1.1"></script>
<script src="/gnhs-guidance/app.js?v=3.0"></script>
<script>
let currentNotifsList = [];

async function toggleNotifs() {
  const dd = document.getElementById('notifDropdown');
  dd.classList.toggle('hidden');
  if (!dd.classList.contains('hidden')) await loadNotifs();
}

async function loadNotifs() {
  try {
    const r = await fetch('/gnhs-guidance/api/get_notifications.php');
    const d = await r.json();
    currentNotifsList = d.notifications || [];
    const dot = document.getElementById('notifDot');
    if (d.unread > 0 && dot) dot.classList.remove('hidden');
    else if (dot) dot.classList.add('hidden');
    const list = document.getElementById('notifList');
    if (!currentNotifsList.length) {
      list.innerHTML = '<div style="padding:30px 20px;text-align:center;color:var(--text-3)"><i class="fas fa-bell-slash" style="font-size:24px;margin-bottom:8px;display:block;opacity:0.5"></i>No notifications</div>';
      return;
    }
    list.innerHTML = currentNotifsList.map(function(n, idx) {
      let icon = 'fa-bell';
      let iconBg = 'var(--maroon-tint)';
      let iconColor = 'var(--maroon)';

      if (n.type === 'session' || n.type === 'session_scheduled') {
        icon = 'fa-calendar-check';
        iconBg = '#dbeafe';
        iconColor = '#1d4ed8';
      } else if (n.type === 'case_update') {
        icon = 'fa-folder-open';
        iconBg = 'var(--maroon-tint)';
        iconColor = 'var(--maroon)';
      } else if (n.type === 'chat') {
        icon = 'fa-comments';
        iconBg = '#d1fae5';
        iconColor = '#059669';
      } else if (n.type === 'referral') {
        icon = 'fa-exchange-alt';
        iconBg = '#fef3c7';
        iconColor = '#d97706';
      } else if (n.type === 'appeal') {
        icon = 'fa-redo';
        iconBg = '#fae8ff';
        iconColor = '#9333ea';
      }

      const timeStr = n.formatted_full || formatNotifDate(n.created_at);

      return '<div class="notif-item' + (n.is_read ? '' : ' unread') + '" onclick="openNotifOverview(' + idx + ')" style="cursor:pointer;transition:background 0.2s" title="Click to view full notification overview">'
        + '<div class="notif-icon" style="background:' + iconBg + ';color:' + iconColor + '"><i class="fas ' + icon + '"></i></div>'
        + '<div style="flex:1;overflow:hidden">'
        + '<div style="display:flex;justify-content:space-between;align-items:baseline;gap:6px">'
        + '<div class="notif-title" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + escapeHtmlNotif(n.title || 'Notification') + '</div>'
        + (!n.is_read ? '<span style="width:7px;height:7px;border-radius:50%;background:var(--maroon);flex-shrink:0" title="Unread"></span>' : '')
        + '</div>'
        + '<div class="notif-msg" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin-bottom:4px">' + escapeHtmlNotif(n.message || '') + '</div>'
        + '<div style="font-size:11px;color:var(--text-3);display:flex;align-items:center;gap:4px">'
        + '<i class="far fa-clock" style="font-size:10px"></i> ' + timeStr
        + '</div>'
        + '</div>'
        + '</div>';
    }).join('');
  } catch(e) {}
}

function openNotifOverview(index) {
  const n = currentNotifsList[index];
  if (!n) return;

  // Mark single notif as read in DB if unread
  if (!n.is_read) {
    n.is_read = 1;
    fetch('/gnhs-guidance/api/get_notifications.php?mark_id=' + n.id).catch(()=>{});
    const items = document.querySelectorAll('.notif-item');
    if (items[index]) items[index].classList.remove('unread');
    
    const remainingUnread = currentNotifsList.filter(item => !item.is_read).length;
    const dot = document.getElementById('notifDot');
    if (dot) {
      if (remainingUnread > 0) dot.classList.remove('hidden');
      else dot.classList.add('hidden');
    }
  }

  const createdAt = n.created_at || '';
  const d = createdAt ? new Date(createdAt.replace(/-/g, '/')) : new Date();
  const dateFormatted = d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
  const timeFormatted = d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
  const fullDateTime = `${dateFormatted} at ${timeFormatted}`;

  const diffSec = Math.floor((new Date() - d) / 1000);
  let timeAgo = 'Just now';
  if (diffSec >= 86400) {
    const days = Math.floor(diffSec / 86400);
    timeAgo = days === 1 ? '1 day ago' : `${days} days ago`;
  } else if (diffSec >= 3600) {
    const hours = Math.floor(diffSec / 3600);
    timeAgo = hours === 1 ? '1 hour ago' : `${hours} hours ago`;
  } else if (diffSec >= 60) {
    const mins = Math.floor(diffSec / 60);
    timeAgo = mins === 1 ? '1 min ago' : `${mins} mins ago`;
  }

  const role = "<?= $role ?>";
  let badgeText = 'General';
  let badgeBg = '#f1f5f9';
  let badgeColor = '#475569';
  let iconHtml = '<i class="fas fa-bell"></i>';
  let iconBg = 'var(--maroon-tint)';
  let iconColor = 'var(--maroon)';
  let actionUrl = '#';
  let actionLabel = 'Go to Page';
  let actionIcon = 'fa-arrow-right';

  const type = n.type || '';
  if (type === 'session' || type === 'session_scheduled') {
    badgeText = 'Counseling Session';
    badgeBg = '#eff6ff';
    badgeColor = '#1d4ed8';
    iconHtml = '<i class="fas fa-calendar-check"></i>';
    iconBg = '#dbeafe';
    iconColor = '#1e40af';
    actionUrl = role === 'admin' ? '/gnhs-guidance/pages/admin-sessions.php' : '/gnhs-guidance/pages/student-sessions.php';
    actionLabel = 'View Counseling Sessions';
    actionIcon = 'fa-calendar-alt';
  } else if (type === 'case_update') {
    badgeText = 'Case Update';
    badgeBg = '#fff0f0';
    badgeColor = 'var(--maroon)';
    iconHtml = '<i class="fas fa-folder-open"></i>';
    iconBg = 'var(--maroon-tint)';
    iconColor = 'var(--maroon)';
    actionUrl = role === 'admin' ? '/gnhs-guidance/pages/admin-cases.php' : '/gnhs-guidance/pages/student-cases.php';
    actionLabel = 'View Case Details';
    actionIcon = 'fa-folder-open';
  } else if (type === 'chat') {
    badgeText = 'Messages';
    badgeBg = '#ecfdf5';
    badgeColor = '#047857';
    iconHtml = '<i class="fas fa-comments"></i>';
    iconBg = '#d1fae5';
    iconColor = '#065f46';
    actionUrl = role === 'admin' ? '/gnhs-guidance/pages/admin-chats.php' : '/gnhs-guidance/pages/student-chats.php';
    actionLabel = 'Open Chat Conversation';
    actionIcon = 'fa-comments';
  } else if (type === 'referral') {
    badgeText = 'Referral';
    badgeBg = '#fffbeb';
    badgeColor = '#b45309';
    iconHtml = '<i class="fas fa-exchange-alt"></i>';
    iconBg = '#fef3c7';
    iconColor = '#92400e';
    actionUrl = role === 'admin' ? '/gnhs-guidance/pages/admin-referrals.php' : '/gnhs-guidance/pages/teacher-referrals.php';
    actionLabel = 'View Referrals';
    actionIcon = 'fa-exchange-alt';
  } else if (type === 'appeal') {
    badgeText = 'Follow-up';
    badgeBg = '#fdf4ff';
    badgeColor = '#7e22ce';
    iconHtml = '<i class="fas fa-redo"></i>';
    iconBg = '#fae8ff';
    iconColor = '#6b21a8';
    actionUrl = role === 'admin' ? '/gnhs-guidance/pages/admin-followups.php' : '/gnhs-guidance/pages/student-cases.php';
    actionLabel = 'View Follow-ups';
    actionIcon = 'fa-redo';
  }

  document.getElementById('notifModalTitle').innerText = n.title || 'Notification';
  document.getElementById('notifModalBadge').innerText = badgeText;
  document.getElementById('notifModalBadge').style.background = badgeBg;
  document.getElementById('notifModalBadge').style.color = badgeColor;

  const iconBox = document.getElementById('notifModalIconBox');
  iconBox.innerHTML = iconHtml;
  iconBox.style.background = iconBg;
  iconBox.style.color = iconColor;

  document.getElementById('notifModalDateTime').innerText = fullDateTime;
  document.getElementById('notifModalTimeAgo').innerText = timeAgo;
  document.getElementById('notifModalMessage').innerHTML = escapeHtmlNotif(n.message || '');

  // Extract structured details if present in message (like Session Date, Mode, Location, Case #)
  const detailsCard = document.getElementById('notifModalDetailsCard');
  const detailsList = document.getElementById('notifModalDetailsList');
  let detailItems = [];

  const msg = n.message || '';
  const caseMatch = msg.match(/#([A-Za-z0-9-]+)/);
  if (caseMatch) {
    detailItems.push(`<div><strong style="color:var(--text-3);display:block;font-size:11px">CASE REFERENCE</strong><span style="font-weight:700;color:var(--maroon)">#${caseMatch[1]}</span></div>`);
  }
  const dateMatch = msg.match(/(?:scheduled on|Date & Time:)\s*([A-Za-z]+ \d{1,2}, \d{4}(?:\s+at\s+\d{1,2}:\d{2}\s*(?:AM|PM))?)/i);
  if (dateMatch) {
    detailItems.push(`<div><strong style="color:var(--text-3);display:block;font-size:11px">SESSION SCHEDULE</strong><span style="font-weight:700;color:var(--text)">${dateMatch[1]}</span></div>`);
  }
  const modeMatch = msg.match(/(?:Session Mode|Mode):\s*([^\n|•]+)/i);
  if (modeMatch) {
    detailItems.push(`<div><strong style="color:var(--text-3);display:block;font-size:11px">SESSION TYPE</strong><span style="font-weight:700;color:var(--text)">${modeMatch[1].trim()}</span></div>`);
  }
  const locMatch = msg.match(/(?:Location \/ Link|Location):\s*([^\n|•]+)/i);
  if (locMatch) {
    detailItems.push(`<div><strong style="color:var(--text-3);display:block;font-size:11px">LOCATION / LINK</strong><span style="font-weight:700;color:var(--text)">${locMatch[1].trim()}</span></div>`);
  }

  if (detailItems.length > 0) {
    detailsList.innerHTML = detailItems.join('');
    detailsCard.classList.remove('hidden');
  } else {
    detailsCard.classList.add('hidden');
  }

  const actionBtn = document.getElementById('notifModalActionBtn');
  actionBtn.href = actionUrl;
  actionBtn.innerHTML = `<span>${actionLabel}</span> <i class="fas ${actionIcon}"></i>`;
  if (actionUrl === '#') actionBtn.style.display = 'none';
  else actionBtn.style.display = 'inline-flex';

  document.getElementById('notifOverviewModal').classList.remove('hidden');
  document.getElementById('notifDropdown')?.classList.add('hidden');
}

function closeNotifOverview() {
  document.getElementById('notifOverviewModal')?.classList.add('hidden');
}

function closeNotifOverviewOnBackdrop(e) {
  if (e && e.target && e.target.id === 'notifOverviewModal') {
    closeNotifOverview();
  }
}

function formatNotifDate(dtStr) {
  if (!dtStr) return '';
  const d = new Date(dtStr.replace(/-/g, '/'));
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + ' • ' + d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
}

function escapeHtmlNotif(str) {
  if (!str) return '';
  return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

async function markAllRead() {
  await fetch('/gnhs-guidance/api/get_notifications.php?mark_read=1');
  document.getElementById('notifDot')?.classList.add('hidden');
  document.querySelectorAll('.notif-item.unread').forEach(function(el){ el.classList.remove('unread'); });
  currentNotifsList.forEach(function(item){ item.is_read = 1; });
}

// Close notif dropdown on click outside
document.addEventListener('click', function(e) {
  const notifDropdown = document.getElementById('notifDropdown');
  const notifBtn = document.querySelector('[onclick="toggleNotifs()"]');
  if (notifDropdown && !notifDropdown.classList.contains('hidden')) {
    if (!notifDropdown.contains(e.target) && (!notifBtn || !notifBtn.contains(e.target))) {
      notifDropdown.classList.add('hidden');
    }
  }
});
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
      const res = await fetch('/gnhs-guidance/api/upload_avatar.php', { method:'POST', body:formData });
      const data = await res.json();
      if (data.success) {
        showToast('Profile picture updated successfully.', 'success');
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
    const res  = await fetch('/gnhs-guidance/api/remove_avatar.php');
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








