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
    $avatarOnError = "onerror=\"if(!this.dataset.t){this.dataset.t='1';this.src=this.src.includes('/gnhs-guidance/uploads/')?this.src.replace('/gnhs-guidance/uploads/','/uploads/'):this.src.replace('/uploads/','/gnhs-guidance/uploads/');}else{this.style.display='none';var fb=this.parentElement.querySelector('.avatar-fallback');if(fb)fb.style.display='flex';}\"";

    // Sidebar links grouped by category per role
    $navLinks = [
        'student' => [
            [
                'section' => null,
                'items' => [
                    ['icon'=>'fa-home',         'label'=>'Dashboard',       'href'=>'student-dashboard.php', 'key'=>'dashboard'],
                ]
            ],
            [
                'section' => 'Counseling & Concerns',
                'items' => [
                    ['icon'=>'fa-file-medical', 'label'=>'Submit Concern',  'href'=>'student-submit.php',    'key'=>'submit'],
                    ['icon'=>'fa-folder-open',  'label'=>'My Cases & Referrals', 'href'=>'student-cases.php', 'key'=>'cases'],
                    ['icon'=>'fa-comments',     'label'=>'Cases Chat',      'href'=>'student-chats.php',     'key'=>'chats'],
                    ['icon'=>'fa-calendar-alt', 'label'=>'My Sessions',     'href'=>'student-sessions.php',  'key'=>'sessions'],
                ]
            ],
            [
                'section' => 'History & Archives',
                'items' => [
                    ['icon'=>'fa-archive',      'label'=>'Case Archive',     'href'=>'student-archive.php',          'key'=>'archive'],
                    ['icon'=>'fa-history',      'label'=>'Chat Archive',     'href'=>'student-chat-archive.php',     'key'=>'chat_archive'],
                    ['icon'=>'fa-calendar-check','label'=>'Sessions Archive', 'href'=>'student-sessions-archive.php', 'key'=>'sessions_archive'],
                ]
            ],
        ],
        'teacher' => [
            [
                'section' => null,
                'items' => [
                    ['icon'=>'fa-home',         'label'=>'Dashboard',       'href'=>'teacher-dashboard.php', 'key'=>'dashboard'],
                ]
            ],
            [
                'section' => 'Students',
                'items' => [
                    ['icon'=>'fa-users',        'label'=>'Advisory Class',  'href'=>'teacher-advisory.php',    'key'=>'advisory'],
                    ['icon'=>'fa-user-plus',    'label'=>'Add Student',     'href'=>'teacher-add-student.php', 'key'=>'add_student'],
                ]
            ],
            [
                'section' => 'Referrals',
                'items' => [
                    ['icon'=>'fa-paper-plane',  'label'=>'Submit Referral', 'href'=>'teacher-referral.php',          'key'=>'referral'],
                    ['icon'=>'fa-list-alt',     'label'=>'My Referrals',    'href'=>'teacher-referrals.php',         'key'=>'referrals'],
                    ['icon'=>'fa-comments',     'label'=>'Referrals Chat',  'href'=>'teacher-referrals-chat.php',    'key'=>'referrals_chat'],
                    ['icon'=>'fa-archive',      'label'=>'Referrals Archive','href'=>'teacher-referrals-archive.php','key'=>'referrals_archive'],
                ]
            ],
        ],
        'admin' => [
            [
                'section' => null,
                'items' => [
                    ['icon'=>'fa-home',         'label'=>'Dashboard',       'href'=>'admin-dashboard.php',   'key'=>'dashboard'],
                ]
            ],
            [
                'section' => 'Case Management',
                'items' => [
                    ['icon'=>'fa-folder-open',  'label'=>'All Cases',       'href'=>'admin-cases.php',       'key'=>'cases'],
                    ['icon'=>'fa-comments',     'label'=>'Cases Chat',      'href'=>'admin-chats.php',       'key'=>'chats'],
                    ['icon'=>'fa-reply-all',    'label'=>'Follow Ups',      'href'=>'admin-followups.php',   'key'=>'followups'],
                    ['icon'=>'fa-calendar-alt', 'label'=>'Sessions',        'href'=>'admin-sessions.php',    'key'=>'sessions'],
                ]
            ],
            [
                'section' => 'Referrals',
                'items' => [
                    ['icon'=>'fa-exchange-alt', 'label'=>'Referrals',       'href'=>'admin-referrals.php',      'key'=>'referrals'],
                    ['icon'=>'fa-comments',     'label'=>'Referrals Chat',  'href'=>'admin-referrals-chat.php', 'key'=>'referrals_chat'],
                ]
            ],
            [
                'section' => 'Archives',
                'items' => [
                    [
                        'icon'=>'fa-archive',
                        'label'=>'Archive',
                        'key'=>'archive_group',
                        'children'=>[
                            ['icon'=>'fa-folder',         'label'=>'Cases Archive',     'href'=>'admin-archive.php',          'key'=>'archive'],
                            ['icon'=>'fa-history',        'label'=>'Chat Archive',      'href'=>'admin-chat-archive.php',     'key'=>'chat_archive'],
                            ['icon'=>'fa-exchange-alt',   'label'=>'Referrals Archive', 'href'=>'admin-referrals-archive.php','key'=>'referrals_archive'],
                            ['icon'=>'fa-calendar-check', 'label'=>'Sessions Archive',  'href'=>'admin-sessions-archive.php', 'key'=>'sessions_archive'],
                        ]
                    ],
                ]
            ],
            [
                'section' => 'Administration',
                'items' => [
                    ['icon'=>'fa-chart-bar',    'label'=>'Analytics',       'href'=>'admin-analytics.php',       'key'=>'analytics'],
                    ['icon'=>'fa-users-cog',    'label'=>'User Accounts',   'href'=>'admin-users.php',           'key'=>'users'],
                    ['icon'=>'fa-user-plus',    'label'=>'Add Student',     'href'=>'admin-add-student.php',     'key'=>'add_student'],
                    ['icon'=>'fa-shield-alt',   'label'=>'2FA Requests',    'href'=>'admin-2fa-requests.php',    'key'=>'2fa_requests'],
                ]
            ],
        ],
    ];

    $pending2faCount = 0;
    if ($role === 'admin') {
        try {
            $db = getDB();
            $pending2faCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE totp_reset_requested = 1")->fetchColumn();
        } catch(Exception $e){}
    }

    $sections = $navLinks[$role] ?? [];
    $sidebarLinks = '';
    foreach ($sections as $sec) {
        if (!empty($sec['section'])) {
            $secTitle = htmlspecialchars($sec['section']);
            $sidebarLinks .= "<div class='nav-section-title'>{$secTitle}</div>";
        }
        foreach ($sec['items'] as $l) {
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
                    $sublinksHtml .= "<a href='{$child['href']}' class='sb-sublink $cActive' id='sb-link-{$child['key']}' data-nav-key='{$child['key']}'><i class='fas {$child['icon']}'></i><span>{$child['label']}</span></a>";
                }

                $sidebarLinks .= "
                <div class='sb-dropdown-group $openClass' id='{$groupId}-wrap' data-nav-key='{$l['key']}'>
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
                $sidebarLinks .= "<a href='{$l['href']}' class='sb-link $active' id='sb-link-{$l['key']}' data-nav-key='{$l['key']}'><i class='fas {$l['icon']}'></i><span>{$l['label']}</span>$badge</a>";
            }
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

    $dashboardUrl = ($role === 'teacher') 
        ? 'teacher-dashboard.php' 
        : (($role === 'admin') ? 'admin-dashboard.php' : 'student-dashboard.php');

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title><?= htmlspecialchars($pageTitle) ?> — GNHS Guidance</title>
  <link rel="icon" type="image/png" href="/gnhs-guidance/favicon.png?v=3"/>
  <link rel="icon" type="image/x-icon" href="/gnhs-guidance/favicon.ico?v=3"/>
  <link rel="shortcut icon" type="image/x-icon" href="/gnhs-guidance/favicon.ico?v=3"/>
  <link rel="apple-touch-icon" href="/gnhs-guidance/gnhs.png?v=3"/>
  <link rel="stylesheet" href="/gnhs-guidance/style.css?v=<?= @filemtime(__DIR__ . '/../style.css') ?: '4.0' ?>"/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&family=Lora:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" rel="stylesheet"/>
  <style>
    /* Critical global dark mode safeguards */
    [data-theme="dark"] .user-card,
    [data-theme="dark"] .notif-dropdown,
    [data-theme="dark"] .notif-panel,
    [data-theme="dark"] .content-card,
    [data-theme="dark"] .sum-card,
    [data-theme="dark"] .modal-box {
      background: var(--bg2) !important;
      background-color: #1e1e1e !important;
      border-color: var(--border) !important;
      color: var(--text) !important;
    }
    [data-theme="dark"] .user-card-name,
    [data-theme="dark"] .notif-header,
    [data-theme="dark"] .notif-header strong {
      color: var(--text) !important;
    }
    [data-theme="dark"] .notif-item {
      border-bottom: 1px solid var(--border) !important;
      color: var(--text) !important;
    }
    [data-theme="dark"] .notif-msg {
      color: var(--text-2) !important;
    }
  </style>
  <script>
    (function(){
      var t = localStorage.getItem('theme') || 'light';
      document.documentElement.setAttribute('data-theme', t);
      document.documentElement.setAttribute('data-color', 'maroon');
      localStorage.setItem('colorTheme', 'maroon');
      window.currentUserRole = <?= json_encode($role) ?>;
      window.currentUserName = <?= json_encode($fullName) ?>;
      window.currentUserAdvisory = <?= json_encode($user['advisory_class'] ?? '') ?>;
    })();
  </script>
</head>
<body class="dashboard-page">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <a href="<?= $dashboardUrl ?>" class="sb-brand" title="Go to Dashboard">
    <img src="/gnhs-guidance/gnhs.png" alt="GNHS Logo" class="sb-logo-img"/>
    <div>
      <div class="sb-school">GNHS</div>
      <div class="sb-sub">Guidance System</div>
    </div>
  </a>
  <nav class="sb-nav">
    <?= $sidebarLinks ?>
  </nav>
  <div class="sb-user" id="sidebarUserBtn" onclick="openProfileModal()" style="cursor:pointer;padding-bottom:max(22px, calc(env(safe-area-inset-bottom, 16px) + 14px));margin-bottom:6px;" title="View Profile / Change Password">
    <div style="width:36px;height:36px;min-width:36px;min-height:36px;border-radius:10px;overflow:hidden;flex-shrink:0;border:2px solid rgba(255,255,255,.25);position:relative;">
      <?php if ($avatarPhoto): ?>
        <img src="<?= htmlspecialchars($avatarPhoto) ?>" alt="Avatar" <?= $avatarOnError ?> style="width:36px;height:36px;object-fit:cover;object-position:center top;display:block;"/>
        <div class="sb-avatar avatar-fallback" style="display:none;"><?= $initials ?></div>
      <?php else: ?>
        <div class="sb-avatar"><?= $initials ?></div>
      <?php endif; ?>
    </div>
    <div class="sb-user-info">
      <div class="sb-user-name"><?= $fullName ?></div>
      <div class="sb-user-role"><?= $roleLabel ?></div>
    </div>
    <a href="#" class="sb-logout" title="Logout" onclick="event.stopPropagation();event.preventDefault();confirmLogout()">
      <i class="fas fa-sign-out-alt"></i>
    </a>
  </div>
</aside>

<!-- SIDEBAR BACKDROP FOR MOBILE -->
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar(false)"></div>

<!-- MAIN -->
<div class="main-content">
  <header class="topbar">
    <div class="topbar-left">
      <button class="topbar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
      </button>
      <a href="<?= $dashboardUrl ?>" class="topbar-brand" id="topbarBrand" title="Go to Dashboard">
        <img src="/gnhs-guidance/gnhs.png" alt="GNHS Logo" class="topbar-logo-img"/>
        <div class="topbar-school-info">
          <span class="topbar-school-name">Guinayang National High School</span>
          <span class="topbar-page-title"><?= htmlspecialchars($pageTitle) ?></span>
        </div>
      </a>
    </div>
    <div class="topbar-right">
      <button type="button" class="help-tour-btn" id="tourTriggerBtn" onclick="startSystemTour()" title="Walk Me Through the System">
        <i class="fas fa-compass"></i>
        <span class="tour-btn-text">Guide Me</span>
      </button>
      <button type="button" class="theme-toggle-btn" id="themeToggleBtn" onclick="toggleTheme()" title="Toggle Dark / Light Mode" aria-label="Toggle Theme">
        <i class="fas fa-moon" id="themeToggleIcon"></i>
      </button>
      <button class="notif-btn" id="topbarNotifBtn" onclick="toggleNotifs()" title="Notifications">
        <i class="fas fa-bell"></i>
        <span class="notif-dot hidden" id="notifDot"></span>
      </button>
      <div class="topbar-user" id="topbarUserBtn" onclick="toggleProfileMenu(event)" style="cursor:pointer;position:relative">
        <div style="width:32px;height:32px;min-width:32px;min-height:32px;border-radius:8px;overflow:hidden;flex-shrink:0;position:relative;">
          <?php if ($avatarPhoto): ?>
            <img src="<?= htmlspecialchars($avatarPhoto) ?>" alt="Avatar" <?= $avatarOnError ?> style="width:32px;height:32px;object-fit:cover;object-position:center top;display:block;"/>
            <div class="tb-avatar avatar-fallback" style="display:none;"><?= $initials ?></div>
          <?php else: ?>
            <div class="tb-avatar"><?= $initials ?></div>
          <?php endif; ?>
        </div>
        <span class="tb-user-name-text"><?= $fullName ?></span>
        <i class="fas fa-chevron-down" style="font-size:11px;color:var(--text-3);margin-left:4px"></i>
        <div class="profile-dropdown hidden" id="profileDropdown">
          <div class="pd-header">
            <div style="width:40px;height:40px;min-width:40px;min-height:40px;border-radius:10px;overflow:hidden;flex-shrink:0;border:2px solid rgba(255,255,255,.3);position:relative;">
              <?php if ($avatarPhoto): ?>
                <img src="<?= htmlspecialchars($avatarPhoto) ?>" alt="Avatar" <?= $avatarOnError ?> style="width:40px;height:40px;object-fit:cover;object-position:center top;display:block;"/>
                <div class="pd-avatar avatar-fallback" style="display:none;"><?= $initials ?></div>
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
          <button class="pd-item" onclick="toggleProfileMenu(); startSystemTour();"><i class="fas fa-compass" style="color:var(--maroon)"></i> Walk Me Through</button>
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
    <div class="modal-box" style="max-width: 580px; width: 95%; border-radius: 20px; padding: 0; overflow: hidden; box-shadow: var(--shadow-lg);">
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
  <div class="modal-overlay hidden" id="profileModal" onclick="if(event.target.id==='profileModal')closeProfileModal()">
    <div class="modal-box" style="max-width:480px;padding:26px 28px">
      <button class="modal-close" onclick="closeProfileModal()"><i class="fas fa-times"></i></button>

      <!-- Tab 1: Profile Info -->
      <div id="pTabContentInfo">
        <div style="text-align:center;margin-bottom:20px">
          <!-- Avatar circle — click overlay to change -->
          <div class="profile-avatar-wrap" onclick="document.getElementById('avatarFileInput').click()" title="Click to change photo">
            <?php if ($avatarPhoto): ?>
              <img src="<?= htmlspecialchars($avatarPhoto) ?>" alt="Avatar" <?= $avatarOnError ?> class="profile-big-avatar-img" id="profileAvatarPreview"/>
              <div class="profile-big-avatar avatar-fallback" id="profileAvatarPreviewFallback" style="display:none;"><?= $initials ?></div>
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
        <div style="display:flex;gap:10px;margin-top:20px">
          <button type="button" class="btn-secondary" style="flex:1;display:inline-flex;align-items:center;justify-content:center;gap:6px" onclick="switchProfileModalTab('security')">
            <i class="fas fa-key"></i> Change Password
          </button>
          <button type="button" class="btn-primary" style="flex:1;display:inline-flex;align-items:center;justify-content:center;gap:6px" onclick="confirmLogout()">
            <i class="fas fa-sign-out-alt"></i> Logout
          </button>
        </div>
      </div>

      <!-- Tab 2: Change Password -->
      <div id="pTabContentSecurity" class="hidden">
        <div style="display:flex;align-items:center;justify-content:flex-start;margin-bottom:12px">
          <button type="button" class="modal-back" onclick="switchProfileModalTab('info')" style="margin:0;cursor:pointer;background:none;border:none;color:var(--text-3);font-size:13px;display:inline-flex;align-items:center;gap:6px;font-weight:600">
            <i class="fas fa-arrow-left"></i> Back to Profile
          </button>
        </div>
        <div style="text-align:center;margin-bottom:18px">
          <div style="width:52px;height:52px;border-radius:14px;background:var(--maroon-pale);color:var(--maroon);display:flex;align-items:center;justify-content:center;margin:0 auto 10px;font-size:22px">
            <i class="fas fa-shield-alt"></i>
          </div>
          <h2 style="font-size:19px;font-weight:900;color:var(--text);margin-bottom:4px">Change Password</h2>
          <span style="font-size:12.5px;color:var(--text-3)">Update your account password. Choose at least 8 characters.</span>
        </div>

        <div id="cpAlertMsg" style="display:none;margin-bottom:14px"></div>

        <form id="profileChangePassForm" onsubmit="event.preventDefault();submitChangePassword();">
          <div class="form-group" style="margin-bottom:14px">
            <label style="font-size:12px;font-weight:700;color:var(--text-2);margin-bottom:5px;display:block">
              Current Password <span style="color:#dc2626">*</span>
            </label>
            <div class="input-wrap" style="position:relative">
              <i class="fas fa-lock"></i>
              <input type="password" id="cpCurrentPass" placeholder="Enter your current password" required autocomplete="current-password" style="width:100%;padding-right:40px"/>
              <button type="button" class="toggle-pass" onclick="togglePassVisibility('cpCurrentPass', this)" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-3);cursor:pointer">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>

          <div class="form-group" style="margin-bottom:14px">
            <label style="font-size:12px;font-weight:700;color:var(--text-2);margin-bottom:5px;display:block">
              New Password <span style="color:#dc2626">*</span>
            </label>
            <div class="input-wrap" style="position:relative">
              <i class="fas fa-key"></i>
              <input type="password" id="cpNewPass" placeholder="Minimum 8 characters" minlength="8" required autocomplete="new-password" oninput="checkProfilePassStrength()" style="width:100%;padding-right:40px"/>
              <button type="button" class="toggle-pass" onclick="togglePassVisibility('cpNewPass', this)" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-3);cursor:pointer">
                <i class="fas fa-eye"></i>
              </button>
            </div>
            <div id="cpStrengthIndicator" style="font-size:11.5px;color:var(--text-3);margin-top:4px">
              <i class="fas fa-info-circle"></i> Must be at least 8 characters
            </div>
          </div>

          <div class="form-group" style="margin-bottom:18px">
            <label style="font-size:12px;font-weight:700;color:var(--text-2);margin-bottom:5px;display:block">
              Confirm New Password <span style="color:#dc2626">*</span>
            </label>
            <div class="input-wrap" style="position:relative">
              <i class="fas fa-check-double"></i>
              <input type="password" id="cpConfirmPass" placeholder="Re-type new password" minlength="8" required autocomplete="new-password" oninput="checkProfilePassStrength()" style="width:100%;padding-right:40px"/>
              <button type="button" class="toggle-pass" onclick="togglePassVisibility('cpConfirmPass', this)" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-3);cursor:pointer">
                <i class="fas fa-eye"></i>
              </button>
            </div>
            <div id="cpMatchIndicator" style="font-size:11.5px;color:var(--text-3);margin-top:4px"></div>
          </div>

          <div style="display:flex;gap:10px">
            <button type="button" class="btn-secondary" style="flex:1" onclick="switchProfileModalTab('info')">
              Cancel
            </button>
            <button type="submit" class="btn-primary" id="btnSubmitChangePass" style="flex:1.5;background:var(--maroon);display:inline-flex;align-items:center;justify-content:center;gap:6px">
              <i class="fas fa-check-circle"></i> Update Password
            </button>
          </div>
        </form>
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

  <!-- Bulk CSV & Excel Import Modal (Available for Counselors & Teachers) -->
  <?php if ($role === 'admin' || $role === 'teacher'): ?>
  <div class="modal-overlay hidden" id="csvImportModal" style="z-index: 2100;" onclick="if(event.target.id==='csvImportModal')closeCsvImportModal()">
    <div class="modal-box" style="max-width:780px;width:95vw;max-height:90vh;display:flex;flex-direction:column;padding:24px 26px;text-align:left;overflow-y:auto">
      
      <!-- Modal Header -->
      <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);padding-bottom:14px;margin-bottom:18px">
        <div style="display:flex;align-items:center;gap:12px">
          <div style="width:44px;height:44px;border-radius:12px;background:rgba(22,163,74,0.12);color:#16a34a;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0">
            <i class="fas fa-file-excel"></i>
          </div>
          <div>
            <h2 class="modal-title" style="margin:0;font-size:18px;text-align:left;display:flex;align-items:center;gap:8px">
              Import Students via CSV / Excel
            </h2>
            <div style="font-size:12px;color:var(--text-3);margin-top:2px">
              Select an Excel (<code>.xlsx</code>/<code>.xls</code>) or <code>.csv</code> spreadsheet to enroll multiple student accounts
            </div>
          </div>
        </div>
        <button type="button" class="modal-close" onclick="closeCsvImportModal()" style="position:static"><i class="fas fa-times"></i></button>
      </div>

      <!-- Native OS File Manager Input (Filtered strictly to Excel & CSV) -->
      <input type="file" id="globalCsvFileInput" accept=".csv, .xlsx, .xls, text/csv, application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0;opacity:0" onchange="handleGlobalCsvSelect(this)" />

      <!-- Step 1: Upload / Dropzone Screen -->
      <div id="csvModalUploadStep">
        <!-- Guidelines banner -->
        <div style="background:var(--maroon-pale, #fff0f0);border:1px solid rgba(85,0,0,0.12);border-radius:12px;padding:14px 16px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
          <div style="font-size:12.5px;color:var(--text-2);line-height:1.5;max-width:520px">
            <strong style="color:var(--maroon);display:block;margin-bottom:2px"><i class="fas fa-info-circle"></i> Spreadsheet Column Headers:</strong>
            <code>first_name</code>, <code>last_name</code>, <code>lrn</code> (strictly 12 digits), <code>email</code>, <code>grade_level</code>, <code>section</code>
            <?= ($role === 'teacher' && !empty($user['advisory_class'])) ? '<br><span style="font-size:11.5px;color:var(--text-3)">Blank grade/section entries default to your advisory class: <strong>' . htmlspecialchars($user['advisory_class']) . '</strong></span>' : '' ?>
          </div>
          <a href="<?= (strpos($_SERVER['REQUEST_URI'] ?? '', '/pages/') !== false ? '../api/download_student_template.php' : 'api/download_student_template.php') ?>" class="btn-sm-primary" style="background:#2563eb;text-decoration:none;display:inline-flex;align-items:center;gap:6px;font-size:12px;padding:8px 14px;white-space:nowrap">
            <i class="fas fa-file-download"></i> Download Template
          </a>
        </div>

        <!-- File Manager Dropzone / Click Box -->
        <label for="globalCsvFileInput" class="csv-dropzone" id="csvModalDropzone" ondragover="handleGlobalCsvDragOver(event)" ondragleave="handleGlobalCsvDragLeave(event)" ondrop="handleGlobalCsvDrop(event)" style="display:block;border:2px dashed #cbd5e1;border-radius:14px;padding:38px 20px;text-align:center;background:var(--bg2, #f8fafc);cursor:pointer;transition:all .2s">
          <i class="fas fa-file-excel" style="font-size:46px;color:#16a34a;margin-bottom:12px;display:block"></i>
          <div style="font-size:16px;font-weight:800;color:var(--text);margin-bottom:4px">Click to Open File Manager</div>
          <div style="font-size:13px;color:var(--text-3);margin-bottom:16px">Filters exclusively for Excel (<code>.xlsx</code>, <code>.xls</code>) and <code>.csv</code> spreadsheet files</div>
          <button type="button" class="btn-primary" onclick="event.preventDefault(); document.getElementById('globalCsvFileInput').click();" style="display:inline-flex;align-items:center;gap:8px;padding:10px 24px;font-size:14px;pointer-events:auto;background:var(--maroon);color:#fff;border-radius:8px">
            <i class="fas fa-folder-open"></i> Browse Files in File Manager
          </button>
        </label>
      </div>

      <!-- Step 2: Live Preview & Validation Screen -->
      <div id="csvModalPreviewStep" style="display:none">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:12px">
          <div>
            <h4 style="margin:0;font-size:14.5px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:8px">
              <i class="fas fa-table" style="color:var(--maroon)"></i>
              <span>File: <span id="csvModalFileName" style="color:var(--maroon)"></span></span>
            </h4>
            <div style="font-size:12px;color:var(--text-3);margin-top:2px" id="csvModalMetaText"></div>
          </div>
          <div style="display:flex;gap:8px">
            <span class="badge-valid" id="csvModalValidBadge" style="background:#dcfce7;color:#166534;padding:4px 12px;border-radius:50px;font-size:12px;font-weight:700">
              <i class="fas fa-check-circle"></i> 0 Ready
            </span>
            <span class="badge-invalid" id="csvModalErrorBadge" style="background:#fee2e2;color:#991b1b;padding:4px 12px;border-radius:50px;font-size:12px;font-weight:700">
              <i class="fas fa-exclamation-circle"></i> 0 Errors
            </span>
          </div>
        </div>

        <div style="border:1px solid var(--border);border-radius:10px;overflow:hidden;max-height:300px;overflow-y:auto;overflow-x:auto;margin-bottom:16px">
          <table style="width:100%;border-collapse:collapse;font-size:12px" id="csvModalPreviewTable">
            <thead>
              <tr style="background:var(--bg2, #f8fafc);border-bottom:1px solid var(--border);color:var(--text-2);text-align:left">
                <th style="padding:10px 12px;width:35px;text-align:center">#</th>
                <th style="padding:10px 12px">Student Name</th>
                <th style="padding:10px 12px;width:120px">12-Digit LRN</th>
                <th style="padding:10px 12px">Email Address</th>
                <th style="padding:10px 12px;width:80px">Grade</th>
                <th style="padding:10px 12px;width:100px">Section</th>
                <th style="padding:10px 12px;width:105px">Status</th>
              </tr>
            </thead>
            <tbody id="csvModalPreviewTbody"></tbody>
          </table>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;border-top:1px solid var(--border);padding-top:14px">
          <button type="button" class="btn-secondary" onclick="resetGlobalCsvModal()">
            <i class="fas fa-redo"></i> Choose Different File
          </button>
          <button type="button" class="btn-primary" id="btnConfirmGlobalImport" onclick="executeGlobalCsvModalImport()" style="background:linear-gradient(135deg,var(--maroon) 0%,#7a0c0c 100%);display:inline-flex;align-items:center;gap:8px;padding:10px 22px;font-size:13.5px">
            <i class="fas fa-user-check"></i> Import Valid Students (<span id="csvModalImportCountText">0</span>)
          </button>
        </div>
      </div>

      <!-- Step 3: Success Screen -->
      <div id="csvModalSuccessStep" style="display:none;text-align:center;padding:24px 12px">
        <div style="width:60px;height:60px;border-radius:50%;background:#dcfce7;color:#16a34a;display:inline-flex;align-items:center;justify-content:center;font-size:28px;margin-bottom:14px">
          <i class="fas fa-check"></i>
        </div>
        <h3 style="font-size:20px;font-weight:900;color:var(--text);margin:0 0 6px" id="csvModalSuccessTitle">Import Completed!</h3>
        <p style="font-size:13.5px;color:var(--text-2);margin:0 0 20px" id="csvModalSuccessDesc"></p>
        <div style="display:flex;gap:12px;justify-content:center">
          <button type="button" class="btn-secondary" onclick="resetGlobalCsvModal()">
            <i class="fas fa-plus"></i> Import Another File
          </button>
          <button type="button" class="btn-primary" onclick="closeCsvImportModalAndReload()" style="background:#16a34a;display:inline-flex;align-items:center;gap:8px">
            <i class="fas fa-sync-alt"></i> Done &amp; Refresh Roster
          </button>
        </div>
      </div>

    </div>
  </div>
  <?php endif; ?>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
<script src="/gnhs-guidance/theme.js?v=<?= @filemtime(__DIR__ . '/../theme.js') ?: '1.2' ?>"></script>
<script src="/gnhs-guidance/app.js?v=<?= @filemtime(__DIR__ . '/../app.js') ?: '3.1' ?>"></script>
<script src="/gnhs-guidance/tour.js?v=<?= @filemtime(__DIR__ . '/../tour.js') ?: '2.0' ?>"></script>
<script>
function toggleSidebar(forceState) {
  const sb = document.getElementById('sidebar');
  const bd = document.getElementById('sidebarBackdrop');
  if (!sb) return;
  const shouldOpen = (forceState !== undefined) ? forceState : !sb.classList.contains('open');
  sb.classList.toggle('open', shouldOpen);
  if (bd) bd.classList.toggle('active', shouldOpen);
}

document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.sb-nav a').forEach(function(a) {
    a.addEventListener('click', function() {
      if (window.innerWidth <= 992) {
        toggleSidebar(false);
      }
    });
  });
});

function showToast(msg, type) {
  type = type || 'success';
  let c = document.getElementById('toast-container');
  if (!c) {
    c = document.createElement('div');
    c.id = 'toast-container';
    document.body.appendChild(c);
  }
  const t = document.createElement('div');
  t.className = 'toast toast-' + type;
  const icons = { success: 'check-circle', error: 'times-circle', info: 'info-circle' };
  const iconName = icons[type] || 'info-circle';
  t.innerHTML = '<i class="fas fa-' + iconName + '"></i>' + msg;
  c.appendChild(t);
  setTimeout(() => {
    t.style.opacity = '0';
    t.style.transition = 'opacity .3s';
    setTimeout(() => t.remove(), 300);
  }, 3200);
}

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

      const isAppealItem = (n.type === 'appeal' || n.type === 'follow_up' || n.type === 'followup' || 
                            (n.title && /appeal|follow.?up/i.test(n.title)) ||
                            (n.message && /appeal|follow.?up/i.test(n.message)));

      if (isAppealItem) {
        icon = 'fa-reply-all';
        iconBg = '#fae8ff';
        iconColor = '#9333ea';
      } else if (n.type === 'session' || n.type === 'session_scheduled') {
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
      }

      const timeStr = n.formatted_full || formatNotifDate(n.created_at);
      const appealBadgeHtml = isAppealItem ? ' <span style="font-size:10px;font-weight:700;background:#fdf4ff;color:#7e22ce;padding:1px 6px;border-radius:4px;border:1px solid #e9d5ff;margin-left:4px">Follow-Up</span>' : '';

      return '<div class="notif-item' + (n.is_read ? '' : ' unread') + '" onclick="openNotifOverview(' + idx + ')" style="cursor:pointer;transition:background 0.2s" title="Click to view full notification overview">'
        + '<div class="notif-icon" style="background:' + iconBg + ';color:' + iconColor + '"><i class="fas ' + icon + '"></i></div>'
        + '<div style="flex:1;overflow:hidden">'
        + '<div style="display:flex;justify-content:space-between;align-items:baseline;gap:6px">'
        + '<div class="notif-title" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + escapeHtmlNotif(n.title || 'Notification') + appealBadgeHtml + '</div>'
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
  const isAppeal = (type === 'appeal' || type === 'follow_up' || type === 'followup' || 
                    (n.title && /appeal|follow.?up/i.test(n.title)) ||
                    (n.message && /appeal|follow.?up/i.test(n.message)));

  if (isAppeal) {
    badgeText = 'Case Appeal / Follow-Up';
    badgeBg = '#fdf4ff';
    badgeColor = '#7e22ce';
    iconHtml = '<i class="fas fa-reply-all"></i>';
    iconBg = '#fae8ff';
    iconColor = '#6b21a8';
    actionUrl = role === 'admin' ? '/gnhs-guidance/pages/admin-followups.php' : '/gnhs-guidance/pages/student-cases.php';
    actionLabel = 'Go to Follow Ups Tab';
    actionIcon = 'fa-reply-all';
  } else if (type === 'session' || type === 'session_scheduled') {
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
  const isNested = window.location.pathname.startsWith('/gnhs-guidance/gnhs-guidance');
  const apiBase = isNested ? '/gnhs-guidance/gnhs-guidance/api/get_notifications.php' : '/gnhs-guidance/api/get_notifications.php';
  try {
    await fetch(apiBase + '?mark_read=1');
  } catch (e) {}
  document.getElementById('notifDot')?.classList.add('hidden');
  document.querySelectorAll('.notif-item.unread').forEach(function(el){ el.classList.remove('unread'); });
  currentNotifsList.forEach(function(item){ item.is_read = 1; });
  location.reload();
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
function toggleProfileMenu(e) {
  if (e && e.stopPropagation) e.stopPropagation();
  const dd = document.getElementById('profileDropdown');
  if (dd) dd.classList.toggle('hidden');
}
function openProfileModal() {
  document.getElementById('profileDropdown')?.classList.add('hidden');
  switchProfileModalTab('info');
  document.getElementById('profileModal')?.classList.remove('hidden');
}

function closeProfileModal() {
  document.getElementById('profileModal')?.classList.add('hidden');
  switchProfileModalTab('info');
}

function switchProfileModalTab(tab) {
  const tabInfo = document.getElementById('pTabContentInfo');
  const tabSec = document.getElementById('pTabContentSecurity');
  const alertBox = document.getElementById('cpAlertMsg');
  if (alertBox) alertBox.style.display = 'none';

  if (tab === 'security') {
    tabInfo?.classList.add('hidden');
    tabSec?.classList.remove('hidden');
    setTimeout(() => document.getElementById('cpCurrentPass')?.focus(), 100);
  } else {
    tabInfo?.classList.remove('hidden');
    tabSec?.classList.add('hidden');
  }
}

function togglePassVisibility(inputId, btn) {
  const el = document.getElementById(inputId);
  if (!el) return;
  const isPass = el.type === 'password';
  el.type = isPass ? 'text' : 'password';
  const icon = btn.querySelector('i');
  if (icon) {
    icon.className = isPass ? 'fas fa-eye-slash' : 'fas fa-eye';
  }
}

function checkProfilePassStrength() {
  const p1 = document.getElementById('cpNewPass')?.value || '';
  const p2 = document.getElementById('cpConfirmPass')?.value || '';
  const lenEl = document.getElementById('cpStrengthIndicator');
  const matchEl = document.getElementById('cpMatchIndicator');

  if (lenEl) {
    if (p1.length === 0) {
      lenEl.innerHTML = '<i class="fas fa-info-circle"></i> Must be at least 8 characters';
      lenEl.style.color = 'var(--text-3)';
    } else if (p1.length < 8) {
      lenEl.innerHTML = '<i class="fas fa-times-circle" style="color:#dc2626"></i> Password is too short (' + p1.length + '/8 characters)';
      lenEl.style.color = '#dc2626';
    } else {
      lenEl.innerHTML = '<i class="fas fa-check-circle" style="color:#16a34a"></i> Password length meets minimum requirement (' + p1.length + ' chars)';
      lenEl.style.color = '#16a34a';
    }
  }

  if (matchEl) {
    if (p2.length === 0) {
      matchEl.innerHTML = '';
    } else if (p1 === p2) {
      matchEl.innerHTML = '<i class="fas fa-check-circle" style="color:#16a34a"></i> Passwords match!';
      matchEl.style.color = '#16a34a';
    } else {
      matchEl.innerHTML = '<i class="fas fa-times-circle" style="color:#dc2626"></i> Passwords do not match';
      matchEl.style.color = '#dc2626';
    }
  }
}

async function submitChangePassword() {
  const curPass = document.getElementById('cpCurrentPass')?.value || '';
  const newPass = document.getElementById('cpNewPass')?.value || '';
  const confPass = document.getElementById('cpConfirmPass')?.value || '';
  const alertBox = document.getElementById('cpAlertMsg');
  const btn = document.getElementById('btnSubmitChangePass');

  if (!curPass || !newPass || !confPass) {
    if (alertBox) {
      alertBox.style.display = 'block';
      alertBox.innerHTML = '<div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:10px 14px;border-radius:8px;font-size:13px;display:flex;align-items:center;gap:8px"><i class="fas fa-exclamation-circle"></i> Please fill in all password fields.</div>';
    }
    return;
  }

  if (newPass.length < 8) {
    if (alertBox) {
      alertBox.style.display = 'block';
      alertBox.innerHTML = '<div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:10px 14px;border-radius:8px;font-size:13px;display:flex;align-items:center;gap:8px"><i class="fas fa-exclamation-circle"></i> New password must be at least 8 characters long.</div>';
    }
    document.getElementById('cpNewPass')?.focus();
    return;
  }

  if (newPass !== confPass) {
    if (alertBox) {
      alertBox.style.display = 'block';
      alertBox.innerHTML = '<div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:10px 14px;border-radius:8px;font-size:13px;display:flex;align-items:center;gap:8px"><i class="fas fa-exclamation-circle"></i> New password and confirmation do not match.</div>';
    }
    document.getElementById('cpConfirmPass')?.focus();
    return;
  }

  const origBtnHtml = btn ? btn.innerHTML : '';
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating…';
  }
  if (alertBox) alertBox.style.display = 'none';

  try {
    const apiUrl = window.location.pathname.includes('/gnhs-guidance/gnhs-guidance/')
      ? '/gnhs-guidance/gnhs-guidance/api/change_password.php'
      : '/gnhs-guidance/api/change_password.php';

    const res = await fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        current_password: curPass,
        new_password: newPass,
        confirm_password: confPass
      })
    });
    const data = await res.json();

    if (btn) {
      btn.disabled = false;
      btn.innerHTML = origBtnHtml;
    }

    if (data.success) {
      if (alertBox) {
        alertBox.style.display = 'block';
        alertBox.innerHTML = '<div style="background:#dcfce7;color:#166534;border:1px solid #86efac;padding:12px 14px;border-radius:8px;font-size:13.5px;font-weight:600;display:flex;align-items:center;gap:8px"><i class="fas fa-check-circle" style="font-size:16px"></i> ' + (data.message || 'Password changed successfully!') + '</div>';
      }
      document.getElementById('cpCurrentPass').value = '';
      document.getElementById('cpNewPass').value = '';
      document.getElementById('cpConfirmPass').value = '';
      if (document.getElementById('cpMatchIndicator')) document.getElementById('cpMatchIndicator').innerHTML = '';
      if (typeof showToast === 'function') {
        showToast('Password changed successfully!', 'success');
      }
      setTimeout(() => {
        switchProfileModalTab('info');
      }, 2000);
    } else {
      if (alertBox) {
        alertBox.style.display = 'block';
        alertBox.innerHTML = '<div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:10px 14px;border-radius:8px;font-size:13px;display:flex;align-items:center;gap:8px"><i class="fas fa-exclamation-circle"></i> ' + (data.message || 'Failed to change password.') + '</div>';
      }
    }
  } catch (err) {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = origBtnHtml;
    }
    if (alertBox) {
      alertBox.style.display = 'block';
      alertBox.innerHTML = '<div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:10px 14px;border-radius:8px;font-size:13px;display:flex;align-items:center;gap:8px"><i class="fas fa-exclamation-circle"></i> Network error. Please try again.</div>';
    }
  }
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

document.addEventListener('DOMContentLoaded', function() {
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('profile') === '1' || window.location.hash === '#profile') {
    openProfileModal();
  } else if (urlParams.get('change_password') === '1' || window.location.hash === '#change-password') {
    openProfileModal();
    switchProfileModalTab('security');
  }
});

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

// ── Global CSV & Excel Import Modal Engine ─────────────────────────────
let _globalCsvValidStudents = [];

function openCsvImportModal() {
  resetGlobalCsvModal();
  document.getElementById('csvImportModal')?.classList.remove('hidden');
}

function closeCsvImportModal() {
  document.getElementById('csvImportModal')?.classList.add('hidden');
}

function closeCsvImportModalAndReload() {
  closeCsvImportModal();
  window.location.reload();
}

function resetGlobalCsvModal() {
  _globalCsvValidStudents = [];
  const inp = document.getElementById('globalCsvFileInput');
  if (inp) inp.value = '';
  const upStep = document.getElementById('csvModalUploadStep');
  const prevStep = document.getElementById('csvModalPreviewStep');
  const succStep = document.getElementById('csvModalSuccessStep');
  if (upStep) upStep.style.display = 'block';
  if (prevStep) prevStep.style.display = 'none';
  if (succStep) succStep.style.display = 'none';
  const tbody = document.getElementById('csvModalPreviewTbody');
  if (tbody) tbody.innerHTML = '';
}

function handleGlobalCsvDragOver(e) {
  e.preventDefault();
  document.getElementById('csvModalDropzone')?.classList.add('dragover');
}

function handleGlobalCsvDragLeave(e) {
  document.getElementById('csvModalDropzone')?.classList.remove('dragover');
}

function handleGlobalCsvDrop(e) {
  e.preventDefault();
  document.getElementById('csvModalDropzone')?.classList.remove('dragover');
  if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
    processGlobalSpreadsheetFile(e.dataTransfer.files[0]);
  }
}

function handleGlobalCsvSelect(input) {
  if (input && input.files && input.files.length > 0) {
    processGlobalSpreadsheetFile(input.files[0]);
  }
}

function processGlobalSpreadsheetFile(file) {
  if (!file) return;
  const fName = file.name || '';
  const lower = fName.toLowerCase();
  if (!lower.endsWith('.csv') && !lower.endsWith('.xlsx') && !lower.endsWith('.xls') && file.type !== 'text/csv') {
    alert('Please select a valid Excel (.xlsx, .xls) or CSV spreadsheet file.');
    return;
  }

  // Reveal modal immediately
  document.getElementById('csvImportModal')?.classList.remove('hidden');

  if (typeof XLSX !== 'undefined') {
    const reader = new FileReader();
    reader.onload = function(e) {
      try {
        const data = new Uint8Array(e.target.result);
        const workbook = XLSX.read(data, { type: 'array' });
        const firstSheet = workbook.SheetNames[0];
        const worksheet = workbook.Sheets[firstSheet];
        const rows = XLSX.utils.sheet_to_json(worksheet, { header: 1, defval: '' });
        parseAndPreviewGlobalRows(rows, fName, file.size);
      } catch(err) {
        parseGlobalCsvPlainText(file, fName);
      }
    };
    reader.readAsArrayBuffer(file);
  } else {
    parseGlobalCsvPlainText(file, fName);
  }
}

function parseGlobalCsvPlainText(file, fName) {
  const reader = new FileReader();
  reader.onload = function(e) {
    const text = e.target.result || '';
    const rows = parseGlobalCsvLines(text);
    parseAndPreviewGlobalRows(rows, fName, file.size);
  };
  reader.readAsText(file);
}

function parseGlobalCsvLines(text) {
  const lines = [];
  let row = [];
  let cur = '';
  let inside = false;
  for (let i = 0; i < text.length; i++) {
    const c = text[i];
    const n = text[i + 1];
    if (c === '"') {
      if (inside && n === '"') { cur += '"'; i++; } else { inside = !inside; }
    } else if (c === ',' && !inside) {
      row.push(cur.trim()); cur = '';
    } else if ((c === '\r' || c === '\n') && !inside) {
      if (c === '\r' && n === '\n') i++;
      row.push(cur.trim());
      if (row.length > 1 || (row.length === 1 && row[0] !== '')) lines.push(row);
      row = []; cur = '';
    } else {
      cur += c;
    }
  }
  if (cur.length > 0 || row.length > 0) {
    row.push(cur.trim());
    if (row.length > 1 || (row.length === 1 && row[0] !== '')) lines.push(row);
  }
  return lines;
}

function parseAndPreviewGlobalRows(rawRows, fileName, fileSize) {
  if (!rawRows || rawRows.length < 2) {
    alert('The selected spreadsheet is empty or has no data rows.');
    return;
  }

  // Header mapping
  const headers = rawRows[0].map(h => String(h).toLowerCase().replace(/[^a-z0-9_]/g, ''));
  const findIdx = (names) => {
    for (let n of names) {
      const idx = headers.indexOf(n);
      if (idx !== -1) return idx;
    }
    return -1;
  };

  const idxFirst   = findIdx(['firstname', 'first_name', 'first', 'givenname', 'pangalan']);
  const idxLast    = findIdx(['lastname', 'last_name', 'last', 'surname', 'familyname', 'apelyido']);
  const idxMiddle  = findIdx(['middlename', 'middle_name', 'middle', 'mid']);
  const idxSuffix  = findIdx(['suffix', 'ext']);
  const idxLrn     = findIdx(['lrn', 'idnumber', 'id_number', 'student_lrn', 'studentlrn', 'learnerreferencenumber']);
  const idxEmail   = findIdx(['email', 'emailaddress', 'email_address', 'student_email', 'studentemail']);
  const idxGrade   = findIdx(['gradelevel', 'grade_level', 'grade', 'level', 'baitang']);
  const idxSection = findIdx(['section', 'pangkat', 'sec']);
  const idxPass    = findIdx(['temporarypassword', 'temporary_password', 'password', 'pass']);
  const idxFullName = findIdx(['name', 'fullname', 'studentname', 'student_name']);
  const idxGradeSec = findIdx(['gradesection', 'grade_section', 'class', 'gradeandsection']);

  if (idxFirst === -1 && idxFullName === -1) {
    alert('Spreadsheet is missing student name! Make sure your file contains first_name, last_name, lrn, and email.');
    return;
  }
  if (idxLrn === -1 || idxEmail === -1) {
    alert('Spreadsheet is missing required column headers! Make sure your file contains lrn and email.');
    return;
  }

  // Teacher advisory defaults if present
  let defGrade = '';
  let defSec = '';
  if (window.currentUserAdvisory) {
    const parts = window.currentUserAdvisory.split('-');
    if (parts.length >= 2) {
      defGrade = parts[0].replace(/[^0-9]/g, '').trim();
      defSec = parts.slice(1).join('-').trim();
    }
  }

  _globalCsvValidStudents = [];
  const tbody = document.getElementById('csvModalPreviewTbody');
  if (tbody) tbody.innerHTML = '';

  let validCount = 0;
  let errorCount = 0;
  const seenLrns = new Set();
  const seenEmails = new Set();

  for (let i = 1; i < rawRows.length; i++) {
    const r = rawRows[i];
    if (!r || (r.length === 1 && !r[0])) continue;

    let first  = idxFirst !== -1 ? String(r[idxFirst] || '').trim() : '';
    let last   = idxLast !== -1 ? String(r[idxLast] || '').trim() : '';
    let middle = idxMiddle !== -1 ? String(r[idxMiddle] || '').trim() : '';
    let suffix = idxSuffix !== -1 ? String(r[idxSuffix] || '').trim() : '';
    const lrn    = String(r[idxLrn] || '').trim().replace(/[^0-9]/g, '');
    const email  = String(r[idxEmail] || '').trim();
    let grade    = idxGrade !== -1 ? String(r[idxGrade] || '').trim() : '';
    let section  = idxSection !== -1 ? String(r[idxSection] || '').trim() : '';
    let pass     = idxPass !== -1 ? String(r[idxPass] || '').trim() : 'Student@2026';
    if (!pass) pass = 'Student@2026';

    // Full name fallback
    if ((!first || !last) && idxFullName !== -1) {
      const full = String(r[idxFullName] || '').trim();
      if (full.includes(',')) {
        const parts = full.split(',').map(p => p.trim());
        last = parts[0] || last;
        const firstParts = (parts[1] || '').split(' ').filter(Boolean);
        first = firstParts[0] || first;
        if (firstParts.length > 1) middle = firstParts.slice(1).join(' ');
      } else {
        const parts = full.split(' ').filter(Boolean);
        first = parts[0] || '';
        last = parts.length > 1 ? parts.slice(1).join(' ') : 'Student';
      }
    }

    // Grade & Section fallback
    if ((!grade || !section) && idxGradeSec !== -1) {
      const gs = String(r[idxGradeSec] || '').trim();
      const parts = gs.split('-');
      if (parts.length >= 2) {
        if (!grade) grade = parts[0].replace(/[^0-9]/g, '').trim();
        if (!section) section = parts.slice(1).join('-').trim();
      }
    }

    if (!grade && defGrade) grade = defGrade;
    if (!section && defSec) section = defSec;
    const gradeNum = grade.replace(/[^0-9]/g, '');

    // Validation
    const errors = [];
    if (!first) errors.push('Missing First Name');
    if (!last)  errors.push('Missing Last Name');
    if (!lrn || lrn.length !== 12) errors.push('LRN must be strictly 12 digits');
    else if (seenLrns.has(lrn)) errors.push('Duplicate LRN in file');

    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.push('Invalid Email');
    else if (seenEmails.has(email.toLowerCase())) errors.push('Duplicate Email in file');

    if (!gradeNum || !['7','8','9','10'].includes(gradeNum)) errors.push('Grade must be 7-10');
    if (!section) errors.push('Missing Section');

    const isValid = errors.length === 0;
    if (isValid) {
      validCount++;
      seenLrns.add(lrn);
      seenEmails.add(email.toLowerCase());
      _globalCsvValidStudents.push({
        first_name: first,
        last_name: last,
        middle_name: middle,
        suffix: suffix,
        lrn: lrn,
        email: email,
        grade_level: gradeNum,
        section: section,
        temporary_password: pass
      });
    } else {
      errorCount++;
    }

    if (tbody) {
      const tr = document.createElement('tr');
      tr.style.borderBottom = '1px solid var(--border)';
      if (!isValid) tr.style.background = '#fff5f5';

      tr.innerHTML = '<td style="padding:8px 12px;text-align:center;font-weight:700;color:var(--text-3)">' + i + '</td>'
        + '<td style="padding:8px 12px"><strong>' + escapeHtml(first) + ' ' + escapeHtml(last) + '</strong> ' + (suffix ? escapeHtml(suffix) : '') + '</td>'
        + '<td style="padding:8px 12px"><code style="font-size:11.5px">' + escapeHtml(lrn || '—') + '</code></td>'
        + '<td style="padding:8px 12px;font-size:11.5px">' + escapeHtml(email || '—') + '</td>'
        + '<td style="padding:8px 12px">Grade ' + escapeHtml(gradeNum || '—') + '</td>'
        + '<td style="padding:8px 12px">' + escapeHtml(section || '—') + '</td>'
        + '<td style="padding:8px 12px">'
        + (isValid 
            ? '<span style="background:#dcfce7;color:#166534;padding:3px 8px;border-radius:50px;font-size:11px;font-weight:700"><i class="fas fa-check"></i> Ready</span>' 
            : '<span style="background:#fee2e2;color:#991b1b;padding:3px 8px;border-radius:50px;font-size:11px;font-weight:700" title="' + escapeHtml(errors.join(', ')) + '"><i class="fas fa-times"></i> ' + escapeHtml(errors[0]) + '</span>')
        + '</td>';
      tbody.appendChild(tr);
    }
  }

  // Update Preview UI
  document.getElementById('csvModalFileName').textContent = fileName;
  const kb = Math.round(fileSize / 1024) || 1;
  document.getElementById('csvModalMetaText').textContent = (rawRows.length - 1) + ' student rows parsed • ' + kb + ' KB';
  document.getElementById('csvModalValidBadge').innerHTML = '<i class="fas fa-check-circle"></i> ' + validCount + ' Ready';
  document.getElementById('csvModalErrorBadge').innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + errorCount + ' Errors';
  document.getElementById('csvModalImportCountText').textContent = validCount;

  const btnConfirm = document.getElementById('btnConfirmGlobalImport');
  if (btnConfirm) btnConfirm.disabled = (validCount === 0);

  document.getElementById('csvModalUploadStep').style.display = 'none';
  document.getElementById('csvModalPreviewStep').style.display = 'block';
  document.getElementById('csvModalSuccessStep').style.display = 'none';
}

async function executeGlobalCsvModalImport() {
  if (!_globalCsvValidStudents || _globalCsvValidStudents.length === 0) {
    alert('There are no valid student records to import.');
    return;
  }

  const btn = document.getElementById('btnConfirmGlobalImport');
  if (!confirm('Are you sure you want to enroll and create accounts for ' + _globalCsvValidStudents.length + ' valid students?')) {
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enrolling Accounts...';

  try {
    const p = window.location.pathname;
    const idx = p.lastIndexOf('/pages/');
    const base = idx !== -1 ? p.substring(0, idx) : '';
    const apiPath = base + '/api/import_students.php';

    const resp = await fetch(apiPath, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ students: _globalCsvValidStudents })
    });

    const res = await resp.json();
    if (res.success) {
      document.getElementById('csvModalPreviewStep').style.display = 'none';
      document.getElementById('csvModalSuccessStep').style.display = 'block';
      document.getElementById('csvModalSuccessTitle').textContent = 'Success! ' + res.imported + ' Students Enrolled.';
      let desc = 'Successfully created ' + res.imported + ' student portal accounts into the database.';
      if (res.skipped > 0) {
        desc += ' (' + res.skipped + ' records were skipped as duplicates).';
      }
      document.getElementById('csvModalSuccessDesc').textContent = desc;
    } else {
      alert('Import failed: ' + (res.message || 'Unknown server error.'));
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-user-check"></i> Import Valid Students (' + _globalCsvValidStudents.length + ')';
    }
  } catch(err) {
    alert('Network error while executing import: ' + err.message);
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-user-check"></i> Import Valid Students (' + _globalCsvValidStudents.length + ')';
  }
}

// Global exports
window.openCsvImportModal = openCsvImportModal;
window.closeCsvImportModal = closeCsvImportModal;
window.closeCsvImportModalAndReload = closeCsvImportModalAndReload;
window.resetGlobalCsvModal = resetGlobalCsvModal;
window.handleGlobalCsvDragOver = handleGlobalCsvDragOver;
window.handleGlobalCsvDragLeave = handleGlobalCsvDragLeave;
window.handleGlobalCsvDrop = handleGlobalCsvDrop;
window.handleGlobalCsvSelect = handleGlobalCsvSelect;
window.executeGlobalCsvModalImport = executeGlobalCsvModalImport;
</script>
</body>
</html>
<?php
} // end renderLayout








