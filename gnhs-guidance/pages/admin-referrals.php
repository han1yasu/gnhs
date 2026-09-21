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
  <div class="modal-box" style="max-width:620px">
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

  document.getElementById("refModalBody").innerHTML =
    "<div class=\"review-meta\" style=\"margin-bottom:16px\">"
    +"<div class=\"rm-row\"><label>Referral #</label><strong style=\"color:var(--maroon)\">"+ref.ref_number+"</strong></div>"
    +"<div class=\"rm-row\"><label>Student Name</label><strong>"+ref.student_name+"</strong></div>"
    +"<div class=\"rm-row\"><label>Grade / Section</label><span>"+ref.grade_section+"</span></div>"
    +"<div class=\"rm-row\"><label>Concern Type</label><span>"+typeLabel+"</span></div>"
    +"<div class=\"rm-row\"><label>Urgency</label><span class=\"priority-badge "+(urgMap[ref.urgency]||"priority-low")+"\"><i class=\"fas fa-circle\"></i> "+ref.urgency.charAt(0).toUpperCase()+ref.urgency.slice(1)+"</span></div>"
    +"<div class=\"rm-row\"><label>Current Status</label><span class=\"status-badge "+(statMap[ref.status]||"status-pending")+"\">"+statLabel[ref.status]+"</span></div>"
    +"<div class=\"rm-row\"><label>Referred By</label><span>"+ref.teacher_name+"</span></div>"
    +"<div class=\"rm-row\"><label>Date Submitted</label><span>"+ref.submitted_at+"</span></div>"
    +"</div>"
    +"<div class=\"form-group\">"
    +"<label style=\"font-size:13px;font-weight:800;color:var(--text-2);display:block;margin-bottom:8px\"><i class=\"fas fa-clipboard\" style=\"color:var(--maroon)\"></i> Teacher\'s Observations</label>"
    +"<div class=\"concern-text\" style=\"white-space:pre-wrap\">"+ref.observations+"</div>"
    +"</div>"
    +"<div class=\"form-group\" style=\"margin-top:18px\">"
    +"<label style=\"font-size:13px;font-weight:700;color:var(--text-2);display:block;margin-bottom:8px\">Update Status</label>"
    +"<div class=\"select-wrap\"><i class=\"fas fa-tag\"></i>"
    +"<select id=\"ref-status-sel\">"+selOpts+"</select>"
    +"</div></div>"
    +"<div class=\"review-actions\" style=\"margin-top:16px\">"
    +"<button class=\"btn-secondary\" onclick=\"document.getElementById(\'refModal\').classList.add(\'hidden\')\"><i class=\"fas fa-times\"></i> Close</button>"
    +"<button class=\"btn-primary\" id=\"saveRefBtn\" onclick=\"updateRefStatus("+ref.id+")\"><i class=\"fas fa-save\"></i> Save Status</button>"
    +"</div>";

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



