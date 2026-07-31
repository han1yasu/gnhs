<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin');
$db   = getDB();

$filterStatus   = $_GET['status']   ?? '';
$filterPriority = $_GET['priority'] ?? '';
$filterType     = $_GET['type']     ?? '';
$filterGrade    = $_GET['grade']    ?? '';
$filterSection  = $_GET['section']  ?? '';
$search         = trim($_GET['q']   ?? '');

// Active cases only (not resolved/closed) — those go to archive
$where = "WHERE c.status NOT IN ('resolved','closed')";
if ($filterStatus)   $where .= " AND c.status='".addslashes($filterStatus)."'";
if ($filterPriority) $where .= " AND c.priority='".addslashes($filterPriority)."'";
if ($filterType)     $where .= " AND c.concern_type='".addslashes($filterType)."'";
if ($filterGrade && $filterSection) {
    // Match both "Grade X - Section" and "Grade X – Section" (em dash vs hyphen)
    $g = addslashes($filterGrade);
    $s = addslashes($filterSection);
    $where .= " AND (s.grade_section='Grade $g - $s' OR s.grade_section='Grade $g – $s' OR s.grade_section LIKE 'Grade $g%$s%')";
} elseif ($filterGrade) {
    $g = addslashes($filterGrade);
    // Match "Grade 9 - X", "Grade 9 – X", or just starts with "Grade 9"
    $where .= " AND (s.grade_section LIKE 'Grade $g -%' OR s.grade_section LIKE 'Grade $g –%' OR s.grade_section LIKE 'Grade $g%')";
}
if ($search) $where .= " AND (
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
    $where ORDER BY
    FIELD(c.priority,'high','medium','low'),
    c.submitted_at DESC")->fetchAll();

function badge(string $s): string {
    $map=['pending'=>'status-pending','under_review'=>'status-under_review','ongoing'=>'status-ongoing','resolved'=>'status-resolved','closed'=>'status-resolved'];
    return "<span class='status-badge ".($map[$s]??'status-pending')."'>".str_replace('_',' ',ucfirst($s))."</span>";
}
function prioBadge(string $p): string {
    $map=['high'=>'priority-high','medium'=>'priority-medium','low'=>'priority-low'];
    return "<span class='priority-badge ".($map[$p]??'priority-low')."'><i class='fas fa-circle'></i>".ucfirst($p)."</span>";
}

$rows = '';
foreach ($cases as $c) {
    $type = ucfirst(str_replace('_',' ',$c['concern_type']));
    $date = date('M d, Y', strtotime($c['submitted_at']));
    $rows .= "<tr>
        <td><strong style='color:var(--maroon)'>{$c['case_number']}</strong></td>
        <td>".htmlspecialchars($c['student_name'])."</td>
        <td>".htmlspecialchars($c['grade_section']??'—')."</td>
        <td>$type</td>
        <td>".badge($c['status'])."</td>
        <td>".prioBadge($c['priority'])."</td>
        <td style='color:var(--text-3);font-size:12px'>$date</td>
        <td><button class='btn-sm-outline' onclick='openCaseReview({$c['id']},\"{$c['case_number']}\",\"{$c['status']}\",\"{$c['priority']}\",".json_encode($c['subject']).",".json_encode($c['description']).")'>
          <i class='fas fa-edit'></i> Review</button></td>
    </tr>";
}
if (!$rows) $rows = "<tr><td colspan='8' style='text-align:center;color:var(--text-3);padding:40px'>".
    ($search ? "No cases found matching \"".htmlspecialchars($search)."\"." : "No active cases found.")."</td></tr>";

$sqVal = htmlspecialchars($search);
$ss1 = $filterStatus==='pending'        ? 'selected' : '';
$ss2 = $filterStatus==='under_review'   ? 'selected' : '';
$ss3 = $filterStatus==='ongoing'        ? 'selected' : '';
$sp1 = $filterPriority==='high'         ? 'selected' : '';
$sp2 = $filterPriority==='medium'       ? 'selected' : '';
$sp3 = $filterPriority==='low'          ? 'selected' : '';
$st1 = $filterType==='bullying'         ? 'selected' : '';
$st2 = $filterType==='academic_stress'  ? 'selected' : '';
$st3 = $filterType==='emotional'        ? 'selected' : '';
$st4 = $filterType==='family'           ? 'selected' : '';
$st5 = $filterType==='peer_conflict'    ? 'selected' : '';
$st6 = $filterType==='behavioral'       ? 'selected' : '';
$st7 = $filterType==='other'            ? 'selected' : '';

$totalActive = count($cases);

$content = <<<HTML
<div class="content-card">
  <div class="card-header">
    <h3><i class="fas fa-folder-open"></i> Active Cases <span style="font-size:13px;font-weight:600;color:var(--text-3);margin-left:6px">($totalActive)</span></h3>
    <a href="admin-archive.php" class="btn-sm-outline"><i class="fas fa-archive"></i> View Archive</a>
  </div>
  <!-- Search & Filters -->
  <div class="filter-bar" style="margin-bottom:16px;flex-wrap:wrap">
    <div style="display:flex;gap:8px;flex:1;min-width:220px">
      <div class="input-wrap" style="flex:1">
        <i class="fas fa-search"></i>
        <input type="text" id="searchQ" value="$sqVal" placeholder="Search by name, case #, subject…"
          onkeydown="if(event.key==='Enter')doSearch()"/>
      </div>
      <button class="btn-sm-primary" onclick="doSearch()"><i class="fas fa-search"></i> Search</button>
    </div>
    <select class="status-select" onchange="applyFilter('status',this.value)">
      <option value="">All Status</option>
      <option value="pending"      $ss1>Pending</option>
      <option value="under_review" $ss2>Under Review</option>
      <option value="ongoing"      $ss3>Ongoing</option>
    </select>
    <select class="status-select" onchange="applyFilter('priority',this.value)">
      <option value="">All Priority</option>
      <option value="high"   $sp1>High</option>
      <option value="medium" $sp2>Medium</option>
      <option value="low"    $sp3>Low</option>
    </select>
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
    <select class="status-select" id="gradeFilterSel" onchange="gradeFilterChange()">
        <option value="">All Grades</option>
        <option value="7">Grade 7</option>
        <option value="8">Grade 8</option>
        <option value="9">Grade 9</option>
        <option value="10">Grade 10</option>
      </select>
      <select class="status-select" id="sectionFilterSel" onchange="applyFilter('section',this.value)">
        <option value="">All Sections</option>
      </select>
      <button class="btn-sm-outline" onclick="clearFilters()"><i class="fas fa-times"></i> Clear</button>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Case #</th><th>Student</th><th>Grade</th><th>Type</th><th>Status</th><th>Priority</th><th>Date</th><th>Action</th></tr></thead>
      <tbody>$rows</tbody>
    </table>
  </div>
</div>

<div class="modal-overlay hidden" id="caseReviewModal" onclick="closeCaseReview(event)">
  <div class="modal-box" style="max-width:700px">
    <button class="modal-close" onclick="document.getElementById('caseReviewModal').classList.add('hidden')"><i class="fas fa-times"></i></button>
    <h2 class="modal-title">Review Case</h2>
    <div id="caseReviewBody"></div>
  </div>
</div>

<script>
const CASE_SECTIONS = {
  '7':  ['Bonifacio','Luna','Rizal','Mabini','Aguinaldo','Jacinto'],
  '8':  ['Confucius','Goswami','Mandela','Socrates','Aristotle','Plato'],
  '9':  ['Dalton','Bohr','Lavoisier','Newton','Curie','Einstein'],
  '10': ['Osmena','Quezon','Magsaysay','Aguinaldo','Macapagal','Marcos']
};
function gradeFilterChange() {
  const grade = document.getElementById('gradeFilterSel').value;
  const sel   = document.getElementById('sectionFilterSel');
  const secs  = CASE_SECTIONS[grade]||[];
  sel.innerHTML = '<option value="">All Sections</option>'
    + secs.map(s=>'<option value="'+s+'">'+s+'</option>').join('');
  applyFilter('grade', grade);
}
function doSearch() {
  applyFilter('q', document.getElementById('searchQ').value.trim());
}
function applyFilter(key, val) {
  const url = new URL(window.location);
  if (val) url.searchParams.set(key, val);
  else url.searchParams.delete(key);
  window.location = url;
}
function clearFilters() {
  window.location = 'admin-cases.php';
}
// Pre-populate section dropdown if grade filter is active
document.addEventListener('DOMContentLoaded', function() {
  const grade = document.getElementById('gradeFilterSel')?.value;
  if (grade) {
    const secs = CASE_SECTIONS[grade]||[];
    const sel  = document.getElementById('sectionFilterSel');
    sel.innerHTML = '<option value="">All Sections</option>'
      + secs.map(s=>'<option value="'+s+'">'+s+'</option>').join('');
  }
});
</script>
HTML;

renderLayout($user, 'All Cases', 'cases', $content);
