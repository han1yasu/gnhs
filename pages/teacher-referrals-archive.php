<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('teacher');
$db   = getDB();
$uid  = $user['id'];

$search        = trim($_GET['q']       ?? '');
$filterType    = trim($_GET['type']    ?? '');
$filterUrgency = trim($_GET['urgency'] ?? '');

$where = "WHERE r.teacher_id=$uid AND r.status IN ('resolved','closed')";
if ($filterType)    $where .= " AND r.concern_type='".addslashes($filterType)."'";
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

function urgencyBadge(string $u): string {
    $map = ['urgent'=>'priority-high','moderate'=>'priority-medium','low'=>'priority-low'];
    return "<span class='priority-badge ".($map[$u]??'priority-low')."'><i class='fas fa-circle'></i>".ucfirst($u)."</span>";
}
function statusBadge(string $s): string {
    $map=['resolved'=>'status-resolved','closed'=>'status-resolved'];
    return "<span class='status-badge ".($map[$s]??'status-resolved')."'>".ucfirst($s)."</span>";
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
$totalCount = count($refs);
if (!$rows) {
    $rows = "<tr><td colspan='8' style='text-align:center;color:var(--text-3);padding:40px'>" .
        ($search || $filterType || $filterUrgency ? "No archived referrals match your current filters." : "You have no archived referrals yet. Referrals marked as resolved by guidance will appear here.") .
        "</td></tr>";
}

$sqVal = htmlspecialchars($search);
$st1 = $filterType==='bullying'        ? 'selected' : '';
$st2 = $filterType==='academic_stress' ? 'selected' : '';
$st3 = $filterType==='emotional'       ? 'selected' : '';
$st4 = $filterType==='family'          ? 'selected' : '';
$st5 = $filterType==='peer_conflict'   ? 'selected' : '';
$st6 = $filterType==='behavioral'      ? 'selected' : '';
$st7 = $filterType==='attendance'      ? 'selected' : '';
$st8 = $filterType==='other'           ? 'selected' : '';

$uSel1 = $filterUrgency==='urgent'   ? 'selected' : '';
$uSel2 = $filterUrgency==='moderate' ? 'selected' : '';
$uSel3 = $filterUrgency==='low'      ? 'selected' : '';

$content = <<<HTML
<div class="content-card">
  <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div>
      <h3 style="margin:0;display:flex;align-items:center;gap:10px;">
        <i class="fas fa-archive" style="color:var(--maroon)"></i> My Archived Referrals 
        <span style="font-size:13px;font-weight:600;color:var(--text-3);">($totalCount records)</span>
      </h3>
    </div>
    <div>
      <a href="teacher-referrals.php" class="btn-sm-outline" style="text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to My Active Referrals
      </a>
    </div>
  </div>

  <div style="background:var(--maroon-pale);border:1px solid var(--maroon-tint);border-radius:10px;padding:12px 16px;margin:16px 0;font-size:13px;color:var(--maroon);display:flex;align-items:center;gap:10px">
    <i class="fas fa-info-circle"></i>
    <span>These are referrals you previously submitted that have been marked as <strong>Resolved</strong> or <strong>Closed</strong> by the Guidance Office.</span>
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

    <select class="status-select" onchange="applyFilter('type',this.value)">
      <option value="">All Concern Types</option>
      <option value="bullying"        $st1>Bullying</option>
      <option value="academic_stress" $st2>Academic Stress</option>
      <option value="emotional"       $st3>Emotional</option>
      <option value="family"          $st4>Family</option>
      <option value="peer_conflict"   $st5>Peer Conflict</option>
      <option value="behavioral"      $st6>Behavioral</option>
      <option value="attendance"      $st7>Attendance</option>
      <option value="other"           $st8>Other</option>
    </select>

    <select class="status-select" onchange="applyFilter('urgency',this.value)">
      <option value="">All Urgencies</option>
      <option value="urgent"   $uSel1>Urgent</option>
      <option value="moderate" $uSel2>Moderate</option>
      <option value="low"      $uSel3>Low</option>
    </select>

    <button class="btn-sm-outline" onclick="window.location='teacher-referrals-archive.php'"><i class="fas fa-times"></i> Clear</button>
  </div>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Ref #</th>
          <th>Student</th>
          <th>Grade/Section</th>
          <th>Concern</th>
          <th>Urgency</th>
          <th>Status</th>
          <th>Date Submitted</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>$rows</tbody>
    </table>
  </div>
</div>

<!-- Modal -->
<div class="modal-overlay hidden" id="refModal" onclick="if(event.target.id==='refModal')this.classList.add('hidden')">
  <div class="modal-box" style="max-width:900px; width:95%">
    <button class="modal-close" onclick="document.getElementById('refModal').classList.add('hidden')"><i class="fas fa-times"></i></button>
    <h2 class="modal-title"><i class="fas fa-archive" style="color:var(--maroon);margin-right:8px"></i>Archived Referral Details</h2>
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
  const urgMap = {urgent:"priority-high", moderate:"priority-medium", low:"priority-low"};
  const typeLabel = ref.concern_type.replace(/_/g," ").replace(/\b\w/g, l=>l.toUpperCase());

  document.getElementById("refModalBody").innerHTML = `
    <div class="review-header-strip" style="display:flex;flex-wrap:wrap;gap:15px;background:var(--bg2);padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid var(--border)">
      <div style="flex:1;min-width:120px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Referral ID</label><strong style="color:var(--maroon);font-size:16px">#${ref.ref_number}</strong></div>
      <div style="flex:2;min-width:150px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Student</label><span style="font-size:15px;font-weight:600">${ref.student_name}</span></div>
      <div style="flex:1.5;min-width:130px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Type</label><span class="status-badge status-ongoing">${typeLabel}</span></div>
      <div style="flex:1.5;min-width:130px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Grade/Sec</label><span style="font-size:14px">${ref.grade_section}</span></div>
      <div style="flex:1.5;min-width:130px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Status</label><span class="status-badge status-resolved">${ref.status.toUpperCase()}</span></div>
    </div>
    
    <div style="display:grid;grid-template-columns: 1.3fr 1fr; gap:20px; align-items:start">
      <div>
        ${ref.ai_summary ? `
          <div class="ai-box" style="margin-bottom:20px;border-left:4px solid var(--blue);background:rgba(37,99,235,0.05);padding:14px;border-radius:8px">
            <div class="ai-box-label" style="color:var(--blue);font-size:13px;font-weight:700;margin-bottom:6px"><i class="fas fa-magic"></i> AI Summary</div>
            <div class="ai-box-text" style="font-size:14px;line-height:1.6;color:var(--text)">${ref.ai_summary}</div>
          </div>
        ` : ""}
        
        <div style="background:var(--bg2);padding:18px;border-radius:8px;border:1px solid var(--border);margin-bottom:14px;">
          <div style="font-size:13px;font-weight:800;color:var(--text-2);margin-bottom:10px;text-transform:uppercase;letter-spacing:1px"><i class="fas fa-quote-left" style="color:var(--maroon)"></i> Your Observations</div>
          <div style="font-size:14px;line-height:1.7;color:var(--text);white-space:pre-wrap;">${ref.observations}</div>
        </div>
      </div>
      
      <div style="background:var(--bg2);padding:20px;border-radius:8px;border:1px solid var(--border)">
        <h3 style="margin-bottom:16px;font-size:15px;color:var(--text);border-bottom:1px solid var(--border);padding-bottom:10px"><i class="fas fa-sliders-h" style="color:var(--maroon)"></i> Actions & Chat</h3>
        <div class="form-group" style="margin-bottom:16px">
          <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:4px">Urgency</label>
          <span class="priority-badge ${urgMap[ref.urgency]||\'priority-low\'}"><i class="fas fa-circle"></i> ${ref.urgency.charAt(0).toUpperCase()+ref.urgency.slice(1)}</span>
        </div>
        <div class="form-group" style="margin-bottom:20px">
          <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:4px">Submitted On</label>
          <span style="font-size:13px;color:var(--text)">${ref.submitted_at}</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px">
          <button class="btn-primary" style="width:100%;background:var(--blue);display:flex;align-items:center;justify-content:center;gap:8px;" onclick="window.location.href=\'teacher-referrals-chat.php?ref_id=${ref.id}\'">
            <i class="fas fa-comments"></i> Open Referrals Chat
          </button>
          <button type="button" class="btn-secondary" style="width:100%" onclick="document.getElementById(\'refModal\').classList.add(\'hidden\')">
            Close
          </button>
        </div>
      </div>
    </div>`;

  document.getElementById("refModal").classList.remove("hidden");
}
</script>';

renderLayout($user, 'Referrals Archive', 'referrals_archive', $content);
