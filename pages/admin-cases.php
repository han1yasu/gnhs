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
} elseif ($filterSection) {
    $s = addslashes($filterSection);
    $where .= " AND s.grade_section LIKE '%$s%'";
}
if ($search) {
    $sq = addslashes($search);
    $sqUnderscore = addslashes(str_replace(' ', '_', $search));
    $where .= " AND (
        LOWER(c.case_number) LIKE LOWER('%$sq%')
        OR LOWER(c.subject) LIKE LOWER('%$sq%')
        OR LOWER(c.description) LIKE LOWER('%$sq%')
        OR LOWER(c.concern_type) LIKE LOWER('%$sq%')
        OR LOWER(c.concern_type) LIKE LOWER('%$sqUnderscore%')
        OR LOWER(REPLACE(c.concern_type, '_', ' ')) LIKE LOWER('%$sq%')
        OR LOWER(s.first_name) LIKE LOWER('%$sq%')
        OR LOWER(s.last_name) LIKE LOWER('%$sq%')
        OR LOWER(CONCAT(s.first_name,' ',s.last_name)) LIKE LOWER('%$sq%')
        OR LOWER(s.grade_section) LIKE LOWER('%$sq%')
        OR LOWER(COALESCE(c.reported_student_name, '')) LIKE LOWER('%$sq%')
        OR LOWER(COALESCE(c.reported_student_grade, '')) LIKE LOWER('%$sq%')
        OR (c.is_anonymous=1 AND LOWER('anonymous') LIKE LOWER('%$sq%'))
    )";
}

$cases = $db->query("SELECT c.*,
    CASE WHEN c.is_anonymous=1 THEN 'Anonymous' ELSE CONCAT(s.first_name,' ',s.last_name) END AS student_name,
    s.grade_section
    FROM cases c LEFT JOIN users s ON c.student_id=s.id
    $where ORDER BY
    FIELD(c.priority,'high','medium','low'),
    c.submitted_at DESC")->fetchAll();

if (!function_exists('badge')) {
    function badge(string $s): string {
        $map=['pending'=>'status-pending','under_review'=>'status-under_review','ongoing'=>'status-ongoing','resolved'=>'status-resolved','closed'=>'status-resolved'];
        return "<span class='status-badge ".($map[$s]??'status-pending')."'>".str_replace('_',' ',ucfirst($s))."</span>";
    }
}
if (!function_exists('prioBadge')) {
    function prioBadge(string $p): string {
        $map=['high'=>'priority-high','medium'=>'priority-medium','low'=>'priority-low'];
        return "<span class='priority-badge ".($map[$p]??'priority-low')."'><i class='fas fa-circle'></i>".ucfirst($p)."</span>";
    }
}

$rows = '';
foreach ($cases as $c) {
    $type = ucfirst(str_replace('_',' ',$c['concern_type']));
    $mediaBadges = '';
    if (!empty($c['evidence_files']) && $c['evidence_files'] !== '[]') {
        $evArray = is_string($c['evidence_files']) ? json_decode($c['evidence_files'], true) : $c['evidence_files'];
        $cnt = is_array($evArray) ? count($evArray) : 1;
        $mediaBadges .= "<span class='badge-evidence' title='{$cnt} attached photo/video evidence file(s)'><i class='fas fa-paperclip'></i> {$cnt} Media</span>";
    }
    if (!empty($c['witnesses'])) {
        $mediaBadges .= "<span class='badge-witness' title='Witnesses mentioned in case report'><i class='fas fa-user-group'></i> Witness</span>";
    }
    if (!empty($c['is_peer_report'])) {
        $mediaBadges .= "<span class='badge-witness' style='background:#fdf2f8;color:#be185d;border-color:#fbcfe8' title='Peer Report: submitted on behalf of another student'><i class='fas fa-hand-holding-heart'></i> Peer</span>";
    }
    $date = date('M d, Y', strtotime($c['submitted_at']));

    if (!empty($c['is_peer_report'])) {
        $targetName = htmlspecialchars($c['reported_student_name'] ?: 'Other Student');
        $targetGrade = !empty($c['reported_student_grade']) ? " <span style='font-size:11px;color:var(--text-3)'>(".htmlspecialchars($c['reported_student_grade']).")</span>" : "";
        $studentDisplay = "<div>
            <strong style='color:var(--text)'>$targetName</strong>$targetGrade
            <div style='font-size:11px;color:var(--text-3);margin-top:2px'>
              <i class='fas fa-user-tag' style='color:var(--maroon)'></i> Rep by: ".htmlspecialchars($c['student_name'])."
            </div>
          </div>";
    } else {
        $studentDisplay = htmlspecialchars($c['student_name']);
    }

    $gradeDisplay = htmlspecialchars($c['grade_section'] ?? '—');
    if (!empty($c['is_peer_report']) && !empty($c['reported_student_grade'])) {
        $gradeDisplay = htmlspecialchars($c['reported_student_grade']);
    }

    $rows .= "<tr>
        <td class='cell-case-num'><span class='case-num-text'>{$c['case_number']}</span></td>
        <td class='cell-student'>$studentDisplay</td>
        <td class='cell-grade'>$gradeDisplay</td>
        <td>
          <div class='case-type-wrap'>
            <span class='case-type-label'>$type</span>
            $mediaBadges
          </div>
        </td>
        <td>".badge($c['status'])."</td>
        <td>".prioBadge($c['priority'])."</td>
        <td class='cell-date'>$date</td>
        <td class='cell-actions'>
          <div class='row-actions'>
            <button type='button' class='btn-row-action' onclick='openCaseReview({$c['id']})'>
              <i class='fas fa-file-lines'></i> Review</button>
            <button type='button' class='btn-row-action btn-action-chat' onclick=\"window.location.href='admin-chats.php?case_id={$c['id']}'\">
              <i class='fas fa-comment-dots'></i> Chat</button>
          </div>
        </td>
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
$sg7  = $filterGrade==='7'              ? 'selected' : '';
$sg8  = $filterGrade==='8'              ? 'selected' : '';
$sg9  = $filterGrade==='9'              ? 'selected' : '';
$sg10 = $filterGrade==='10'             ? 'selected' : '';

$currentGradeJs   = json_encode($filterGrade);
$currentSectionJs = json_encode($filterSection);

$totalActive = count($cases);

$content = <<<HTML
<div class="content-card">
  <div class="card-header">
    <h3><i class="fas fa-folder-open"></i> Active Cases <span style="font-size:13px;font-weight:600;color:var(--text-3);margin-left:6px">($totalActive)</span></h3>
    <a href="admin-archive.php" class="btn-sm-outline"><i class="fas fa-archive"></i> View Archive</a>
  </div>
  <!-- Search & Filters Toolbar -->
  <div class="filter-toolbar">
    <div class="filter-search-box">
      <i class="fas fa-search"></i>
      <input type="text" class="filter-search-input" id="searchQ" value="$sqVal" placeholder="Search by name, case #, subject…"
        onkeydown="if(event.key==='Enter')doSearch()"/>
    </div>
    <button type="button" class="filter-btn-search" onclick="doSearch()"><i class="fas fa-search"></i> Search</button>
    <select class="filter-select" onchange="applyFilter('status',this.value)">
      <option value="">All Status</option>
      <option value="pending"      $ss1>Pending</option>
      <option value="under_review" $ss2>Under Review</option>
      <option value="ongoing"      $ss3>Ongoing</option>
    </select>
    <select class="filter-select" onchange="applyFilter('priority',this.value)">
      <option value="">All Priority</option>
      <option value="high"   $sp1>High</option>
      <option value="medium" $sp2>Medium</option>
      <option value="low"    $sp3>Low</option>
    </select>
    <select class="filter-select" onchange="applyFilter('type',this.value)">
      <option value="">All Types</option>
      <option value="bullying"        $st1>Bullying</option>
      <option value="academic_stress" $st2>Academic Stress</option>
      <option value="emotional"       $st3>Emotional</option>
      <option value="family"          $st4>Family</option>
      <option value="peer_conflict"   $st5>Peer Conflict</option>
      <option value="behavioral"      $st6>Behavioral</option>
      <option value="other"           $st7>Other</option>
    </select>
    <select class="filter-select" id="gradeFilterSel" onchange="gradeFilterChange()">
      <option value="">All Grades</option>
      <option value="7"  $sg7>Grade 7</option>
      <option value="8"  $sg8>Grade 8</option>
      <option value="9"  $sg9>Grade 9</option>
      <option value="10" $sg10>Grade 10</option>
    </select>
    <select class="filter-select" id="sectionFilterSel" onchange="applyFilter('section',this.value)">
      <option value="">All Sections</option>
    </select>
    <button type="button" class="filter-btn-clear" onclick="clearFilters()"><i class="fas fa-rotate-left"></i> Reset</button>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th style="width:90px">Case #</th>
          <th>Student</th>
          <th style="width:125px">Grade</th>
          <th>Type</th>
          <th style="width:115px">Status</th>
          <th style="width:95px">Priority</th>
          <th style="width:100px">Date</th>
          <th style="width:145px">Action</th>
        </tr>
      </thead>
      <tbody>$rows</tbody>
    </table>
  </div>
</div>

<div class="modal-overlay hidden" id="caseReviewModal" onclick="closeCaseReview(event)">
  <div class="modal-box" style="max-width:950px;width:95%;padding:30px">
    <button class="modal-close" onclick="document.getElementById('caseReviewModal').classList.add('hidden')"><i class="fas fa-times"></i></button>
    <h2 class="modal-title">Review Case</h2>
    <div id="caseReviewBody"></div>
  </div>
</div>

<script>
const CURRENT_GRADE   = $currentGradeJs;
const CURRENT_SECTION = $currentSectionJs;

const CASE_SECTIONS = {
  '7':  ['Bonifacio', 'Burgos', 'Del Pilar', 'Diego Silang', 'Luna', 'Mabini', 'Malvar', 'Rizal'],
  '8':  ['Abrahams', 'Basho', 'Confucius', 'Ghandi', 'Goswami', 'Kalidasa', 'Mandela', 'Mencius', 'Tagore', 'Valmiki', 'Voltaire'],
  '9':  ['Bohr', 'Curie', 'Dalton', 'Darwin', 'Einstein', 'Faraday', 'Galileo', 'Newton'],
  '10': ['Aguinaldo', 'Aquino', 'Garcia', 'Laurel', 'Macapagal', 'Magsaysay', 'Marcos', 'Osmena', 'Quezon']
};

function populateSections(grade, selectedSec) {
  const sel = document.getElementById('sectionFilterSel');
  if (!sel) return;
  const secs = CASE_SECTIONS[grade] || [];
  let html = '<option value="">All Sections</option>';
  secs.forEach(function(s) {
    const selAttr = (s === selectedSec) ? ' selected' : '';
    html += '<option value="' + s + '"' + selAttr + '>' + s + '</option>';
  });
  sel.innerHTML = html;
}

function gradeFilterChange() {
  const grade = document.getElementById('gradeFilterSel').value;
  const url = new URL(window.location);
  if (grade) {
    url.searchParams.set('grade', grade);
  } else {
    url.searchParams.delete('grade');
  }
  // When grade changes (or is cleared to All Grades), clear section from URL
  url.searchParams.delete('section');
  window.location = url;
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

// Pre-populate section dropdown and preserve active section if grade filter is active
document.addEventListener('DOMContentLoaded', function() {
  if (CURRENT_GRADE) {
    populateSections(CURRENT_GRADE, CURRENT_SECTION);
  } else {
    populateSections('', '');
  }
});
</script>
HTML;

renderLayout($user, 'All Cases', 'cases', $content);




