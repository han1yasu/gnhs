<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin');
$db   = getDB();

$search = trim($_GET['q'] ?? '');
$filterType = $_GET['type'] ?? '';

$where = "WHERE c.status IN ('resolved','closed') AND COALESCE(c.is_follow_up, 0) = 0";
if ($filterType) $where .= " AND c.concern_type='".addslashes($filterType)."'";
if ($search)     $where .= " AND (
    c.case_number LIKE '%".addslashes($search)."%'
    OR c.subject LIKE '%".addslashes($search)."%'
    OR s.first_name LIKE '%".addslashes($search)."%'
    OR s.last_name LIKE '%".addslashes($search)."%'
    OR CONCAT(s.first_name,' ',s.last_name) LIKE '%".addslashes($search)."%'
)";

$cases = $db->query("SELECT c.*,
    CASE WHEN c.is_anonymous=1 THEN 'Anonymous' ELSE CONCAT(s.first_name,' ',s.last_name) END AS student_name,
    s.grade_section
    FROM cases c LEFT JOIN users s ON c.student_id=s.id
    $where ORDER BY c.updated_at DESC")->fetchAll();

function badge(string $s): string {
    $map=['resolved'=>'status-resolved','closed'=>'status-resolved'];
    return "<span class='status-badge ".($map[$s]??'status-resolved')."'>".ucfirst($s)."</span>";
}
function prioBadge(string $p): string {
    $map=['high'=>'priority-high','medium'=>'priority-medium','low'=>'priority-low'];
    return "<span class='priority-badge ".($map[$p]??'priority-low')."'><i class='fas fa-circle'></i>".ucfirst($p)."</span>";
}

$rows = '';
foreach ($cases as $c) {
    $type = ucfirst(str_replace('_',' ',$c['concern_type']));
    $submitted = date('M d, Y', strtotime($c['submitted_at']));
    $updated   = date('M d, Y', strtotime($c['updated_at']));
    $rows .= "<tr>
        <td><strong style='color:var(--maroon)'>{$c['case_number']}</strong></td>
        <td>".htmlspecialchars($c['student_name'])."</td>
        <td>".htmlspecialchars($c['grade_section']??'—')."</td>
        <td>$type</td>
        <td>".badge($c['status'])."</td>
        <td>".prioBadge($c['priority'])."</td>
        <td style='color:var(--text-3);font-size:12px'>$submitted</td>
        <td style='color:var(--text-3);font-size:12px'>$updated</td>
        <td><button class='btn-sm-outline' onclick=\"window.location.href='admin-chats.php?archive_id={$c['id']}'\">
          <i class='fas fa-comments'></i> View Chat</button></td>
    </tr>";
}
if (!$rows) $rows = "<tr><td colspan='9' style='text-align:center;color:var(--text-3);padding:40px'>".
    ($search ? "No archived chats matching \"".htmlspecialchars($search)."\"." : "No archived chats yet.")."</td></tr>";

$sqVal = htmlspecialchars($search);
$total = count($cases);
$st1 = $filterType==='bullying'        ? 'selected' : '';
$st2 = $filterType==='academic_stress' ? 'selected' : '';
$st3 = $filterType==='emotional'       ? 'selected' : '';
$st4 = $filterType==='family'          ? 'selected' : '';
$st5 = $filterType==='peer_conflict'   ? 'selected' : '';
$st6 = $filterType==='behavioral'      ? 'selected' : '';
$st7 = $filterType==='other'           ? 'selected' : '';

$content = <<<HTML
<div class="content-card">
  <div class="card-header">
    <h3><i class="fas fa-comments"></i> Archived Chats <span style="font-size:13px;font-weight:600;color:var(--text-3);margin-left:6px">($total resolved/closed)</span></h3>
    <a href="admin-cases.php" class="btn-sm-outline"><i class="fas fa-arrow-left"></i> Back to Active</a>
  </div>
  <div style="background:var(--maroon-pale);border:1px solid var(--maroon-tint);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:var(--maroon);display:flex;align-items:center;gap:10px">
    <i class="fas fa-info-circle"></i>
    These are cases that have been marked as <strong>Resolved</strong> or <strong>Closed</strong>. They are kept for records.
  </div>
  <div class="filter-bar" style="margin-bottom:16px;flex-wrap:wrap">
    <div style="display:flex;gap:8px;flex:1;min-width:220px">
      <div class="input-wrap" style="flex:1">
        <i class="fas fa-search"></i>
        <input type="text" id="searchQ" value="$sqVal" placeholder="Search archived chats…"
          onkeydown="if(event.key==='Enter')doSearch()"/>
      </div>
      <button class="btn-sm-primary" onclick="doSearch()"><i class="fas fa-search"></i> Search</button>
    </div>
    <select class="status-select" onchange="applyFilter('type',this.value)">
      <option value="">All Types</option>
      <option value="bullying"        $st1>Bullying</option>
      <option value="academic_stress" $st2>Academic Stress</option>
      <option value="emotional"       $st3>Emotional</option>
      <option value="family"          $st4>Family</option>
      <option value="peer_conflict"   $st5>Peer Conflict</option>
      <option value="behavioral"      $st6>Behavioral</option>
      <option value="other"           $st7>Other</option>
    </select>
    <button class="btn-sm-outline" onclick="window.location='admin-archive.php'"><i class="fas fa-times"></i> Clear</button>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Case #</th><th>Student</th><th>Grade</th><th>Type</th><th>Status</th><th>Priority</th><th>Submitted</th><th>Resolved</th><th>Action</th></tr></thead>
      <tbody>$rows</tbody>
    </table>
  </div>
</div>

<script>
function doSearch() {
  const q = document.getElementById('searchQ').value.trim();
  const t = new URLSearchParams(window.location.search).get('type')||'';
  let url = 'admin-chat-archive.php?';
  if(q) url += 'q='+encodeURIComponent(q)+'&';
  if(t) url += 'type='+encodeURIComponent(t);
  window.location = url;
}
function applyFilter(k,v) {
  const p = new URLSearchParams(window.location.search);
  if(v) p.set(k,v); else p.delete(k);
  window.location = 'admin-chat-archive.php?' + p.toString();
}
</script>
HTML;

renderLayout($user, 'Chat Archive', 'chat_archive', $content);



