<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin');
$db   = getDB();

$filterStatus  = $_GET['status']  ?? '';
$filterUrgency = $_GET['urgency'] ?? '';
$where = "WHERE 1=1";
if ($filterStatus)  $where .= " AND r.status='".addslashes($filterStatus)."'";
if ($filterUrgency) $where .= " AND r.urgency='".addslashes($filterUrgency)."'";

$refs = $db->query("SELECT r.*,CONCAT(t.first_name,' ',t.last_name) AS teacher_name
    FROM referrals r JOIN users t ON r.teacher_id=t.id
    $where ORDER BY
    FIELD(r.urgency,'urgent','moderate','low'),
    r.submitted_at DESC")->fetchAll();

function urgencyBadge(string $u): string {
    $map=['urgent'=>'priority-high','moderate'=>'priority-medium','low'=>'priority-low'];
    return "<span class='priority-badge ".($map[$u]??'priority-low')."'><i class='fas fa-circle'></i> ".ucfirst($u)."</span>";
}
function statusBadge(string $s): string {
    $map=['pending'=>'status-pending','under_review'=>'status-under_review','ongoing'=>'status-ongoing','resolved'=>'status-resolved'];
    return "<span class='status-badge ".($map[$s]??'status-pending')."'>".str_replace('_',' ',ucfirst($s))."</span>";
}

// Build rows — pass full data as JSON to onclick
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
        'submitted_at' => date('M d, Y', strtotime($r['submitted_at'])),
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
        <td><button class='btn-sm-outline' onclick='openRefModal(this)' data-ref='$refData'>
          <i class='fas fa-eye'></i> View</button></td>
    </tr>";
}
if (!$rows) $rows = "<tr><td colspan='9' style='text-align:center;color:var(--text-3);padding:40px'>No referrals found.</td></tr>";

$ss1 = $filterStatus==='pending'      ? 'selected' : '';
$ss2 = $filterStatus==='under_review' ? 'selected' : '';
$ss3 = $filterStatus==='ongoing'      ? 'selected' : '';
$ss4 = $filterStatus==='resolved'     ? 'selected' : '';
$su1 = $filterUrgency==='urgent'      ? 'selected' : '';
$su2 = $filterUrgency==='moderate'    ? 'selected' : '';
$su3 = $filterUrgency==='low'         ? 'selected' : '';

$content = <<<HTML
<div class="content-card">
  <div class="card-header">
    <h3><i class="fas fa-exchange-alt"></i> All Referrals</h3>
    <div class="filter-bar">
      <select class="status-select" onchange="applyFilter('status',this.value)">
        <option value="">All Status</option>
        <option value="pending"      $ss1>Pending</option>
        <option value="under_review" $ss2>Under Review</option>
        <option value="ongoing"      $ss3>Ongoing</option>
        <option value="resolved"     $ss4>Resolved</option>
      </select>
      <select class="status-select" onchange="applyFilter('urgency',this.value)">
        <option value="">All Urgency</option>
        <option value="urgent"   $su1>Urgent</option>
        <option value="moderate" $su2>Moderate</option>
        <option value="low"      $su3>Low</option>
      </select>
    </div>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Ref #</th><th>Student</th><th>Grade/Section</th><th>Concern</th><th>Urgency</th><th>Referred By</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
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

// JS is outside heredoc to prevent PHP variable interpolation issues
$content .= '
<script>
function applyFilter(key, val) {
  const url = new URL(window.location);
  if (val) url.searchParams.set(key, val); else url.searchParams.delete(key);
  window.location = url;
}

function openRefModal(btn) {
  const ref = JSON.parse(btn.getAttribute("data-ref"));
  const urgMap   = {urgent:"priority-high", moderate:"priority-medium", low:"priority-low"};
  const statMap  = {pending:"status-pending", under_review:"status-under_review", ongoing:"status-ongoing", resolved:"status-resolved"};
  const statLabel = {pending:"Pending", under_review:"Under Review", ongoing:"Ongoing", resolved:"Resolved"};
  const typeLabel = ref.concern_type.replace(/_/g," ").replace(/\b\w/g, l=>l.toUpperCase());

  const selOpts = ["pending","under_review","ongoing","resolved"].map(s =>
    "<option value=\""+s+"\""+(s===ref.status?" selected":"")+">"+statLabel[s]+"</option>"
  ).join("");

  document.getElementById("refModalBody").innerHTML = `
    <div class="review-header-strip" style="display:flex;flex-wrap:wrap;gap:15px;background:var(--bg2);padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid var(--border)">
      <div style="flex:1;min-width:120px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Referral ID</label><strong style="color:var(--maroon);font-size:16px">#${ref.ref_number}</strong></div>
      <div style="flex:2;min-width:150px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Student</label><span style="font-size:15px;font-weight:600">${ref.student_name}</span></div>
      <div style="flex:1.5;min-width:130px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Type</label><span class="status-badge status-ongoing">${typeLabel}</span></div>
      <div style="flex:1.5;min-width:130px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Grade/Sec</label><span style="font-size:14px">${ref.grade_section}</span></div>
      <div style="flex:1.5;min-width:130px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Submitted</label><span style="font-size:13px;color:var(--text-2)">${ref.submitted_at}</span></div>
    </div>
    
    <div class="review-grid" style="display:grid;grid-template-columns: 1.2fr 1fr; gap:25px; align-items:start">
      <div>
        ${ref.ai_summary ? `<div class="ai-box" style="margin-bottom:20px;border-left:4px solid var(--blue);background:rgba(37,99,235,0.05)"><div class="ai-box-label" style="color:var(--blue);font-size:14px;margin-bottom:8px"><i class="fas fa-magic"></i> AI Summary</div><div class="ai-box-text" style="font-size:15px;line-height:1.6">${ref.ai_summary}</div></div>` : \'\'}
        <div style="margin-bottom:14px;background:var(--bg2);padding:20px;border-radius:8px;border:1px solid var(--border)">
          <div style="font-size:13px;font-weight:800;color:var(--text-2);margin-bottom:12px;text-transform:uppercase;letter-spacing:1px"><i class="fas fa-quote-left" style="color:var(--maroon)"></i> Teacher\'s Observations</div>
          <div class="concern-text" style="font-size:15px;line-height:1.7;color:var(--text);background:transparent;padding:0;border:none;white-space:pre-wrap;">${ref.observations}</div>
        </div>
      </div>
      
      <div style="background:var(--bg2);padding:20px;border-radius:8px;border:1px solid var(--border)">
        <h3 style="margin-bottom:20px;font-size:16px;color:var(--text);border-bottom:1px solid var(--border);padding-bottom:10px"><i class="fas fa-sliders-h" style="color:var(--maroon)"></i> Action Panel</h3>
        <div class="form-group">
          <label>Urgency</label>
          <div style="margin-bottom:15px;"><span class="priority-badge ${urgMap[ref.urgency]||\'priority-low\'}"><i class="fas fa-circle"></i> ${ref.urgency.charAt(0).toUpperCase()+ref.urgency.slice(1)}</span></div>
        </div>
        <div class="form-group">
          <label>Update Status</label>
          <div class="select-wrap"><i class="fas fa-tag"></i>
            <select id="ref-status-sel">${selOpts}</select>
          </div>
        </div>
        <div class="form-group" style="margin-top:20px;border-top:1px solid var(--border);padding-top:20px">
          <label>Referred By</label>
          <div style="font-weight:600;margin-bottom:15px;">${ref.teacher_name}</div>
          <button class="btn-primary" style="width:100%;background:var(--blue)" onclick="window.location.href=\'admin-referrals-chat.php?ref_id=${ref.id}\'">
            <i class="fas fa-comment-dots"></i> Message Teacher
          </button>
        </div>
        <div class="review-actions" style="margin-top:16px;display:flex;gap:10px;">
          <button class="btn-secondary" style="flex:1" onclick="document.getElementById(\'refModal\').classList.add(\'hidden\')"><i class="fas fa-times"></i> Cancel</button>
          <button class="btn-primary" style="flex:1" id="saveRefBtn" onclick="updateRefStatus(${ref.id})"><i class="fas fa-save"></i> Save</button>
        </div>
      </div>
    </div>`;

  document.getElementById("refModal").classList.remove("hidden");
}

async function updateRefStatus(id) {
  const status = document.getElementById("ref-status-sel").value;
  const btn    = document.getElementById("saveRefBtn");
  if (btn) { btn.disabled=true; btn.innerHTML="<span class=\"spinner\"></span> Saving…"; }
  try {
    const r = await fetch("/gnhs-guidance/api/update_referral.php?id="+id+"&status="+status);
    const d = await r.json();
    if (d.success) {
      showToast("Referral status updated!","success");
      document.getElementById("refModal").classList.add("hidden");
      setTimeout(()=>location.reload(), 1200);
    } else {
      showToast(d.message||"Update failed.","error");
      if (btn) { btn.disabled=false; btn.innerHTML="<i class=\"fas fa-save\"></i> Save Status"; }
    }
  } catch(e) {
    showToast("Error updating referral.","error");
    if (btn) { btn.disabled=false; btn.innerHTML="<i class=\"fas fa-save\"></i> Save Status"; }
  }
}
</script>';

renderLayout($user, 'Referrals', 'referrals', $content);



