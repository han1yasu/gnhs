<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('student');

$db = getDB();

$studentFullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$revStudentName  = trim(($user['last_name'] ?? '') . ' ' . ($user['first_name'] ?? ''));

// Fetch resolved or closed cases for this student
$stmt = $db->prepare("SELECT * FROM cases WHERE student_id=? AND status IN ('resolved','closed') ORDER BY updated_at DESC, submitted_at DESC");
$stmt->execute([$user['id']]);
$cases = $stmt->fetchAll();

// Fetch archived teacher referrals for this student
$refStmt = $db->prepare("SELECT r.*, CONCAT(t.first_name, ' ', t.last_name) AS teacher_name,
    cs.session_date, cs.ended_at AS session_ended_at, cs.recommendations AS session_recommendations, cs.notes AS session_notes,
    u.first_name AS counselor_first, u.last_name AS counselor_last
    FROM referrals r
    JOIN users t ON r.teacher_id = t.id
    LEFT JOIN counseling_sessions cs ON cs.id = (
        SELECT id FROM counseling_sessions WHERE referral_id = r.id ORDER BY session_date DESC LIMIT 1
    )
    LEFT JOIN users u ON cs.counselor_id = u.id
    WHERE (
        r.student_id = :uid 
        OR LOWER(TRIM(r.student_name)) = LOWER(:fullName) 
        OR LOWER(TRIM(r.student_name)) = LOWER(:revFullName)
    ) AND r.status IN ('resolved', 'closed')
    ORDER BY COALESCE(cs.ended_at, r.submitted_at) DESC, r.submitted_at DESC");
$refStmt->execute([':uid' => $user['id'], ':fullName' => $studentFullName, ':revFullName' => $revStudentName]);
$archivedRefs = $refStmt->fetchAll();

function badge(string $s): string {
    $map=['pending'=>'status-pending','under_review'=>'status-under_review','ongoing'=>'status-ongoing','resolved'=>'status-resolved','closed'=>'status-resolved'];
    return "<span class='status-badge ".($map[$s]??'status-resolved')."'>".str_replace('_',' ',ucfirst($s))."</span>";
}
function prioBadge(string $p): string {
    $map=['high'=>'priority-high','medium'=>'priority-medium','low'=>'priority-low'];
    return "<span class='priority-badge ".($map[$p]??'priority-low')."'><i class='fas fa-circle'></i>".ucfirst($p)."</span>";
}

ob_start();
?>
<div class="header-card">
  <h2><i class="fas fa-archive" style="color:var(--maroon);margin-right:10px"></i> Case Archive</h2>
  <p style="color:var(--text-2);margin-top:6px;">View your past resolved cases. If you feel a concern was not properly resolved, you may submit an appeal or follow up.</p>
</div>

<div style="background:var(--maroon-pale);border:1px solid var(--maroon-tint);border-radius:10px;padding:12px 18px;margin-top:18px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
  <div style="display:flex;align-items:center;gap:10px;font-size:13.5px;color:var(--text)">
    <i class="fas fa-shield-halved" style="color:var(--maroon);font-size:18px"></i>
    <span>Official guidance resolutions and counseling outcomes are documented here. You can click <strong>View</strong> to review resolution details, or <strong>Appeal</strong> if further intervention is needed.</span>
  </div>
  <a href="student-cases.php" class="btn-sm-outline" style="font-size:12px;padding:6px 14px;white-space:nowrap;display:inline-flex;align-items:center;gap:6px">
    <i class="fas fa-folder-open"></i> Back to My Cases
  </a>
</div>

<div class="content-card" style="margin-top:18px">
  <div class="card-header" style="flex-wrap:wrap;gap:12px">
    <div style="display:flex;gap:10px;align-items:center">
      <button type="button" class="btn-sm-outline" id="tabCasesArchiveBtn" onclick="switchArchiveTab('cases')" style="padding:8px 14px;font-weight:700;display:inline-flex;align-items:center;gap:6px;cursor:pointer;background:var(--maroon);color:#fff;border-color:var(--maroon)">
        <i class="fas fa-folder-open"></i> Resolved Cases (<?= count($cases) ?>)
      </button>
      <button type="button" class="btn-sm-outline" id="tabRefsArchiveBtn" onclick="switchArchiveTab('referrals')" style="padding:8px 14px;font-weight:700;display:inline-flex;align-items:center;gap:6px;cursor:pointer">
        <i class="fas fa-hand-holding-heart"></i> Teacher Referrals (<?= count($archivedRefs) ?>)
      </button>
    </div>
    <a href="student-submit.php" class="btn-sm-primary"><i class="fas fa-plus"></i> New Concern</a>
  </div>

  <div id="archiveCasesPane">

  <?php if (empty($cases)): ?>
    <div style="text-align:center;padding:60px 20px;color:var(--text-3)">
      <div style="width:72px;height:72px;border-radius:50%;background:rgba(85,0,0,0.06);color:var(--maroon);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:28px">
        <i class="fas fa-box-open"></i>
      </div>
      <h3 style="font-size:17px;font-weight:700;color:var(--text);margin-bottom:6px">No Resolved Cases in Archive</h3>
      <p style="font-size:13.5px;max-width:440px;margin:0 auto;line-height:1.5">When your active concerns are reviewed, addressed, and concluded by the Guidance Counselor, they will appear here in your permanent archive.</p>
      <div style="margin-top:18px">
        <a href="student-cases.php" class="btn-sm-primary" style="display:inline-flex;align-items:center;gap:6px"><i class="fas fa-folder-open"></i> View My Active Cases</a>
      </div>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Case #</th>
            <th>Subject</th>
            <th>Type</th>
            <th>Status</th>
            <th>Priority</th>
            <th>Resolved Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cases as $c): 
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
                 $peerName = htmlspecialchars($c['reported_student_name'] ?: 'Peer');
                 $mediaBadges .= "<span class='badge-witness' style='background:#fdf2f8;color:#be185d;border-color:#fbcfe8' title='Reported for: {$peerName}'><i class='fas fa-hand-holding-heart'></i> For: {$peerName}</span>";
             }
             $anonBadge = !empty($c['is_anonymous']) ? "<span class='badge-anon' title='Your identity is anonymous to the guidance counselor'><i class='fas fa-user-secret'></i> Anon</span>" : "";
             $dateLabel = $c['updated_at'] ? date('M d, Y h:i A', strtotime($c['updated_at'])) : date('M d, Y', strtotime($c['submitted_at']));
          ?>
            <tr>
              <td class="cell-case-num"><span class="case-num-text"><?= htmlspecialchars($c['case_number']) ?></span><?= $anonBadge ?></td>
              <td style="font-weight:600"><?= htmlspecialchars($c['subject']) ?></td>
              <td>
                <div class="case-type-wrap">
                  <span class="case-type-label"><?= $type ?></span>
                  <?= $mediaBadges ?>
                </div>
              </td>
              <td><?= badge($c['status']) ?></td>
              <td><?= prioBadge($c['priority']) ?></td>
              <td class="cell-date"><?= $dateLabel ?></td>
              <td class="cell-actions" style="white-space:nowrap">
                <div style="display:flex;align-items:center;gap:6px">
                  <button type="button" class="btn-row-action" onclick="openCaseModal(<?= (int)$c['id'] ?>)">
                    <i class="fas fa-eye"></i> View
                  </button>
                  <?php if (!empty($c['is_follow_up'])): ?>
                    <span class="badge-witness" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;padding:5px 9px;border-radius:6px;font-weight:700;font-size:11.5px;display:inline-flex;align-items:center;gap:4px" title="Appeal under counselor review">
                      <i class="fas fa-clock-rotate-left"></i> Appealed
                    </span>
                  <?php elseif (!empty($c['follow_up_msg'])): ?>
                    <span class="badge-witness" style="background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;padding:5px 9px;border-radius:6px;font-weight:700;font-size:11.5px;display:inline-flex;align-items:center;gap:4px" title="Appeal was reviewed and addressed by counselor">
                      <i class="fas fa-check-circle"></i> Appeal Addressed
                    </span>
                    <button type="button" class="btn-sm-primary" style="background:var(--maroon);padding:5px 10px;font-size:12px;display:inline-flex;align-items:center;gap:5px" onclick="openAppealModal(<?= (int)$c['id'] ?>, '<?= htmlspecialchars($c['case_number']) ?>')">
                      <i class="fas fa-reply"></i> Re-appeal
                    </button>
                  <?php else: ?>
                    <button type="button" class="btn-sm-primary" style="background:var(--maroon);padding:5px 10px;font-size:12px;display:inline-flex;align-items:center;gap:5px" onclick="openAppealModal(<?= (int)$c['id'] ?>, '<?= htmlspecialchars($c['case_number']) ?>')">
                      <i class="fas fa-reply"></i> Appeal
                    </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  </div>

  <!-- Pane 2: Archived Referrals -->
  <div id="archiveRefsPane" style="display:none">
  <?php if (empty($archivedRefs)): ?>
    <div style="text-align:center;padding:60px 20px;color:var(--text-3)">
      <div style="width:72px;height:72px;border-radius:50%;background:rgba(37,99,235,0.06);color:#2563eb;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:28px">
        <i class="fas fa-hand-holding-heart"></i>
      </div>
      <h3 style="font-size:17px;font-weight:700;color:var(--text);margin-bottom:6px">No Archived Teacher Referrals</h3>
      <p style="font-size:13.5px;max-width:440px;margin:0 auto;line-height:1.5">When teacher referrals on your behalf are completed and concluded, they will appear here in your permanent archive.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Ref #</th>
            <th>Referred By</th>
            <th>Concern</th>
            <th>Urgency</th>
            <th>Status</th>
            <th>Resolved Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($archivedRefs as $ar): 
             $type = ucfirst(str_replace('_',' ',$ar['concern_type']));
             $arDate = !empty($ar['session_ended_at']) ? date('M d, Y h:i A', strtotime($ar['session_ended_at'])) : date('M d, Y', strtotime($ar['submitted_at']));
             $arJson = htmlspecialchars(json_encode([
                 'ref_number'     => $ar['ref_number'],
                 'teacher_name'   => $ar['teacher_name'],
                 'concern_type'   => $ar['concern_type'],
                 'urgency'        => $ar['urgency'],
                 'status'         => $ar['status'],
                 'observations'   => $ar['observations'],
                 'ai_summary'     => $ar['ai_summary'] ?? '',
                 'submitted_at'   => date('M d, Y g:i A', strtotime($ar['submitted_at'])),
                 'resolved_at'    => $arDate,
                 'counselor_name' => trim(($ar['counselor_first'] ?? '') . ' ' . ($ar['counselor_last'] ?? '')) ?: 'Guidance Staff',
                 'session_recommendations' => $ar['session_recommendations'] ?? ''
             ]), ENT_QUOTES, 'UTF-8');
          ?>
            <tr>
              <td class="cell-case-num"><span class="case-num-text" style="color:var(--maroon);font-weight:800"><?= htmlspecialchars($ar['ref_number']) ?></span></td>
              <td style="font-weight:600"><i class="fas fa-chalkboard-teacher" style="color:var(--maroon);margin-right:4px"></i><?= htmlspecialchars($ar['teacher_name']) ?></td>
              <td><span class="case-type-label"><?= $type ?></span></td>
              <td><?= prioBadge($ar['urgency']) ?></td>
              <td><span class="status-badge status-resolved">Resolved</span></td>
              <td class="cell-date"><?= $arDate ?></td>
              <td class="cell-actions">
                <button type="button" class="btn-row-action" onclick="openArchivedRefModal(this)" data-ref='<?= $arJson ?>'>
                  <i class="fas fa-eye"></i> View Outcome
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  </div>
</div>

<!-- Archived Referral Detail Modal -->
<div class="modal-overlay hidden" id="archivedRefModal" onclick="if(event.target.id==='archivedRefModal')this.classList.add('hidden')">
  <div class="modal-box" style="max-width:650px;width:95%">
    <button type="button" class="modal-close" onclick="document.getElementById('archivedRefModal').classList.add('hidden')" aria-label="Close" title="Close"><i class="fas fa-times"></i></button>
    <div id="archivedRefModalBody"></div>
  </div>
</div>

<!-- Case Detail Modal -->
<div class="modal-overlay hidden" id="caseModal" onclick="if(event.target.id==='caseModal')this.classList.add('hidden')">
  <div class="modal-box" style="max-width:600px">
    <button type="button" class="modal-close" onclick="document.getElementById('caseModal').classList.add('hidden')" aria-label="Close" title="Close"><i class="fas fa-times"></i></button>
    <h2 class="modal-title">Case Detail</h2>
    <div id="caseModalBody"></div>
  </div>
</div>

<!-- Appeal Modal -->
<div class="modal-overlay hidden" id="appealModal" onclick="if(event.target.id==='appealModal')this.classList.add('hidden')">
  <div class="modal-box" style="max-width:540px">
    <button type="button" class="modal-close" onclick="closeAppealModal()" aria-label="Close" title="Close"><i class="fas fa-times"></i></button>
    <div style="width:48px;height:48px;border-radius:12px;background:rgba(85,0,0,0.08);color:var(--maroon);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:20px">
      <i class="fas fa-reply-all"></i>
    </div>
    <h2 class="modal-title" style="text-align:center">Appeal Resolved Case</h2>
    <p class="modal-sub" style="text-align:center;margin-bottom:16px">Case Ref: <strong id="appealCaseNum" style="color:var(--maroon)"></strong></p>
    
    <div id="appealMsg" style="display:none;margin-bottom:14px;padding:10px 14px;border-radius:8px;font-size:13px"></div>
    
    <input type="hidden" id="appealCaseId">
    
    <div class="form-group" style="text-align:left">
      <label style="font-weight:700;font-size:13px;color:var(--text);display:block;margin-bottom:6px">
        Reason for Appeal / Follow-Up <span class="req" style="color:red">*</span>
      </label>
      <p style="font-size:12px;color:var(--text-3);margin-top:0;margin-bottom:8px">
        Explain clearly why you feel this concern requires further guidance intervention or follow-up counseling.
      </p>
      <textarea id="appealDescription" rows="5" maxlength="4000" placeholder="Please describe what transpired or why you wish to appeal this case resolution (max 400 words)..." style="width:100%;padding:12px;border-radius:8px;border:1px solid var(--border);resize:vertical;font-family:inherit;font-size:13.5px;box-sizing:border-box" oninput="updateAppealCounter()"></textarea>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:4px">
        <span id="appealWordCounter" style="font-size:11.5px;color:var(--text-3)">0 / 400 words</span>
        <span style="font-size:11.5px;color:var(--text-3)">Min. 10 characters</span>
      </div>
    </div>
    
    <div class="form-actions" style="margin-top:20px;display:flex;gap:10px;justify-content:flex-end">
      <button type="button" class="btn-secondary" onclick="closeAppealModal()">Cancel</button>
      <button type="button" class="btn-primary" id="btnSubmitAppeal" onclick="submitAppeal()" style="display:inline-flex;align-items:center;gap:6px">
        <i class="fas fa-paper-plane"></i> Submit Appeal
      </button>
    </div>
  </div>
</div>

<script>
function openAppealModal(id, caseNum) {
    document.getElementById('appealCaseId').value = id;
    document.getElementById('appealCaseNum').textContent = caseNum;
    document.getElementById('appealDescription').value = '';
    const msg = document.getElementById('appealMsg');
    msg.style.display = 'none';
    msg.innerHTML = '';
    updateAppealCounter();
    document.getElementById('appealModal').classList.remove('hidden');
    document.getElementById('appealDescription').focus();
}

function closeAppealModal() {
    document.getElementById('appealModal').classList.add('hidden');
}

function updateAppealCounter() {
    const text = document.getElementById('appealDescription').value.trim();
    const words = text ? text.split(/\s+/).filter(Boolean).length : 0;
    const counter = document.getElementById('appealWordCounter');
    if (counter) {
        counter.textContent = words + ' / 400 words';
        counter.style.color = words > 400 ? '#dc2626' : 'var(--text-3)';
    }
}

async function submitAppeal() {
    const id = document.getElementById('appealCaseId').value;
    const desc = document.getElementById('appealDescription').value.trim();
    const msgBox = document.getElementById('appealMsg');
    const btn = document.getElementById('btnSubmitAppeal');
    
    if (!desc || desc.length < 10) {
        msgBox.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please provide a clear explanation for your appeal (at least 10 characters).';
        msgBox.style.background = '#fef2f2';
        msgBox.style.color = '#dc2626';
        msgBox.style.border = '1px solid #fecaca';
        msgBox.style.display = 'block';
        return;
    }

    const words = desc.split(/\s+/).filter(Boolean).length;
    if (words > 400) {
        msgBox.innerHTML = '<i class="fas fa-exclamation-circle"></i> Appeal description must not exceed 400 words.';
        msgBox.style.background = '#fef2f2';
        msgBox.style.color = '#dc2626';
        msgBox.style.border = '1px solid #fecaca';
        msgBox.style.display = 'block';
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    
    try {
        const res = await fetch('../api/submit_appeal.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ case_id: id, message: desc })
        });
        const data = await res.json();
        if (data.success) {
            msgBox.innerHTML = '<i class="fas fa-check-circle"></i> Appeal submitted successfully! Your case has been queued for guidance counselor review.';
            msgBox.style.background = '#f0fdf4';
            msgBox.style.color = '#15803d';
            msgBox.style.border = '1px solid #bbf7d0';
            msgBox.style.display = 'block';
            setTimeout(() => location.reload(), 300);
        } else {
            msgBox.innerHTML = '<i class="fas fa-circle-exclamation"></i> ' + (data.message || 'Error submitting appeal.');
            msgBox.style.background = '#fef2f2';
            msgBox.style.color = '#dc2626';
            msgBox.style.border = '1px solid #fecaca';
            msgBox.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Appeal';
        }
    } catch(e) {
        msgBox.innerHTML = '<i class="fas fa-circle-exclamation"></i> Server error. Please try again.';
        msgBox.style.background = '#fef2f2';
        msgBox.style.color = '#dc2626';
        msgBox.style.border = '1px solid #fecaca';
        msgBox.style.display = 'block';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Appeal';
    }
}

function switchArchiveTab(tab) {
  const btnCases = document.getElementById('tabCasesArchiveBtn');
  const btnRefs  = document.getElementById('tabRefsArchiveBtn');
  const paneCases = document.getElementById('archiveCasesPane');
  const paneRefs  = document.getElementById('archiveRefsPane');

  if (tab === 'referrals') {
    btnCases.style.background = '';
    btnCases.style.color = '';
    btnCases.style.borderColor = '';
    btnRefs.style.background = 'var(--maroon)';
    btnRefs.style.color = '#fff';
    btnRefs.style.borderColor = 'var(--maroon)';
    paneCases.style.display = 'none';
    paneRefs.style.display  = 'block';
  } else {
    btnRefs.style.background = '';
    btnRefs.style.color = '';
    btnRefs.style.borderColor = '';
    btnCases.style.background = 'var(--maroon)';
    btnCases.style.color = '#fff';
    btnCases.style.borderColor = 'var(--maroon)';
    paneRefs.style.display  = 'none';
    paneCases.style.display = 'block';
  }
}

function openArchivedRefModal(btn) {
  const ref = JSON.parse(btn.getAttribute('data-ref'));
  if (!ref) return;

  const typeLabel = (ref.concern_type || '').replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());

  document.getElementById('archivedRefModalBody').innerHTML = `
    <div style="border-bottom:1px solid var(--border);padding-bottom:14px;margin-bottom:18px;padding-right:48px;position:relative">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap">
        <span style="background:var(--maroon-pale);color:var(--maroon);font-weight:900;font-size:14px;padding:3px 10px;border-radius:6px;border:1px solid var(--maroon-tint)">#${ref.ref_number}</span>
        <span class="status-badge status-resolved">Resolved</span>
      </div>
      <h2 style="font-size:18px;font-weight:800;color:var(--text);margin:0 0 8px 0;display:flex;align-items:center;gap:8px">
        <i class="fas fa-archive" style="color:var(--maroon)"></i> Archived Referral from Teacher ${ref.teacher_name}
      </h2>
      <div style="font-size:12.5px;color:var(--text-3);display:flex;align-items:center;gap:18px;flex-wrap:wrap">
        <span><i class="fas fa-tag" style="color:var(--maroon);margin-right:4px"></i> Concern: <strong style="color:var(--text)">${typeLabel}</strong></span>
        <span><i class="fas fa-calendar-check" style="color:var(--maroon);margin-right:4px"></i> Resolved on: <strong style="color:var(--text)">${ref.resolved_at}</strong></span>
      </div>
    </div>

    <div style="background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:10px;padding:16px;margin-bottom:18px">
      <div style="font-size:12px;font-weight:800;color:#166534;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px">
        <i class="fas fa-check-circle"></i> Official Guidance Resolution Outcome
      </div>
      <div style="font-size:13.5px;color:#14532d;line-height:1.6">
        This teacher referral regarding <strong>${typeLabel}</strong> was addressed and concluded by Counselor <strong>${ref.counselor_name}</strong>.
      </div>
      ${ref.session_recommendations ? `
        <div style="margin-top:10px;padding-top:10px;border-top:1px dashed #86efac;font-size:13px;color:#14532d">
          <strong>Guidance Recommendations:</strong><br>${ref.session_recommendations}
        </div>` : ''}
    </div>

    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:18px">
      <div style="font-size:12px;font-weight:800;color:var(--maroon);text-transform:uppercase;letter-spacing:0.8px;margin-bottom:8px">
        <i class="fas fa-quote-left" style="color:var(--maroon)"></i> Teacher's Original Observations
      </div>
      <div style="font-size:13.5px;line-height:1.6;color:var(--text);white-space:pre-wrap;background:var(--bg);padding:12px;border-radius:8px">${ref.observations}</div>
    </div>

    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:14px;border-top:1px solid var(--border)">
      <button type="button" class="btn-secondary" onclick="document.getElementById('archivedRefModal').classList.add('hidden')">Close</button>
      <a href="student-sessions-archive.php" class="btn-primary" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px">
        <i class="fas fa-box-archive"></i> Go to Sessions Archive
      </a>
    </div>
  `;

  document.getElementById('archivedRefModal').classList.remove('hidden');
}

document.addEventListener('DOMContentLoaded', function() {
  const p = new URLSearchParams(window.location.search);
  const openId = p.get('open_case') || p.get('case_id');
  if (openId && typeof openCaseModal === 'function') {
    setTimeout(function() {
      openCaseModal(parseInt(openId));
    }, 200);
  }
});
</script>
<?php
$content = ob_get_clean();

renderLayout($user, 'Case Archive', 'archive', $content);
