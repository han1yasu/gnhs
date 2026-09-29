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

// Cases by month (last 6)
$byMonth = $db->query("SELECT DATE_FORMAT(submitted_at,'%b %Y') as mo, COUNT(*) as cnt
    FROM cases WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY mo ORDER BY submitted_at ASC")->fetchAll();

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
$monthLabels  = json_encode(array_column($byMonth,'mo'));
$monthData    = json_encode(array_column($byMonth,'cnt'));
$gradeLabels  = json_encode(array_column($byGrade,'grade_level'));
$gradeData    = json_encode(array_column($byGrade,'cnt'));
$sectionLabels= json_encode(array_column($topSections,'grade_section'));
$sectionData  = json_encode(array_column($topSections,'cnt'));
$highGradeLabels = json_encode(array_column($highByGrade,'grade_level'));
$highGradeData   = json_encode(array_column($highByGrade,'cnt'));
$ageLabels    = json_encode(array_column($byAge,'age'));
$ageData      = json_encode(array_column($byAge,'cnt'));

// --- Teacher Referrals Stats ---
$totalReferrals = (int)$db->query("SELECT COUNT(*) FROM referrals")->fetchColumn();

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

<!-- Row 1: Monthly trend + Status donut -->
<div class="analytics-grid" style="margin-bottom:22px">
  <div class="content-card">
    <div class="card-header"><h3><i class="fas fa-chart-line"></i> Cases Over Last 6 Months</h3></div>
    <canvas id="monthChart" height="100"></canvas>
  </div>
  <div class="content-card">
    <div class="card-header"><h3><i class="fas fa-chart-pie"></i> Cases by Status</h3></div>
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
<h3 style="margin:40px 0 20px;border-bottom:1px solid var(--border);padding-bottom:10px;"><i class="fas fa-chalkboard-teacher"></i> Teacher Referrals Analytics (Total: {$totalReferrals})</h3>

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

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
const maroon='#550000', maroonMid='#7a0000', maroonLight='#a00000';
const typeLabels  =$typeLabels;  const typeData  =$typeData;
const statusLabels=$statusLabels; const statusData=$statusData;
const monthLabels =$monthLabels;  const monthData =$monthData;
const gradeLabels =$gradeLabels;  const gradeData =$gradeData;
const sectionLabels=$sectionLabels; const sectionData=$sectionData;
const highGradeLabels=$highGradeLabels; const highGradeData=$highGradeData;
const ageLabels=$ageLabels; const ageData=$ageData;

const refStatusLabels = $refStatusLabels; const refStatusData = $refStatusData;
const refTypeLabels = $refTypeLabels; const refTypeData = $refTypeData;
const topTeacherLabels = $topTeacherLabels; const topTeacherData = $topTeacherData;

new Chart(document.getElementById('monthChart'),{type:'line',data:{labels:monthLabels,datasets:[{label:'Cases',data:monthData,borderColor:maroon,backgroundColor:'rgba(85,0,0,.08)',tension:.4,fill:true,pointBackgroundColor:maroon}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}}});

new Chart(document.getElementById('statusChart'),{type:'doughnut',data:{labels:statusLabels.map(s=>s.replace('_',' ')),datasets:[{data:statusData,backgroundColor:['#d97706','#2563eb','#7c3aed','#16a34a','#6b7280']}]},options:{plugins:{legend:{position:'bottom'}}}});

new Chart(document.getElementById('gradeChart'),{type:'bar',data:{labels:gradeLabels,datasets:[{label:'Cases',data:gradeData,backgroundColor:[maroon,maroonMid,maroonLight,'#dc2626']}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}}});

new Chart(document.getElementById('highGradeChart'),{type:'bar',data:{labels:highGradeLabels,datasets:[{label:'High Priority',data:highGradeData,backgroundColor:'#dc2626'}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}}});

new Chart(document.getElementById('ageChart'),{type:'bar',data:{labels:ageLabels.map(a=>a+' yrs'),datasets:[{label:'Cases',data:ageData,backgroundColor:maroonLight}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}}});

new Chart(document.getElementById('typeChart'),{type:'bar',data:{labels:typeLabels.map(s=>s.replace(/_/g,' ')),datasets:[{label:'Cases',data:typeData,backgroundColor:[maroon,maroonMid,maroonLight,'#dc2626','#ef4444','#f87171','#fca5a5']}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}}});

new Chart(document.getElementById('sectionChart'),{type:'horizontalBar'||'bar',data:{labels:sectionLabels,datasets:[{label:'Cases',data:sectionData,backgroundColor:maroon}]},options:{indexAxis:'y',plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,ticks:{stepSize:1}}}}});

new Chart(document.getElementById('refStatusChart'),{type:'doughnut',data:{labels:refStatusLabels.map(s=>s.replace('_',' ')),datasets:[{data:refStatusData,backgroundColor:['#d97706','#2563eb','#16a34a','#6b7280']}]},options:{plugins:{legend:{position:'bottom'}}}});

new Chart(document.getElementById('refTeacherChart'),{type:'bar',data:{labels:topTeacherLabels,datasets:[{label:'Referrals',data:topTeacherData,backgroundColor:maroonMid}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}}});

new Chart(document.getElementById('refTypeChart'),{type:'bar',data:{labels:refTypeLabels.map(s=>s.replace(/_/g,' ')),datasets:[{label:'Referrals',data:refTypeData,backgroundColor:maroonLight}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}}});
</script>
HTML;

renderLayout($user, 'Analytics', 'analytics', $content);



