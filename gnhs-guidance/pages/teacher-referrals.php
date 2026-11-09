<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('teacher');
$db   = getDB();
$uid  = $user['id'];

$filterStatus  = $_GET['status']  ?? '';
$filterUrgency = $_GET['urgency'] ?? '';
$search        = trim($_GET['q']  ?? '');

if ($filterStatus) {
    $where = "WHERE r.teacher_id=$uid AND r.status='".addslashes($filterStatus)."'";
} else {
    $where = "WHERE r.teacher_id=$uid AND r.status NOT IN ('resolved','closed')";
}

if ($filterUrgency) $where .= " AND r.urgency='".addslashes($filterUrgency)."'";

if ($search) {
    $s = addslashes($search);
    $where .= " AND (
        r.ref_number LIKE '%$s%'
        OR r.student_name LIKE '%$s%'
        OR r.grade_section LIKE '%$s%'
        OR r.observations LIKE '%$s%'
    )";
}

$refs = $db->query("SELECT r.* FROM referrals r $where ORDER BY r.submitted_at DESC")->fetchAll();

$archivedCount = (int)$db->query("SELECT COUNT(*) FROM referrals WHERE teacher_id=$uid AND status IN ('resolved','closed')")->fetchColumn();
$activeCount   = (int)$db->query("SELECT COUNT(*) FROM referrals WHERE teacher_id=$uid AND status NOT IN ('resolved','closed')")->fetchColumn();

function urgencyBadge(string $u): string {
    $map = ['urgent'=>'priority-high','moderate'=>'priority-medium','low'=>'priority-low'];
    return "<span class='priority-badge ".($map[$u]??'priority-low')."'><i class='fas fa-circle'></i>".ucfirst($u)."</span>";
}
function statusBadge(string $s): string {
    $map=['pending'=>'status-pending','under_review'=>'status-under_review','ongoing'=>'status-ongoing','resolved'=>'status-resolved','closed'=>'status-resolved'];
    return "<span class='status-badge ".($map[$s]??'status-pending')."'>".str_replace('_',' ',ucfirst($s))."</span>";
}

$rows = '';
foreach ($refs as $r) {
    $type = ucfirst(str_replace('_',' ',$r['concern_type']));
    $date = date('M d, Y', strtotime($r['submitted_at']));
    $refData = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
    $rows .= "<tr>
        <td><strong style='color:var(--maroon)'>{$r['ref_number']}</strong></td>
        <td>".htmlspecialchars($r['student_name'])."</td>
        <td>".htmlspecialchars($r['grade_section'])."</td>
        <td>$type</td>
        <td>".urgencyBadge($r['urgency'])."</td>
        <td>".statusBadge($r['status'])."</td>
        <td style='color:var(--text-3);font-size:13px'>$date</td>
        <td><button class='btn-sm-outline' onclick='openRefModal(this)' data-ref='$refData'><i class='fas fa-eye'></i> View</button></td>
    </tr>";
}
if (!$rows) $rows = "<tr><td colspan='8' style='text-align:center;color:var(--text-3);padding:40px'>".($search ? "No referrals matching \"".htmlspecialchars($search)."\"." : "No active referrals found. <a href='teacher-referral.php' style='color:var(--maroon);font-weight:700'>Submit one →</a>")."</td></tr>";

$sqVal = htmlspecialchars($search);
$sSel1 = $filterStatus==='pending'      ? 'selected' : '';
$sSel2 = $filterStatus==='under_review' ? 'selected' : '';
$sSel3 = $filterStatus==='ongoing'      ? 'selected' : '';
$sSel4 = $filterStatus==='resolved'     ? 'selected' : '';
$sSel5 = $filterStatus==='closed'       ? 'selected' : '';
$uSel1 = $filterUrgency==='urgent'      ? 'selected' : '';
$uSel2 = $filterUrgency==='moderate'    ? 'selected' : '';
$uSel3 = $filterUrgency==='low'         ? 'selected' : '';

$content = <<<HTML
<div class="content-card">
  <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div>
      <h3 style="margin:0;display:flex;align-items:center;gap:10px;">
        <i class="fas fa-list-alt" style="color:var(--maroon)"></i> My Active Referrals
        <span style="font-size:13px;font-weight:600;color:var(--text-3);">($activeCount active)</span>
      </h3>
    </div>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <a href="teacher-referrals-archive.php" class="btn-sm-outline" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fas fa-archive"></i> Referrals Archive ($archivedCount)
      </a>
      <a href="teacher-referral.php" class="btn-sm-primary" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fas fa-plus"></i> New Referral
      </a>
    </div>
  </div>

  <div class="filter-bar" style="margin-bottom:18px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
    <div style="display:flex;gap:8px;flex:1;min-width:240px">
      <div class="input-wrap" style="flex:1">
        <i class="fas fa-search"></i>
        <input type="text" id="searchQ" value="$sqVal" placeholder="Search by student, ref #, or notes…"
          onkeydown="if(event.key==='Enter')doSearch()"/>
      </div>
      <button class="btn-sm-primary" onclick="doSearch()"><i class="fas fa-search"></i> Search</button>
    </div>

    <select class="status-select" onchange="applyFilter('status',this.value)">
      <option value="">Active Only (Pending, Review, Ongoing)</option>
      <option value="pending"      $sSel1>Pending</option>
      <option value="under_review" $sSel2>Under Review</option>
      <option value="ongoing"      $sSel3>Ongoing</option>
      <option value="resolved"     $sSel4>Resolved</option>
      <option value="closed"       $sSel5>Closed</option>
    </select>

    <select class="status-select" onchange="applyFilter('urgency',this.value)">
      <option value="">All Urgency</option>
      <option value="urgent"   $uSel1>Urgent</option>
      <option value="moderate" $uSel2>Moderate</option>
      <option value="low"      $uSel3>Low</option>
    </select>

    <button class="btn-sm-outline" onclick="window.location='teacher-referrals.php'"><i class="fas fa-times"></i> Clear</button>
  </div>

  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Ref #</th><th>Student Name</th><th>Grade/Section</th><th>Concern</th><th>Urgency</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
      <tbody>$rows</tbody>
    </table>
  </div>
</div>

<!-- Referral Detail Modal -->
<div class="modal-overlay hidden" id="refModal" onclick="if(event.target.id==='refModal')this.classList.add('hidden')">
  <div class="modal-box" style="max-width:1000px; width:95%">
    <button class="modal-close" onclick="document.getElementById('refModal').classList.add('hidden')"><i class="fas fa-times"></i></button>
    <h2 class="modal-title"><i class="fas fa-exchange-alt" style="color:var(--maroon);margin-right:8px"></i>Referral Detail</h2>
    <div id="refModalBody"></div>
  </div>
</div>
HTML;

$content .= '
<script>
function doSearch() {
  applyFilter("q", document.getElementById("searchQ").value.trim());
}

function applyFilter(key, val) {
  const url = new URL(window.location);
  if (val) url.searchParams.set(key, val); else url.searchParams.delete(key);
  window.location = url;
}

function openRefModal(btn) {
  const ref = JSON.parse(btn.getAttribute("data-ref"));
  const urgMap   = {urgent:"priority-high", moderate:"priority-medium", low:"priority-low"};
  const statMap  = {pending:"status-pending", under_review:"status-under_review", ongoing:"status-ongoing", resolved:"status-resolved", closed:"status-resolved"};
  const statLabel = {pending:"Pending", under_review:"Under Review", ongoing:"Ongoing", resolved:"Resolved", closed:"Closed"};
  const typeLabel = ref.concern_type.replace(/_/g," ").replace(/\b\w/g, l=>l.toUpperCase());

  document.getElementById("refModalBody").innerHTML = `
    <div class="review-header-strip" style="display:flex;flex-wrap:wrap;gap:15px;background:var(--bg2);padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid var(--border)">
      <div style="flex:1;min-width:120px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Referral ID</label><strong style="color:var(--maroon);font-size:16px">#${ref.ref_number}</strong></div>
      <div style="flex:2;min-width:150px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Student</label><span style="font-size:15px;font-weight:600">${ref.student_name}</span></div>
      <div style="flex:1.5;min-width:130px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Type</label><span class="status-badge status-ongoing">${typeLabel}</span></div>
      <div style="flex:1.5;min-width:130px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Grade/Sec</label><span style="font-size:14px">${ref.grade_section}</span></div>
      <div style="flex:1.5;min-width:130px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Submitted</label><span style="font-size:13px;color:var(--text-2)">${ref.submitted_at}</span></div>
    </div>
    
    <div style="display:grid;grid-template-columns: 1.2fr 1fr; gap:25px; align-items:start">
      <div>
        ${ref.ai_summary ? `<div class="ai-box" style="margin-bottom:20px;border-left:4px solid var(--blue);background:rgba(37,99,235,0.05)"><div class="ai-box-label" style="color:var(--blue);font-size:14px;margin-bottom:8px"><i class="fas fa-magic"></i> AI Summary</div><div class="ai-box-text" style="font-size:15px;line-height:1.6">${ref.ai_summary}</div></div>` : \'\'}
        <div style="margin-bottom:14px;background:var(--bg2);padding:20px;border-radius:8px;border:1px solid var(--border)">
          <div style="font-size:13px;font-weight:800;color:var(--text-2);margin-bottom:12px;text-transform:uppercase;letter-spacing:1px"><i class="fas fa-quote-left" style="color:var(--maroon)"></i> My Observations</div>
          <div class="concern-text" style="font-size:15px;line-height:1.7;color:var(--text);background:transparent;padding:0;border:none;white-space:pre-wrap;">${ref.observations}</div>
        </div>
      </div>
      
      <div style="background:var(--bg2);padding:20px;border-radius:8px;border:1px solid var(--border)">
        <h3 style="margin-bottom:20px;font-size:16px;color:var(--text);border-bottom:1px solid var(--border);padding-bottom:10px"><i class="fas fa-info-circle" style="color:var(--maroon)"></i> Referral Info</h3>
        <div class="form-group" style="margin-bottom:15px">
          <label style="display:block;margin-bottom:8px">Urgency</label>
          <span class="priority-badge ${urgMap[ref.urgency]||\'priority-low\'}"><i class="fas fa-circle"></i> ${ref.urgency.charAt(0).toUpperCase()+ref.urgency.slice(1)}</span>
        </div>
        <div class="form-group" style="margin-bottom:20px">
          <label style="display:block;margin-bottom:8px">Current Status</label>
          <span class="status-badge ${statMap[ref.status]||\'status-pending\'}">${statLabel[ref.status]}</span>
        </div>
        
        <div class="review-actions" style="margin-top:20px;border-top:1px solid var(--border);padding-top:20px;display:flex;gap:10px;">
          <button class="btn-secondary" style="flex:1" onclick="document.getElementById(\'refModal\').classList.add(\'hidden\')"><i class="fas fa-times"></i> Close</button>
          <button class="btn-primary" style="flex:1;background:var(--blue)" onclick="window.location.href=\'teacher-referrals-chat.php?ref_id=${ref.id}\'">
            <i class="fas fa-comment-dots"></i> Go to Chat
          </button>
        </div>
      </div>
    </div>`;

  document.getElementById("refModal").classList.remove("hidden");
}
</script>
';

renderLayout($user, 'My Referrals', 'referrals', $content);
