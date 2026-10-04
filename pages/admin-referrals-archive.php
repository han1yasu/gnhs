<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin');
$db   = getDB();

$search        = trim($_GET['q']        ?? '');
$filterType    = trim($_GET['type']     ?? '');
$filterUrgency = trim($_GET['urgency']  ?? '');
$filterTeacher = (int)($_GET['teacher'] ?? 0);

$where = "WHERE r.status IN ('resolved','closed')";
if ($filterType)    $where .= " AND r.concern_type='".addslashes($filterType)."'";
if ($filterUrgency) $where .= " AND r.urgency='".addslashes($filterUrgency)."'";
if ($filterTeacher) $where .= " AND r.teacher_id=" . $filterTeacher;
if ($search) {
    $s = addslashes($search);
    $where .= " AND (
        r.ref_number LIKE '%$s%'
        OR r.student_name LIKE '%$s%'
        OR r.grade_section LIKE '%$s%'
        OR r.observations LIKE '%$s%'
        OR t.first_name LIKE '%$s%'
        OR t.last_name LIKE '%$s%'
        OR CONCAT(t.first_name,' ',t.last_name) LIKE '%$s%'
    )";
}

$refs = $db->query("SELECT r.*, CONCAT(t.first_name,' ',t.last_name) AS teacher_name, t.email AS teacher_email
    FROM referrals r JOIN users t ON r.teacher_id=t.id
    $where ORDER BY r.submitted_at DESC")->fetchAll();

$teachersList = $db->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM users WHERE role='teacher' ORDER BY first_name ASC")->fetchAll();

function urgencyBadge(string $u): string {
    $map=['urgent'=>'priority-high','moderate'=>'priority-medium','low'=>'priority-low'];
    return "<span class='priority-badge ".($map[$u]??'priority-low')."'><i class='fas fa-circle'></i> ".ucfirst($u)."</span>";
}
function statusBadge(string $s): string {
    $map=['resolved'=>'status-resolved','closed'=>'status-resolved'];
    return "<span class='status-badge ".($map[$s]??'status-resolved')."'>".ucfirst($s)."</span>";
}

$rows = '';
foreach ($refs as $r) {
    $type     = ucfirst(str_replace('_',' ',$r['concern_type']));
    $date     = date('M d, Y', strtotime($r['submitted_at']));
    $refData  = json_encode([
        'id'           => (int)$r['id'],
        'ref_number'   => $r['ref_number'],
        'student_name' => $r['student_name'],
        'grade_section'=> $r['grade_section'],
        'concern_type' => $r['concern_type'],
        'urgency'      => $r['urgency'],
        'status'       => $r['status'],
        'observations' => $r['observations'],
        'ai_summary'   => $r['ai_summary'] ?? null,
        'teacher_name' => $r['teacher_name'],
        'teacher_email'=> $r['teacher_email'] ?? '',
        'submitted_at' => date('M d, Y g:i A', strtotime($r['submitted_at'])),
    ], JSON_HEX_QUOT | JSON_HEX_APOS);

    $rows .= "<tr>
        <td><strong style='color:var(--maroon)'>{$r['ref_number']}</strong></td>
        <td>".htmlspecialchars($r['student_name'])."</td>
        <td>".htmlspecialchars($r['grade_section'])."</td>
        <td>$type</td>
        <td>".urgencyBadge($r['urgency'])."</td>
        <td>".htmlspecialchars($r['teacher_name'])."</td>
        <td>".statusBadge($r['status'])."</td>
        <td style='color:var(--text-3);font-size:12px'>$date</td>
        <td>
          <button class='btn-sm-outline' onclick='openArchivedRefModal(this)' data-ref='$refData'>
            <i class='fas fa-eye'></i> View
          </button>
        </td>
    </tr>";
}

$totalCount = count($refs);
if (!$rows) {
    $rows = "<tr><td colspan='9' style='text-align:center;color:var(--text-3);padding:40px'>" .
        ($search || $filterType || $filterUrgency || $filterTeacher ? "No archived referrals match your current filters." : "No archived referrals found. Referrals marked as resolved or closed will be stored here.") .
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

$su1 = $filterUrgency==='urgent'       ? 'selected' : '';
$su2 = $filterUrgency==='moderate'     ? 'selected' : '';
$su3 = $filterUrgency==='low'          ? 'selected' : '';

$teacherOptions = '';
foreach ($teachersList as $t) {
    $selected = ($filterTeacher === (int)$t['id']) ? 'selected' : '';
    $teacherOptions .= "<option value='{$t['id']}' $selected>" . htmlspecialchars($t['name']) . "</option>";
}

$content = <<<HTML
<div class="content-card">
  <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div>
      <h3 style="margin:0;display:flex;align-items:center;gap:10px;">
        <i class="fas fa-archive" style="color:var(--maroon)"></i> Referrals Archive 
        <span style="font-size:13px;font-weight:600;color:var(--text-3);">($totalCount records)</span>
      </h3>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
      <a href="admin-referrals.php" class="btn-sm-outline" style="text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to Active Referrals
      </a>
    </div>
  </div>

  <div style="background:var(--maroon-pale);border:1px solid var(--maroon-tint);border-radius:10px;padding:12px 16px;margin:16px 0;font-size:13px;color:var(--maroon);display:flex;align-items:center;gap:10px">
    <i class="fas fa-info-circle"></i>
    <span>These are teacher referrals that have been addressed and marked as <strong>Resolved</strong> or <strong>Closed</strong>. They are preserved for student counseling history and record keeping.</span>
  </div>

  <!-- Filter & Search Bar -->
  <div class="filter-bar" style="margin-bottom:18px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
    <div style="display:flex;gap:8px;flex:1;min-width:240px">
      <div class="input-wrap" style="flex:1">
        <i class="fas fa-search"></i>
        <input type="text" id="searchQ" value="$sqVal" placeholder="Search by student, teacher, ref #, or keyword…"
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
      <option value="">All Urgency</option>
      <option value="urgent"   $su1>Urgent</option>
      <option value="moderate" $su2>Moderate</option>
      <option value="low"      $su3>Low</option>
    </select>

    <select class="status-select" onchange="applyFilter('teacher',this.value)">
      <option value="">All Teachers</option>
      $teacherOptions
    </select>

    <button class="btn-sm-outline" onclick="window.location='admin-referrals-archive.php'"><i class="fas fa-times"></i> Clear</button>
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
          <th>Referred By</th>
          <th>Status</th>
          <th>Date Submitted</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>$rows</tbody>
    </table>
  </div>
</div>

<!-- Archived Referral Detail Modal -->
<div class="modal-overlay hidden" id="archivedRefModal" onclick="if(event.target.id==='archivedRefModal')this.classList.add('hidden')">
  <div class="modal-box" style="max-width:980px; width:95%">
    <button class="modal-close" onclick="document.getElementById('archivedRefModal').classList.add('hidden')"><i class="fas fa-times"></i></button>
    <h2 class="modal-title"><i class="fas fa-archive" style="color:var(--maroon);margin-right:8px"></i>Archived Referral Detail</h2>
    <div id="archivedRefModalBody"></div>
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
  if (val) url.searchParams.set(key, val);
  else url.searchParams.delete(key);
  window.location = url;
}

function openArchivedRefModal(btn) {
  const ref = JSON.parse(btn.getAttribute("data-ref"));
  const urgMap   = {urgent:"priority-high", moderate:"priority-medium", low:"priority-low"};
  const typeLabel = ref.concern_type.replace(/_/g," ").replace(/\b\w/g, l=>l.toUpperCase());

  document.getElementById("archivedRefModalBody").innerHTML = `
    <div class="review-header-strip" style="display:flex;flex-wrap:wrap;gap:15px;background:var(--bg2);padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid var(--border)">
      <div style="flex:1;min-width:120px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Referral ID</label><strong style="color:var(--maroon);font-size:16px">#${ref.ref_number}</strong></div>
      <div style="flex:2;min-width:150px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Student</label><span style="font-size:15px;font-weight:600">${ref.student_name}</span></div>
      <div style="flex:1.5;min-width:130px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Concern</label><span class="status-badge status-ongoing">${typeLabel}</span></div>
      <div style="flex:1.5;min-width:130px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Grade/Sec</label><span style="font-size:14px">${ref.grade_section}</span></div>
      <div style="flex:1.5;min-width:130px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Submitted</label><span style="font-size:13px;color:var(--text-2)">${ref.submitted_at}</span></div>
    </div>
    
    <div class="review-grid" style="display:grid;grid-template-columns: 1.3fr 1fr; gap:25px; align-items:start">
      <div>
        ${ref.ai_summary ? `
          <div class="ai-box" style="margin-bottom:20px;border-left:4px solid var(--blue);background:rgba(37,99,235,0.05);padding:14px;border-radius:8px">
            <div class="ai-box-label" style="color:var(--blue);font-size:13px;font-weight:700;margin-bottom:6px">
              <i class="fas fa-magic"></i> AI Summary
            </div>
            <div class="ai-box-text" style="font-size:14px;line-height:1.6;color:var(--text)">${ref.ai_summary}</div>
          </div>
        ` : ""}
        
        <div style="background:var(--bg2);padding:18px;border-radius:8px;border:1px solid var(--border);margin-bottom:16px;">
          <div style="font-size:13px;font-weight:800;color:var(--text-2);margin-bottom:10px;text-transform:uppercase;letter-spacing:1px">
            <i class="fas fa-quote-left" style="color:var(--maroon)"></i> Teacher\'s Observations
          </div>
          <div style="font-size:14px;line-height:1.7;color:var(--text);white-space:pre-wrap;">${ref.observations}</div>
        </div>
      </div>
      
      <div style="background:var(--bg2);padding:20px;border-radius:8px;border:1px solid var(--border)">
        <h3 style="margin-bottom:16px;font-size:15px;color:var(--text);border-bottom:1px solid var(--border);padding-bottom:10px">
          <i class="fas fa-info-circle" style="color:var(--maroon)"></i> Archive Information
        </h3>

        <div class="form-group" style="margin-bottom:14px">
          <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:4px">Archive Status</label>
          <span class="status-badge status-resolved"><i class="fas fa-check-circle"></i> ${ref.status.toUpperCase()}</span>
        </div>

        <div class="form-group" style="margin-bottom:14px">
          <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:4px">Urgency Level</label>
          <span class="priority-badge ${urgMap[ref.urgency]||\'priority-low\'}"><i class="fas fa-circle"></i> ${ref.urgency.charAt(0).toUpperCase()+ref.urgency.slice(1)}</span>
        </div>

        <div class="form-group" style="margin-bottom:18px;border-top:1px solid var(--border);padding-top:14px">
          <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:4px">Referred By</label>
          <div style="font-weight:700;font-size:14px;color:var(--text)">${ref.teacher_name}</div>
          ${ref.teacher_email ? `<div style="font-size:12px;color:var(--text-3);margin-top:2px">${ref.teacher_email}</div>` : ""}
        </div>

        <div style="display:flex;flex-direction:column;gap:10px;">
          <button class="btn-primary" style="width:100%;background:var(--blue);display:flex;align-items:center;justify-content:center;gap:8px;" onclick="window.location.href=\'admin-referrals-chat.php?ref_id=${ref.id}\'">
            <i class="fas fa-comments"></i> View Chat History
          </button>

          <button type="button" class="btn-sm-outline" style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px;padding:8px;" onclick="printArchivedSlip(${ref.id})">
            <i class="fas fa-print"></i> Print Referral Slip
          </button>

          <div style="border-top:1px solid var(--border);margin-top:8px;padding-top:12px;">
            <label style="font-size:12px;font-weight:700;color:var(--text-2);display:block;margin-bottom:6px">Reopen Referral</label>
            <p style="font-size:11px;color:var(--text-3);margin-bottom:8px">If this case needs follow-up, restore it to Active Referrals.</p>
            <div style="display:flex;gap:8px">
              <button class="btn-sm-outline" style="flex:1;border-color:var(--maroon);color:var(--maroon);display:flex;align-items:center;justify-content:center;gap:6px;" onclick="restoreReferral(${ref.id}, \'ongoing\')">
                <i class="fas fa-undo"></i> Reopen (Ongoing)
              </button>
              <button class="btn-sm-outline" style="flex:1;border-color:var(--blue);color:var(--blue);display:flex;align-items:center;justify-content:center;gap:6px;" onclick="restoreReferral(${ref.id}, \'under_review\')">
                <i class="fas fa-sync-alt"></i> Under Review
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  `;

  document.getElementById("archivedRefModal").classList.remove("hidden");
}

async function restoreReferral(id, newStatus) {
  if (!confirm("Are you sure you want to restore this referral to active status (" + newStatus.replace(\'_\',\' \') + ")?")) return;
  try {
    const r = await fetch("/gnhs-guidance/api/update_referral.php?id=" + id + "&status=" + newStatus);
    const d = await r.json();
    if (d.success) {
      showToast("Referral restored to Active Referrals!", "success");
      document.getElementById("archivedRefModal").classList.add("hidden");
      setTimeout(() => location.reload(), 1200);
    } else {
      showToast(d.message || "Failed to restore referral.", "error");
    }
  } catch(e) {
    showToast("Error updating referral status.", "error");
  }
}

function printArchivedSlip(id) {
  window.print();
}
</script>
';

renderLayout($user, 'Referrals Archive', 'referrals_archive', $content);
