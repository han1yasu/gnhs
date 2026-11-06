<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('student');

$db = getDB();
// Fetch resolved or closed cases for this student
$stmt = $db->prepare("SELECT * FROM cases WHERE student_id=? AND status IN ('resolved','closed') ORDER BY updated_at DESC, submitted_at DESC");
$stmt->execute([$user['id']]);
$cases = $stmt->fetchAll();

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
  <div class="card-header">
    <h3><i class="fas fa-box-archive"></i> Resolved & Completed Cases (<?= count($cases) ?>)</h3>
    <a href="student-submit.php" class="btn-sm-primary"><i class="fas fa-plus"></i> New Concern</a>
  </div>

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

<!-- Case Detail Modal -->
<div class="modal-overlay hidden" id="caseModal" onclick="if(event.target.id==='caseModal')this.classList.add('hidden')">
  <div class="modal-box" style="max-width:600px">
    <button class="modal-close" onclick="document.getElementById('caseModal').classList.add('hidden')"><i class="fas fa-times"></i></button>
    <h2 class="modal-title">Case Detail</h2>
    <div id="caseModalBody"></div>
  </div>
</div>

<!-- Appeal Modal -->
<div class="modal-overlay hidden" id="appealModal" onclick="if(event.target.id==='appealModal')this.classList.add('hidden')">
  <div class="modal-box" style="max-width:540px">
    <button class="modal-close" onclick="closeAppealModal()"><i class="fas fa-times"></i></button>
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
        const res = await fetch('/gnhs-guidance/api/submit_appeal.php', {
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
            setTimeout(() => location.reload(), 1500);
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
