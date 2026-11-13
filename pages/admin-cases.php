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

    // Detect if search term matches any known concern type or badge keyword
    $isTypeSearch = false;
    $concernTypes = ['bullying', 'bully', 'academic', 'stress', 'academic_stress', 'emotional', 'family', 'peer', 'peer_conflict', 'behavioral', 'behavior', 'other'];
    foreach ($concernTypes as $ct) {
        if (stripos($sq, $ct) !== false || stripos($ct, $sq) !== false) {
            $isTypeSearch = true;
            break;
        }
    }

    $where .= " AND (
        LOWER(c.case_number) LIKE LOWER('%$sq%')
        OR LOWER(c.concern_type) LIKE LOWER('%$sq%')
        OR LOWER(c.concern_type) LIKE LOWER('%$sqUnderscore%')
        OR LOWER(REPLACE(c.concern_type, '_', ' ')) LIKE LOWER('%$sq%')
        OR LOWER(s.first_name) LIKE LOWER('%$sq%')
        OR LOWER(s.last_name) LIKE LOWER('%$sq%')
        OR LOWER(CONCAT(s.first_name,' ',s.last_name)) LIKE LOWER('%$sq%')
        OR LOWER(s.grade_section) LIKE LOWER('%$sq%')
        OR LOWER(COALESCE(c.reported_student_name, '')) LIKE LOWER('%$sq%')
        OR LOWER(COALESCE(c.reported_student_grade, '')) LIKE LOWER('%$sq%')
        OR LOWER(c.status) LIKE LOWER('%$sq%')
        OR LOWER(c.priority) LIKE LOWER('%$sq%')
        OR (c.is_anonymous=1 AND LOWER('anonymous') LIKE LOWER('%$sq%'))
        OR (c.is_peer_report=1 AND LOWER('peer') LIKE LOWER('%$sq%'))
        OR (c.witnesses IS NOT NULL AND c.witnesses != '' AND LOWER('witness') LIKE LOWER('%$sq%'))
        OR (c.evidence_files IS NOT NULL AND c.evidence_files != '' AND c.evidence_files != '[]' AND LOWER('media') LIKE LOWER('%$sq%'))
        " . (!$isTypeSearch ? "OR LOWER(c.subject) LIKE LOWER('%$sq%')" : "") . "
    )";
}

$cases = $db->query("SELECT c.*,
    CASE WHEN c.is_anonymous=1 THEN 'Anonymous' ELSE CONCAT(s.first_name,' ',s.last_name) END AS student_name,
    s.grade_section
    FROM cases c LEFT JOIN users s ON c.student_id=s.id
    $where ORDER BY
    CAST(SUBSTRING(c.case_number, 3) AS UNSIGNED) ASC,
    c.id ASC")->fetchAll();

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

$highlightCase = trim($_GET['case'] ?? '');
$isReopenedBanner = !empty($_GET['reopened']);
$reopenedBannerHtml = '';
if ($isReopenedBanner) {
    $hlCaseSafe = htmlspecialchars($highlightCase ?: 'selected');
    $reopenedBannerHtml = <<<HTML
<div style="background:#fdf4ff;border:1.5px solid #d8b4fe;border-radius:12px;padding:14px 18px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;gap:12px;box-shadow:0 2px 10px rgba(126,34,206,0.08);animation:fadeIn .3s ease">
  <div style="display:flex;align-items:center;gap:12px">
    <div style="width:38px;height:38px;border-radius:10px;background:#fae8ff;color:#7e22ce;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">
      <i class="fas fa-reply-all"></i>
    </div>
    <div>
      <div style="font-weight:700;font-size:14px;color:#581c87">Case {$hlCaseSafe} Reopened Successfully!</div>
      <div style="font-size:12.5px;color:#7e22ce">This case has been restored to active status from follow-up appeals and is prioritized below at the top of All Cases.</div>
    </div>
  </div>
  <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;color:#7e22ce;font-size:16px;cursor:pointer;padding:4px" title="Dismiss"><i class="fas fa-times"></i></button>
</div>
HTML;
}

$rows = '';
foreach ($cases as $c) {
    $rawType = strtolower($c['concern_type']);
    $type = ucfirst(str_replace('_',' ',$c['concern_type']));
    $mediaBadges = '';
    $hasMedia = (!empty($c['evidence_files']) && $c['evidence_files'] !== '[]');
    $hasWitness = !empty($c['witnesses']);
    $isPeer = !empty($c['is_peer_report']);
    $isReopened = (!empty($c['follow_up_msg']) && empty($c['is_follow_up']) && in_array($c['status'], ['pending', 'under_review', 'ongoing']));

    if ($isReopened) {
        $mediaBadges .= "<span class='badge-witness' style='background:#fdf4ff;color:#7e22ce;border-color:#e9d5ff' title='Appealed by student and reopened by Guidance'><i class='fas fa-reply-all'></i> Reopened</span>";
    }
    if ($hasMedia) {
        $evArray = is_string($c['evidence_files']) ? json_decode($c['evidence_files'], true) : $c['evidence_files'];
        $cnt = is_array($evArray) ? count($evArray) : 1;
        $mediaBadges .= "<span class='badge-evidence' title='{$cnt} attached photo/video evidence file(s)'><i class='fas fa-paperclip'></i> {$cnt} Media</span>";
    }
    if ($hasWitness) {
        $mediaBadges .= "<span class='badge-witness' title='Witnesses mentioned in case report'><i class='fas fa-user-group'></i> Witness</span>";
    }
    if ($isPeer) {
        $mediaBadges .= "<span class='badge-witness' style='background:#fdf2f8;color:#be185d;border-color:#fbcfe8' title='Peer Report: submitted on behalf of another student'><i class='fas fa-hand-holding-heart'></i> Peer</span>";
    }
    $date = date('M d, Y', strtotime($c['submitted_at']));

    if ($isPeer) {
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
    if ($isPeer && !empty($c['reported_student_grade'])) {
        $gradeDisplay = htmlspecialchars($c['reported_student_grade']);
    }

    // Build rich searchable text for client-side live filter
    $searchTokens = [
        $c['case_number'],
        $c['student_name'],
        $c['reported_student_name'] ?? '',
        $c['grade_section'] ?? '',
        $c['reported_student_grade'] ?? '',
        $rawType,
        str_replace('_', ' ', $rawType),
        $type,
        $c['status'],
        str_replace('_', ' ', $c['status']),
        $c['priority'],
        $isReopened ? 'reopened appeal follow-up' : '',
        $isPeer ? 'peer peer-report' : '',
        $hasWitness ? 'witness' : '',
        $hasMedia ? 'media evidence' : '',
        $c['is_anonymous'] ? 'anonymous anon' : '',
        $date
    ];
    $searchData = htmlspecialchars(strtolower(implode(' ', array_filter($searchTokens))), ENT_QUOTES);

    $isHighlighted = ($highlightCase && $c['case_number'] === $highlightCase);
    $rowClass = 'case-row' . ($isHighlighted ? ' case-row-highlighted' : '');
    $rowStyle = $isHighlighted ? "style='background:rgba(126,34,206,0.07);border-left:4px solid #7e22ce'" : "";

    $rows .= "<tr class='$rowClass' data-search=\"$searchData\" $rowStyle>
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
if (!$rows) $rows = "<tr class='no-active-cases-row'><td colspan='8' style='text-align:center;color:var(--text-3);padding:40px'>".
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
$reopenedBannerHtml

<!-- Page Header Card (matching modern reference UI) -->
<div class="cases-page-header">
  <div class="cph-info-row">
    <div class="cph-icon-box">
      <i class="fas fa-folder-open"></i>
    </div>
    <div class="cph-text-col">
      <h2 class="cph-title">All Active Cases</h2>
      <p class="cph-desc">Review, manage, and filter guidance cases <span class="cph-count-pill" id="activeCasesCount">($totalActive Active)</span></p>
    </div>
  </div>
  <a href="admin-archive.php" class="cph-action-btn">
    <i class="fas fa-archive"></i> View Cases Archive
  </a>
</div>

<!-- Filter Card (6 Equal-Sized Filter Boxes divided by 2 on Mobile) -->
<div class="cases-filter-card">
  <!-- Search Row -->
  <div class="filter-search-row">
    <div class="filter-search-box-wrap">
      <i class="fas fa-search filter-search-ico"></i>
      <input type="text" class="filter-search-input-field" id="searchQ" value="$sqVal" placeholder="Search by name, case #, type, grade, status…"
        autocomplete="off"
        onkeydown="if(event.key==='Enter'){event.preventDefault();doSearch();}"/>
    </div>
    <button type="button" class="btn-search-trigger" onclick="doSearch()"><i class="fas fa-search"></i> Search</button>
  </div>

  <!-- 6 Equal-Sized Filter Boxes (Divided by 2) -->
  <div class="filter-six-grid">
    <!-- 1. Status -->
    <div class="filter-equal-box">
      <select class="filter-equal-control" onchange="applyFilter('status',this.value)">
        <option value="">All Status</option>
        <option value="pending"      $ss1>Pending</option>
        <option value="under_review" $ss2>Under Review</option>
        <option value="ongoing"      $ss3>Ongoing</option>
      </select>
    </div>

    <!-- 2. Priority -->
    <div class="filter-equal-box">
      <select class="filter-equal-control" onchange="applyFilter('priority',this.value)">
        <option value="">All Priority</option>
        <option value="high"   $sp1>High</option>
        <option value="medium" $sp2>Medium</option>
        <option value="low"    $sp3>Low</option>
      </select>
    </div>

    <!-- 3. Types -->
    <div class="filter-equal-box">
      <select class="filter-equal-control" onchange="applyFilter('type',this.value)">
        <option value="">All Types</option>
        <option value="bullying"        $st1>Bullying</option>
        <option value="academic_stress" $st2>Academic Stress</option>
        <option value="emotional"       $st3>Emotional</option>
        <option value="family"          $st4>Family</option>
        <option value="peer_conflict"   $st5>Peer Conflict</option>
        <option value="behavioral"      $st6>Behavioral</option>
        <option value="other"           $st7>Other</option>
      </select>
    </div>

    <!-- 4. Grades -->
    <div class="filter-equal-box">
      <select class="filter-equal-control" id="gradeFilterSel" onchange="gradeFilterChange()">
        <option value="">All Grades</option>
        <option value="7"  $sg7>Grade 7</option>
        <option value="8"  $sg8>Grade 8</option>
        <option value="9"  $sg9>Grade 9</option>
        <option value="10" $sg10>Grade 10</option>
      </select>
    </div>

    <!-- 5. Section -->
    <div class="filter-equal-box">
      <select class="filter-equal-control" id="sectionFilterSel" onchange="applyFilter('section',this.value)">
        <option value="">All Sections</option>
      </select>
    </div>

    <!-- 6. Reset -->
    <div class="filter-equal-box">
      <button type="button" class="filter-equal-control filter-equal-reset" onclick="clearFilters()">
        <i class="fas fa-rotate-left"></i> Reset
      </button>
    </div>
  </div>
</div>

<!-- Case Records Table Card -->
<div class="cases-table-card">
  <div class="cases-table-card-header">
    <div class="ctc-title"><i class="fas fa-table-list" style="color:var(--maroon)"></i> Case Records</div>
    <div class="ctc-hint"><i class="fas fa-arrows-left-right"></i> Swipe table horizontally to see all columns</div>
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
  const q = document.getElementById('searchQ')?.value.trim();
  if (q) url.searchParams.set('q', q);
  window.location = url;
}

let searchDebounceTimer = null;

function filterCasesLive(query) {
  const q = (query || '').trim().toLowerCase();
  const rows = document.querySelectorAll('.data-table tbody tr.case-row');
  const countBadge = document.getElementById('activeCasesCount');
  const tbody = document.querySelector('.data-table tbody');
  
  if (!tbody) return;

  // Remove existing dynamic no-match row if any
  const existingNoMatch = tbody.querySelector('.live-no-match-row');
  if (existingNoMatch) existingNoMatch.remove();

  if (!rows || rows.length === 0) return;

  if (!q) {
    rows.forEach(r => r.style.display = '');
    if (countBadge) countBadge.textContent = '(' + rows.length + ')';
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => syncSearchUrl(''), 300);
    return;
  }

  const tokens = q.split(/\s+/).filter(Boolean);
  let visibleCount = 0;

  rows.forEach(r => {
    const searchData = r.getAttribute('data-search') || '';
    const matchesAll = tokens.every(tok => searchData.includes(tok));
    if (matchesAll) {
      r.style.display = '';
      visibleCount++;
    } else {
      r.style.display = 'none';
    }
  });

  if (visibleCount === 0) {
    const noMatchTr = document.createElement('tr');
    noMatchTr.className = 'live-no-match-row';
    noMatchTr.innerHTML = '<td colspan="8" style="text-align:center;color:var(--text-3);padding:40px">' +
      '<i class="fas fa-search" style="margin-right:8px;opacity:0.5;font-size:16px"></i>' +
      'No cases found matching "<strong>' + escapeHtml(query) + '</strong>".</td>';
    tbody.appendChild(noMatchTr);
  }

  if (countBadge) {
    countBadge.textContent = '(' + visibleCount + (visibleCount !== rows.length ? ' of ' + rows.length : '') + ')';
  }

  clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => syncSearchUrl(q), 400);
}

function syncSearchUrl(q) {
  try {
    const url = new URL(window.location);
    if (q) url.searchParams.set('q', q);
    else url.searchParams.delete('q');
    window.history.replaceState({}, '', url);
  } catch (e) {}
}

function doSearch() {
  const input = document.getElementById('searchQ');
  const val = input ? input.value.trim() : '';
  filterCasesLive(val);
  syncSearchUrl(val);
}

function applyFilter(key, val) {
  const url = new URL(window.location);
  if (val) url.searchParams.set(key, val);
  else url.searchParams.delete(key);
  const q = document.getElementById('searchQ')?.value.trim();
  if (q) url.searchParams.set('q', q);
  window.location = url;
}

function clearFilters() {
  window.location = 'admin-cases.php';
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/[&<>"']/g, function(m) {
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
  });
}

// Live typing listener + pre-population
document.addEventListener('DOMContentLoaded', function() {
  const input = document.getElementById('searchQ');
  if (input) {
    input.addEventListener('input', function() {
      filterCasesLive(this.value);
    });
    if (input.value.trim()) {
      filterCasesLive(input.value.trim());
    }
  }

  if (CURRENT_GRADE) {
    populateSections(CURRENT_GRADE, CURRENT_SECTION);
  } else {
    populateSections('', '');
  }
});
</script>
HTML;

renderLayout($user, 'All Cases', 'cases', $content);




