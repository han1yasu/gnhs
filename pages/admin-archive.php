<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin');
$db   = getDB();

$search = trim($_GET['q'] ?? '');
$filterType = $_GET['type'] ?? '';

$where = "WHERE c.status IN ('resolved','closed') AND COALESCE(c.is_follow_up, 0) = 0";
if ($filterType) $where .= " AND c.concern_type='".addslashes($filterType)."'";
if ($search) {
    $sq = addslashes($search);
    $sqUnderscore = addslashes(str_replace(' ', '_', $search));

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
        OR (COALESCE(c.is_anonymous, 0) = 0 AND (
            LOWER(s.first_name) LIKE LOWER('%$sq%')
            OR LOWER(s.last_name) LIKE LOWER('%$sq%')
            OR LOWER(CONCAT(s.first_name,' ',s.last_name)) LIKE LOWER('%$sq%')
            OR LOWER(s.grade_section) LIKE LOWER('%$sq%')
        ))
        OR LOWER(COALESCE(c.reported_student_name, '')) LIKE LOWER('%$sq%')
        OR LOWER(c.status) LIKE LOWER('%$sq%')
        OR LOWER(c.priority) LIKE LOWER('%$sq%')
        OR (c.is_anonymous=1 AND LOWER('anonymous') LIKE LOWER('%$sq%'))
        " . (!$isTypeSearch ? "OR LOWER(c.subject) LIKE LOWER('%$sq%')" : "") . "
    )";
}

$cases = $db->query("SELECT c.*,
    CASE WHEN c.is_anonymous=1 THEN 'Anonymous' ELSE CONCAT(s.first_name,' ',s.last_name) END AS student_name,
    CASE WHEN c.is_anonymous=1 THEN 'Confidential' ELSE s.grade_section END AS grade_section
    FROM cases c LEFT JOIN users s ON c.student_id=s.id
    $where ORDER BY c.submitted_at DESC, c.id DESC")->fetchAll();

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
    $rawType = strtolower($c['concern_type']);
    $type = ucfirst(str_replace('_',' ',$c['concern_type']));
    $submitted = date('M d, Y', strtotime($c['submitted_at']));
    $updated   = date('M d, Y', strtotime($c['updated_at']));

    $searchTokens = [
        $c['case_number'],
        $c['student_name'],
        $c['reported_student_name'] ?? '',
        $c['grade_section'] ?? '',
        $rawType,
        str_replace('_', ' ', $rawType),
        $type,
        $c['status'],
        $c['priority'],
        $c['is_anonymous'] ? 'anonymous anon' : '',
        $submitted,
        $updated
    ];
    $searchData = htmlspecialchars(strtolower(implode(' ', array_filter($searchTokens))), ENT_QUOTES);

    $rows .= "<tr class='case-row' data-search=\"$searchData\">
        <td class='cell-case-num'><span class='case-num-text'>{$c['case_number']}</span></td>
        <td class='cell-student'>".htmlspecialchars($c['student_name'])."</td>
        <td class='cell-grade'>".htmlspecialchars($c['grade_section']??'—')."</td>
        <td>
          <div class='case-type-wrap'>
            <span class='case-type-label'>$type</span>
          </div>
        </td>
        <td>".badge($c['status'])."</td>
        <td>".prioBadge($c['priority'])."</td>
        <td class='cell-date'>$submitted</td>
        <td class='cell-date'>$updated</td>
        <td class='cell-actions'><button type='button' class='btn-row-action' onclick='viewArchivedCase({$c['id']})'>
          <i class='fas fa-eye'></i> View</button></td>
    </tr>";
}
if (!$rows) $rows = "<tr class='no-archive-row'><td colspan='9' style='text-align:center;color:var(--text-3);padding:40px'>".
    ($search ? "No archived cases matching \"".htmlspecialchars($search)."\"." : "No archived cases yet.")."</td></tr>";

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
    <h3><i class="fas fa-archive"></i> Archived Cases <span id="archiveCasesCount" style="font-size:13px;font-weight:600;color:var(--text-3);margin-left:6px">($total resolved/closed)</span></h3>
    <a href="admin-cases.php" class="btn-sm-outline"><i class="fas fa-arrow-left"></i> Back to Active</a>
  </div>
  <div style="background:var(--maroon-pale);border:1px solid var(--maroon-tint);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:var(--maroon);display:flex;align-items:center;gap:10px">
    <i class="fas fa-info-circle" style="flex-shrink:0"></i>
    <span style="flex:1;line-height:1.5">These are cases that have been marked as <strong>Resolved</strong> or <strong>Closed</strong>. They are kept for records.</span>
  </div>
  <div class="filter-bar" style="margin-bottom:16px;flex-wrap:wrap">
    <div style="display:flex;gap:8px;flex:1;min-width:220px">
      <div class="input-wrap" style="flex:1">
        <i class="fas fa-search"></i>
        <input type="text" id="searchQ" value="$sqVal" placeholder="Search archived cases by name, case #, type…"
          autocomplete="off"
          onkeydown="if(event.key==='Enter'){event.preventDefault();doSearch();}"/>
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

<!-- View Modal -->
<div class="modal-overlay hidden" id="archiveModal" onclick="if(event.target.id==='archiveModal')this.classList.add('hidden')">
  <div class="modal-box" style="max-width:660px">
    <button class="modal-close" onclick="document.getElementById('archiveModal').classList.add('hidden')"><i class="fas fa-times"></i></button>
    <h2 class="modal-title">Archived Case</h2>
    <div id="archiveModalBody"><div style="text-align:center;padding:40px"><span class="spinner" style="border-color:rgba(85,0,0,.2);border-top-color:var(--maroon);display:inline-block"></span></div></div>
  </div>
</div>

<!-- Reopen Case Tabbed Popup (Reason -> Confirmation) -->
<div class="modal-overlay hidden" id="reopenCaseModal" style="z-index:100020" onclick="if(event.target.id==='reopenCaseModal')closeReopenCaseModal()">
  <div class="modal-box" style="max-width:520px;width:95%;padding:26px">
    <button class="modal-close" onclick="closeReopenCaseModal()"><i class="fas fa-times"></i></button>
    <div style="width:50px;height:50px;border-radius:14px;background:#fdf4ff;color:#7e22ce;display:flex;align-items:center;justify-content:center;font-size:22px;margin:0 auto 12px;border:1px solid #e9d5ff">
      <i class="fas fa-folder-open"></i>
    </div>
    <h2 class="modal-title" style="text-align:center;margin-bottom:4px">Reopen Archived Case</h2>
    <p class="modal-sub" id="reopenCaseSub" style="text-align:center;margin-bottom:16px"></p>

    <div style="display:flex;gap:8px;margin-bottom:18px;background:var(--bg2);padding:5px;border-radius:10px;border:1px solid var(--border)">
      <button type="button" id="rcTabBtn1" onclick="setReopenCaseStep(1)" style="flex:1;padding:8px 10px;border-radius:7px;border:none;font-size:12.5px;font-weight:700;cursor:pointer;background:var(--maroon);color:#fff">
        1. Reopen Reason
      </button>
      <button type="button" id="rcTabBtn2" onclick="goReopenCaseConfirmStep()" style="flex:1;padding:8px 10px;border-radius:7px;border:none;font-size:12.5px;font-weight:700;cursor:pointer;background:transparent;color:var(--text-3)">
        2. Confirmation
      </button>
    </div>

    <div id="rcErrMsg" class="error-msg" style="display:none;margin-bottom:12px"></div>

    <div id="rcStep1">
      <div class="form-group" style="margin-bottom:14px">
        <label style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px">Restore Case To Active Status</label>
        <div class="select-wrap"><i class="fas fa-tag"></i>
          <select id="reopenCaseTargetStatus">
            <option value="pending">Pending</option>
            <option value="ongoing">Ongoing</option>
          </select>
        </div>
      </div>
      <div class="form-group" style="margin-bottom:18px">
        <label style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px">Reason for Reopening Case <span class="req">*</span></label>
        <textarea id="reopenCaseReasonInput" rows="4" class="form-control" style="width:100%;padding:10px 12px;border-radius:8px;border:1.5px solid var(--border);font-size:13.5px;box-sizing:border-box;background:var(--bg)" placeholder="Explain why the Guidance Counselor is reopening this case (e.g., new incident surfaced, follow-up observation needed, parent requested reassessment)..."></textarea>
        <small style="font-size:11.5px;color:var(--text-3);display:block;margin-top:4px">This reason is logged in case notes and shared with the student.</small>
      </div>
      <div style="display:flex;gap:10px">
        <button type="button" class="btn-secondary" style="flex:1" onclick="closeReopenCaseModal()">Cancel</button>
        <button type="button" class="btn-primary" style="flex:1" onclick="goReopenCaseConfirmStep()">Continue &rarr;</button>
      </div>
    </div>

    <div id="rcStep2" style="display:none">
      <div style="background:#fdf4ff;border:1.5px solid #d8b4fe;border-radius:10px;padding:14px;margin-bottom:16px">
        <div style="font-size:12px;font-weight:800;color:#7e22ce;text-transform:uppercase;margin-bottom:8px">
          <i class="fas fa-check-double"></i> Confirm Case Reopening
        </div>
        <div style="font-size:13px;color:var(--text);margin-bottom:6px"><strong>Case:</strong> <span id="rcConfirmNum"></span></div>
        <div style="font-size:13px;color:var(--text);margin-bottom:6px"><strong>New Active Status:</strong> <span id="rcConfirmStatus"></span></div>
        <div style="font-size:13px;color:var(--text)"><strong>Counselor Reason:</strong>
          <div id="rcConfirmReason" style="margin-top:4px;background:#fff;padding:8px 10px;border-radius:6px;border:1px solid #e9d5ff;color:#581c87;white-space:pre-wrap"></div>
        </div>
      </div>
      <p style="font-size:12.5px;color:var(--text-2);margin-bottom:16px;text-align:center">Are you sure you want to reopen this case and move it back to Active Cases?</p>
      <div style="display:flex;gap:10px">
        <button type="button" class="btn-secondary" style="flex:1" onclick="setReopenCaseStep(1)">&larr; Edit Reason</button>
        <button type="button" class="btn-primary" id="btnConfirmReopenCase" style="flex:1;background:#7e22ce;border-color:#6b21a8" onclick="submitReopenArchivedCase()"><i class="fas fa-folder-open"></i> Confirm &amp; Reopen</button>
      </div>
    </div>
  </div>
</div>

<script>
let searchDebounceTimer = null;

function filterArchiveLive(query) {
  const q = (query || '').trim().toLowerCase();
  const rows = document.querySelectorAll('.data-table tbody tr.case-row');
  const countBadge = document.getElementById('archiveCasesCount');
  const tbody = document.querySelector('.data-table tbody');
  if (!tbody) return;

  const existingNoMatch = tbody.querySelector('.live-no-match-row');
  if (existingNoMatch) existingNoMatch.remove();

  if (!rows || rows.length === 0) return;

  if (!q) {
    rows.forEach(r => r.style.display = '');
    if (countBadge) countBadge.textContent = '(' + rows.length + ' resolved/closed)';
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
    noMatchTr.innerHTML = '<td colspan="9" style="text-align:center;color:var(--text-3);padding:40px">' +
      '<i class="fas fa-search" style="margin-right:8px;opacity:0.5;font-size:16px"></i>' +
      'No archived cases found matching "<strong>' + escapeHtml(query) + '</strong>".</td>';
    tbody.appendChild(noMatchTr);
  }

  if (countBadge) {
    countBadge.textContent = '(' + visibleCount + (visibleCount !== rows.length ? ' of ' + rows.length : '') + ' resolved/closed)';
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
  filterArchiveLive(val);
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

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/[&<>"']/g, function(m) {
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
  });
}

document.addEventListener('DOMContentLoaded', function() {
  const input = document.getElementById('searchQ');
  if (input) {
    input.addEventListener('input', function() {
      filterArchiveLive(this.value);
    });
    if (input.value.trim()) {
      filterArchiveLive(input.value.trim());
    }
  }
});

async function viewArchivedCase(id) {
  document.getElementById('archiveModal').classList.remove('hidden');
  const body = document.getElementById('archiveModalBody');
  body.innerHTML = '<div style="text-align:center;padding:40px"><span class="spinner" style="border-color:rgba(85,0,0,.2);border-top-color:var(--maroon);display:inline-block"></span></div>';
  try {
    const p = window.location.pathname || '';
    const apiUrl = p.includes('/gnhs-guidance/gnhs-guidance/')
      ? '/gnhs-guidance/gnhs-guidance/api/get_case.php?id=' + id + '&_t=' + Date.now()
      : '/gnhs-guidance/api/get_case.php?id=' + id + '&_t=' + Date.now();
    const r = await fetch(apiUrl, { cache: 'no-store' });
    const d = await r.json();
    if (d.success) {
      const c = d.case, notes = d.notes||[];
      let evidenceList = [];
      if (c.evidence_files) {
        try {
          evidenceList = typeof c.evidence_files === 'string' ? JSON.parse(c.evidence_files) : c.evidence_files;
        } catch(e) { evidenceList = []; }
      }
      if (!Array.isArray(evidenceList)) evidenceList = [];

      body.innerHTML = '<div class="review-meta">'
        +'<div class="rm-row"><label>Case #</label><strong style="color:var(--maroon)">'+c.case_number+'</strong></div>'
        +'<div class="rm-row"><label>Student</label><span>'+c.student_name+'</span></div>'
        +'<div class="rm-row"><label>Type</label><span>'+c.concern_type.replace(/_/g,' ')+'</span></div>'
        +'<div class="rm-row"><label>Status</label><span class="status-badge status-resolved">'+c.status+'</span></div>'
        +'<div class="rm-row"><label>Submitted</label><span>'+c.submitted_at+'</span></div>'
        +'</div>'
        +'<div class="form-group" style="margin-top:14px"><label style="font-size:13px;font-weight:800;color:var(--text-2);display:block;margin-bottom:8px">Description</label>'
        +'<div class="concern-text">'+c.description+'</div></div>'
        +(c.witnesses ? '<div style="margin-top:14px;background:var(--bg2);padding:14px;border-radius:8px;border:1px solid var(--border)"><label style="font-size:12px;font-weight:800;color:var(--text-2);display:block;margin-bottom:6px"><i class="fas fa-users" style="color:var(--maroon)"></i> Witnesses</label><div style="font-size:14px;white-space:pre-wrap">'+escapeHtml(c.witnesses)+'</div></div>' : '')
        +(evidenceList.length ? '<div style="margin-top:14px;background:var(--bg2);padding:14px;border-radius:8px;border:1px solid var(--border)"><label style="font-size:12px;font-weight:800;color:var(--text-2);display:block;margin-bottom:10px"><i class="fas fa-photo-film" style="color:var(--maroon)"></i> Attached Evidence ('+evidenceList.length+')</label><div class="evidence-gallery-grid">'+renderEvidenceGallery(evidenceList)+'</div></div>' : '')
        +(c.ai_summary ? '<div class="ai-box" style="margin-top:12px"><div class="ai-box-label"><i class="fas fa-robot"></i> AI Summary</div><div class="ai-box-text">'+c.ai_summary+'</div></div>' : '')
        +(c.resolution ? '<div style="margin-top:14px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:14px">'
          +'<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">'
          +'<strong style="color:#15803d;font-size:13px"><i class="fas fa-check-circle"></i> Official Case Resolution</strong>'
          +(c.resolution_process ? '<span style="font-size:11px;font-weight:700;background:#dcfce7;color:#166534;border:1px solid #86efac;padding:2px 8px;border-radius:12px">'+escapeHtml(c.resolution_process)+'</span>' : '')
          +'</div>'
          +'<div style="font-size:13px;color:#166534;white-space:pre-wrap">'+escapeHtml(c.resolution)+'</div>'
          +(c.guidance_recommendations ? '<div style="margin-top:8px;padding-top:8px;border-top:1px dashed #86efac"><strong style="color:#1d4ed8;font-size:12px;display:block;margin-bottom:2px"><i class="fas fa-lightbulb"></i> Guidance Recommendations &amp; Advice for Student:</strong><div style="font-size:12.5px;color:#1e3a8a;white-space:pre-wrap">'+escapeHtml(c.guidance_recommendations)+'</div></div>' : '')
          +(c.sanctions ? '<div style="margin-top:8px;padding-top:8px;border-top:1px dashed #86efac"><strong style="color:#991b1b;font-size:12px;display:block;margin-bottom:2px"><i class="fas fa-gavel"></i> Regulated School Rule Sanction / Action:</strong><div style="font-size:12.5px;color:#7f1d1d;white-space:pre-wrap">'+escapeHtml(c.sanctions)+'</div></div>' : '')+'</div>' : '')
        +(notes.length ? '<div style="margin-top:14px"><label style="font-size:13px;font-weight:800;color:var(--text-2);display:block;margin-bottom:8px">Counselor Notes</label>'
          +notes.map(n=>'<div class="case-note-item '+(n.is_visible_to_student=='1'?'note-response':'note-counselor')+'">'+escapeHtml(n.content)+'</div>').join('')+'</div>' : '')
        +'<div style="margin-top:18px;padding-top:14px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">'
        +'<div style="font-size:12px;color:var(--text-3)">Need to resume guidance action on this case?</div>'
        +'<button type="button" class="btn-sm-outline" data-case-id="'+c.id+'" data-case-num="'+escapeHtml(c.case_number)+'" data-student-name="'+escapeHtml(c.student_name||'Student')+'" style="border-color:#7e22ce;color:#7e22ce;font-weight:700;padding:8px 14px;display:inline-flex;align-items:center;gap:6px;cursor:pointer" onclick="openReopenCaseFromBtn(this)">'
        +'<i class="fas fa-undo"></i> Reopen Case</button></div>';
    }
  } catch(e) {
    body.innerHTML = '<p style="text-align:center;color:var(--text-3);padding:24px">Could not load case details.</p>';
  }
}

let _reopenCaseId = null;
let _reopenCaseNum = '';

function openReopenCaseFromBtn(btn) {
  if (!btn) return;
  openReopenCaseModal(btn.dataset.caseId, btn.dataset.caseNum, btn.dataset.studentName);
}

function openReopenCaseModal(caseId, caseNum, studentName) {
  _reopenCaseId = caseId;
  _reopenCaseNum = caseNum;
  document.getElementById('reopenCaseSub').textContent = 'Case #' + caseNum + ' • ' + (studentName || 'Student');
  document.getElementById('reopenCaseReasonInput').value = '';
  document.getElementById('reopenCaseTargetStatus').value = 'pending';
  const errEl = document.getElementById('rcErrMsg');
  if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }
  setReopenCaseStep(1);
  const modal = document.getElementById('reopenCaseModal');
  if (modal) {
    if (modal.parentElement !== document.body) document.body.appendChild(modal);
    modal.classList.remove('hidden');
  }
  setTimeout(() => document.getElementById('reopenCaseReasonInput')?.focus(), 120);
}

function closeReopenCaseModal() {
  document.getElementById('reopenCaseModal')?.classList.add('hidden');
}

function setReopenCaseStep(step) {
  const s1 = document.getElementById('rcStep1');
  const s2 = document.getElementById('rcStep2');
  const b1 = document.getElementById('rcTabBtn1');
  const b2 = document.getElementById('rcTabBtn2');
  if (step === 2) {
    s1.style.display = 'none';
    s2.style.display = 'block';
    b1.style.background = 'transparent'; b1.style.color = 'var(--text-3)';
    b2.style.background = '#7e22ce'; b2.style.color = '#fff';
  } else {
    s2.style.display = 'none';
    s1.style.display = 'block';
    b1.style.background = 'var(--maroon)'; b1.style.color = '#fff';
    b2.style.background = 'transparent'; b2.style.color = 'var(--text-3)';
  }
}

function goReopenCaseConfirmStep() {
  const reason = (document.getElementById('reopenCaseReasonInput')?.value || '').trim();
  const status = document.getElementById('reopenCaseTargetStatus')?.value || 'pending';
  const errEl = document.getElementById('rcErrMsg');
  if (reason.length < 5) {
    if (errEl) {
      errEl.textContent = 'Please provide a clear reason (at least 5 characters) before continuing.';
      errEl.style.display = 'block';
    }
    document.getElementById('reopenCaseReasonInput')?.focus();
    return;
  }
  if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }
  document.getElementById('rcConfirmNum').textContent = '#' + _reopenCaseNum;
  document.getElementById('rcConfirmStatus').textContent = status === 'ongoing' ? 'Ongoing' : 'Pending';
  document.getElementById('rcConfirmReason').textContent = reason;
  setReopenCaseStep(2);
}

async function submitReopenArchivedCase() {
  if (!_reopenCaseId) return;
  const reason = (document.getElementById('reopenCaseReasonInput')?.value || '').trim();
  const status = document.getElementById('reopenCaseTargetStatus')?.value || 'pending';
  const btn = document.getElementById('btnConfirmReopenCase');
  const errEl = document.getElementById('rcErrMsg');
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Reopening…'; }
  try {
    const p = window.location.pathname || '';
    const apiUrl = p.includes('/gnhs-guidance/gnhs-guidance/')
      ? '/gnhs-guidance/gnhs-guidance/api/update_case.php'
      : '/gnhs-guidance/api/update_case.php';
    const r = await fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ case_id: _reopenCaseId, status: status, reopen_reason: reason })
    });
    const d = await r.json();
    if (d.success) {
      showToast('Case #' + _reopenCaseNum + ' reopened and moved to All Cases!', 'success');
      closeReopenCaseModal();
      document.getElementById('archiveModal')?.classList.add('hidden');
      setTimeout(() => window.location.href = 'admin-cases.php?reopened=1&case=' + encodeURIComponent(_reopenCaseNum), 250);
    } else {
      if (errEl) { errEl.textContent = d.message || 'Failed to reopen case.'; errEl.style.display = 'block'; }
      setReopenCaseStep(1);
    }
  } catch (e) {
    if (errEl) { errEl.textContent = 'Network error while reopening case.'; errEl.style.display = 'block'; }
    setReopenCaseStep(1);
  } finally {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-folder-open"></i> Confirm &amp; Reopen'; }
  }
}
</script>
HTML;

renderLayout($user, 'Case Archive', 'archive', $content);



