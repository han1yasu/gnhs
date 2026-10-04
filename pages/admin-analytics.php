<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin');
$db   = getDB();

// Overall stats
$total    = (int)$db->query("SELECT COUNT(*) FROM cases")->fetchColumn();
$resolved = (int)$db->query("SELECT COUNT(*) FROM cases WHERE status='resolved'")->fetchColumn();
$highPrio = (int)$db->query("SELECT COUNT(*) FROM cases WHERE priority='high'")->fetchColumn();
$avgDays  = $db->query("SELECT ROUND(AVG(DATEDIFF(updated_at,submitted_at)),1) FROM cases WHERE status='resolved'")->fetchColumn() ?? 'N/A';
$rate     = $total > 0 ? round($resolved/$total*100) : 0;

// Cases by type
$byType = $db->query("SELECT concern_type, COUNT(*) as cnt FROM cases GROUP BY concern_type ORDER BY cnt DESC")->fetchAll();

// Cases by status
$byStatus = $db->query("SELECT status, COUNT(*) as cnt FROM cases GROUP BY status")->fetchAll();

// Continuous last 6 months calculation
$sixMonthsAgo = date('Y-m-01 00:00:00', strtotime('-5 months'));
$stmt = $db->prepare("
    SELECT DATE_FORMAT(submitted_at, '%Y-%m') as ym, COUNT(*) as cnt 
    FROM cases 
    WHERE submitted_at >= ? 
    GROUP BY ym
");
$stmt->execute([$sixMonthsAgo]);
$monthCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$initMonthLabels = [];
$initMonthData = [];
$initPointsMeta = [];
for ($i = 5; $i >= 0; $i--) {
    $ym = date('Y-m', strtotime("-$i month"));
    $initMonthLabels[] = date('M Y', strtotime("-$i month"));
    $initMonthData[] = (int)($monthCounts[$ym] ?? 0);
    $initPointsMeta[] = [
        'date_from' => date('Y-m-01 00:00:00', strtotime("-$i month")),
        'date_to'   => date('Y-m-t 23:59:59', strtotime("-$i month")),
        'label'     => date('F Y', strtotime("-$i month")),
        'period'    => 'month'
    ];
}
$monthLabels     = json_encode($initMonthLabels);
$monthData       = json_encode($initMonthData);
$monthPointsMeta = json_encode($initPointsMeta);
$initialTotal    = array_sum($initMonthData);
$initialPeak     = !empty($initMonthData) ? max($initMonthData) : 0;

// Available months for timeline dropdown
$caseMonths = $db->query("
    SELECT DISTINCT DATE_FORMAT(submitted_at, '%Y-%m') as ym, 
                    DATE_FORMAT(submitted_at, '%M %Y') as label 
    FROM cases 
    WHERE submitted_at IS NOT NULL 
    ORDER BY ym DESC
")->fetchAll(PDO::FETCH_ASSOC);

$availMonthsMap = [];
foreach ($caseMonths as $cm) {
    $availMonthsMap[$cm['ym']] = $cm['label'];
}
for ($i = 0; $i < 12; $i++) {
    $ym = date('Y-m', strtotime("-$i month"));
    $lbl = date('F Y', strtotime("-$i month"));
    if (!isset($availMonthsMap[$ym])) {
        $availMonthsMap[$ym] = $lbl;
    }
}
krsort($availMonthsMap);

$monthSelectOptions = '';
$currYm = date('Y-m');
foreach ($availMonthsMap as $ym => $lbl) {
    $sel = ($ym === $currYm) ? 'selected' : '';
    $monthSelectOptions .= "<option value='$ym' $sel>$lbl</option>";
}

// Cases by GRADE (extract grade number from grade_section)
$byGrade = $db->query("SELECT
    CASE
        WHEN s.grade_section LIKE 'Grade 7%' THEN 'Grade 7'
        WHEN s.grade_section LIKE 'Grade 8%' THEN 'Grade 8'
        WHEN s.grade_section LIKE 'Grade 9%' THEN 'Grade 9'
        WHEN s.grade_section LIKE 'Grade 10%' THEN 'Grade 10'
        ELSE 'Unknown'
    END AS grade_level,
    COUNT(*) as cnt
    FROM cases c JOIN users s ON c.student_id=s.id
    WHERE s.grade_section IS NOT NULL
    GROUP BY grade_level ORDER BY grade_level ASC")->fetchAll();

// Cases by AGE
$byAge = $db->query("SELECT s.age, COUNT(c.id) as cnt
    FROM cases c JOIN users s ON c.student_id=s.id
    WHERE s.age IS NOT NULL
    GROUP BY s.age ORDER BY s.age ASC")->fetchAll();

// Cases by concern type PER GRADE
$byTypePerGrade = $db->query("SELECT
    CASE
        WHEN s.grade_section LIKE 'Grade 7%' THEN 'Grade 7'
        WHEN s.grade_section LIKE 'Grade 8%' THEN 'Grade 8'
        WHEN s.grade_section LIKE 'Grade 9%' THEN 'Grade 9'
        WHEN s.grade_section LIKE 'Grade 10%' THEN 'Grade 10'
        ELSE 'Unknown'
    END AS grade_level,
    c.concern_type, COUNT(*) as cnt
    FROM cases c JOIN users s ON c.student_id=s.id
    WHERE s.grade_section IS NOT NULL
    GROUP BY grade_level, c.concern_type
    ORDER BY grade_level, cnt DESC")->fetchAll();

// Top section with most cases
$topSections = $db->query("SELECT s.grade_section, COUNT(*) as cnt
    FROM cases c JOIN users s ON c.student_id=s.id
    WHERE s.grade_section IS NOT NULL
    GROUP BY s.grade_section ORDER BY cnt DESC LIMIT 8")->fetchAll();

// High priority by grade
$highByGrade = $db->query("SELECT
    CASE
        WHEN s.grade_section LIKE 'Grade 7%' THEN 'Grade 7'
        WHEN s.grade_section LIKE 'Grade 8%' THEN 'Grade 8'
        WHEN s.grade_section LIKE 'Grade 9%' THEN 'Grade 9'
        WHEN s.grade_section LIKE 'Grade 10%' THEN 'Grade 10'
        ELSE 'Unknown'
    END AS grade_level, COUNT(*) as cnt
    FROM cases c JOIN users s ON c.student_id=s.id
    WHERE c.priority='high' AND s.grade_section IS NOT NULL
    GROUP BY grade_level ORDER BY cnt DESC")->fetchAll();

// Encode all for JS
$typeLabels   = json_encode(array_column($byType,'concern_type'));
$typeData     = json_encode(array_column($byType,'cnt'));
$statusLabels = json_encode(array_column($byStatus,'status'));
$statusData   = json_encode(array_column($byStatus,'cnt'));
$gradeLabels  = json_encode(array_column($byGrade,'grade_level'));
$gradeData    = json_encode(array_column($byGrade,'cnt'));
$sectionLabels= json_encode(array_column($topSections,'grade_section'));
$sectionData  = json_encode(array_column($topSections,'cnt'));
$highGradeLabels = json_encode(array_column($highByGrade,'grade_level'));
$highGradeData   = json_encode(array_column($highByGrade,'cnt'));
$ageLabels    = json_encode(array_column($byAge,'age'));
$ageData      = json_encode(array_column($byAge,'cnt'));

// --- Teacher Referrals Stats ---
$totalReferrals    = (int)$db->query("SELECT COUNT(*) FROM referrals")->fetchColumn();
$pendingReferrals  = (int)$db->query("SELECT COUNT(*) FROM referrals WHERE status='pending'")->fetchColumn();
$ongoingReferrals  = (int)$db->query("SELECT COUNT(*) FROM referrals WHERE status IN ('under_review','ongoing')")->fetchColumn();
$resolvedReferrals = (int)$db->query("SELECT COUNT(*) FROM referrals WHERE status IN ('resolved','closed')")->fetchColumn();
$urgentReferrals   = (int)$db->query("SELECT COUNT(*) FROM referrals WHERE urgency='urgent'")->fetchColumn();

// Referrals by Status
$refByStatus = $db->query("SELECT status, COUNT(*) as cnt FROM referrals GROUP BY status")->fetchAll();

// Referrals by Concern Type
$refByType = $db->query("SELECT concern_type, COUNT(*) as cnt FROM referrals GROUP BY concern_type ORDER BY cnt DESC")->fetchAll();

// Top referring teachers
$topTeachers = $db->query("SELECT CONCAT(u.first_name, ' ', u.last_name) as teacher_name, COUNT(*) as cnt
    FROM referrals r JOIN users u ON r.teacher_id = u.id
    GROUP BY r.teacher_id ORDER BY cnt DESC LIMIT 5")->fetchAll();

$refStatusLabels = json_encode(array_column($refByStatus, 'status'));
$refStatusData   = json_encode(array_column($refByStatus, 'cnt'));
$refTypeLabels   = json_encode(array_column($refByType, 'concern_type'));
$refTypeData     = json_encode(array_column($refByType, 'cnt'));
$topTeacherLabels= json_encode(array_column($topTeachers, 'teacher_name'));
$topTeacherData  = json_encode(array_column($topTeachers, 'cnt'));

// Build per-grade type breakdown table
$gradeTypeRows = '';
$gradeGroups = [];
foreach ($byTypePerGrade as $row) {
    $gradeGroups[$row['grade_level']][] = $row;
}
foreach (['Grade 7','Grade 8','Grade 9','Grade 10'] as $g) {
    if (!isset($gradeGroups[$g])) continue;
    $rows = $gradeGroups[$g];
    $total_g = array_sum(array_column($rows,'cnt'));
    $gradeTypeRows .= "<tr style='background:var(--maroon-pale)'><td colspan='3' style='font-weight:800;color:var(--maroon);font-size:13px'><i class='fas fa-layer-group'></i> $g — $total_g total cases</td></tr>";
    foreach ($rows as $r) {
        $typeLabel = ucfirst(str_replace('_',' ',$r['concern_type']));
        $pct = $total_g > 0 ? round($r['cnt']/$total_g*100) : 0;
        $gradeTypeRows .= "<tr>
            <td style='padding-left:24px'>$typeLabel</td>
            <td style='text-align:center;font-weight:700'>{$r['cnt']}</td>
            <td><div style='background:var(--bg2);border-radius:50px;overflow:hidden;height:8px;width:100%'><div style='background:var(--maroon);height:100%;width:{$pct}%;border-radius:50px'></div></div></td>
        </tr>";
    }
}
if (!$gradeTypeRows) $gradeTypeRows = "<tr><td colspan='3' style='text-align:center;color:var(--text-3);padding:20px'>Not enough data yet.</td></tr>";

$content = <<<HTML
<div class="summary-cards" style="grid-template-columns:repeat(4,1fr)">
  <div class="sum-card">
    <div class="sum-icon" style="background:#fff0f0;color:var(--maroon)"><i class="fas fa-folder-open"></i></div>
    <div><div class="sum-num">$total</div><div class="sum-label">Total Cases</div></div>
  </div>
  <div class="sum-card">
    <div class="sum-icon" style="background:#f0fdf4;color:#16a34a"><i class="fas fa-check-circle"></i></div>
    <div><div class="sum-num">{$rate}%</div><div class="sum-label">Resolution Rate</div></div>
  </div>
  <div class="sum-card">
    <div class="sum-icon" style="background:#fef2f2;color:#dc2626"><i class="fas fa-exclamation-triangle"></i></div>
    <div><div class="sum-num">$highPrio</div><div class="sum-label">High Priority</div></div>
  </div>
  <div class="sum-card">
    <div class="sum-icon" style="background:#eff6ff;color:#2563eb"><i class="fas fa-clock"></i></div>
    <div><div class="sum-num">$avgDays</div><div class="sum-label">Avg Days to Resolve</div></div>
  </div>
</div>

<!-- Row 1: Interactive Cases Timeline + Status donut -->
<div class="analytics-grid" style="margin-bottom:22px">
  <div class="content-card" style="position:relative">
    <div class="card-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:12px">
      <div>
        <h3 style="font-size:15px;font-weight:800;display:flex;align-items:center;gap:8px;margin:0">
          <i class="fas fa-chart-line" style="color:var(--maroon)"></i>
          <span id="timelineTitleText">Cases Over Last 6 Months</span>
        </h3>
        <div id="timelineSubtitleText" style="color:var(--text-3);font-size:12px;margin-top:3px;font-weight:normal">
          Monthly breakdown for the past 6 months
        </div>
      </div>

      <!-- Controls: Range + Specific Month + Day/Week Toggle -->
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <!-- Range Dropdown -->
        <div style="display:inline-flex;align-items:center;gap:5px">
          <label for="timelineRangeSelect" style="font-size:11px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:0.5px">Timeframe</label>
          <select id="timelineRangeSelect" onchange="onTimelineRangeChange()" style="padding:5px 10px;font-size:12px;font-weight:600;border:1px solid var(--border);border-radius:6px;background:var(--card-bg,#fff);color:var(--text);cursor:pointer;outline:none">
            <option value="last_6_months" selected>Last 6 Months</option>
            <option value="specific_month">Specific Month</option>
            <option value="last_8_weeks">Last 8 Weeks (Weekly)</option>
            <option value="last_30_days">Last 30 Days (Daily)</option>
            <option value="last_7_days">Last 7 Days (Daily)</option>
          </select>
        </div>

        <!-- Specific Month Dropdown (shown when specific_month is selected) -->
        <div id="timelineMonthWrapper" style="display:none;align-items:center;gap:5px">
          <label for="timelineMonthSelect" style="font-size:11px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:0.5px">Month</label>
          <select id="timelineMonthSelect" onchange="onTimelineMonthChange()" style="padding:5px 10px;font-size:12px;font-weight:600;border:1px solid var(--border);border-radius:6px;background:var(--card-bg,#fff);color:var(--text);cursor:pointer;outline:none">
            $monthSelectOptions
          </select>
        </div>

        <!-- Day / Week Granularity Toggle -->
        <div id="timelineGranularityWrapper" style="display:inline-flex;align-items:center;gap:5px">
          <label style="font-size:11px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:0.5px">View by</label>
          <div style="display:inline-flex;border:1px solid var(--border);border-radius:6px;padding:2px;background:var(--bg2,#f3f4f6)">
            <button type="button" id="btnGranularityDay" onclick="setGranularity('day')" title="View by Day" style="border:none;background:transparent;color:var(--text-2);padding:3px 10px;font-size:11px;font-weight:700;border-radius:4px;cursor:pointer;transition:all .15s">Day</button>
            <button type="button" id="btnGranularityWeek" onclick="setGranularity('week')" title="View by Week" style="border:none;background:transparent;color:var(--text-2);padding:3px 10px;font-size:11px;font-weight:700;border-radius:4px;cursor:pointer;transition:all .15s">Week</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Stats Bar -->
    <div style="display:flex;align-items:center;justify-content:space-between;background:var(--maroon-pale,#fff0f0);border:1px solid rgba(85,0,0,0.08);border-radius:8px;padding:6px 12px;margin-bottom:14px;font-size:12px;flex-wrap:wrap;gap:8px">
      <div style="display:flex;align-items:center;gap:14px">
        <span><strong style="color:var(--maroon)">Total in View:</strong> <span id="timelineTotalCount" style="font-weight:800;color:var(--text)">$initialTotal</span> cases</span>
        <span style="color:var(--border)">|</span>
        <span><strong style="color:var(--maroon)">Peak Volume:</strong> <span id="timelinePeakCount" style="font-weight:800;color:var(--text)">$initialPeak</span></span>
      </div>
      <div style="font-size:11px;color:var(--text-3);display:flex;align-items:center;gap:6px">
        <span style="background:rgba(85,0,0,0.07);color:var(--maroon);padding:2px 8px;border-radius:12px;font-weight:700;display:inline-flex;align-items:center;gap:4px">
          <i class="fas fa-mouse-pointer" style="font-size:10px"></i> Click any dot to view cases
        </span>
      </div>
    </div>

    <!-- Chart container with loading overlay -->
    <div style="position:relative;min-height:180px">
      <div id="timelineLoadingOverlay" style="display:none;position:absolute;inset:0;background:rgba(255,255,255,0.75);backdrop-filter:blur(1px);z-index:4;align-items:center;justify-content:center;border-radius:8px">
        <div style="font-size:12px;font-weight:700;color:var(--maroon);display:flex;align-items:center;gap:6px">
          <i class="fas fa-spinner fa-spin"></i> Updating chart...
        </div>
      </div>
      <canvas id="monthChart" height="110"></canvas>
    </div>
  </div>
  <div class="content-card">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;gap:8px">
      <h3 style="margin:0"><i class="fas fa-chart-pie"></i> Cases by Status</h3>
      <span style="background:rgba(85,0,0,0.07);color:var(--maroon);padding:2px 8px;border-radius:12px;font-size:11px;font-weight:700;display:inline-flex;align-items:center;gap:4px">
        <i class="fas fa-mouse-pointer" style="font-size:10px"></i> Click slice to view
      </span>
    </div>
    <canvas id="statusChart" height="180"></canvas>
  </div>
</div>

<!-- Row 2: Cases by Grade + High Priority by Grade + Cases by Age -->
<div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:22px;margin-bottom:22px">
  <div class="content-card">
    <div class="card-header"><h3><i class="fas fa-layer-group"></i> Cases by Grade Level</h3></div>
    <canvas id="gradeChart" height="160"></canvas>
  </div>
  <div class="content-card">
    <div class="card-header"><h3><i class="fas fa-exclamation-triangle"></i> High Priority by Grade</h3></div>
    <canvas id="highGradeChart" height="160"></canvas>
  </div>
  <div class="content-card">
    <div class="card-header"><h3><i class="fas fa-user-clock"></i> Cases by Age</h3></div>
    <canvas id="ageChart" height="160"></canvas>
  </div>
</div>

<!-- Row 3: Concern type + Top sections -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-bottom:22px">
  <div class="content-card">
    <div class="card-header"><h3><i class="fas fa-chart-bar"></i> Cases by Concern Type</h3></div>
    <canvas id="typeChart" height="160"></canvas>
  </div>
  <div class="content-card">
    <div class="card-header"><h3><i class="fas fa-users"></i> Top Sections by Case Count</h3></div>
    <canvas id="sectionChart" height="160"></canvas>
  </div>
</div>

<!-- Row 4: Concern type breakdown per grade -->
<div class="content-card">
  <div class="card-header"><h3><i class="fas fa-table"></i> Concern Type Breakdown by Grade</h3></div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Concern Type</th><th style="text-align:center">Count</th><th style="width:200px">Distribution</th></tr></thead>
      <tbody>$gradeTypeRows</tbody>
    </table>
  </div>
</div>

<!-- Row 5: Teacher Referrals Analytics -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin:40px 0 16px;border-bottom:1px solid var(--border);padding-bottom:12px;">
  <h3 style="margin:0;font-size:18px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:10px;">
    <i class="fas fa-chalkboard-teacher" style="color:var(--maroon)"></i> Teacher Referrals Analytics 
    <span style="font-size:14px;font-weight:600;color:var(--text-3);">(Total: $totalReferrals)</span>
  </h3>
  <div style="display:flex;gap:10px;align-items:center;">
    <a href="admin-referrals.php" class="btn-sm-outline" style="text-decoration:none;"><i class="fas fa-list-alt"></i> Manage Referrals</a>
    <a href="admin-referrals-archive.php" class="btn-sm-outline" style="text-decoration:none;"><i class="fas fa-archive"></i> Referrals Archive</a>
  </div>
</div>

<div class="summary-cards" style="grid-template-columns:repeat(4,1fr);margin-bottom:22px">
  <div class="sum-card">
    <div class="sum-icon" style="background:#fff0f0;color:var(--maroon)"><i class="fas fa-exchange-alt"></i></div>
    <div><div class="sum-num">$totalReferrals</div><div class="sum-label">Total Referrals</div></div>
  </div>
  <div class="sum-card">
    <div class="sum-icon" style="background:#fffbeb;color:#d97706"><i class="fas fa-clock"></i></div>
    <div><div class="sum-num">$pendingReferrals</div><div class="sum-label">Pending Review</div></div>
  </div>
  <div class="sum-card">
    <div class="sum-icon" style="background:#eff6ff;color:#2563eb"><i class="fas fa-spinner"></i></div>
    <div><div class="sum-num">$ongoingReferrals</div><div class="sum-label">In Progress</div></div>
  </div>
  <div class="sum-card">
    <div class="sum-icon" style="background:#f0fdf4;color:#16a34a"><i class="fas fa-check-double"></i></div>
    <div><div class="sum-num">$resolvedReferrals</div><div class="sum-label">Resolved / Archived</div></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-bottom:22px">
  <div class="content-card">
    <div class="card-header"><h3><i class="fas fa-chart-pie"></i> Referrals by Status</h3></div>
    <canvas id="refStatusChart" height="150"></canvas>
  </div>
  <div class="content-card">
    <div class="card-header"><h3><i class="fas fa-medal"></i> Top Referring Teachers</h3></div>
    <canvas id="refTeacherChart" height="150"></canvas>
  </div>
</div>

<div class="content-card" style="margin-bottom:22px">
  <div class="card-header"><h3><i class="fas fa-chart-bar"></i> Referrals by Concern Type</h3></div>
  <canvas id="refTypeChart" height="80"></canvas>
</div>

<style>
.drilldown-case-card {
  transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}
.drilldown-case-card:hover {
  border-color: var(--maroon) !important;
  box-shadow: 0 4px 14px rgba(85,0,0,0.08) !important;
  transform: translateY(-1px);
}

/* Position drilldown & review modals near the top of the viewport without needing to scroll down */
#casesDrilldownModal.modal-overlay,
#caseReviewModal.modal-overlay {
  position: fixed !important;
  top: 0 !important;
  left: 0 !important;
  right: 0 !important;
  bottom: 0 !important;
  width: 100vw !important;
  height: 100vh !important;
  display: flex !important;
  align-items: flex-start !important;
  justify-content: center !important;
  padding: 40px 20px 20px 20px !important;
  overflow-y: auto !important;
  background: rgba(20, 0, 0, 0.7) !important;
  backdrop-filter: blur(12px) !important;
  -webkit-backdrop-filter: blur(12px) !important;
  z-index: 10000 !important;
}

#casesDrilldownModal.hidden,
#caseReviewModal.hidden {
  display: none !important;
}

#casesDrilldownModal .modal-box,
#caseReviewModal .modal-box {
  margin: 0 auto !important;
  max-height: calc(100vh - 75px) !important;
}
</style>

<!-- Drilldown Modal for Timeline Points & Status Slices -->
<div class="modal-overlay hidden" id="casesDrilldownModal" onclick="if(event.target===this)closeDrilldownModal()" style="z-index:10000">
  <div class="modal-box wide-modal" style="background:#ffffff !important;border-radius:20px;box-shadow:0 25px 60px rgba(0,0,0,0.4);max-width:780px;width:95%;padding:26px;display:flex;flex-direction:column;position:relative;z-index:10001;color:#1f2937">
    <!-- Modal Header -->
    <div style="display:flex;align-items:flex-start;justify-content:space-between;padding-bottom:14px;border-bottom:1px solid #e5e7eb;margin-bottom:16px">
      <div>
        <h3 id="drilldownModalTitle" style="margin:0;font-size:18px;font-weight:800;color:#111827;display:flex;align-items:center;gap:8px">
          <i class="fas fa-folder-open" style="color:var(--maroon)"></i> Cases
        </h3>
        <p id="drilldownModalSubtitle" style="margin:4px 0 0 0;font-size:13px;color:#6b7280">Showing cases submitted for this selection</p>
      </div>
      <button type="button" class="modal-close" onclick="closeDrilldownModal()" style="position:static;width:34px;height:34px;cursor:pointer;background:#f3f4f6;border-radius:50%;border:none;display:flex;align-items:center;justify-content:center;color:#4b5563"><i class="fas fa-times"></i></button>
    </div>

    <!-- Modal Scrollable Content -->
    <div id="drilldownModalBody" style="overflow-y:auto;padding-right:6px;flex:1;max-height:calc(100vh - 240px);min-height:120px;color:#1f2937">
      <!-- Dynamic list loaded via AJAX -->
    </div>

    <!-- Modal Footer -->
    <div style="display:flex;align-items:center;justify-content:space-between;padding-top:14px;border-top:1px solid #e5e7eb;margin-top:14px;font-size:13px">
      <span id="drilldownModalCount" style="color:#6b7280;font-weight:600">0 cases found</span>
      <button type="button" class="btn-sm-outline" onclick="closeDrilldownModal()" style="cursor:pointer">Close</button>
    </div>
  </div>
</div>

<!-- Case Review Modal (Used when counselor clicks 'Quick Review') -->
<div class="modal-overlay hidden" id="caseReviewModal" onclick="if(event.target===this)document.getElementById('caseReviewModal').classList.add('hidden')" style="z-index:10005">
  <div class="modal-box" style="max-width:920px;width:95%;padding:28px;position:relative;background:#ffffff !important;border-radius:20px;box-shadow:0 25px 60px rgba(0,0,0,0.4);max-height:calc(100vh - 75px);overflow-y:auto;color:#1f2937">
    <button type="button" class="modal-close" onclick="document.getElementById('caseReviewModal').classList.add('hidden')" style="cursor:pointer;position:absolute;top:18px;right:18px"><i class="fas fa-times"></i></button>
    <h2 class="modal-title" style="text-align:left;font-size:20px;margin-bottom:16px;color:#111827"><i class="fas fa-clipboard-check" style="color:var(--maroon)"></i> Review Case</h2>
    <div id="caseReviewBody"></div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
const maroon='#550000', maroonMid='#7a0000', maroonLight='#a00000';
const typeLabels  =$typeLabels;  const typeData  =$typeData;
const statusLabels=$statusLabels; const statusData=$statusData;
const monthLabels =$monthLabels;  const monthData =$monthData;
const monthPointsMeta =$monthPointsMeta;
const gradeLabels =$gradeLabels;  const gradeData =$gradeData;
const sectionLabels=$sectionLabels; const sectionData=$sectionData;
const highGradeLabels=$highGradeLabels; const highGradeData=$highGradeData;
const ageLabels=$ageLabels; const ageData=$ageData;

const refStatusLabels = $refStatusLabels; const refStatusData = $refStatusData;
const refTypeLabels = $refTypeLabels; const refTypeData = $refTypeData;
const topTeacherLabels = $topTeacherLabels; const topTeacherData = $topTeacherData;

let _timelineChart = null;
let _currentRange = 'last_6_months';
let _currentGranularity = 'day';
let _timelinePointsMeta = monthPointsMeta || [];

_timelineChart = new Chart(document.getElementById('monthChart'), {
  type: 'line',
  data: {
    labels: monthLabels,
    datasets: [{
      label: 'Cases',
      data: monthData,
      borderColor: maroon,
      backgroundColor: 'rgba(85,0,0,.08)',
      tension: 0.35,
      fill: true,
      pointBackgroundColor: maroon,
      pointBorderColor: '#ffffff',
      pointBorderWidth: 2,
      pointRadius: 6,
      pointHoverRadius: 9,
      pointHitRadius: 18,
      borderWidth: 2.5
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: true,
    interaction: {
      mode: 'nearest',
      axis: 'x',
      intersect: false
    },
    onHover: function(event, elements) {
      if (event && event.native && event.native.target) {
        event.native.target.style.cursor = (elements && elements.length > 0) ? 'pointer' : 'default';
      }
    },
    onClick: function(event, elements) {
      var chartElements = elements;
      if (!chartElements || chartElements.length === 0) {
        chartElements = _timelineChart.getElementsAtEventForMode(event, 'nearest', { axis: 'x', intersect: false }, true);
      }
      if (!chartElements || chartElements.length === 0) return;
      var idx = chartElements[0].index;
      var meta = _timelinePointsMeta[idx];
      if (meta) {
        openCasesModalByTimeline(meta);
      }
    },
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label: function(context) {
            var val = context.parsed.y;
            return ' ' + val + (val === 1 ? ' case' : ' cases') + ' (Click dot to view)';
          }
        }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        ticks: { stepSize: 1, precision: 0 }
      },
      x: {
        grid: { display: false }
      }
    }
  }
});

function updateGranularityButtons() {
  const btnDay = document.getElementById('btnGranularityDay');
  const btnWeek = document.getElementById('btnGranularityWeek');
  if (!btnDay || !btnWeek) return;

  if (_currentRange === 'specific_month') {
    btnDay.style.background = (_currentGranularity === 'day') ? 'var(--maroon)' : 'transparent';
    btnDay.style.color = (_currentGranularity === 'day') ? '#fff' : 'var(--text-2)';
    btnWeek.style.background = (_currentGranularity === 'week') ? 'var(--maroon)' : 'transparent';
    btnWeek.style.color = (_currentGranularity === 'week') ? '#fff' : 'var(--text-2)';
  } else if (_currentRange === 'last_8_weeks') {
    btnDay.style.background = 'transparent';
    btnDay.style.color = 'var(--text-2)';
    btnWeek.style.background = 'var(--maroon)';
    btnWeek.style.color = '#fff';
  } else if (_currentRange === 'last_30_days' || _currentRange === 'last_7_days') {
    btnDay.style.background = 'var(--maroon)';
    btnDay.style.color = '#fff';
    btnWeek.style.background = 'transparent';
    btnWeek.style.color = 'var(--text-2)';
  } else {
    btnDay.style.background = 'transparent';
    btnDay.style.color = 'var(--text-2)';
    btnWeek.style.background = 'transparent';
    btnWeek.style.color = 'var(--text-2)';
  }
}

function setGranularity(g) {
  _currentGranularity = g;
  if (_currentRange === 'specific_month') {
    updateGranularityButtons();
    fetchTimeline();
  } else {
    if (g === 'day') {
      document.getElementById('timelineRangeSelect').value = 'last_30_days';
      _currentRange = 'last_30_days';
    } else {
      document.getElementById('timelineRangeSelect').value = 'last_8_weeks';
      _currentRange = 'last_8_weeks';
    }
    updateGranularityButtons();
    fetchTimeline();
  }
}

function onTimelineRangeChange() {
  const range = document.getElementById('timelineRangeSelect').value;
  _currentRange = range;
  const monthWrap = document.getElementById('timelineMonthWrapper');

  if (range === 'specific_month') {
    monthWrap.style.display = 'inline-flex';
    if (!_currentGranularity) _currentGranularity = 'day';
  } else {
    monthWrap.style.display = 'none';
  }

  updateGranularityButtons();
  fetchTimeline();
}

function onTimelineMonthChange() {
  fetchTimeline();
}

function fetchTimeline() {
  const overlay = document.getElementById('timelineLoadingOverlay');
  if (overlay) overlay.style.display = 'flex';

  const monthVal = document.getElementById('timelineMonthSelect') ? document.getElementById('timelineMonthSelect').value : '';
  const params = new URLSearchParams({
    range: _currentRange,
    granularity: _currentGranularity,
    month: monthVal
  });

  fetch('/gnhs-guidance/api/get_analytics_timeline.php?' + params.toString())
    .then(r => r.json())
    .then(res => {
      if (overlay) overlay.style.display = 'none';
      if (!res.success) {
        console.error('Timeline error:', res.message);
        return;
      }

      _timelinePointsMeta = res.points_meta || [];

      if (_timelineChart) {
        _timelineChart.data.labels = res.labels;
        _timelineChart.data.datasets[0].data = res.data;
        _timelineChart.update('active');
      }

      document.getElementById('timelineTitleText').textContent = res.title;
      document.getElementById('timelineSubtitleText').textContent = res.subtitle;
      document.getElementById('timelineTotalCount').textContent = res.total;
      document.getElementById('timelinePeakCount').textContent = res.max;
    })
    .catch(err => {
      if (overlay) overlay.style.display = 'none';
      console.error('Error fetching timeline data:', err);
    });
}

function ensureModalsInBody() {
  const m1 = document.getElementById('casesDrilldownModal');
  const m2 = document.getElementById('caseReviewModal');
  if (m1 && m1.parentElement !== document.body) document.body.appendChild(m1);
  if (m2 && m2.parentElement !== document.body) document.body.appendChild(m2);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', ensureModalsInBody);
} else {
  ensureModalsInBody();
}

function openCasesModalByTimeline(meta) {
  ensureModalsInBody();
  const modal = document.getElementById('casesDrilldownModal');
  const title = document.getElementById('drilldownModalTitle');
  const subtitle = document.getElementById('drilldownModalSubtitle');
  const body = document.getElementById('drilldownModalBody');
  const countSpan = document.getElementById('drilldownModalCount');

  modal.classList.remove('hidden');
  title.innerHTML = '<i class="fas fa-calendar-day" style="color:var(--maroon)"></i> ' + (meta.label || 'Cases on Date');
  subtitle.textContent = 'Loading cases submitted for this timeframe...';
  body.innerHTML = '<div style="text-align:center;padding:50px 20px"><i class="fas fa-spinner fa-spin" style="font-size:32px;color:var(--maroon)"></i><p style="margin-top:12px;color:var(--text-3);font-size:13px">Loading cases...</p></div>';
  countSpan.textContent = 'Loading...';

  const params = new URLSearchParams({
    type: 'timeline',
    date_from: meta.date_from || '',
    date_to: meta.date_to || '',
    label: meta.label || ''
  });

  fetch('/gnhs-guidance/api/get_analytics_cases.php?' + params.toString())
    .then(r => r.json())
    .then(res => {
      if (!res.success) {
        body.innerHTML = '<div style="text-align:center;padding:30px;color:#dc2626"><i class="fas fa-exclamation-circle"></i> ' + (res.message || 'Error loading cases') + '</div>';
        return;
      }
      renderCasesDrilldown(res);
    })
    .catch(err => {
      body.innerHTML = '<div style="text-align:center;padding:30px;color:#dc2626"><i class="fas fa-exclamation-triangle"></i> Network error loading cases</div>';
      console.error(err);
    });
}

function openCasesModalByStatus(statusKey) {
  ensureModalsInBody();
  const modal = document.getElementById('casesDrilldownModal');
  const title = document.getElementById('drilldownModalTitle');
  const subtitle = document.getElementById('drilldownModalSubtitle');
  const body = document.getElementById('drilldownModalBody');
  const countSpan = document.getElementById('drilldownModalCount');

  const prettyStatus = (statusKey || '').replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());

  modal.classList.remove('hidden');
  title.innerHTML = '<i class="fas fa-chart-pie" style="color:var(--maroon)"></i> Cases: ' + prettyStatus;
  subtitle.textContent = 'Loading cases with status ' + prettyStatus + '...';
  body.innerHTML = '<div style="text-align:center;padding:50px 20px"><i class="fas fa-spinner fa-spin" style="font-size:32px;color:var(--maroon)"></i><p style="margin-top:12px;color:var(--text-3);font-size:13px">Loading cases...</p></div>';
  countSpan.textContent = 'Loading...';

  const params = new URLSearchParams({
    type: 'status',
    status: statusKey
  });

  fetch('/gnhs-guidance/api/get_analytics_cases.php?' + params.toString())
    .then(r => r.json())
    .then(res => {
      if (!res.success) {
        body.innerHTML = '<div style="text-align:center;padding:30px;color:#dc2626"><i class="fas fa-exclamation-circle"></i> ' + (res.message || 'Error loading cases') + '</div>';
        return;
      }
      renderCasesDrilldown(res);
    })
    .catch(err => {
      body.innerHTML = '<div style="text-align:center;padding:30px;color:#dc2626"><i class="fas fa-exclamation-triangle"></i> Network error loading cases</div>';
      console.error(err);
    });
}

function closeDrilldownModal() {
  const modal = document.getElementById('casesDrilldownModal');
  if (modal) modal.classList.add('hidden');
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function renderCasesDrilldown(res) {
  const title = document.getElementById('drilldownModalTitle');
  const subtitle = document.getElementById('drilldownModalSubtitle');
  const body = document.getElementById('drilldownModalBody');
  const countSpan = document.getElementById('drilldownModalCount');

  if (res.title) title.innerHTML = '<i class="fas fa-folder-open" style="color:var(--maroon)"></i> ' + escapeHtml(res.title);
  if (res.subtitle) subtitle.textContent = res.subtitle;
  countSpan.textContent = res.count + (res.count === 1 ? ' case found' : ' cases found');

  if (!res.cases || res.cases.length === 0) {
    if (countSpan) countSpan.textContent = '0 cases found';
    body.innerHTML = '<div style="text-align:center;padding:55px 20px;background:#fbfbfb;border-radius:14px;border:1px dashed #d1d5db;margin:8px 0">' +
      '<div style="width:68px;height:68px;border-radius:50%;background:#fee2e2;color:var(--maroon);display:inline-flex;align-items:center;justify-content:center;font-size:26px;margin-bottom:14px"><i class="fas fa-folder-open"></i></div>' +
      '<h4 style="margin:0 0 6px 0;font-size:17px;font-weight:800;color:#111827">No Cases Recorded</h4>' +
      '<p style="margin:0;font-size:13px;color:#6b7280;line-height:1.6">There were <strong>no counseling cases recorded</strong> for this specific date or selection.</p>' +
      '</div>';
    return;
  }

  const prioBadges = {
    high: '<span class="priority-badge priority-high"><i class="fas fa-circle"></i>High</span>',
    medium: '<span class="priority-badge priority-medium"><i class="fas fa-circle"></i>Medium</span>',
    low: '<span class="priority-badge priority-low"><i class="fas fa-circle"></i>Low</span>'
  };

  const statusBadges = {
    pending: '<span class="status-badge status-pending">Pending</span>',
    under_review: '<span class="status-badge status-under_review">Under Review</span>',
    ongoing: '<span class="status-badge status-ongoing">Ongoing</span>',
    resolved: '<span class="status-badge status-resolved">Resolved</span>',
    closed: '<span class="status-badge status-resolved">Closed</span>'
  };

  let html = '';
  res.cases.forEach(function(c) {
    const sBadge = statusBadges[c.status] || ('<span class="status-badge status-pending">' + escapeHtml(c.status) + '</span>');
    const pBadge = prioBadges[c.priority] || ('<span class="priority-badge priority-low">' + escapeHtml(c.priority) + '</span>');
    const descOrSummary = c.ai_summary ? ('<div style="margin-bottom:4px"><span style="font-size:11px;font-weight:700;color:var(--blue);text-transform:uppercase"><i class="fas fa-magic"></i> AI Summary:</span> ' + escapeHtml(c.ai_summary) + '</div>') : ('<div>' + escapeHtml(c.description || '') + '</div>');

    html += '<div class="drilldown-case-card" style="background:var(--card-bg,#fff);border:1px solid var(--border,#e5e7eb);border-radius:10px;padding:15px 18px;margin-bottom:12px;box-shadow:0 1px 3px rgba(0,0,0,0.03)">' +
      '<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:10px">' +
        '<div style="display:flex;align-items:center;gap:10px">' +
          '<strong style="color:var(--maroon);font-size:15px">#' + escapeHtml(c.case_number) + '</strong>' +
          sBadge +
          pBadge +
        '</div>' +
        '<span style="font-size:12px;color:var(--text-3)"><i class="far fa-clock"></i> ' + escapeHtml(c.formatted_date) + '</span>' +
      '</div>' +

      '<div style="display:flex;align-items:center;gap:14px;font-size:13px;margin-bottom:10px;color:var(--text-2);flex-wrap:wrap">' +
        '<div><i class="fas fa-user" style="color:var(--maroon);width:14px"></i> <strong>Student:</strong> ' + escapeHtml(c.student_name) + '</div>' +
        '<div><i class="fas fa-graduation-cap" style="color:var(--maroon);width:14px"></i> <strong>Grade/Sec:</strong> ' + escapeHtml(c.grade_section) + '</div>' +
        '<div><i class="fas fa-tag" style="color:var(--maroon);width:14px"></i> <strong>Concern:</strong> ' + escapeHtml(c.formatted_type) + '</div>' +
      '</div>' +

      '<div style="background:var(--bg2,#f9fafb);border-radius:8px;padding:10px 14px;margin-bottom:12px;border:1px solid var(--border,#f0f0f0);font-size:13px;line-height:1.5;color:var(--text-2)">' +
        '<div style="font-weight:700;color:var(--text);margin-bottom:4px">' + escapeHtml(c.subject || 'Concern Details') + '</div>' +
        '<div style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">' + descOrSummary + '</div>' +
      '</div>' +

      '<div style="display:flex;align-items:center;justify-content:flex-end;gap:10px">' +
        '<button type="button" class="btn-sm-outline" onclick="openCaseReviewFromDrilldown(' + c.id + ',\'' + escapeHtml(c.case_number) + '\',\'' + escapeHtml(c.status) + '\',\'' + escapeHtml(c.priority) + '\')" style="display:inline-flex;align-items:center;gap:6px;cursor:pointer">' +
          '<i class="fas fa-eye"></i> Quick Review' +
        '</button>' +
        '<a href="' + escapeHtml(c.target_url) + '" class="btn-sm" style="display:inline-flex;align-items:center;gap:6px;background:var(--maroon);color:#fff;text-decoration:none;padding:5px 12px;border-radius:6px;font-size:12px;font-weight:700">' +
          '<i class="fas fa-external-link-alt"></i> Go to Case' +
        '</a>' +
      '</div>' +
    '</div>';
  });

  body.innerHTML = html;
}

function openCaseReviewFromDrilldown(caseId, caseNum, status, priority) {
  ensureModalsInBody();
  closeDrilldownModal();
  if (typeof openCaseReview === 'function') {
    openCaseReview(caseId, caseNum, status, priority);
  } else {
    window.location = 'admin-cases.php?q=' + encodeURIComponent(caseNum);
  }
}

new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: {
    labels: statusLabels.map(function(s) { return s.replace('_', ' '); }),
    datasets: [{
      data: statusData,
      backgroundColor: ['#d97706', '#2563eb', '#7c3aed', '#16a34a', '#6b7280'],
      hoverOffset: 6
    }]
  },
  options: {
    plugins: {
      legend: { position: 'bottom' },
      tooltip: {
        callbacks: {
          label: function(context) {
            var val = context.parsed;
            return ' ' + context.label + ': ' + val + (val === 1 ? ' case' : ' cases') + ' (Click slice to view)';
          }
        }
      }
    },
    onHover: function(event, elements) {
      if (event && event.native && event.native.target) {
        event.native.target.style.cursor = (elements && elements.length > 0) ? 'pointer' : 'default';
      }
    },
    onClick: function(event, elements) {
      if (!elements || elements.length === 0) return;
      var idx = elements[0].index;
      var statusKey = statusLabels[idx];
      if (statusKey) {
        openCasesModalByStatus(statusKey);
      }
    }
  }
});

new Chart(document.getElementById('gradeChart'),{type:'bar',data:{labels:gradeLabels,datasets:[{label:'Cases',data:gradeData,backgroundColor:[maroon,maroonMid,maroonLight,'#dc2626']}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}}});

new Chart(document.getElementById('highGradeChart'),{type:'bar',data:{labels:highGradeLabels,datasets:[{label:'High Priority',data:highGradeData,backgroundColor:'#dc2626'}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}}});

new Chart(document.getElementById('ageChart'),{type:'bar',data:{labels:ageLabels.map(a=>a+' yrs'),datasets:[{label:'Cases',data:ageData,backgroundColor:maroonLight}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}}});

new Chart(document.getElementById('typeChart'),{type:'bar',data:{labels:typeLabels.map(s=>s.replace(/_/g,' ')),datasets:[{label:'Cases',data:typeData,backgroundColor:[maroon,maroonMid,maroonLight,'#dc2626','#ef4444','#f87171','#fca5a5']}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}}});

new Chart(document.getElementById('sectionChart'), {
  type: 'bar',
  data: {
    labels: sectionLabels,
    datasets: [{
      label: 'Cases',
      data: sectionData,
      backgroundColor: maroon,
      borderRadius: 4
    }]
  },
  options: {
    indexAxis: 'y',
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
      x: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } }
    }
  }
});

// Teacher Referrals Status Chart
const refStatusColorMap = {
  'pending': '#d97706',
  'under_review': '#2563eb',
  'ongoing': '#7c3aed',
  'resolved': '#16a34a',
  'closed': '#6b7280'
};
const refStatusColors = refStatusLabels.map(function(s) {
  return refStatusColorMap[s] || maroon;
});

new Chart(document.getElementById('refStatusChart'), {
  type: 'doughnut',
  data: {
    labels: refStatusLabels.map(function(s) {
      return s.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
    }),
    datasets: [{
      data: refStatusData,
      backgroundColor: refStatusColors.length > 0 ? refStatusColors : ['#d97706', '#2563eb', '#7c3aed', '#16a34a', '#6b7280'],
      hoverOffset: 6
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { position: 'bottom' },
      tooltip: {
        callbacks: {
          label: function(ctx) {
            var val = ctx.parsed;
            return ' ' + ctx.label + ': ' + val + (val === 1 ? ' referral' : ' referrals');
          }
        }
      }
    }
  }
});

// Top Referring Teachers Chart
new Chart(document.getElementById('refTeacherChart'), {
  type: 'bar',
  data: {
    labels: topTeacherLabels,
    datasets: [{
      label: 'Referrals',
      data: topTeacherData,
      backgroundColor: maroonMid,
      borderRadius: 6
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label: function(ctx) {
            var val = ctx.parsed.y;
            return ' ' + val + (val === 1 ? ' referral' : ' referrals');
          }
        }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        ticks: { stepSize: 1, precision: 0 }
      },
      x: {
        grid: { display: false }
      }
    }
  }
});

// Referrals by Concern Type Chart
new Chart(document.getElementById('refTypeChart'), {
  type: 'bar',
  data: {
    labels: refTypeLabels.map(function(s) {
      return s.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
    }),
    datasets: [{
      label: 'Referrals',
      data: refTypeData,
      backgroundColor: [maroon, maroonMid, maroonLight, '#dc2626', '#ef4444', '#f87171', '#fca5a5'],
      borderRadius: 6
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label: function(ctx) {
            var val = ctx.parsed.y;
            return ' ' + val + (val === 1 ? ' referral' : ' referrals');
          }
        }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        ticks: { stepSize: 1, precision: 0 }
      },
      x: {
        grid: { display: false }
      }
    }
  }
});
</script>
HTML;

renderLayout($user, 'Analytics', 'analytics', $content);



