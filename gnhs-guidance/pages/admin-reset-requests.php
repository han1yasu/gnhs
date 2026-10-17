<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin');
$db   = getDB();

$filterStatus = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');

$where = "WHERE 1=1";
if ($filterStatus) {
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
    CONCAT(COALESCE(c.first_name,'Guidance'), ' ', COALESCE(c.last_name,'Counselor')) AS reviewer_name
    FROM password_reset_requests prr
    LEFT JOIN users u ON prr.user_id = u.id
    LEFT JOIN users c ON prr.reviewed_by = c.id
    $where
    ORDER BY FIELD(prr.status, 'pending', 'approved', 'rejected'), prr.created_at DESC")->fetchAll();

// Metric counts
$countPending  = (int)$db->query("SELECT COUNT(*) FROM password_reset_requests WHERE status='pending'")->fetchColumn();
$countApproved = (int)$db->query("SELECT COUNT(*) FROM password_reset_requests WHERE status='approved'")->fetchColumn();
$countRejected = (int)$db->query("SELECT COUNT(*) FROM password_reset_requests WHERE status='rejected'")->fetchColumn();
$countTotal    = (int)$db->query("SELECT COUNT(*) FROM password_reset_requests")->fetchColumn();
$countTotp     = (int)$db->query("SELECT COUNT(*) FROM users WHERE totp_reset_requested = 1")->fetchColumn();

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
        'pending'  => "<span class='status-badge status-pending'><i class='fas fa-clock'></i> Pending</span>",
        'approved' => "<span class='status-badge status-resolved'><i class='fas fa-check-circle'></i> Approved & Reset</span>",
        'rejected' => "<span class='status-badge status-danger' style='background:#fee2e2;color:#991b1b;border:1px solid #fecaca'><i class='fas fa-times-circle'></i> Rejected</span>"
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
            <button type='button' class='btn-sm-outline' onclick='openViewApprovedModal({$rJson})' style='font-size:12px;padding:6px 11px;display:inline-flex;align-items:center;gap:6px'>
                <i class='fas fa-eye'></i> View Credentials
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
    $rows = "<tr><td colspan='8' style='text-align:center;color:var(--text-3);padding:45px'>" .
        ($search ? "No password reset requests found matching \"<strong>" . htmlspecialchars($search) . "</strong>\"." : "No password reset requests recorded.") . "</td></tr>";
}

$sqVal     = htmlspecialchars($search);
$stAll     = $filterStatus === '' ? 'selected' : '';
$stPending = $filterStatus === 'pending' ? 'selected' : '';
$stApprv   = $filterStatus === 'approved' ? 'selected' : '';
$stRej     = $filterStatus === 'rejected' ? 'selected' : '';

$content = <<<HTML
<!-- Top Tab Bar: 2FA Requests vs Password Reset Requests -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px">
  <div class="totp-tab-bar" style="background:var(--bg2);padding:4px;border-radius:12px;border:1px solid var(--border);display:inline-flex">
    <a href="admin-2fa-requests.php" class="tab-pill" style="text-decoration:none">
      <i class="fas fa-shield-alt"></i> 2FA Reset Requests <span style="font-size:11px;opacity:0.8">($countTotp)</span>
    </a>
    <a href="admin-reset-requests.php" class="tab-pill active" style="text-decoration:none">
      <i class="fas fa-key"></i> Password Reset Requests <span style="font-size:11px;font-weight:800;background:var(--maroon);color:#fff;padding:1px 6px;border-radius:10px;margin-left:4px">$countPending</span>
    </a>
    <a href="admin-users.php" class="tab-pill" style="text-decoration:none">
      <i class="fas fa-users"></i> Users Directory
    </a>
  </div>
</div>

<!-- Summary Cards -->
<div class="summary-cards" style="grid-template-columns:repeat(auto-fit,minmax(210px,1fr));margin-bottom:20px">
  <div class="sum-card" style="border-left:4px solid #d97706">
    <div class="sum-icon" style="background:rgba(217,119,6,0.1);color:#d97706"><i class="fas fa-clock"></i></div>
    <div>
      <div class="sum-num">$countPending</div>
      <div class="sum-label">Pending Requests</div>
    </div>
  </div>

  <div class="sum-card" style="border-left:4px solid #16a34a">
    <div class="sum-icon" style="background:rgba(22,163,74,0.1);color:#16a34a"><i class="fas fa-check-circle"></i></div>
    <div>
      <div class="sum-num">$countApproved</div>
      <div class="sum-label">Approved &amp; Reset</div>
    </div>
  </div>

  <div class="sum-card" style="border-left:4px solid #dc2626">
    <div class="sum-icon" style="background:rgba(220,38,38,0.1);color:#dc2626"><i class="fas fa-times-circle"></i></div>
    <div>
      <div class="sum-num">$countRejected</div>
      <div class="sum-label">Rejected / Dismissed</div>
    </div>
  </div>

  <div class="sum-card" style="border-left:4px solid var(--maroon)">
    <div class="sum-icon" style="background:rgba(85,0,0,0.1);color:var(--maroon)"><i class="fas fa-user-graduate"></i></div>
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
      <span style="font-size:13px;color:var(--text-3)">Official school record of student password reset requests verified through security test questions. Counselors are authorized to issue new temporary passwords.</span>
    </div>
  </div>

  <div style="background:var(--maroon-pale);border:1px solid var(--maroon-tint);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:var(--maroon);display:flex;align-items:center;gap:10px">
    <i class="fas fa-shield-halved" style="font-size:16px;flex-shrink:0"></i>
    <span>Students verified their personal security recovery questions before lodging these requests. Approved temporary passwords unlock the student account immediately.</span>
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
      <option value="" $stAll>All Request Statuses</option>
      <option value="pending" $stPending>Pending Review</option>
      <option value="approved" $stApprv>Approved &amp; Issued</option>
      <option value="rejected" $stRej>Rejected</option>
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
          <span style="font-size:12.5px;color:var(--text-3)">Official Guidance Counselor Password Reset</span>
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
      <label style="font-size:13px;font-weight:700;margin-bottom:6px;display:block">Counselor Notes / Issuance Remarks <span style="font-size:11px;color:var(--text-3);font-weight:normal">(Optional)</span></label>
      <textarea id="mAdminNotes" rows="2" class="form-control" style="width:100%;padding:10px;border-radius:8px;border:1.5px solid var(--border);font-size:13px" placeholder="e.g. Identity verified in office; temporary password issued in person..."></textarea>
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
<!-- VIEW APPROVED CREDENTIALS MODAL                                -->
<!-- ============================================================== -->
<div class="modal-overlay hidden" id="viewApprovedModal" onclick="if(event.target===this) closeViewApprovedModal()">
  <div class="modal-box" style="max-width:480px;width:95%;padding:28px">
    <button type="button" class="modal-close" onclick="closeViewApprovedModal()"><i class="fas fa-times"></i></button>

    <div style="text-align:center;margin-bottom:18px">
      <div style="width:56px;height:56px;border-radius:50%;background:#dcfce7;color:#16a34a;display:flex;align-items:center;justify-content:center;font-size:24px;margin:0 auto 12px auto">
        <i class="fas fa-circle-check"></i>
      </div>
      <h3 style="font-size:18px;font-weight:800;color:var(--text);margin-bottom:4px">Issued Temporary Credentials</h3>
      <span style="font-size:12.5px;color:var(--text-3)">Official Guidance Record</span>
    </div>

    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:16px">
      <div style="margin-bottom:8px">
        <span style="font-size:11.5px;color:var(--text-3);display:block">Student Name</span>
        <strong id="vStudentName" style="font-size:13.5px;color:var(--text)">—</strong>
      </div>
      <div style="margin-bottom:8px">
        <span style="font-size:11.5px;color:var(--text-3);display:block">Contact Email</span>
        <span id="vStudentEmail" style="font-size:13px;color:var(--text)">—</span>
      </div>
      <div style="margin-bottom:12px">
        <span style="font-size:11.5px;color:var(--text-3);display:block">Temporary Password Issued</span>
        <div style="display:flex;align-items:center;gap:8px;margin-top:4px">
          <input type="text" id="vTempPassword" readonly style="flex:1;background:#fff;border:1.5px solid #86efac;padding:8px 12px;border-radius:8px;font-family:monospace;font-size:14px;font-weight:800;color:#166534"/>
          <button type="button" class="btn-sm-primary" onclick="copyTempPassword()" style="font-size:12px;padding:8px 12px;background:#16a34a">
            <i class="fas fa-copy"></i> Copy
          </button>
        </div>
      </div>
      <div>
        <span style="font-size:11.5px;color:var(--text-3);display:block">Reviewed &amp; Issued By</span>
        <span id="vReviewer" style="font-size:12.5px;color:var(--text-2);font-weight:600">—</span>
      </div>
    </div>

    <button type="button" class="btn-secondary" style="width:100%" onclick="closeViewApprovedModal()">Close Record</button>
  </div>
</div>

<script>
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
  document.getElementById('processResetModal').classList.remove('hidden');
}

function closeResetModal() {
  document.getElementById('processResetModal').classList.add('hidden');
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
        admin_notes: reason || 'Rejected by Counselor'
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
  document.getElementById('vTempPassword').value = req.temp_password || '(Password previously set)';
  document.getElementById('vReviewer').textContent = (req.reviewer_name || 'Counselor') + ' at ' + (req.reviewed_at || '—');
  document.getElementById('viewApprovedModal').classList.remove('hidden');
}

function closeViewApprovedModal() {
  document.getElementById('viewApprovedModal').classList.add('hidden');
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
