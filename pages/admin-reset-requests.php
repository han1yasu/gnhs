<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin');
$db   = getDB();

$filterStatus = $_GET['status'] ?? 'active';
$search       = trim($_GET['q'] ?? '');

$where = "WHERE 1=1";
if ($filterStatus === 'active') {
    $where .= " AND prr.status IN ('pending', 'approved')";
} elseif ($filterStatus === 'completed') {
    $where .= " AND prr.status = 'completed'";
} elseif ($filterStatus === 'all') {
    // Show all records
} elseif ($filterStatus) {
    $where .= " AND prr.status = '" . addslashes($filterStatus) . "'";
}

if ($search) {
    $sq = addslashes($search);
    $where .= " AND (
        LOWER(prr.full_name) LIKE LOWER('%$sq%')
        OR LOWER(prr.email) LIKE LOWER('%$sq%')
        OR LOWER(prr.id_number) LIKE LOWER('%$sq%')
        OR LOWER(COALESCE(prr.grade_section, '')) LIKE LOWER('%$sq%')
        OR LOWER(COALESCE(prr.admin_notes, '')) LIKE LOWER('%$sq%')
    )";
}

$requests = $db->query("SELECT prr.*, u.avatar_photo, u.avatar_initials,
    CONCAT(COALESCE(c.first_name,'IT'), ' ', COALESCE(c.last_name,'Admin')) AS reviewer_name
    FROM password_reset_requests prr
    LEFT JOIN users u ON prr.user_id = u.id
    LEFT JOIN users c ON prr.reviewed_by = c.id
    $where
    ORDER BY FIELD(prr.status, 'pending', 'approved', 'completed', 'rejected'), prr.created_at DESC")->fetchAll();

// Metric counts
$countPending   = (int)$db->query("SELECT COUNT(*) FROM password_reset_requests WHERE status='pending'")->fetchColumn();
$countApproved  = (int)$db->query("SELECT COUNT(*) FROM password_reset_requests WHERE status='approved'")->fetchColumn();
$countCompleted = (int)$db->query("SELECT COUNT(*) FROM password_reset_requests WHERE status='completed'")->fetchColumn();
$countRejected  = (int)$db->query("SELECT COUNT(*) FROM password_reset_requests WHERE status='rejected'")->fetchColumn();
$countTotal     = (int)$db->query("SELECT COUNT(*) FROM password_reset_requests")->fetchColumn();
$countActive    = $countPending + $countApproved;
$countTotp      = (int)$db->query("SELECT COUNT(*) FROM users WHERE totp_reset_requested = 1")->fetchColumn();

function renderResetAvatar(array $r): string {
    if (!empty($r['avatar_photo'])) {
        return "<div class='user-card-avatar' style='width:42px;height:42px;border-radius:12px;overflow:hidden;background:none;padding:0;flex-shrink:0'>
            <img src='".htmlspecialchars($r['avatar_photo'])."' style='width:100%;height:100%;object-fit:cover;display:block;'/>
        </div>";
    }
    $initials = htmlspecialchars($r['avatar_initials'] ?? strtoupper($r['full_name'][0] ?? 'S'));
    return "<div class='avatar-circle' style='width:42px;height:42px;border-radius:12px;font-size:14px;flex-shrink:0'>$initials</div>";
}

$rows = '';
foreach ($requests as $r) {
    $avatar = renderResetAvatar($r);
    $name = htmlspecialchars($r['full_name']);
    $email = htmlspecialchars($r['email']);
    $idNum = htmlspecialchars($r['id_number'] ?: '—');
    $gradeSec = htmlspecialchars($r['grade_section'] ?: '—');
    $role = ucfirst($r['role'] ?: 'Student');

    $dt = new DateTime($r['created_at']);
    $dateDisplay = $dt->format('M d, Y h:i A');

    // Status Badge
    $statusMap = [
        'pending'   => "<span class='status-badge status-pending'><i class='fas fa-clock'></i> Pending Review</span>",
        'approved'  => "<span class='status-badge' style='background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe'><i class='fas fa-key'></i> Approved &amp; Issued</span>",
        'completed' => "<span class='status-badge status-resolved' style='background:#dcfce7;color:#166534;border:1px solid #86efac;font-weight:700'><i class='fas fa-check-double'></i> Completed</span>",
        'rejected'  => "<span class='status-badge status-danger' style='background:#fee2e2;color:#991b1b;border:1px solid #fecaca'><i class='fas fa-times-circle'></i> Rejected</span>"
    ];
    $statusBadge = $statusMap[$r['status']] ?? "<span class='status-badge'>".htmlspecialchars($r['status'])."</span>";

    // Security Verification Badge
    $secBadge = !empty($r['security_verified'])
        ? "<span style='display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:700;background:#dcfce7;color:#166534;border:1px solid #86efac;padding:3px 9px;border-radius:12px'><i class='fas fa-shield-check'></i> Security Verified</span>"
        : "<span style='display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:700;background:#fef3c7;color:#92400e;border:1px solid #fde68a;padding:3px 9px;border-radius:12px'><i class='fas fa-user-clock'></i> In-Person Verification</span>";

    $rJson = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');

    // Action buttons
    $actionBtns = '';
    if ($r['status'] === 'pending') {
        $actionBtns = "
            <div style='display:inline-flex;gap:6px;align-items:center'>
                <button type='button' class='btn-sm-primary' onclick='openResetModal({$rJson})' style='font-size:12px;padding:6px 12px;background:#16a34a;display:inline-flex;align-items:center;gap:6px'>
                    <i class='fas fa-key'></i> Reset Password
                </button>
                <button type='button' class='btn-sm-outline' onclick='openRejectModal({$r['id']}, \"{$name}\")' style='font-size:12px;padding:6px 10px;color:#dc2626;border-color:#fca5a5' title='Reject request'>
                    <i class='fas fa-times'></i>
                </button>
            </div>";
    } elseif ($r['status'] === 'approved') {
        $actionBtns = "
            <div style='display:inline-flex;gap:6px;align-items:center'>
                <button type='button' class='btn-sm-outline' onclick='openViewApprovedModal({$rJson})' style='font-size:12px;padding:6px 11px;display:inline-flex;align-items:center;gap:6px'>
                    <i class='fas fa-eye'></i> View Credentials
                </button>
                <button type='button' class='btn-sm-outline' onclick='markRequestCompleted({$r['id']})' style='font-size:12px;padding:6px 10px;color:#166534;border-color:#86efac;background:#f0fdf4' title='Mark as Completed'>
                    <i class='fas fa-check'></i> Mark Done
                </button>
            </div>";
    } elseif ($r['status'] === 'completed') {
        $actionBtns = "
            <button type='button' class='btn-sm-outline' onclick='openViewApprovedModal({$rJson})' style='font-size:12px;padding:6px 11px;display:inline-flex;align-items:center;gap:6px;color:#166534;border-color:#86efac;background:#f0fdf4'>
                <i class='fas fa-clipboard-check'></i> View Record
            </button>";
    } else {
        $actionBtns = "<span style='font-size:12px;color:var(--text-3);font-style:italic'>Request closed</span>";
    }

    $searchTokens = strtolower("{$r['full_name']} {$r['email']} {$r['id_number']} {$r['grade_section']} {$r['status']}");

    $rows .= "<tr class='reset-req-row' data-search=\"$searchTokens\">
        <td style='padding:14px 16px;'>
            <div style='display:flex;align-items:center;gap:12px;'>
                $avatar
                <div>
                    <strong style='font-size:14px;color:var(--text);display:block'>$name</strong>
                    <span style='font-size:12px;color:var(--text-3)'><i class='fas fa-user-tag'></i> $role</span>
                </div>
            </div>
        </td>
        <td>
            <div style='font-size:13px;font-weight:600;color:var(--text);display:flex;align-items:center;gap:6px'>
                <i class='fas fa-envelope' style='color:var(--maroon);font-size:12px'></i> $email
            </div>
        </td>
        <td><strong style='color:var(--maroon);font-size:13px'>$idNum</strong></td>
        <td><span style='font-size:13px;color:var(--text-2)'>$gradeSec</span></td>
        <td>$secBadge</td>
        <td><span style='font-size:12.5px;color:var(--text-3)'>$dateDisplay</span></td>
        <td>$statusBadge</td>
        <td>$actionBtns</td>
    </tr>";
}

if (!$rows) {
    $emptyMsg = "No password reset requests found.";
    if ($filterStatus === 'active') {
        $emptyMsg = "No non-completed (active) password reset requests at this time. All student requests are up to date!";
    } elseif ($filterStatus === 'completed') {
        $emptyMsg = "No completed password reset requests found.";
    } elseif ($filterStatus === 'pending') {
        $emptyMsg = "No pending password reset requests awaiting IT review.";
    } elseif ($filterStatus === 'approved') {
        $emptyMsg = "No approved password reset requests awaiting student login.";
    }
    if ($search) {
        $emptyMsg = "No password reset requests found matching \"<strong>" . htmlspecialchars($search) . "</strong>\".";
    }
    $rows = "<tr><td colspan='8' style='text-align:center;color:var(--text-3);padding:45px'>$emptyMsg</td></tr>";
}

$sqVal       = htmlspecialchars($search);
$stActive    = ($filterStatus === 'active') ? 'selected' : '';
$stCompleted = ($filterStatus === 'completed') ? 'selected' : '';
$stPending   = ($filterStatus === 'pending') ? 'selected' : '';
$stApprv     = ($filterStatus === 'approved') ? 'selected' : '';
$stRej       = ($filterStatus === 'rejected') ? 'selected' : '';
$stAll       = ($filterStatus === 'all') ? 'selected' : '';

$clsActive    = ($filterStatus === 'active') ? 'reset-filter-tab active-active' : 'reset-filter-tab';
$clsCompleted = ($filterStatus === 'completed') ? 'reset-filter-tab active-completed' : 'reset-filter-tab';
$clsPending   = ($filterStatus === 'pending') ? 'reset-filter-tab active-pending' : 'reset-filter-tab';
$clsApproved  = ($filterStatus === 'approved') ? 'reset-filter-tab active-approved' : 'reset-filter-tab';
$clsAll       = ($filterStatus === 'all') ? 'reset-filter-tab active-all' : 'reset-filter-tab';

$content = <<<HTML
<style>
.totp-tab-bar {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--bg2);
  padding: 6px;
  border-radius: 12px;
  border: 1px solid var(--border);
  box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
.tab-pill {
  padding: 8px 18px;
  border-radius: 8px;
  font-size: 13.5px;
  font-weight: 700;
  color: var(--text-2);
  cursor: pointer;
  background: transparent;
  transition: all .2s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: none;
  text-decoration: none;
}
.tab-pill:hover {
  background: rgba(0,0,0,0.04);
  color: var(--text);
}
.tab-pill.active {
  background: var(--maroon);
  color: #fff !important;
  box-shadow: 0 2px 8px rgba(85,0,0,.25);
}
.tab-pill.active span.pill-badge {
  background: rgba(255,255,255,0.25) !important;
  color: #fff !important;
}

/* Dedicated Filter Tab Bar */
.reset-filter-tabs {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 18px;
  flex-wrap: wrap;
}
.reset-filter-tab {
  padding: 9px 16px;
  border-radius: 10px;
  text-decoration: none;
  font-size: 13px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: 1.5px solid var(--border);
  background: var(--bg2);
  color: var(--text-2);
  transition: all 0.2s ease;
}
.reset-filter-tab:hover {
  border-color: var(--maroon-tint);
  background: var(--bg);
  color: var(--text);
  transform: translateY(-1px);
}
.reset-filter-tab .badge-count {
  font-size: 11px;
  font-weight: 800;
  padding: 2px 8px;
  border-radius: 12px;
  background: rgba(0,0,0,0.06);
  color: var(--text-2);
}

.reset-filter-tab.active-active {
  background: var(--maroon) !important;
  color: #fff !important;
  border-color: var(--maroon) !important;
  box-shadow: 0 2px 8px rgba(85,0,0,0.25);
}
.reset-filter-tab.active-active .badge-count {
  background: rgba(255,255,255,0.25) !important;
  color: #fff !important;
}

.reset-filter-tab.active-completed {
  background: #16a34a !important;
  color: #fff !important;
  border-color: #16a34a !important;
  box-shadow: 0 2px 8px rgba(22,163,74,0.25);
}
.reset-filter-tab.active-completed .badge-count {
  background: rgba(255,255,255,0.25) !important;
  color: #fff !important;
}

.reset-filter-tab.active-pending {
  background: #d97706 !important;
  color: #fff !important;
  border-color: #d97706 !important;
  box-shadow: 0 2px 8px rgba(217,119,6,0.25);
}
.reset-filter-tab.active-pending .badge-count {
  background: rgba(255,255,255,0.25) !important;
  color: #fff !important;
}

.reset-filter-tab.active-approved {
  background: #2563eb !important;
  color: #fff !important;
  border-color: #2563eb !important;
  box-shadow: 0 2px 8px rgba(37,99,235,0.25);
}
.reset-filter-tab.active-approved .badge-count {
  background: rgba(255,255,255,0.25) !important;
  color: #fff !important;
}

.reset-filter-tab.active-all {
  background: var(--text) !important;
  color: var(--bg) !important;
  border-color: var(--text) !important;
  box-shadow: 0 2px 8px rgba(0,0,0,0.25);
}
.reset-filter-tab.active-all .badge-count {
  background: rgba(255,255,255,0.25) !important;
  color: var(--bg) !important;
}
</style>

<!-- Top Tab Bar: 2FA Requests vs Password Reset Requests -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px">
  <div class="totp-tab-bar">
    <a href="admin-2fa-requests.php" class="tab-pill">
      <i class="fas fa-shield-alt"></i> 2FA Reset Requests <span class="pill-badge badge-count" style="font-size:11px;font-weight:700;background:rgba(0,0,0,0.06);padding:2px 7px;border-radius:10px;margin-left:4px">$countTotp</span>
    </a>
    <a href="admin-reset-requests.php" class="tab-pill active">
      <i class="fas fa-key"></i> Password Reset Requests <span class="pill-badge" style="font-size:11px;font-weight:800;background:rgba(255,255,255,0.25);color:#fff;padding:2px 7px;border-radius:10px;margin-left:4px">$countPending</span>
    </a>
    <a href="admin-users.php" class="tab-pill">
      <i class="fas fa-users"></i> Users Directory
    </a>
  </div>
</div>

<!-- Summary Cards (Separating Completed vs Non-Completed) -->
<div class="summary-cards" style="grid-template-columns:repeat(auto-fit,minmax(190px,1fr));margin-bottom:20px;gap:14px">
  <div class="sum-card" style="border-left:4px solid var(--maroon)">
    <div class="sum-icon" style="background:rgba(85,0,0,0.1);color:var(--maroon)"><i class="fas fa-hourglass-half"></i></div>
    <div>
      <div class="sum-num">$countActive</div>
      <div class="sum-label">Non-Completed (Active)</div>
    </div>
  </div>

  <div class="sum-card" style="border-left:4px solid #16a34a">
    <div class="sum-icon" style="background:rgba(22,163,74,0.1);color:#16a34a"><i class="fas fa-check-double"></i></div>
    <div>
      <div class="sum-num">$countCompleted</div>
      <div class="sum-label">Completed Requests</div>
    </div>
  </div>

  <div class="sum-card" style="border-left:4px solid #d97706">
    <div class="sum-icon" style="background:rgba(217,119,6,0.1);color:#d97706"><i class="fas fa-clock"></i></div>
    <div>
      <div class="sum-num">$countPending</div>
      <div class="sum-label">Pending Review</div>
    </div>
  </div>

  <div class="sum-card" style="border-left:4px solid #2563eb">
    <div class="sum-icon" style="background:rgba(37,99,235,0.1);color:#2563eb"><i class="fas fa-key"></i></div>
    <div>
      <div class="sum-num">$countApproved</div>
      <div class="sum-label">Approved &amp; Issued</div>
    </div>
  </div>

  <div class="sum-card" style="border-left:4px solid #64748b">
    <div class="sum-icon" style="background:rgba(100,116,139,0.1);color:#64748b"><i class="fas fa-list-check"></i></div>
    <div>
      <div class="sum-num">$countTotal</div>
      <div class="sum-label">Total Student Records</div>
    </div>
  </div>
</div>

<div class="content-card">
  <div class="card-header">
    <div>
      <h3 style="display:flex;align-items:center;gap:8px">
        <i class="fas fa-key" style="color:var(--maroon)"></i> Student Password Reset Requests
        <span style="font-size:13px;font-weight:600;color:var(--text-3)">($countPending pending review)</span>
      </h3>
      <span style="font-size:13px;color:var(--text-3)">Official school record of student password reset requests verified through security test questions. IT Administrators are authorized to issue new temporary passwords.</span>
    </div>
  </div>

  <!-- Dedicated Status Filter Tabs (Separating Completed vs Non-Completed) -->
  <div class="reset-filter-tabs">
    <a href="admin-reset-requests.php?status=active" class="$clsActive">
      <i class="fas fa-hourglass-half"></i> Non-Completed Requests <span class="badge-count">$countActive</span>
    </a>
    <a href="admin-reset-requests.php?status=completed" class="$clsCompleted">
      <i class="fas fa-check-double"></i> Completed Requests <span class="badge-count">$countCompleted</span>
    </a>
    <a href="admin-reset-requests.php?status=pending" class="$clsPending">
      <i class="fas fa-clock"></i> Pending Review Only <span class="badge-count">$countPending</span>
    </a>
    <a href="admin-reset-requests.php?status=approved" class="$clsApproved">
      <i class="fas fa-key"></i> Approved Only <span class="badge-count">$countApproved</span>
    </a>
    <a href="admin-reset-requests.php?status=all" class="$clsAll">
      <i class="fas fa-list-check"></i> All Records <span class="badge-count">$countTotal</span>
    </a>
  </div>

  <div style="background:var(--maroon-pale);border:1px solid var(--maroon-tint);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:var(--maroon);display:flex;align-items:center;gap:10px">
    <i class="fas fa-shield-halved" style="font-size:16px;flex-shrink:0"></i>
    <span>Students verified their personal security recovery questions before lodging these requests. Approved temporary passwords unlock the student account immediately, and completed requests are archived above.</span>
  </div>

  <!-- Filter & Search Bar -->
  <div class="filter-bar" style="margin-bottom:18px;flex-wrap:wrap">
    <div style="display:flex;gap:8px;flex:1;min-width:240px">
      <div class="input-wrap" style="flex:1">
        <i class="fas fa-search"></i>
        <input type="text" id="resetSearchQ" value="$sqVal" placeholder="Search by student name, email, LRN / ID, grade & section…" onkeydown="if(event.key==='Enter') doResetSearch()"/>
      </div>
      <button class="btn-sm-primary" onclick="doResetSearch()"><i class="fas fa-search"></i> Search</button>
    </div>

    <select class="status-select" onchange="applyResetFilter('status', this.value)">
      <option value="active"    $stActive>Non-Completed (Pending &amp; Approved)</option>
      <option value="completed" $stCompleted>Completed Requests (Resolved)</option>
      <option value="pending"   $stPending>Pending Review Only</option>
      <option value="approved"  $stApprv>Approved &amp; Issued Only</option>
      <option value="rejected"  $stRej>Rejected / Dismissed</option>
      <option value="all"       $stAll>All Request Records</option>
    </select>

    <button class="btn-sm-outline" onclick="window.location='admin-reset-requests.php'"><i class="fas fa-times"></i> Reset</button>
  </div>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Student / User</th>
          <th>Contact Email (For Record)</th>
          <th>LRN / ID #</th>
          <th>Grade &amp; Section</th>
          <th>Security Test</th>
          <th>Requested Date</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        $rows
      </tbody>
    </table>
  </div>
</div>

<!-- ============================================================== -->
<!-- PROCESS RESET PASSWORD MODAL (COUNSELOR ISSUANCE)              -->
<!-- ============================================================== -->
<div class="modal-overlay hidden" id="processResetModal" onclick="if(event.target===this) closeResetModal()">
  <div class="modal-box" style="max-width:540px;width:95%;padding:28px">
    <button type="button" class="modal-close" onclick="closeResetModal()"><i class="fas fa-times"></i></button>

    <div style="margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--border)">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
        <div style="width:40px;height:40px;border-radius:10px;background:#dcfce7;color:#16a34a;display:flex;align-items:center;justify-content:center;font-size:18px">
          <i class="fas fa-key"></i>
        </div>
        <div>
          <h3 style="font-size:18px;font-weight:800;color:var(--text);margin:0">Approve &amp; Issue Temporary Password</h3>
          <span style="font-size:12.5px;color:var(--text-3)">Official School IT Administrator Password Reset</span>
        </div>
      </div>
    </div>

    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:12px 14px;margin-bottom:18px">
      <div style="display:flex;justify-content:space-between;margin-bottom:6px">
        <span style="font-size:12px;color:var(--text-3)">Student Name:</span>
        <strong id="mStudentName" style="font-size:13px;color:var(--text)">—</strong>
      </div>
      <div style="display:flex;justify-content:space-between;margin-bottom:6px">
        <span style="font-size:12px;color:var(--text-3)">Email Address:</span>
        <strong id="mStudentEmail" style="font-size:13px;color:var(--text)">—</strong>
      </div>
      <div style="display:flex;justify-content:space-between">
        <span style="font-size:12px;color:var(--text-3)">LRN / Student ID:</span>
        <strong id="mStudentID" style="font-size:13px;color:var(--maroon)">—</strong>
      </div>
    </div>

    <div class="form-group" style="margin-bottom:14px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
        <label style="font-size:13px;font-weight:700;margin:0">New Temporary Password <span style="color:#dc2626">*</span></label>
        <button type="button" class="btn-sm-outline" onclick="generateSecurePassword()" style="font-size:11.5px;padding:3px 9px;color:var(--maroon);border-color:var(--maroon-tint)">
          <i class="fas fa-bolt"></i> Generate Secure
        </button>
      </div>
      <div class="input-wrap">
        <i class="fas fa-lock"></i>
        <input type="text" id="mNewPassword" class="form-control" placeholder="Enter new password (at least 6 chars)" style="font-family:monospace;font-size:14px;font-weight:700;letter-spacing:1px" required/>
      </div>
    </div>

    <div class="form-group" style="margin-bottom:20px">
      <label style="font-size:13px;font-weight:700;margin-bottom:6px;display:block">IT Administrator Notes / Issuance Remarks <span style="font-size:11px;color:var(--text-3);font-weight:normal">(Optional)</span></label>
      <textarea id="mAdminNotes" rows="2" class="form-control" style="width:100%;padding:10px;border-radius:8px;border:1.5px solid var(--border);font-size:13px" placeholder="e.g. Identity verified in IT office; temporary password issued in person..."></textarea>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:12px">
      <button type="button" class="btn-secondary" onclick="closeResetModal()">Cancel</button>
      <button type="button" class="btn-primary" id="btnConfirmReset" style="background:#16a34a;display:inline-flex;align-items:center;gap:6px;font-weight:700;padding:10px 18px" onclick="submitApproveReset()">
        <i class="fas fa-check-circle"></i> Save &amp; Issue Password
      </button>
    </div>
  </div>
</div>

<!-- ============================================================== -->
<!-- VIEW APPROVED / COMPLETED CREDENTIALS MODAL                    -->
<!-- ============================================================== -->
<div class="modal-overlay hidden" id="viewApprovedModal" onclick="if(event.target===this) closeViewApprovedModal()">
  <div class="modal-box" style="max-width:500px;width:95%;padding:28px">
    <button type="button" class="modal-close" onclick="closeViewApprovedModal()"><i class="fas fa-times"></i></button>

    <div style="text-align:center;margin-bottom:18px">
      <div id="vModalIconWrap" style="width:56px;height:56px;border-radius:50%;background:#dcfce7;color:#166534;display:flex;align-items:center;justify-content:center;font-size:24px;margin:0 auto 12px auto">
        <i id="vModalIcon" class="fas fa-circle-check"></i>
      </div>
      <h3 id="vModalTitle" style="font-size:18px;font-weight:800;color:var(--text);margin-bottom:4px">Password Reset Record</h3>
      <span style="font-size:12.5px;color:var(--text-3)">Official Guidance Record</span>
    </div>

    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:16px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid var(--border)">
        <span style="font-size:12px;color:var(--text-3)">Request Status</span>
        <div id="vStatusBadge"></div>
      </div>
      <div style="margin-bottom:8px">
        <span style="font-size:11.5px;color:var(--text-3);display:block">Student Name</span>
        <strong id="vStudentName" style="font-size:13.5px;color:var(--text)">—</strong>
      </div>
      <div style="margin-bottom:8px">
        <span style="font-size:11.5px;color:var(--text-3);display:block">Contact Email</span>
        <span id="vStudentEmail" style="font-size:13px;color:var(--text)">—</span>
      </div>
      <div id="vTempPasswordWrap" style="margin-bottom:12px">
        <span style="font-size:11.5px;color:var(--text-3);display:block">Temporary Password Issued</span>
        <div style="display:flex;align-items:center;gap:8px;margin-top:4px">
          <input type="text" id="vTempPassword" readonly style="flex:1;background:#fff;border:1.5px solid #86efac;padding:8px 12px;border-radius:8px;font-family:monospace;font-size:14px;font-weight:800;color:#166534"/>
          <button type="button" class="btn-sm-primary" onclick="copyTempPassword()" style="font-size:12px;padding:8px 12px;background:#16a34a">
            <i class="fas fa-copy"></i> Copy
          </button>
        </div>
      </div>
      <div style="margin-bottom:8px">
        <span style="font-size:11.5px;color:var(--text-3);display:block">Reviewed &amp; Processed By</span>
        <span id="vReviewer" style="font-size:12.5px;color:var(--text-2);font-weight:600">—</span>
      </div>
      <div id="vNotesWrap">
        <span style="font-size:11.5px;color:var(--text-3);display:block">IT Administrator Notes / Remarks</span>
        <div id="vAdminNotes" style="font-size:12px;color:var(--text-2);background:var(--bg);padding:8px 10px;border-radius:6px;border:1px solid var(--border);white-space:pre-wrap;margin-top:4px"></div>
      </div>
    </div>

    <button type="button" class="btn-secondary" style="width:100%" onclick="closeViewApprovedModal()">Close Record</button>
  </div>
</div>

<script>
// Local Toast fallback
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

let _currentReqData = null;

function doResetSearch() {
  const q = document.getElementById('resetSearchQ').value.trim();
  applyResetFilter('q', q);
}

function applyResetFilter(key, val) {
  const url = new URL(window.location.href);
  if (val) url.searchParams.set(key, val);
  else url.searchParams.delete(key);
  window.location.href = url.toString();
}

function generateSecurePassword() {
  const prefix = 'GNHS-' + String(Math.floor(1000 + Math.random() * 9000));
  const chars = '!@#$';
  const symbol = chars[Math.floor(Math.random() * chars.length)];
  const pwd = prefix + symbol;
  document.getElementById('mNewPassword').value = pwd;
}

function openResetModal(req) {
  _currentReqData = req;
  document.getElementById('mStudentName').textContent = req.full_name;
  document.getElementById('mStudentEmail').textContent = req.email;
  document.getElementById('mStudentID').textContent = req.id_number || '—';
  document.getElementById('mAdminNotes').value = '';
  generateSecurePassword();
  const m = document.getElementById('processResetModal');
  if (m) {
    m.classList.remove('hidden');
    m.style.display = 'flex';
  }
}

function closeResetModal() {
  const m = document.getElementById('processResetModal');
  if (m) {
    m.classList.add('hidden');
    m.style.display = 'none';
  }
  _currentReqData = null;
}

async function submitApproveReset() {
  if (!_currentReqData) return;
  const newPassword = document.getElementById('mNewPassword').value.trim();
  const notes = document.getElementById('mAdminNotes').value.trim();

  if (!newPassword || newPassword.length < 6) {
    showToast('Please provide a password of at least 6 characters.', 'error');
    document.getElementById('mNewPassword').focus();
    return;
  }

  const btn = document.getElementById('btnConfirmReset');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Issuing Password…';

  try {
    const apiUrl = window.location.pathname.includes('/gnhs-guidance/gnhs-guidance/')
      ? '/gnhs-guidance/gnhs-guidance/api/admin_process_reset_request.php'
      : '/gnhs-guidance/api/admin_process_reset_request.php';

    const res = await fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        request_id: _currentReqData.id,
        action: 'approve',
        new_password: newPassword,
        admin_notes: notes
      })
    });
    const data = await res.json();
    if (data.success) {
      closeResetModal();
      showToast(data.message || 'Password successfully reset and issued!', 'success');
      setTimeout(() => location.reload(), 1400);
    } else {
      showToast(data.message || 'Failed to process request.', 'error');
    }
  } catch (err) {
    showToast('Network error while processing reset request.', 'error');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-check-circle"></i> Save &amp; Issue Password';
  }
}

async function markRequestCompleted(requestId) {
  if (!confirm('Mark this password reset request as completed?')) return;

  try {
    const apiUrl = window.location.pathname.includes('/gnhs-guidance/gnhs-guidance/')
      ? '/gnhs-guidance/gnhs-guidance/api/admin_process_reset_request.php'
      : '/gnhs-guidance/api/admin_process_reset_request.php';

    const res = await fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        request_id: requestId,
        action: 'complete',
        admin_notes: 'Fulfillment completed and verified by IT Administrator.'
      })
    });
    const data = await res.json();
    if (data.success) {
      showToast('Password reset request marked as completed!', 'success');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(data.message || 'Failed to update request.', 'error');
    }
  } catch (err) {
    showToast('Network error occurred.', 'error');
  }
}

async function openRejectModal(requestId, studentName) {
  const reason = prompt('Please specify reason for rejecting password reset request for ' + studentName + ':');
  if (reason === null) return;

  try {
    const apiUrl = window.location.pathname.includes('/gnhs-guidance/gnhs-guidance/')
      ? '/gnhs-guidance/gnhs-guidance/api/admin_process_reset_request.php'
      : '/gnhs-guidance/api/admin_process_reset_request.php';

    const res = await fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        request_id: requestId,
        action: 'reject',
        admin_notes: reason || 'Rejected by IT Administrator'
      })
    });
    const data = await res.json();
    if (data.success) {
      showToast('Password reset request rejected.', 'success');
      setTimeout(() => location.reload(), 1200);
    } else {
      showToast(data.message || 'Failed to reject request.', 'error');
    }
  } catch (err) {
    showToast('Network error occurred.', 'error');
  }
}

function openViewApprovedModal(req) {
  document.getElementById('vStudentName').textContent = req.full_name;
  document.getElementById('vStudentEmail').textContent = req.email;
  document.getElementById('vTempPassword').value = req.temp_password || '(Password set by School IT)';
  document.getElementById('vReviewer').textContent = (req.reviewer_name || 'IT Admin') + ' at ' + (req.reviewed_at || '—');
  
  const statusWrap = document.getElementById('vStatusBadge');
  const isCompleted = req.status === 'completed';
  if (statusWrap) {
    if (isCompleted) {
      statusWrap.innerHTML = "<span class='status-badge' style='background:#dcfce7;color:#166534;border:1px solid #86efac;font-weight:700'><i class='fas fa-check-double'></i> Completed</span>";
    } else {
      statusWrap.innerHTML = "<span class='status-badge' style='background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe'><i class='fas fa-key'></i> Approved &amp; Issued</span>";
    }
  }
  
  const iconWrap = document.getElementById('vModalIconWrap');
  const iconEl = document.getElementById('vModalIcon');
  const titleEl = document.getElementById('vModalTitle');
  if (iconWrap && iconEl && titleEl) {
    if (isCompleted) {
      iconWrap.style.background = '#dcfce7';
      iconWrap.style.color = '#166534';
      iconEl.className = 'fas fa-check-double';
      titleEl.textContent = 'Completed Reset Request Record';
    } else {
      iconWrap.style.background = '#eff6ff';
      iconWrap.style.color = '#1d4ed8';
      iconEl.className = 'fas fa-key';
      titleEl.textContent = 'Issued Temporary Credentials';
    }
  }

  const notesEl = document.getElementById('vAdminNotes');
  if (notesEl) {
    notesEl.textContent = req.admin_notes || 'No counselor remarks entered.';
  }

  const m = document.getElementById('viewApprovedModal');
  if (m) {
    m.classList.remove('hidden');
    m.style.display = 'flex';
  }
}

function closeViewApprovedModal() {
  const m = document.getElementById('viewApprovedModal');
  if (m) {
    m.classList.add('hidden');
    m.style.display = 'none';
  }
}

function copyTempPassword() {
  const input = document.getElementById('vTempPassword');
  input.select();
  navigator.clipboard.writeText(input.value);
  showToast('Temporary password copied to clipboard!', 'success');
}
</script>
HTML;

renderLayout($user, 'Password Reset Requests', 'reset_requests', $content);
