<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('teacher');

$db = getDB();
$advisoryClass = $user['advisory_class'] ?? '';
$students = [];
$advisoryCases = [];
$studentCasesMap = [];
$totalStudents = 0;
$activeCasesCount = 0;
$resolvedCasesCount = 0;
$studentsWithConcernsCount = 0;

// Grade and section parsing
$gradeNum = '';
$sectionName = '';
if ($advisoryClass) {
    $parts = explode(' - ', $advisoryClass, 2);
    $gradeNum = preg_replace('/[^0-9]/', '', $parts[0] ?? '');
    $sectionName = trim($parts[1] ?? '');
}

// Concern type metadata mapper
if (!function_exists('getConcernMeta')) {
function getConcernMeta(string $type): array {
    $typeLower = strtolower(trim($type));
    $map = [
        'bullying' => [
            'label' => 'Bullying Concern',
            'short' => 'Bullying',
            'icon'  => 'fa-shield-alt',
            'color' => '#dc2626',
            'bg'    => '#fef2f2',
            'border'=> '#fecaca',
            'guidance_note' => 'Student reported bullying or harassment concerns. The Guidance Office is actively handling this matter. Advisory teachers are requested to maintain observant, vigilant supervision in classrooms and common areas without singling out the student.'
        ],
        'peer_conflict' => [
            'label' => 'Peer Conflict / Peer Problem',
            'short' => 'Peer Problem',
            'icon'  => 'fa-user-friends',
            'color' => '#0284c7',
            'bg'    => '#f0f9ff',
            'border'=> '#bae6fd',
            'guidance_note' => 'Student reported interpersonal or peer friction. Guidance recommends fostering inclusive group activities and monitoring social dynamics without directly interrogating students.'
        ],
        'academic_stress' => [
            'label' => 'Academic Stress & Workload',
            'short' => 'Academic Stress',
            'icon'  => 'fa-book-open',
            'color' => '#d97706',
            'bg'    => '#fffbeb',
            'border'=> '#fde68a',
            'guidance_note' => 'Student indicated academic distress or study pressure. Advisory teachers may check in gently regarding deadline pacing, understanding of lessons, or study habits.'
        ],
        'emotional' => [
            'label' => 'Emotional / Mental Well-being',
            'short' => 'Emotional Well-being',
            'icon'  => 'fa-heart',
            'color' => '#9333ea',
            'bg'    => '#faf5ff',
            'border'=> '#e9d5ff',
            'guidance_note' => 'Student reported emotional or well-being difficulties. Please offer a supportive, non-judgmental classroom environment and be attentive to sudden behavioral or emotional shifts.'
        ],
        'family' => [
            'label' => 'Family / Domestic Concern',
            'short' => 'Family Concern',
            'icon'  => 'fa-home',
            'color' => '#059669',
            'bg'    => '#f0fdf4',
            'border'=> '#bbf7d0',
            'guidance_note' => 'Student reported personal or family-related concerns impacting their school experience. Exercise empathy and supportive flexibility when feasible.'
        ],
        'behavioral' => [
            'label' => 'Behavioral Adjustment',
            'short' => 'Behavioral',
            'icon'  => 'fa-user-clock',
            'color' => '#ea580c',
            'bg'    => '#fff7ed',
            'border'=> '#fed7aa',
            'guidance_note' => 'Guidance is supporting the student with behavioral adjustment. Consistent classroom routines, positive reinforcement, and clear expectations are recommended.'
        ],
        'other' => [
            'label' => 'General Guidance Concern',
            'short' => 'General Concern',
            'icon'  => 'fa-tag',
            'color' => '#475569',
            'bg'    => '#f8fafc',
            'border'=> '#e2e8f0',
            'guidance_note' => 'Student has raised an advisory guidance matter. Guidance staff are reviewing the situation.'
        ]
    ];
    return $map[$typeLower] ?? [
        'label' => ucfirst(str_replace('_', ' ', $type)),
        'short' => ucfirst(str_replace('_', ' ', $type)),
        'icon'  => 'fa-clipboard-list',
        'color' => '#550000',
        'bg'    => '#fff0f0',
        'border'=> '#fecaca',
        'guidance_note' => 'Guidance staff are handling this student concern. Please maintain an encouraging and supportive classroom posture.'
    ];
}
}

if (!function_exists('statusBadgeHtml')) {
function statusBadgeHtml(string $s): string {
    $map = [
        'pending'      => 'status-pending',
        'under_review' => 'status-under_review',
        'ongoing'      => 'status-ongoing',
        'resolved'     => 'status-resolved',
        'closed'       => 'status-resolved'
    ];
    $cls = $map[$s] ?? 'status-pending';
    $label = str_replace('_', ' ', ucfirst($s));
    return "<span class='status-badge {$cls}'>{$label}</span>";
}
}

if ($advisoryClass) {
    // 1. Fetch students in this advisory class
    $stmt = $db->prepare("SELECT id, first_name, last_name, email, id_number, avatar_initials, avatar_photo, grade_section 
        FROM users 
        WHERE role='student' 
          AND REPLACE(grade_section, ' – ', ' - ') = REPLACE(?, ' – ', ' - ')
          AND (is_active=1 OR is_active IS NULL)
        ORDER BY last_name ASC, first_name ASC");
    $stmt->execute([$advisoryClass]);
    $students = $stmt->fetchAll();
    $totalStudents = count($students);

    // 2. Fetch concerns raised by students in this advisory class
    // NOTE: Strictly exclude description, notes, witnesses, and evidence for privacy
    $cStmt = $db->prepare("SELECT c.id, c.case_number, c.student_id, c.is_anonymous, c.concern_type, c.priority, c.status, c.submitted_at,
        c.subject, c.description, c.reported_teacher_name, c.is_against_adviser,
        s.first_name, s.last_name, s.id_number, s.avatar_initials, s.avatar_photo, s.grade_section,
        CONCAT(COALESCE(a.first_name,'Guidance'), ' ', COALESCE(a.last_name,'Counselor')) AS counselor_name
        FROM cases c
        JOIN users s ON c.student_id = s.id
        LEFT JOIN users a ON c.assigned_to = a.id
        WHERE REPLACE(s.grade_section, ' – ', ' - ') = REPLACE(?, ' – ', ' - ')
          AND (c.is_against_adviser = 0 OR c.is_against_adviser IS NULL)
          AND LOWER(c.concern_type) NOT LIKE '%teacher%'
          AND LOWER(c.concern_type) NOT LIKE '%adviser%'
          AND LOWER(c.concern_type) NOT LIKE '%faculty%'
          AND LOWER(c.concern_type) NOT LIKE '%instructor%'
        ORDER BY c.submitted_at DESC");
    $cStmt->execute([$advisoryClass]);
    $rawAdvisoryCases = $cStmt->fetchAll();

    // Secondary strict privacy filter: never display if student concern mentions the teacher by name
    $teacherLastName  = strtolower(trim($user['last_name'] ?? ''));
    $teacherFirstName = strtolower(trim($user['first_name'] ?? ''));
    $advisoryCases    = [];

    foreach ($rawAdvisoryCases as $c) {
        $cSub = strtolower($c['subject'] ?? '');
        $cDesc = strtolower($c['description'] ?? '');
        $cRep = strtolower($c['reported_teacher_name'] ?? '');
        if (($teacherLastName && strlen($teacherLastName) > 2 && (strpos($cSub, $teacherLastName) !== false || strpos($cDesc, $teacherLastName) !== false || strpos($cRep, $teacherLastName) !== false)) ||
            ($teacherFirstName && strlen($teacherFirstName) > 2 && (strpos($cSub, $teacherFirstName) !== false || strpos($cDesc, $teacherFirstName) !== false || strpos($cRep, $teacherFirstName) !== false))) {
            continue; // Adviser is strictly forbidden from seeing concerns about themselves
        }
        $advisoryCases[] = $c;
    }

    foreach ($advisoryCases as $c) {
        if (in_array($c['status'], ['resolved', 'closed'])) {
            $resolvedCasesCount++;
        } else {
            $activeCasesCount++;
        }

        // Only associate with student if not anonymous (privacy compliance)
        if (empty($c['is_anonymous'])) {
            $sid = (int)$c['student_id'];
            if (!isset($studentCasesMap[$sid])) {
                $studentCasesMap[$sid] = [];
            }
            $studentCasesMap[$sid][] = $c;
        }
    }
    $studentsWithConcernsCount = count($studentCasesMap);
}

// Build JSON data store for instant modal interactions
$jsonDataStore = [
    'advisory_class' => $advisoryClass,
    'students' => [],
    'cases' => []
];

foreach ($students as $s) {
    $sid = (int)$s['id'];
    $sCases = $studentCasesMap[$sid] ?? [];
    $casesSummary = [];
    foreach ($sCases as $sc) {
        $meta = getConcernMeta($sc['concern_type']);
        $casesSummary[] = [
            'id' => (int)$sc['id'],
            'case_number' => $sc['case_number'],
            'concern_type' => $sc['concern_type'],
            'concern_label' => $meta['label'],
            'concern_icon' => $meta['icon'],
            'concern_color' => $meta['color'],
            'guidance_note' => $meta['guidance_note'],
            'status' => $sc['status'],
            'status_label' => str_replace('_', ' ', ucfirst($sc['status'])),
            'submitted_at' => date('M d, Y h:i A', strtotime($sc['submitted_at'])),
            'counselor_name' => $sc['counselor_name']
        ];
    }
    $jsonDataStore['students'][$sid] = [
        'id' => $sid,
        'name' => $s['first_name'] . ' ' . $s['last_name'],
        'id_number' => $s['id_number'],
        'email' => $s['email'],
        'avatar_photo' => $s['avatar_photo'] ?? null,
        'avatar_initials' => $s['avatar_initials'] ?? '',
        'grade_section' => $s['grade_section'],
        'cases' => $casesSummary
    ];
}

foreach ($advisoryCases as $ac) {
    $meta = getConcernMeta($ac['concern_type']);
    $isAnon = !empty($ac['is_anonymous']);
    $jsonDataStore['cases'][(int)$ac['id']] = [
        'id' => (int)$ac['id'],
        'case_number' => $ac['case_number'],
        'is_anonymous' => $isAnon,
        'student_id' => $isAnon ? null : (int)$ac['student_id'],
        'student_name' => $isAnon ? 'Anonymous Student' : ($ac['first_name'] . ' ' . $ac['last_name']),
        'id_number' => $isAnon ? 'Confidential' : $ac['id_number'],
        'grade_section' => $ac['grade_section'],
        'avatar_photo' => $isAnon ? null : ($ac['avatar_photo'] ?? null),
        'avatar_initials' => $isAnon ? '?' : ($ac['avatar_initials'] ?? ''),
        'concern_type' => $ac['concern_type'],
        'concern_label' => $meta['label'],
        'concern_icon' => $meta['icon'],
        'concern_color' => $meta['color'],
        'guidance_note' => $meta['guidance_note'],
        'status' => $ac['status'],
        'status_label' => str_replace('_', ' ', ucfirst($ac['status'])),
        'submitted_at' => date('M d, Y h:i A', strtotime($ac['submitted_at'])),
        'counselor_name' => $ac['counselor_name']
    ];
}

$jsonDataStoreJson = json_encode($jsonDataStore, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$noConcernsCount = max(0, $totalStudents - $studentsWithConcernsCount);

// Generate HTML Content
$content = <<<HTML
<div class="header-card">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div>
      <h2 style="margin:0"><i class="fas fa-users" style="color:var(--maroon);margin-right:10px"></i> My Advisory Class & Student Concerns</h2>
      <p style="color:var(--text-2);margin-top:6px;margin-bottom:0">View students under your assigned advisory class and track high-level guidance concern summaries.</p>
    </div>
    <div>
      <span class="badge-privacy"><i class="fas fa-shield-alt"></i> Confidential Guidance Care Protocol</span>
    </div>
  </div>
</div>
HTML;

if (!$advisoryClass) {
    $content .= <<<HTML
<div class="content-card" style="margin-top:20px;text-align:center;padding:50px 20px;">
  <i class="fas fa-chalkboard-teacher" style="font-size:52px;color:var(--border);margin-bottom:16px;"></i>
  <h3 style="color:var(--text-2);margin-bottom:8px">No Advisory Class Assigned</h3>
  <p style="color:var(--text-3);max-width:500px;margin:0 auto 18px">You currently do not have an advisory section assigned to your account. Please contact the Guidance Counselor or Administrator to set your advisory class.</p>
</div>
HTML;
} else {
    // 4 Summary Metric Cards
    $content .= <<<HTML
<div class="summary-cards" style="margin-top:20px">
  <div class="sum-card">
    <div class="sum-icon" style="background:var(--maroon-pale);color:var(--maroon)"><i class="fas fa-users"></i></div>
    <div>
      <div class="sum-num">{$totalStudents}</div>
      <div class="sum-label">Advisory Students</div>
    </div>
  </div>
  <div class="sum-card">
    <div class="sum-icon" style="background:#fee2e2;color:#dc2626"><i class="fas fa-exclamation-circle"></i></div>
    <div>
      <div class="sum-num">{$studentsWithConcernsCount}</div>
      <div class="sum-label">Students with Concerns</div>
    </div>
  </div>
  <div class="sum-card">
    <div class="sum-icon" style="background:#fffbeb;color:#d97706"><i class="fas fa-folder-open"></i></div>
    <div>
      <div class="sum-num">{$activeCasesCount}</div>
      <div class="sum-label">Active Concerns</div>
    </div>
  </div>
  <div class="sum-card">
    <div class="sum-icon" style="background:#f0fdf4;color:#16a34a"><i class="fas fa-check-circle"></i></div>
    <div>
      <div class="sum-num">{$resolvedCasesCount}</div>
      <div class="sum-label">Resolved Concerns</div>
    </div>
  </div>
</div>

<!-- Confidentiality & Guidance Privacy Protocol Banner -->
<div class="advisory-privacy-banner">
  <div class="apb-icon"><i class="fas fa-user-shield"></i></div>
  <div class="apb-content">
    <strong>Advisory Guidance & Student Privacy Protocol</strong>
    <p>Advisory teachers can view if a student has raised a guidance concern along with its high-level summary category (e.g., Peer Conflict / Problem, Bullying, Academic Stress). In compliance with DepEd student counseling ethics and student data privacy laws, full incident narratives, witness testimonies, and counselor notes remain strictly confidential between the student and the Guidance Office. This summary is provided so you can offer supportive, mindful classroom care.</p>
  </div>
</div>

<!-- Section 1: Advisory Student Roster -->
<div class="content-card" style="margin-bottom:24px">
  <div class="card-header" style="flex-wrap:wrap;gap:12px">
    <div style="display:flex;align-items:center;gap:10px">
      <h3 style="margin:0"><i class="fas fa-address-book" style="color:var(--maroon);margin-right:8px"></i> Advisory Class Roster</h3>
      <span style="background:var(--maroon-pale);color:var(--maroon);padding:3px 12px;border-radius:50px;font-size:12px;font-weight:700">
        {$advisoryClass}
      </span>
    </div>
    <a href="teacher-referral.php" class="btn-sm-primary" style="text-decoration:none">
      <i class="fas fa-user-plus"></i> New Referral
    </a>
  </div>

  <div class="roster-filter-bar">
    <div class="roster-search-wrap">
      <i class="fas fa-search"></i>
      <input type="text" id="rosterSearch" placeholder="Search student by name or ID number..." oninput="applyRosterFilters()" />
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <button type="button" class="filter-btn active" id="btnFilterAll" onclick="setRosterTab('all')">
        All Students ({$totalStudents})
      </button>
      <button type="button" class="filter-btn" id="btnFilterConcerns" onclick="setRosterTab('concerns')">
        With Concerns ({$studentsWithConcernsCount})
      </button>
      <button type="button" class="filter-btn" id="btnFilterNone" onclick="setRosterTab('none')">
        No Concerns ({$noConcernsCount})
      </button>
    </div>
  </div>
HTML;

    if (empty($students)) {
        $content .= <<<HTML
  <div style="text-align:center;padding:40px 20px;color:var(--text-3)">
    <i class="fas fa-user-graduate" style="font-size:48px;color:var(--border);margin-bottom:16px;"></i>
    <p>No students found registered for section <strong>{$advisoryClass}</strong>.</p>
  </div>
HTML;
    } else {
        $content .= <<<HTML
  <div class="table-responsive">
    <table class="data-table" id="rosterTable">
      <thead>
        <tr>
          <th>Student Name</th>
          <th>ID Number</th>
          <th>Email</th>
          <th>Guidance Concern Status (Summary)</th>
          <th style="text-align:right">Action</th>
        </tr>
      </thead>
      <tbody>
HTML;

        foreach ($students as $s) {
            $sid = (int)$s['id'];
            $name = htmlspecialchars($s['first_name'] . ' ' . $s['last_name']);
            $idNum = htmlspecialchars($s['id_number']);
            $email = htmlspecialchars($s['email']);
            $photo = $s['avatar_photo'] ?? null;
            $initials = htmlspecialchars($s['avatar_initials'] ?? strtoupper($s['first_name'][0]));

            $avatarHtml = $photo 
                ? '<img src="'.htmlspecialchars($photo).'" style="width:100%;height:100%;object-fit:cover;">' 
                : $initials;

            $sCases = $studentCasesMap[$sid] ?? [];
            $hasConcerns = !empty($sCases);
            $filterCategory = $hasConcerns ? 'concerns' : 'none';

            $concernStatusHtml = '';
            $actionBtnHtml = '';

            if ($hasConcerns) {
                $caseCount = count($sCases);
                $firstCase = $sCases[0];
                $meta = getConcernMeta($firstCase['concern_type']);

                if ($caseCount === 1) {
                    $statusLabel = str_replace('_', ' ', ucfirst($firstCase['status']));
                    $concernStatusHtml = "
                    <div style='display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap'>
                      <span class='concern-pill-active' style='background:{$meta['bg']};color:{$meta['color']};border-color:{$meta['border']}'>
                        <i class='fas {$meta['icon']}'></i> Raised: {$meta['short']}
                      </span>
                      ".statusBadgeHtml($firstCase['status'])."
                    </div>";
                } else {
                    $concernStatusHtml = "
                    <div style='display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap'>
                      <span class='concern-pill-active'>
                        <i class='fas fa-exclamation-circle'></i> {$caseCount} Concerns Raised
                      </span>
                      <span style='font-size:12px;color:var(--text-3)'>(" . htmlspecialchars($meta['short']) . ", +)</span>
                    </div>";
                }

                $actionBtnHtml = "
                <div style='display:flex;align-items:center;justify-content:flex-end;gap:8px'>
                  <button type='button' class='btn-sm-primary' onclick='openStudentConcernsModal({$sid})'>
                    <i class='fas fa-eye'></i> View Summary
                  </button>
                  <a href='teacher-referral.php?student_name=".urlencode($s['first_name'].' '.$s['last_name'])."&grade=".urlencode($gradeNum)."&section=".urlencode($sectionName)."&concern_type=".urlencode($firstCase['concern_type'])."' class='btn-sm-outline' title='Submit Teacher Referral'>
                    <i class='fas fa-clipboard-check'></i> Referral
                  </a>
                </div>";
            } else {
                $concernStatusHtml = "
                <span class='concern-pill-none'>
                  <i class='fas fa-check-circle'></i> No concerns raised
                </span>";

                $actionBtnHtml = "
                <div style='display:flex;align-items:center;justify-content:flex-end;gap:8px'>
                  <a href='teacher-referral.php?student_name=".urlencode($s['first_name'].' '.$s['last_name'])."&grade=".urlencode($gradeNum)."&section=".urlencode($sectionName)."' class='btn-sm-outline' title='Submit Referral for this student'>
                    <i class='fas fa-plus'></i> Referral
                  </a>
                </div>";
            }

            $content .= <<<HTML
        <tr class="roster-row" data-category="{$filterCategory}" data-name="{$name}" data-idnum="{$idNum}">
          <td>
            <div style="display:flex;align-items:center;gap:12px">
              <div style="width:34px;height:34px;border-radius:10px;overflow:hidden;flex-shrink:0;background:var(--maroon-pale);color:var(--maroon);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;box-shadow:0 2px 6px rgba(85,0,0,0.08)">
                {$avatarHtml}
              </div>
              <div>
                <strong style="color:var(--text);display:block;line-height:1.2">{$name}</strong>
                <span style="font-size:11px;color:var(--text-3)">{$advisoryClass}</span>
              </div>
            </div>
          </td>
          <td><code style="font-size:12px;background:var(--bg2);padding:2px 6px;border-radius:4px;color:var(--text-2)">{$idNum}</code></td>
          <td><a href="mailto:{$email}" style="color:var(--maroon);text-decoration:none;font-size:13px"><i class="fas fa-envelope"></i> {$email}</a></td>
          <td>{$concernStatusHtml}</td>
          <td style="text-align:right">{$actionBtnHtml}</td>
        </tr>
HTML;
        }

        $content .= <<<HTML
      </tbody>
    </table>
  </div>
HTML;
    }

    $content .= "</div>\n";

    // Section 2: Advisory Concerns Summary Log
    $content .= <<<HTML
<div class="content-card" style="margin-top:24px">
  <div class="card-header">
    <div style="display:flex;align-items:center;gap:10px">
      <h3 style="margin:0"><i class="fas fa-clipboard-list" style="color:var(--maroon);margin-right:8px"></i> Advisory Class Concerns Log (Summary View)</h3>
      <span style="background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:50px;font-size:12px;font-weight:700">
        {$activeCasesCount} active / {$totalStudents} students
      </span>
    </div>
    <span style="font-size:12px;color:var(--text-3)">High-level summary only • Full details confidential</span>
  </div>
HTML;

    if (empty($advisoryCases)) {
        $content .= <<<HTML
  <div style="text-align:center;padding:36px 20px;color:var(--text-3)">
    <i class="fas fa-check-circle" style="font-size:44px;color:#16a34a;margin-bottom:12px;display:block"></i>
    <strong style="color:var(--text-2);font-size:15px">All Clear!</strong>
    <p style="margin:6px 0 0;font-size:13px">No guidance concerns have been submitted by students in your advisory class.</p>
  </div>
HTML;
    } else {
        $content .= <<<HTML
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Case #</th>
          <th>Student</th>
          <th>Concern Category</th>
          <th>Date Raised</th>
          <th>Status</th>
          <th style="text-align:right">Action</th>
        </tr>
      </thead>
      <tbody>
HTML;

        foreach ($advisoryCases as $ac) {
            $cid = (int)$ac['id'];
            $cNum = htmlspecialchars($ac['case_number']);
            $isAnon = !empty($ac['is_anonymous']);
            $studentDisplay = $isAnon 
                ? "<span style='color:var(--text-3);font-style:italic'><i class='fas fa-user-secret' style='color:var(--text-3)'></i> Anonymous Student</span>"
                : "<strong>" . htmlspecialchars($ac['first_name'] . ' ' . $ac['last_name']) . "</strong>";

            $meta = getConcernMeta($ac['concern_type']);
            $date = date('M d, Y', strtotime($ac['submitted_at']));
            $sBadge = statusBadgeHtml($ac['status']);

            $content .= <<<HTML
        <tr>
          <td><strong style="color:var(--maroon)">{$cNum}</strong></td>
          <td>{$studentDisplay}</td>
          <td>
            <span style="display:inline-flex;align-items:center;gap:6px;font-weight:700;color:{$meta['color']};font-size:13px">
              <i class="fas {$meta['icon']}"></i> {$meta['label']}
            </span>
          </td>
          <td style="color:var(--text-3);font-size:12px">{$date}</td>
          <td>{$sBadge}</td>
          <td style="text-align:right">
            <button type="button" class="btn-sm-outline" onclick="openCaseSummaryModal({$cid})">
              <i class="fas fa-file-alt"></i> View Summary
            </button>
          </td>
        </tr>
HTML;
        }

        $content .= <<<HTML
      </tbody>
    </table>
  </div>
HTML;
    }

    $content .= "</div>\n";
}

// Concern Summary Modal HTML
$content .= <<<HTML
<!-- Concern Summary Modal (Teacher View) -->
<div class="modal-overlay hidden" id="concernSummaryModal" onclick="if(event.target.id==='concernSummaryModal')closeConcernModal()">
  <div class="modal-box wide-modal" style="max-width:680px">
    <button class="modal-close" onclick="closeConcernModal()"><i class="fas fa-times"></i></button>
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;border-bottom:1px solid var(--border);padding-bottom:14px">
      <div style="width:44px;height:44px;border-radius:12px;background:var(--maroon-pale);color:var(--maroon);display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0">
        <i class="fas fa-info-circle"></i>
      </div>
      <div>
        <h2 class="modal-title" style="text-align:left;margin:0;font-size:20px">Student Guidance Concern Summary</h2>
        <div style="font-size:12px;color:var(--text-3);margin-top:2px">Official summary for advisory teacher awareness</div>
      </div>
    </div>

    <div id="modalSummaryBody">
      <!-- Injected by JavaScript -->
    </div>
  </div>
</div>
HTML;

// Attach pure JavaScript using concatenation to ensure complete compatibility
$content .= '
<script>
// JSON Data Store
const ADVISORY_DATA = ' . ($jsonDataStoreJson ?: '{}') . ';
let currentFilterTab = "all";

function setRosterTab(tab) {
  currentFilterTab = tab;
  document.getElementById("btnFilterAll").classList.toggle("active", tab === "all");
  document.getElementById("btnFilterConcerns").classList.toggle("active", tab === "concerns");
  document.getElementById("btnFilterNone").classList.toggle("active", tab === "none");
  applyRosterFilters();
}

function applyRosterFilters() {
  const q = (document.getElementById("rosterSearch") ? document.getElementById("rosterSearch").value : "").toLowerCase().trim();
  const rows = document.querySelectorAll("#rosterTable tbody .roster-row");

  rows.forEach(r => {
    const cat = r.getAttribute("data-category");
    const name = (r.getAttribute("data-name") || "").toLowerCase();
    const idnum = (r.getAttribute("data-idnum") || "").toLowerCase();

    let matchesTab = true;
    if (currentFilterTab === "concerns" && cat !== "concerns") matchesTab = false;
    if (currentFilterTab === "none" && cat !== "none") matchesTab = false;

    let matchesSearch = true;
    if (q) {
      matchesSearch = name.includes(q) || idnum.includes(q);
    }

    r.style.display = (matchesTab && matchesSearch) ? "" : "none";
  });
}

function closeConcernModal() {
  document.getElementById("concernSummaryModal").classList.add("hidden");
}

function openStudentConcernsModal(studentId) {
  const student = ADVISORY_DATA.students ? ADVISORY_DATA.students[studentId] : null;
  if (!student) return;

  const container = document.getElementById("modalSummaryBody");
  const avatarEl = student.avatar_photo 
    ? "<img src=\"" + student.avatar_photo + "\" style=\"width:100%;height:100%;object-fit:cover;border-radius:12px\" />"
    : "<div style=\"width:48px;height:48px;border-radius:12px;background:var(--maroon-pale);color:var(--maroon);display:flex;align-items:center;justify-content:center;font-weight:900;font-size:16px\">" + (student.avatar_initials || "S") + "</div>";

  let casesHtml = "";
  if (student.cases && student.cases.length > 0) {
    casesHtml = student.cases.map(function(c) {
      const sectionClean = (student.grade_section || "").replace(/^Grade\s*\d+\s*-\s*/i, "");
      return ""
        + "<div class=\"concern-summary-card\">"
        + "  <div class=\"csc-header\">"
        + "    <div class=\"csc-title\">"
        + "      <span style=\"color:" + c.concern_color + ";font-size:18px\"><i class=\"fas " + c.concern_icon + "\"></i></span>"
        + "      <span style=\"color:var(--text);font-weight:800\">" + c.concern_label + "</span>"
        + "    </div>"
        + "    <div style=\"display:flex;align-items:center;gap:8px\">"
        + "      <span class=\"status-badge status-" + c.status + "\">" + c.status_label + "</span>"
        + "      <strong style=\"color:var(--maroon);font-size:13px\">" + c.case_number + "</strong>"
        + "    </div>"
        + "  </div>"
        + "  <div style=\"display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:12.5px;color:var(--text-2);margin-bottom:10px;background:var(--bg2);padding:10px 12px;border-radius:8px\">"
        + "    <div><i class=\"fas fa-calendar-alt\" style=\"color:var(--maroon);margin-right:6px\"></i> <strong>Raised:</strong> " + c.submitted_at + "</div>"
        + "    <div><i class=\"fas fa-user-tie\" style=\"color:var(--maroon);margin-right:6px\"></i> <strong>Counselor:</strong> " + c.counselor_name + "</div>"
        + "  </div>"
        + "  <div class=\"csc-guidance-box\">"
        + "    <strong><i class=\"fas fa-lightbulb\" style=\"color:var(--maroon)\"></i> Teacher Classroom Recommendation:</strong>"
        + "    " + c.guidance_note
        + "  </div>"
        + "  <div class=\"csc-confidential-tag\">"
        + "    <i class=\"fas fa-lock\" style=\"color:var(--maroon)\"></i>"
        + "    <span><strong>Confidentiality Notice:</strong> Specific incident description, private witness statements, and counseling notes remain strictly confidential between the student and Guidance Counselor.</span>"
        + "  </div>"
        + "  <div style=\"margin-top:12px;display:flex;justify-content:flex-end\">"
        + "    <a href=\"teacher-referral.php?student_name=" + encodeURIComponent(student.name) + "&concern_type=" + encodeURIComponent(c.concern_type) + "\" class=\"btn-sm-primary\" style=\"text-decoration:none\">"
        + "      <i class=\"fas fa-clipboard-check\"></i> Submit Teacher Observation / Referral"
        + "    </a>"
        + "  </div>"
        + "</div>";
    }).join("");
  } else {
    casesHtml = "<div style=\"text-align:center;padding:30px;color:var(--text-3)\">"
      + "<i class=\"fas fa-check-circle\" style=\"font-size:36px;color:#16a34a;margin-bottom:8px;display:block\"></i>"
      + "<p>No concerns raised by this student.</p>"
      + "</div>";
  }

  container.innerHTML = ""
    + "<div style=\"display:flex;align-items:center;gap:14px;padding:12px 14px;background:var(--bg2);border-radius:12px;margin-bottom:18px\">"
    + "  <div style=\"width:48px;height:48px;flex-shrink:0\">" + avatarEl + "</div>"
    + "  <div style=\"flex:1\">"
    + "    <div style=\"font-size:16px;font-weight:800;color:var(--text)\">" + student.name + "</div>"
    + "    <div style=\"font-size:12px;color:var(--text-3);margin-top:2px\">"
    + "      ID: <strong>" + student.id_number + "</strong> • Section: <strong>" + student.grade_section + "</strong>"
    + "    </div>"
    + "  </div>"
    + "</div>"
    + "<div>"
    + "  <h4 style=\"font-size:14px;font-weight:800;color:var(--text-2);margin-bottom:12px\">"
    + "    <i class=\"fas fa-folder-open\" style=\"color:var(--maroon);margin-right:6px\"></i> Guidance Concern Summary Log"
    + "  </h4>"
    + casesHtml
    + "</div>"
    + "<div style=\"display:flex;justify-content:flex-end;margin-top:16px;padding-top:12px;border-top:1px solid var(--border)\">"
    + "  <button class=\"btn-secondary\" onclick=\"closeConcernModal()\">Close</button>"
    + "</div>";

  document.getElementById("concernSummaryModal").classList.remove("hidden");
}

function openCaseSummaryModal(caseId) {
  const c = ADVISORY_DATA.cases ? ADVISORY_DATA.cases[caseId] : null;
  if (!c) return;

  const container = document.getElementById("modalSummaryBody");
  const avatarEl = c.avatar_photo 
    ? "<img src=\"" + c.avatar_photo + "\" style=\"width:100%;height:100%;object-fit:cover;border-radius:12px\" />"
    : "<div style=\"width:48px;height:48px;border-radius:12px;background:var(--maroon-pale);color:var(--maroon);display:flex;align-items:center;justify-content:center;font-weight:900;font-size:16px\">" + (c.avatar_initials || "?") + "</div>";

  const referralBtn = c.is_anonymous ? "" : ""
    + "<div style=\"margin-top:12px;display:flex;justify-content:flex-end\">"
    + "  <a href=\"teacher-referral.php?student_name=" + encodeURIComponent(c.student_name) + "&concern_type=" + encodeURIComponent(c.concern_type) + "\" class=\"btn-sm-primary\" style=\"text-decoration:none\">"
    + "    <i class=\"fas fa-clipboard-check\"></i> Submit Teacher Observation Referral"
    + "  </a>"
    + "</div>";

  container.innerHTML = ""
    + "<div style=\"display:flex;align-items:center;gap:14px;padding:12px 14px;background:var(--bg2);border-radius:12px;margin-bottom:18px\">"
    + "  <div style=\"width:48px;height:48px;flex-shrink:0\">" + avatarEl + "</div>"
    + "  <div style=\"flex:1\">"
    + "    <div style=\"font-size:16px;font-weight:800;color:var(--text)\">" + c.student_name + "</div>"
    + "    <div style=\"font-size:12px;color:var(--text-3);margin-top:2px\">"
    + "      ID: <strong>" + c.id_number + "</strong> • Section: <strong>" + c.grade_section + "</strong>"
    + "    </div>"
    + "  </div>"
    + "  <div>"
    + "    <strong style=\"color:var(--maroon);font-size:15px\">" + c.case_number + "</strong>"
    + "  </div>"
    + "</div>"
    + "<div class=\"concern-summary-card\">"
    + "  <div class=\"csc-header\">"
    + "    <div class=\"csc-title\">"
    + "      <span style=\"color:" + c.concern_color + ";font-size:20px\"><i class=\"fas " + c.concern_icon + "\"></i></span>"
    + "      <span style=\"color:var(--text);font-weight:800;font-size:16px\">" + c.concern_label + "</span>"
    + "    </div>"
    + "    <div>"
    + "      <span class=\"status-badge status-" + c.status + "\">" + c.status_label + "</span>"
    + "    </div>"
    + "  </div>"
    + "  <div style=\"display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:12.5px;color:var(--text-2);margin-bottom:12px;background:var(--bg2);padding:10px 12px;border-radius:8px\">"
    + "    <div><i class=\"fas fa-calendar-alt\" style=\"color:var(--maroon);margin-right:6px\"></i> <strong>Date Raised:</strong> " + c.submitted_at + "</div>"
    + "    <div><i class=\"fas fa-user-tie\" style=\"color:var(--maroon);margin-right:6px\"></i> <strong>Assigned Counselor:</strong> " + c.counselor_name + "</div>"
    + "  </div>"
    + "  <div class=\"csc-guidance-box\">"
    + "    <strong><i class=\"fas fa-lightbulb\" style=\"color:var(--maroon)\"></i> Teacher Classroom Recommendation:</strong>"
    + "    " + c.guidance_note
    + "  </div>"
    + "  <div class=\"csc-confidential-tag\">"
    + "    <i class=\"fas fa-lock\" style=\"color:var(--maroon)\"></i>"
    + "    <span><strong>Student Privacy Notice:</strong> Full incident descriptions, private witness logs, and counseling session notes are strictly confidential between the student and Guidance Counselor. Advisory teachers are notified so you can foster an empathetic classroom environment.</span>"
    + "  </div>"
    + referralBtn
    + "</div>"
    + "<div style=\"display:flex;justify-content:flex-end;margin-top:16px;padding-top:12px;border-top:1px solid var(--border)\">"
    + "  <button class=\"btn-secondary\" onclick=\"closeConcernModal()\">Close</button>"
    + "</div>";

  document.getElementById("concernSummaryModal").classList.remove("hidden");
}
</script>
';

renderLayout($user, 'My Advisory Class', 'advisory', $content);
