<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin');
$db   = getDB();

// Fetch summary metrics
$pendingUsers = $db->query("SELECT * FROM users WHERE totp_reset_requested = 1 ORDER BY first_name ASC")->fetchAll();
$activeTotpUsers = $db->query("SELECT * FROM users WHERE totp_enabled = 1 ORDER BY first_name ASC")->fetchAll();
$allUsers = $db->query("SELECT * FROM users ORDER BY role, first_name ASC")->fetchAll();

$pendingCount = count($pendingUsers);
$activeCount  = count($activeTotpUsers);
$totalCount   = count($allUsers);

// Helper function for user avatar
function renderUserAvatar(array $u): string {
    if (!empty($u['avatar_photo'])) {
        return "<div class='user-card-avatar' style='width:42px;height:42px;border-radius:12px;overflow:hidden;background:none;padding:0;flex-shrink:0'>
            <img src='".htmlspecialchars($u['avatar_photo'])."' style='width:100%;height:100%;object-fit:cover;display:block;'/>
        </div>";
    }
    $initials = htmlspecialchars($u['avatar_initials'] ?? strtoupper($u['first_name'][0] . ($u['last_name'][0] ?? '')));
    return "<div class='avatar-circle' style='width:42px;height:42px;border-radius:12px;font-size:14px;flex-shrink:0'>$initials</div>";
}

// Build rows for Pending Requests table
$pendingRows = '';
foreach ($pendingUsers as $u) {
    $name = htmlspecialchars($u['first_name'] . ' ' . $u['last_name']);
    $email = htmlspecialchars($u['email']);
    $idNum = htmlspecialchars($u['id_number']);
    $roleClass = 'role-' . $u['role'];
    $roleLabel = ucfirst($u['role'] === 'admin' ? 'Counselor' : $u['role']);
    $avatar = renderUserAvatar($u);
    
    $detail = '';
    if ($u['role'] === 'student' && $u['grade_section']) {
        $detail = "<div style='font-size:12px;color:var(--text-3);margin-top:2px'><i class='fas fa-school' style='color:var(--maroon);width:12px'></i> " . htmlspecialchars($u['grade_section']) . "</div>";
    } elseif ($u['role'] === 'teacher' && !empty($u['advisory_class'])) {
        $detail = "<div style='font-size:12px;color:var(--maroon);font-weight:700;margin-top:2px'><i class='fas fa-chalkboard-teacher' style='width:12px'></i> " . htmlspecialchars($u['advisory_class']) . "</div>";
    }

    $rawName = htmlspecialchars($u['first_name'] . ' ' . $u['last_name'], ENT_QUOTES);
    $rawEmail = htmlspecialchars($u['email'], ENT_QUOTES);
    $rawRole = htmlspecialchars($roleLabel, ENT_QUOTES);

    $pendingRows .= "<tr class='totp-req-row' data-search='{$name} {$email} {$idNum} {$roleLabel}'>
        <td style='padding:14px 16px;'>
            <div style='display:flex;align-items:center;gap:12px;'>
                $avatar
                <div>
                    <strong style='font-size:14px;color:var(--text);display:block'>$name</strong>
                    <span style='font-size:12px;color:var(--text-3)'>$email</span>
                </div>
            </div>
        </td>
        <td><span class='user-card-role $roleClass' style='font-size:11px;padding:3px 10px;'>$roleLabel</span></td>
        <td>$detail <div style='font-size:12px;color:var(--text-3)'>ID: $idNum</div></td>
        <td>
            <span style='background:rgba(217,119,6,0.12);border:1px solid rgba(217,119,6,0.25);color:#d97706;font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;display:inline-flex;align-items:center;gap:5px;'>
                <i class='fas fa-clock'></i> Reset Requested
            </span>
        </td>
        <td style='text-align:right;padding-right:16px;'>
            <div style='display:inline-flex;gap:8px;'>
                <button type='button' class='btn-sm-primary' style='background:#16a34a;border-color:#15803d;padding:6px 14px;font-size:12px;display:inline-flex;align-items:center;gap:6px;' onclick=\"openTotpActionModal({$u['id']}, '$rawName', '$rawEmail', '$rawRole', 'reset')\">
                    <i class='fas fa-check'></i> Reset 2FA
                </button>
                <button type='button' class='btn-sm-outline' style='color:var(--text-2);border-color:var(--border);padding:6px 12px;font-size:12px;' onclick=\"openTotpActionModal({$u['id']}, '$rawName', '$rawEmail', '$rawRole', 'dismiss')\" title='Dismiss request without resetting'>
                    <i class='fas fa-times'></i> Dismiss
                </button>
            </div>
        </td>
    </tr>";
}

// Build rows for Active 2FA Users table
$activeRows = '';
foreach ($activeTotpUsers as $u) {
    $name = htmlspecialchars($u['first_name'] . ' ' . $u['last_name']);
    $email = htmlspecialchars($u['email']);
    $idNum = htmlspecialchars($u['id_number']);
    $roleClass = 'role-' . $u['role'];
    $roleLabel = ucfirst($u['role'] === 'admin' ? 'Counselor' : $u['role']);
    $avatar = renderUserAvatar($u);

    $rawName = htmlspecialchars($u['first_name'] . ' ' . $u['last_name'], ENT_QUOTES);
    $rawEmail = htmlspecialchars($u['email'], ENT_QUOTES);
    $rawRole = htmlspecialchars($roleLabel, ENT_QUOTES);

    $activeRows .= "<tr class='totp-active-row' data-search='{$name} {$email} {$idNum} {$roleLabel}'>
        <td style='padding:14px 16px;'>
            <div style='display:flex;align-items:center;gap:12px;'>
                $avatar
                <div>
                    <strong style='font-size:14px;color:var(--text);display:block'>$name</strong>
                    <span style='font-size:12px;color:var(--text-3)'>$email</span>
                </div>
            </div>
        </td>
        <td><span class='user-card-role $roleClass' style='font-size:11px;padding:3px 10px;'>$roleLabel</span></td>
        <td><div style='font-size:12px;color:var(--text-3)'>ID: $idNum</div></td>
        <td>
            <span style='background:rgba(22,163,74,0.12);border:1px solid rgba(22,163,74,0.25);color:#16a34a;font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;display:inline-flex;align-items:center;gap:5px;'>
                <i class='fas fa-shield-alt'></i> 2FA Active
            </span>
        </td>
        <td style='text-align:right;padding-right:16px;'>
            <button type='button' class='btn-sm-outline' style='color:#d97706;border-color:rgba(217,119,6,0.3);background:rgba(217,119,6,0.08);padding:6px 12px;font-size:12px;display:inline-flex;align-items:center;gap:6px;' onclick=\"openTotpActionModal({$u['id']}, '$rawName', '$rawEmail', '$rawRole', 'reset')\">
                <i class='fas fa-redo'></i> Reset 2FA
            </button>
        </td>
    </tr>";
}

$emptyPendingHtml = "
<div id='pendingEmptyState' style='display:" . ($pendingCount ? 'none' : 'block') . ";text-align:center;padding:44px 20px;background:var(--bg);border:1px dashed var(--border);border-radius:12px;margin:16px 0;'>
    <h3 style='font-size:16px;font-weight:800;color:var(--text);margin-bottom:6px'>No Pending 2FA Reset Requests</h3>
    <p style='color:var(--text-3);font-size:13.5px;max-width:460px;margin:0 auto;line-height:1.5'>
        Whenever a student or teacher requests a 2FA reset from the login screen, their verification request will appear here for administrator approval.
    </p>
</div>";

$emptyActiveHtml = "
<div id='activeEmptyState' style='display:" . ($activeCount ? 'none' : 'block') . ";text-align:center;padding:44px 20px;background:var(--bg);border:1px dashed var(--border);border-radius:12px;margin:16px 0;'>
    <h3 style='font-size:16px;font-weight:800;color:var(--text);margin-bottom:6px'>No Active 2FA Accounts</h3>
    <p style='color:var(--text-3);font-size:13.5px;max-width:400px;margin:0 auto'>Users will appear here once they complete two-factor authentication setup.</p>
</div>";

$pendingPassResets = 0;
try {
    $pendingPassResets = (int)$db->query("SELECT COUNT(*) FROM password_reset_requests WHERE status = 'pending'")->fetchColumn();
} catch (\Throwable $e) {}

$content = <<<HTML
<style>
.totp-tab-bar {

    display: flex;
    align-items: center;
    gap: 6px;
    background: var(--bg2);
    padding: 4px;
    border-radius: 12px;
    border: 1px solid var(--border);
}
.tab-pill {
    padding: 8px 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    color: var(--text-2);
    cursor: pointer;
    background: transparent;
    transition: all .2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
}
.tab-pill:hover {
    color: var(--maroon);
    background: var(--maroon-pale);
}
.tab-pill.active {
    color: var(--maroon);
    background: #fff;
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
}
[data-theme="dark"] .totp-tab-bar {
    background: var(--bg);
    border-color: var(--border);
}
[data-theme="dark"] .tab-pill {
    color: var(--text-2);
}
[data-theme="dark"] .tab-pill:hover {
    color: var(--text);
    background: rgba(255, 255, 255, 0.06);
}
[data-theme="dark"] .tab-pill.active {
    color: #fff;
    background: #27272a;
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 1px 6px rgba(0, 0, 0, 0.35);
}
.totp-table th {
    padding: 12px 16px;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-3);
    font-weight: 700;
    border-bottom: 1px solid var(--border);
    background: var(--bg);
}
.totp-table td {
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}
.totp-table tr:hover td {
    background: rgba(0,0,0,0.015);
}
[data-theme="dark"] .totp-table tr:hover td {
    background: rgba(255,255,255,0.02);
}
.totp-notice-box {
    border-radius: 10px;
    padding: 12px 14px;
    margin-bottom: 18px;
    text-align: left;
    font-size: 13px;
    line-height: 1.5;
}
.totp-notice-warning {
    background: rgba(217, 119, 6, 0.12);
    border: 1px solid rgba(217, 119, 6, 0.25);
    color: #d97706;
}
.totp-notice-neutral {
    background: var(--bg2);
    border: 1px solid var(--border);
    color: var(--text-2);
}
[data-theme="dark"] .totp-notice-warning {
    background: rgba(217, 119, 6, 0.15);
    border-color: rgba(217, 119, 6, 0.35);
    color: #fbbf24;
}
</style>

<!-- Top Tab Bar: 2FA Requests vs Password Reset Requests -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px">
  <div class="totp-tab-bar" style="background:var(--bg2);padding:4px;border-radius:12px;border:1px solid var(--border);display:inline-flex">
    <a href="admin-2fa-requests.php" class="tab-pill active" style="text-decoration:none">
      <i class="fas fa-shield-alt"></i> 2FA Reset Requests <span style="font-size:11px;font-weight:800;background:var(--maroon);color:#fff;padding:1px 6px;border-radius:10px;margin-left:4px">$pendingCount</span>
    </a>
    <a href="admin-reset-requests.php" class="tab-pill" style="text-decoration:none">
      <i class="fas fa-key"></i> Password Reset Requests <span style="font-size:11px;opacity:0.8">($pendingPassResets)</span>
    </a>
    <a href="admin-users.php" class="tab-pill" style="text-decoration:none">
      <i class="fas fa-users"></i> Users Directory
    </a>
  </div>
</div>

<!-- Summary Cards -->
<div class="summary-cards" style="grid-template-columns:repeat(3, 1fr);margin-bottom:24px;">
    <div class="sum-card" style="border-left:4px solid #f59e0b;">
        <div class="sum-icon" style="background:rgba(217,119,6,0.12);color:#d97706">
            <i class="fas fa-clock"></i>
        </div>
        <div>
            <span class="sum-num" style="color:#d97706">$pendingCount</span>
            <div class="sum-label">Pending 2FA Requests</div>
        </div>
    </div>

    <div class="sum-card" style="border-left:4px solid #16a34a;">
        <div class="sum-icon" style="background:rgba(22,163,74,0.12);color:#16a34a">
            <i class="fas fa-shield-alt"></i>
        </div>
        <div>
            <span class="sum-num" style="color:#16a34a">$activeCount</span>
            <div class="sum-label">Active 2FA Accounts</div>
        </div>
    </div>

    <div class="sum-card" style="border-left:4px solid var(--maroon);">
        <div class="sum-icon" style="background:rgba(85,0,0,0.1);color:var(--maroon)">
            <i class="fas fa-users"></i>
        </div>
        <div>
            <span class="sum-num" style="color:var(--maroon)">$totalCount</span>
            <div class="sum-label">Total System Users</div>
        </div>
    </div>
</div>

<!-- Main Table Card -->
<div class="content-card">
    <div class="card-header" style="flex-wrap:wrap;gap:14px;margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:16px;">
        <div class="totp-tab-bar">
            <button type="button" class="tab-pill active" id="tabBtnPending" onclick="switchView('pending')">
                <i class="fas fa-clock"></i> Pending Requests 
                <span style="background:" . ($pendingCount ? '#e11d48' : 'var(--text-3)') . ";color:#fff;font-size:11px;font-weight:800;padding:2px 7px;border-radius:10px;margin-left:2px;">$pendingCount</span>
            </button>
            <button type="button" class="tab-pill" id="tabBtnActive" onclick="switchView('active')">
                <i class="fas fa-shield-alt"></i> Active 2FA Users ($activeCount)
            </button>
        </div>

        <div style="display:flex;gap:10px;align-items:center;">
            <div style="position:relative;display:flex;align-items:center">
                <i class="fas fa-search" style="position:absolute;left:12px;color:var(--text-3);font-size:13px;pointer-events:none"></i>
                <input type="text" id="totpSearchInput" placeholder="Search user by name, email..." oninput="filterTotpTable(this.value)" style="padding:8px 12px 8px 34px;border:1px solid var(--border);border-radius:8px;font-size:13px;outline:none;background:var(--bg2);color:var(--text);width:240px;transition:.2s" />
            </div>
            <a href="admin-users.php" class="btn-sm-outline" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;padding:8px 14px;">
                <i class="fas fa-users"></i> All Users
            </a>
        </div>
    </div>

    <!-- Section 1: Pending Requests Table -->
    <div id="viewPending">
        $emptyPendingHtml
        <div id="pendingTableWrapper" style="overflow-x:auto;" class="table-wrap">
            <table class="totp-table" style="width:100%;border-collapse:collapse;display:" . ($pendingCount ? 'table' : 'none') . ";">
                <thead>
                    <tr>
                        <th style="text-align:left;">User</th>
                        <th style="text-align:left;">Role</th>
                        <th style="text-align:left;">Grade / Class / ID</th>
                        <th style="text-align:left;">Status</th>
                        <th style="text-align:right;padding-right:16px;">Action</th>
                    </tr>
                </thead>
                <tbody id="pendingTbody">
                    $pendingRows
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section 2: Active 2FA Users Table -->
    <div id="viewActive" style="display:none;">
        $emptyActiveHtml
        <div id="activeTableWrapper" style="overflow-x:auto;" class="table-wrap">
            <table class="totp-table" style="width:100%;border-collapse:collapse;display:" . ($activeCount ? 'table' : 'none') . ";">
                <thead>
                    <tr>
                        <th style="text-align:left;">User</th>
                        <th style="text-align:left;">Role</th>
                        <th style="text-align:left;">Grade / Class / ID</th>
                        <th style="text-align:left;">Status</th>
                        <th style="text-align:right;padding-right:16px;">Action</th>
                    </tr>
                </thead>
                <tbody id="activeTbody">
                    $activeRows
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Approve / Reset 2FA or Dismiss -->
<div class="modal-overlay hidden" id="totpActionModal" onclick="closeTotpActionModal(event)">
    <div class="modal-box" style="max-width:480px">
        <button class="modal-close" onclick="closeTotpActionModal()"><i class="fas fa-times"></i></button>
        <div style="margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--border)">
            <h2 class="modal-title" id="actionModalTitle" style="text-align:left;font-size:18px;margin-bottom:4px">Reset Two-Factor Authentication</h2>
            <p class="modal-sub" id="actionModalSub" style="text-align:left;font-size:13px;margin:0">Unlink authenticator for this user</p>
        </div>

        <!-- User mini card -->
        <div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:12px 14px;margin-bottom:16px;display:flex;align-items:center;gap:12px;text-align:left;">
            <div style="width:40px;height:40px;border-radius:10px;background:rgba(85,0,0,0.1);color:var(--maroon);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;">
                <i class="fas fa-user-shield"></i>
            </div>
            <div style="overflow:hidden;flex:1">
                <div id="actionTargetName" style="font-weight:700;font-size:14px;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">User Name</div>
                <div id="actionTargetSub" style="font-size:12px;color:var(--text-3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">user@example.com</div>
            </div>
            <span id="actionTargetRoleBadge" class="user-card-role role-student" style="font-size:11px;padding:3px 10px;flex-shrink:0">Student</span>
        </div>

        <div id="actionExplanationBox" class="totp-notice-box totp-notice-warning">
            <i class="fas fa-info-circle" style="margin-right:4px;"></i>
            <span id="actionExplanationText">Resetting 2FA clears this user's current authenticator key. When they log in again with their password, they will be given a brand-new QR code to scan.</span>
        </div>

        <div id="actionModalMsg" style="display:none;margin-bottom:14px"></div>

        <div class="form-actions" style="display:flex;gap:10px;justify-content:flex-end">
            <button type="button" class="btn-secondary" onclick="closeTotpActionModal()">Cancel</button>
            <button type="button" class="btn-primary" id="confirmActionBtn" onclick="submitTotpAction()"><i class="fas fa-check"></i> Confirm</button>
        </div>
    </div>
</div>

<script>
let _currentActionUserId = null;
let _currentActionMode   = 'reset'; // 'reset' or 'dismiss'
let _currentActiveView   = 'pending';

function switchView(view) {
    _currentActiveView = view;
    const btnPending = document.getElementById('tabBtnPending');
    const btnActive  = document.getElementById('tabBtnActive');
    const viewPending = document.getElementById('viewPending');
    const viewActive  = document.getElementById('viewActive');

    if (view === 'pending') {
        btnPending.classList.add('active');
        btnActive.classList.remove('active');
        viewPending.style.display = 'block';
        viewActive.style.display = 'none';
    } else {
        btnActive.classList.add('active');
        btnPending.classList.remove('active');
        viewActive.style.display = 'block';
        viewPending.style.display = 'none';
    }
    
    // Clear search on switch
    const search = document.getElementById('totpSearchInput');
    if (search) {
        search.value = '';
        filterTotpTable('');
    }
}

function filterTotpTable(val) {
    val = val.toLowerCase().trim();
    const selector = _currentActiveView === 'pending' ? '#pendingTbody tr' : '#activeTbody tr';
    const rows = document.querySelectorAll(selector);
    let matchCount = 0;

    rows.forEach(r => {
        const text = (r.getAttribute('data-search') || r.textContent).toLowerCase();
        const match = !val || text.includes(val);
        r.style.display = match ? '' : 'none';
        if (match) matchCount++;
    });
}

function openTotpActionModal(userId, name, email, role, action) {
    _currentActionUserId = userId;
    _currentActionMode   = action;

    const titleEl = document.getElementById('actionModalTitle');
    const subEl   = document.getElementById('actionModalSub');
    const expBox  = document.getElementById('actionExplanationBox');
    const expText = document.getElementById('actionExplanationText');
    const btn     = document.getElementById('confirmActionBtn');

    document.getElementById('actionTargetName').textContent = name;
    document.getElementById('actionTargetSub').textContent = email;
    
    const roleBadge = document.getElementById('actionTargetRoleBadge');
    roleBadge.textContent = role;
    roleBadge.className = 'user-card-role role-' + role.toLowerCase();

    if (action === 'dismiss') {
        titleEl.textContent = 'Dismiss 2FA Reset Request';
        subEl.textContent = 'Clear pending request for ' + name;
        expBox.className = 'totp-notice-box totp-notice-neutral';
        expText.innerHTML = 'This will dismiss the reset request. The user\'s current two-factor authenticator setup will <strong>not</strong> be removed or reset.';
        btn.className = 'btn-secondary';
        btn.style.background = '';
        btn.innerHTML = '<i class="fas fa-times"></i> Dismiss Request';
    } else {
        titleEl.textContent = 'Reset Two-Factor Authentication';
        subEl.textContent = 'Unlink authenticator app for ' + name;
        expBox.className = 'totp-notice-box totp-notice-warning';
        expText.innerHTML = 'Resetting 2FA clears this user\'s current authenticator key. When they log in again with their password, they will see a <strong>brand-new QR code</strong> to scan with Google Authenticator or Authy.';
        btn.className = 'btn-primary';
        btn.style.background = 'linear-gradient(135deg, #16a34a, #15803d)';
        btn.innerHTML = '<i class="fas fa-check"></i> Reset 2FA Now';
    }

    hideEl('actionModalMsg');
    document.getElementById('totpActionModal').classList.remove('hidden');
}

function closeTotpActionModal(e) {
    if (!e || e.target.id === 'totpActionModal' || e.target.closest('.modal-close') || (e.target.tagName==='BUTTON' && e.target.innerText.trim()==='Cancel')) {
        document.getElementById('totpActionModal')?.classList.add('hidden');
    }
}

async function submitTotpAction() {
    if (!_currentActionUserId) return;
    const btn = document.getElementById('confirmActionBtn');
    setLoading(btn, true);

    const apiEndpoint = _currentActionMode === 'dismiss'
        ? '/gnhs-guidance/api/admin_reset_totp.php?action=dismiss'
        : '/gnhs-guidance/api/admin_reset_totp.php?action=reset';

    try {
        const res = await apiPost(apiEndpoint, { user_id: _currentActionUserId });
        if (res.success) {
            showToast(res.message || 'Action completed successfully.', 'success');
            closeTotpActionModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showEl('actionModalMsg');
            document.getElementById('actionModalMsg').innerHTML = `<div class="error-msg">\${res.message || 'Operation failed.'}</div>`;
            setLoading(btn, false);
        }
    } catch(e) {
        showEl('actionModalMsg');
        document.getElementById('actionModalMsg').innerHTML = '<div class="error-msg">Connection error. Please try again.</div>';
        setLoading(btn, false);
    }
}
</script>
HTML;

renderLayout($user, '2FA Requests', '2fa_requests', $content);
