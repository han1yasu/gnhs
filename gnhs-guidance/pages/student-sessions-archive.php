<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('student');
$db   = getDB();
$uid  = $user['id'];

function getStudentGuidanceRecommendations(array $s, PDO $db): string {
    if (!empty($s['session_recommendations'])) {
        return trim($s['session_recommendations']);
    }
    if (!empty($s['guidance_recommendations'])) {
        return trim($s['guidance_recommendations']);
    }
    if (!empty($s['case_id'])) {
        $stmt = $db->prepare("SELECT content FROM case_notes WHERE case_id = ? AND is_visible_to_student = 1 ORDER BY id DESC LIMIT 1");
        $stmt->execute([(int)$s['case_id']]);
        $visibleNote = $stmt->fetchColumn();
        if ($visibleNote) {
            return trim($visibleNote);
        }
    }
    $type = strtolower($s['concern_type'] ?? 'other');
    $defaultRecs = [
        'bullying' => "1. Your safety and emotional well-being are the highest priority of the Guidance Office. Continue to communicate openly with your counselor.\n2. Maintain safe boundaries; report any hostile or uncomfortable interactions immediately to guidance staff or your adviser.\n3. Remember you are fully supported by school policy; follow-up check-ins and peer mediation are actively available.",
        'academic_stress' => "1. Organize your study time into focused 25-minute intervals with structured resting periods.\n2. Reach out to your subject teachers or class adviser for academic consultations and lesson clarifications.\n3. Maintain healthy sleep routines and visit the Guidance Office whenever workload pressure feels overwhelming.",
        'emotional' => "1. Be gentle with yourself; experiencing emotional distress is valid, and you never have to carry challenges alone.\n2. Engage in grounding exercises, creative hobbies, and adequate rest to restore your energy.\n3. The Guidance Counselor is always available for follow-up listening sessions in a safe, confidential space.",
        'peer_conflict' => "1. Practice calm, active listening and respectful communication when interacting with peers.\n2. Give tense situations cooling-off time and avoid retaliatory words or online posts.\n3. The Guidance Office is prepared to facilitate peaceful, constructive restorative dialogue whenever needed.",
        'family' => "1. Focus on what is within your control, balancing home responsibilities with your school commitments.\n2. Stay connected with supportive friends, teachers, and guidance counselors.\n3. The Guidance Office remains an open, confidential space to discuss personal feelings and find coping strategies."
    ];
    return $defaultRecs[$type] ?? "1. Continue following your guidance action plan and keep open communication with your counselor.\n2. Utilize school guidance resources whenever you encounter difficulties or need support.\n3. Focus on personal well-being, healthy habits, and positive growth in your school journey.";
}

// Completed counseling sessions
$sessions = $db->query("SELECT cs.*,
    u.first_name AS counselor_first, u.last_name AS counselor_last,
    c.id AS case_id, c.case_number, c.subject AS case_subject, c.description AS case_description,
    c.concern_type, c.priority AS case_priority, c.status AS case_status,
    c.is_peer_report, c.reported_student_name, c.reported_student_grade,
    c.resolution, c.sanctions, c.guidance_recommendations,
    cs.recommendations AS session_recommendations
    FROM counseling_sessions cs 
    JOIN users u ON cs.counselor_id=u.id 
    JOIN cases c ON cs.case_id=c.id
    WHERE cs.student_id=$uid AND cs.status = 'completed'
    ORDER BY cs.session_date DESC")->fetchAll();

$rows = '';
foreach ($sessions as $s) {
    $dt = new DateTime($s['session_date']);
    $statusBadge = "<span class='status-badge status-resolved' style='background:#dcfce7;color:#166534;border:1px solid #86efac;font-weight:700'><i class='fas fa-check-circle'></i> Completed</span>";

    $recommendationsText = getStudentGuidanceRecommendations($s, $db);

    $sessionData = [
        'id'                     => (int)$s['id'],
        'case_id'                => (int)$s['case_id'],
        'case_number'            => $s['case_number'],
        'case_subject'           => $s['case_subject'],
        'case_description'       => $s['case_description'],
        'concern_type'           => $s['concern_type'],
        'case_priority'          => $s['case_priority'],
        'case_status'            => $s['case_status'],
        'is_peer_report'         => (int)($s['is_peer_report'] ?? 0),
        'reported_student_name'  => $s['reported_student_name'] ?? '',
        'reported_student_grade' => $s['reported_student_grade'] ?? '',
        'session_date_formatted' => $dt->format('M d, Y'),
        'session_time_formatted' => $dt->format('h:i A'),
        'counselor_name'         => trim($s['counselor_first'].' '.$s['counselor_last']),
        'location'               => $s['location'] ?? 'Guidance Office',
        'session_type'           => ucfirst($s['session_type'] ?? 'In-person'),
        'status'                 => $s['status'],
        'recommendations'        => $recommendationsText,
        'resolution'             => $s['resolution'] ?? '',
        'sanctions'              => $s['sanctions'] ?? ''
    ];
    $jsonAttr = htmlspecialchars(json_encode($sessionData), ENT_QUOTES, 'UTF-8');

    $peerIndicator = !empty($s['is_peer_report']) 
        ? "<div style='font-size:11px;color:#be185d;margin-top:2px'><i class='fas fa-hand-holding-heart'></i> For: ".htmlspecialchars($s['reported_student_name']?:'Peer')."</div>" 
        : "";

    $rows .= "<tr class='session-overview-row' onclick='openSessionRowOverview(this)' data-session='{$jsonAttr}' title='Click to view session summary and guidance recommendations'>
        <td>
          <strong style='color:var(--maroon);font-size:14px'>{$s['case_number']}</strong>
          $peerIndicator
        </td>
        <td>".$dt->format('M d, Y')."</td>
        <td style='font-weight:600'>".$dt->format('h:i A')."</td>
        <td>".htmlspecialchars($s['counselor_first'].' '.$s['counselor_last'])."</td>
        <td><i class='fas fa-map-marker-alt' style='color:var(--maroon);font-size:11px'></i> ".htmlspecialchars($s['location'] ?? 'Guidance Office')."</td>
        <td>".ucfirst($s['session_type'] ?? 'In-person')."</td>
        <td>$statusBadge</td>
        <td style='text-align:right'>
          <button type='button' class='btn-row-action' style='display:inline-flex;align-items:center;gap:5px'>
            <i class='fas fa-eye'></i> View Summary
          </button>
        </td>
    </tr>";
}
if (!$rows) {
    $rows = "<tr><td colspan='8' style='text-align:center;color:var(--text-3);padding:45px'>
        <i class='fas fa-calendar-check' style='font-size:32px;color:var(--border);display:block;margin-bottom:12px'></i>
        No completed counseling sessions archived yet.
    </td></tr>";
}

$countCompleted = count($sessions);

$content = <<<HTML
<style>
.session-overview-row {
  cursor: pointer;
  transition: all 0.15s ease;
}
.session-overview-row:hover {
  background-color: #fdf2f2 !important;
}
.session-overview-row:hover td {
  color: var(--text);
}
.session-overview-row:active {
  background-color: #fee2e2 !important;
}
</style>

<!-- Top Transition Banner to Active Sessions -->
<div style="background:var(--maroon-pale);border:1px solid var(--maroon-tint);border-radius:10px;padding:12px 18px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
  <div style="display:flex;align-items:center;gap:10px;font-size:13.5px;color:var(--text)">
    <i class="fas fa-calendar-alt" style="color:var(--maroon);font-size:18px"></i>
    <span>Looking for upcoming or active counseling appointments? Visit your active <strong>My Sessions</strong> page.</span>
  </div>
  <a href="student-sessions.php" class="btn-sm-primary" style="font-size:12px;padding:6px 14px;white-space:nowrap;display:inline-flex;align-items:center;gap:6px">
    <i class="fas fa-calendar-alt"></i> Go to Active Sessions &rarr;
  </a>
</div>

<div class="content-card">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <div>
      <h3 style="display:flex;align-items:center;gap:8px">
        <i class="fas fa-calendar-check" style="color:var(--maroon)"></i> Sessions Archive
        <span style="font-size:13px;font-weight:600;color:var(--text-3)">($countCompleted completed records)</span>
      </h3>
      <span style="font-size:13px;color:var(--text-3)">Official school archive of your completed counseling sessions, counselor recommendations, and resolutions.</span>
    </div>
    <span style="font-size:12.5px;color:var(--text-3);display:inline-flex;align-items:center;gap:6px">
      <i class="fas fa-hand-pointer" style="color:var(--maroon)"></i> Click any row to view recommendations
    </span>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Case #</th><th>Date</th><th>Time</th><th>Counselor</th><th>Location</th><th>Type</th><th>Status</th><th style="text-align:right">Action</th></tr></thead>
      <tbody>$rows</tbody>
    </table>
  </div>
</div>

<!-- ============================================================== -->
<!-- SESSION OVERVIEW & RECOMMENDATIONS MODAL FOR STUDENT -->
<!-- ============================================================== -->
<div class="modal-overlay hidden" id="studentSessionModal" onclick="if(event.target===this) closeStudentSessionModal()">
  <div class="modal-box" style="max-width:680px;width:95%;padding:26px;max-height:90vh;overflow-y:auto">
    <button type="button" class="modal-close" onclick="closeStudentSessionModal()"><i class="fas fa-times"></i></button>
    <div id="studentSessionModalBody">
      <div style="text-align:center;padding:40px"><span class="spinner" style="border-color:rgba(85,0,0,.2);border-top-color:var(--maroon);display:inline-block"></span></div>
    </div>
  </div>
</div>

<script>
function closeStudentSessionModal() {
  const m = document.getElementById('studentSessionModal');
  if (m) m.classList.add('hidden');
}

function openSessionRowOverview(rowEl) {
  if (!rowEl || !rowEl.dataset.session) return;
  let d;
  try {
    d = JSON.parse(rowEl.dataset.session);
  } catch (err) {
    return;
  }

  const modal = document.getElementById('studentSessionModal');
  const body = document.getElementById('studentSessionModalBody');
  if (!modal || !body) return;

  modal.classList.remove('hidden');

  const prioClass = (d.case_priority === 'high') ? 'priority-high' 
                  : ((d.case_priority === 'medium') ? 'priority-medium' : 'priority-low');

  const typeClean = (d.concern_type || '').replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());

  // Peer report banner
  let peerBanner = '';
  if (d.is_peer_report == 1) {
    peerBanner = `
      <div style="background:#fdf2f8;border:1.5px solid #fbcfe8;border-radius:10px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:12px">
        <div style="width:34px;height:34px;border-radius:50%;background:#f472b6;color:#fff;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0">
          <i class="fas fa-hand-holding-heart"></i>
        </div>
        <div style="flex:1">
          <div style="font-size:11px;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:0.5px">Peer Report</div>
          <div style="font-size:13px;color:#831843;margin-top:2px">
            Submitted for affected student: <strong>\${escapeHtml(d.reported_student_name || 'Classmate')}</strong>
            \${d.reported_student_grade ? ' (' + escapeHtml(d.reported_student_grade) + ')' : ''}
          </div>
        </div>
      </div>`;
  }

  body.innerHTML = `
    <!-- Modal Header -->
    <div style="border-bottom:1px solid var(--border);padding-bottom:14px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:flex-start">
      <div>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
          <span style="background:var(--maroon-pale);color:var(--maroon);font-weight:900;font-size:14px;padding:3px 10px;border-radius:6px;border:1px solid var(--maroon-tint)">#\${escapeHtml(d.case_number)}</span>
          <span class="status-badge status-resolved" style="font-size:11.5px;padding:3px 10px"><i class="fas fa-check-circle"></i> Completed Session</span>
        </div>
        <h2 style="font-size:18px;font-weight:800;color:var(--text);margin:0">\${escapeHtml(d.case_subject || 'Completed Counseling Session')}</h2>
      </div>
    </div>

    \${peerBanner}

    <!-- Appointment Schedule Grid -->
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:16px">
      <div style="font-size:12px;font-weight:800;color:var(--maroon);text-transform:uppercase;letter-spacing:1px;margin-bottom:12px">
        <i class="fas fa-calendar-check"></i> Session &amp; Counselor Details
      </div>
      <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:14px">
        <div>
          <label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:2px">Counselor</label>
          <span style="font-size:14.5px;font-weight:700;color:var(--text)"><i class="fas fa-user-shield" style="color:var(--maroon);margin-right:4px"></i> \${escapeHtml(d.counselor_name || 'Guidance Staff')}</span>
        </div>
        <div>
          <label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:2px">Session Date &amp; Time</label>
          <span style="font-size:14px;font-weight:700;color:var(--text)"><i class="fas fa-clock" style="color:var(--maroon);margin-right:4px"></i> \${d.session_date_formatted} at \${d.session_time_formatted}</span>
        </div>
        <div>
          <label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:2px">Location</label>
          <span style="font-size:13.5px;font-weight:600;color:var(--text)"><i class="fas fa-map-marker-alt" style="color:var(--maroon);margin-right:4px"></i> \${escapeHtml(d.location || 'Guidance Office')}</span>
        </div>
        <div>
          <label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:2px">Session Mode</label>
          <span style="font-size:13.5px;font-weight:600;color:var(--text)"><i class="fas fa-handshake" style="color:var(--maroon);margin-right:4px"></i> \${escapeHtml(d.session_type || 'In-person')}</span>
        </div>
      </div>
    </div>

    <!-- Case Context Section -->
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:16px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <div style="font-size:12px;font-weight:800;color:var(--maroon);text-transform:uppercase;letter-spacing:1px">
          <i class="fas fa-clipboard-list"></i> Linked Case Information
        </div>
        <div style="display:flex;gap:8px">
          <span class="status-badge status-ongoing" style="font-size:11px">\${typeClean}</span>
          <span class="priority-badge \${prioClass}" style="font-size:11px"><i class="fas fa-circle"></i> \${(d.case_priority||'medium').toUpperCase()}</span>
        </div>
      </div>
      <div style="font-size:12px;color:var(--text-3);margin-bottom:4px;font-weight:700;text-transform:uppercase">Your Submitted Concern:</div>
      <div class="concern-text" style="font-size:13.5px;max-height:160px;overflow-y:auto;background:var(--bg);border-radius:8px;padding:10px;line-height:1.6">
        \${escapeHtml(d.case_description || 'No description provided.')}
      </div>
    </div>

    <!-- Guidance Recommendations & Next Steps Section -->
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:16px">
      <div style="font-size:12px;font-weight:800;color:var(--maroon);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;display:flex;align-items:center;gap:6px">
        <i class="fas fa-lightbulb" style="color:var(--maroon);font-size:14px"></i> Guidance Office Recommendations &amp; Next Steps
      </div>
      <div style="font-size:13.5px;color:var(--text);line-height:1.6;white-space:pre-wrap;background:var(--bg);padding:12px 14px;border-radius:8px;border-left:3px solid var(--maroon)">\${escapeHtml(d.recommendations || 'The Guidance Office recommends maintaining regular communication with your counselor, practicing positive self-care, and reporting any concerns immediately to guidance staff.')}</div>
    </div>

    \${d.resolution ? `
      <div style="background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:10px;padding:16px;margin-bottom:16px">
        <div style="font-weight:800;color:#166534;font-size:13px;margin-bottom:4px"><i class="fas fa-check-circle"></i> Official Case Resolution</div>
        <div style="font-size:13.5px;color:#14532d;line-height:1.5;white-space:pre-wrap">\${escapeHtml(d.resolution)}</div>
        \${d.sanctions ? `
          <div style="margin-top:10px;padding-top:10px;border-top:1px dashed #86efac">
            <div style="font-weight:800;color:#991b1b;font-size:11.5px;margin-bottom:2px"><i class="fas fa-gavel"></i> School Action:</div>
            <div style="font-size:12.5px;color:#7f1d1d">\${escapeHtml(d.sanctions)}</div>
          </div>` : ''}
      </div>` : ''}

    <!-- Bottom Action Buttons -->
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:14px;border-top:1px solid var(--border)">
      <button type="button" class="btn-sm-outline" onclick="closeStudentSessionModal()">Close</button>
      <button type="button" class="btn-sm-primary" style="background:var(--blue)" onclick="window.location.href='student-chats.php?case_id=' + \${d.case_id}">
        <i class="fas fa-comment-dots"></i> Message Counselor
      </button>
      <button type="button" class="btn-sm-primary" onclick="window.location.href='student-archive.php?open_case=' + \${d.case_id}">
        <i class="fas fa-folder-open"></i> View Case in Archive
      </button>
    </div>
  `;
}
</script>
HTML;

renderLayout($user, 'Sessions Archive', 'sessions_archive', $content);
