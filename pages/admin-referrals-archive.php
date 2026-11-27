<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin');
$db   = getDB();

$search        = trim($_GET['q']        ?? '');
$filterType    = trim($_GET['type']     ?? '');
$filterUrgency = trim($_GET['urgency']  ?? '');
$filterTeacher = (int)($_GET['teacher'] ?? 0);

$where = "WHERE r.status IN ('resolved','closed')";
if ($filterType)    $where .= " AND r.concern_type='".addslashes($filterType)."'";
if ($filterUrgency) $where .= " AND r.urgency='".addslashes($filterUrgency)."'";
if ($filterTeacher) $where .= " AND r.teacher_id=" . $filterTeacher;
if ($search) {
    $s = addslashes($search);
    $where .= " AND (
        r.ref_number LIKE '%$s%'
        OR r.student_name LIKE '%$s%'
        OR r.grade_section LIKE '%$s%'
        OR r.observations LIKE '%$s%'
        OR t.first_name LIKE '%$s%'
        OR t.last_name LIKE '%$s%'
        OR CONCAT(t.first_name,' ',t.last_name) LIKE '%$s%'
    )";
}

$refs = $db->query("SELECT r.*, CONCAT(t.first_name,' ',t.last_name) AS teacher_name, t.email AS teacher_email
    FROM referrals r JOIN users t ON r.teacher_id=t.id
    $where ORDER BY r.submitted_at DESC, r.id DESC")->fetchAll();

$teachersList = $db->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM users WHERE role='teacher' ORDER BY first_name ASC")->fetchAll();

function urgencyBadge(string $u): string {
    $map=['urgent'=>'priority-high','moderate'=>'priority-medium','low'=>'priority-low'];
    return "<span class='priority-badge ".($map[$u]??'priority-low')."'><i class='fas fa-circle'></i> ".ucfirst($u)."</span>";
}
function statusBadge(string $s): string {
    $map=['resolved'=>'status-resolved'];
    return "<span class='status-badge ".($map[$s]??'status-resolved')."'>".ucfirst($s)."</span>";
}

$rows = '';
foreach ($refs as $r) {
    $rawType  = strtolower($r['concern_type']);
    $type     = ucfirst(str_replace('_',' ',$r['concern_type']));
    $date     = date('M d, Y', strtotime($r['submitted_at']));
    $refData  = json_encode([
        'id'           => (int)$r['id'],
        'ref_number'   => $r['ref_number'],
        'student_name' => $r['student_name'],
        'grade_section'=> $r['grade_section'],
        'concern_type' => $r['concern_type'],
        'urgency'      => $r['urgency'],
        'status'       => $r['status'],
        'observations' => $r['observations'],
        'ai_summary'   => $r['ai_summary'] ?? null,
        'teacher_name' => $r['teacher_name'],
        'teacher_email'=> $r['teacher_email'] ?? '',
        'submitted_at' => date('M d, Y g:i A', strtotime($r['submitted_at'])),
    ], JSON_HEX_QUOT | JSON_HEX_APOS);

    $searchTokens = [
        $r['ref_number'],
        preg_replace('/[^0-9]/', '', $r['ref_number']),
        $r['student_name'],
        $r['grade_section'],
        $rawType,
        str_replace('_', ' ', $rawType),
        $type,
        $r['urgency'],
        $r['teacher_name'],
        $r['status'],
        $date,
    ];
    $searchData = htmlspecialchars(strtolower(implode(' ', array_filter($searchTokens))), ENT_QUOTES);

    $rows .= "<tr class='ref-archive-row' data-search=\"$searchData\">
        <td><strong style='color:var(--maroon)'>{$r['ref_number']}</strong></td>
        <td>".htmlspecialchars($r['student_name'])."</td>
        <td>".htmlspecialchars($r['grade_section'])."</td>
        <td>$type</td>
        <td>".urgencyBadge($r['urgency'])."</td>
        <td>".htmlspecialchars($r['teacher_name'])."</td>
        <td>".statusBadge($r['status'])."</td>
        <td style='color:var(--text-3);font-size:12px'>$date</td>
        <td>
          <button class='btn-sm-outline' onclick='openArchivedRefModal(this)' data-ref='$refData'>
            <i class='fas fa-eye'></i> View
          </button>
        </td>
    </tr>";
}

$totalCount = count($refs);
if (!$rows) {
    $rows = "<tr><td colspan='9' style='text-align:center;color:var(--text-3);padding:40px'>" .
        ($search || $filterType || $filterUrgency || $filterTeacher ? "No archived referrals match your current filters." : "No archived referrals found. Referrals marked as resolved will be stored here.") .
        "</td></tr>";
}

$sqVal = htmlspecialchars($search);
$st1 = $filterType==='bullying'        ? 'selected' : '';
$st2 = $filterType==='academic_stress' ? 'selected' : '';
$st3 = $filterType==='emotional'       ? 'selected' : '';
$st4 = $filterType==='family'          ? 'selected' : '';
$st5 = $filterType==='peer_conflict'   ? 'selected' : '';
$st6 = $filterType==='behavioral'      ? 'selected' : '';
$st7 = $filterType==='attendance'      ? 'selected' : '';
$st8 = $filterType==='other'           ? 'selected' : '';

$su1 = $filterUrgency==='urgent'       ? 'selected' : '';
$su2 = $filterUrgency==='moderate'     ? 'selected' : '';
$su3 = $filterUrgency==='low'          ? 'selected' : '';

$teacherOptions = '';
foreach ($teachersList as $t) {
    $selected = ($filterTeacher === (int)$t['id']) ? 'selected' : '';
    $teacherOptions .= "<option value='{$t['id']}' $selected>" . htmlspecialchars($t['name']) . "</option>";
}

$content = <<<HTML
<div class="content-card">
  <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div>
      <h3 style="margin:0;display:flex;align-items:center;gap:10px;">
        <i class="fas fa-archive" style="color:var(--maroon)"></i> Referrals Archive 
        <span id="archiveRefsCount" style="font-size:13px;font-weight:600;color:var(--text-3);">($totalCount records)</span>
      </h3>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
      <a href="admin-referrals.php" class="btn-sm-outline" style="text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Back to Active Referrals
      </a>
    </div>
  </div>

  <div style="background:var(--maroon-pale);border:1px solid var(--maroon-tint);border-radius:10px;padding:12px 16px;margin:16px 0;font-size:13px;color:var(--maroon);display:flex;align-items:center;gap:10px">
    <i class="fas fa-info-circle" style="flex-shrink:0"></i>
    <span style="flex:1;line-height:1.5">These are teacher referrals that have been addressed and marked as <strong>Resolved</strong>. They are preserved for student counseling history and record keeping.</span>
  </div>

  <!-- Filter & Search Bar -->
  <div class="filter-bar" style="margin-bottom:18px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
    <div style="display:flex;gap:8px;flex:1;min-width:240px">
      <div class="input-wrap" style="flex:1">
        <i class="fas fa-search"></i>
        <input type="text" id="searchQ" value="$sqVal" placeholder="Search by student, teacher, ref #, or keyword…" autocomplete="off"
          oninput="filterArchiveRefsLive(this.value)"
          onkeydown="if(event.key==='Enter'){event.preventDefault();doSearch();}"/>
      </div>
      <button class="btn-sm-primary" onclick="doSearch()"><i class="fas fa-search"></i> Search</button>
    </div>

    <select class="status-select" onchange="applyFilter('type',this.value)">
      <option value="">All Concern Types</option>
      <option value="bullying"        $st1>Bullying</option>
      <option value="academic_stress" $st2>Academic Stress</option>
      <option value="emotional"       $st3>Emotional</option>
      <option value="family"          $st4>Family</option>
      <option value="peer_conflict"   $st5>Peer Conflict</option>
      <option value="behavioral"      $st6>Behavioral</option>
      <option value="attendance"      $st7>Attendance</option>
      <option value="other"           $st8>Other</option>
    </select>

    <select class="status-select" onchange="applyFilter('urgency',this.value)">
      <option value="">All Urgency</option>
      <option value="urgent"   $su1>Urgent</option>
      <option value="moderate" $su2>Moderate</option>
      <option value="low"      $su3>Low</option>
    </select>

    <select class="status-select" onchange="applyFilter('teacher',this.value)">
      <option value="">All Teachers</option>
      $teacherOptions
    </select>

    <button class="btn-sm-outline" onclick="window.location='admin-referrals-archive.php'"><i class="fas fa-times"></i> Clear</button>
  </div>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Ref #</th>
          <th>Student</th>
          <th>Grade/Section</th>
          <th>Concern</th>
          <th>Urgency</th>
          <th>Referred By</th>
          <th>Status</th>
          <th>Date Submitted</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>$rows</tbody>
    </table>
  </div>
</div>

<!-- Archived Referral Detail Modal -->
<div class="modal-overlay hidden" id="archivedRefModal" onclick="if(event.target.id==='archivedRefModal')this.classList.add('hidden')">
  <div class="modal-box" style="max-width:980px; width:95%">
    <button class="modal-close" onclick="document.getElementById('archivedRefModal').classList.add('hidden')"><i class="fas fa-times"></i></button>
    <h2 class="modal-title"><i class="fas fa-archive" style="color:var(--maroon);margin-right:8px"></i>Archived Referral Detail</h2>
    <div id="archivedRefModalBody"></div>
  </div>
</div>

<!-- Reopen Referral Tabbed Popup (Reason -> Confirmation) -->
<div class="modal-overlay hidden" id="reopenRefModal" style="z-index:100020" onclick="if(event.target.id==='reopenRefModal')closeReopenRefModal()">
  <div class="modal-box" style="max-width:520px;width:95%;padding:26px">
    <button class="modal-close" onclick="closeReopenRefModal()"><i class="fas fa-times"></i></button>
    <div style="width:50px;height:50px;border-radius:14px;background:#fdf4ff;color:#7e22ce;display:flex;align-items:center;justify-content:center;font-size:22px;margin:0 auto 12px;border:1px solid #e9d5ff">
      <i class="fas fa-undo"></i>
    </div>
    <h2 class="modal-title" style="text-align:center;margin-bottom:4px">Reopen Archived Referral</h2>
    <p class="modal-sub" id="reopenRefSub" style="text-align:center;margin-bottom:16px"></p>

    <div style="display:flex;gap:8px;margin-bottom:18px;background:var(--bg2);padding:5px;border-radius:10px;border:1px solid var(--border)">
      <button type="button" id="rrTabBtn1" onclick="setReopenRefStep(1)" style="flex:1;padding:8px 10px;border-radius:7px;border:none;font-size:12.5px;font-weight:700;cursor:pointer;background:var(--maroon);color:#fff">
        1. Reopen Reason
      </button>
      <button type="button" id="rrTabBtn2" onclick="goReopenRefConfirmStep()" style="flex:1;padding:8px 10px;border-radius:7px;border:none;font-size:12.5px;font-weight:700;cursor:pointer;background:transparent;color:var(--text-3)">
        2. Confirmation
      </button>
    </div>

    <div id="rrErrMsg" class="error-msg" style="display:none;margin-bottom:12px"></div>

    <div id="rrStep1">
      <div style="background:var(--bg2);border:1px solid var(--border);border-radius:8px;padding:10px 12px;margin-bottom:14px;font-size:13px;color:var(--text-2)">
        <strong>Target Active Status:</strong> <span class="status-badge status-pending" style="margin-left:6px">Pending</span>
      </div>
      <div class="form-group" style="margin-bottom:18px">
        <label style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px">Reason for Reopening Referral <span class="req">*</span></label>
        <textarea id="reopenRefReasonInput" rows="4" class="form-control" style="width:100%;padding:10px 12px;border-radius:8px;border:1.5px solid var(--border);font-size:13.5px;box-sizing:border-box;background:var(--bg)" placeholder="Explain why the Guidance Counselor is reopening this referral (e.g., follow-up monitoring requested, recurring behavioral concern)..."></textarea>
        <small style="font-size:11.5px;color:var(--text-3);display:block;margin-top:4px">This reason is logged and included in the notification sent to the referring teacher and student.</small>
      </div>
      <div style="display:flex;gap:10px">
        <button type="button" class="btn-secondary" style="flex:1" onclick="closeReopenRefModal()">Cancel</button>
        <button type="button" class="btn-primary" style="flex:1" onclick="goReopenRefConfirmStep()">Continue &rarr;</button>
      </div>
    </div>

    <div id="rrStep2" style="display:none">
      <div style="background:#fdf4ff;border:1.5px solid #d8b4fe;border-radius:10px;padding:14px;margin-bottom:16px">
        <div style="font-size:12px;font-weight:800;color:#7e22ce;text-transform:uppercase;margin-bottom:8px">
          <i class="fas fa-check-double"></i> Confirm Referral Reopening
        </div>
        <div style="font-size:13px;color:var(--text);margin-bottom:6px"><strong>Referral:</strong> <span id="rrConfirmNum"></span></div>
        <div style="font-size:13px;color:var(--text);margin-bottom:6px"><strong>New Active Status:</strong> <span id="rrConfirmStatus"></span></div>
        <div style="font-size:13px;color:var(--text)"><strong>Counselor Reason:</strong>
          <div id="rrConfirmReason" style="margin-top:4px;background:#fff;padding:8px 10px;border-radius:6px;border:1px solid #e9d5ff;color:#581c87;white-space:pre-wrap"></div>
        </div>
      </div>
      <p style="font-size:12.5px;color:var(--text-2);margin-bottom:16px;text-align:center">Are you sure you want to reopen this referral and move it back to Active Referrals?</p>
      <div style="display:flex;gap:10px">
        <button type="button" class="btn-secondary" style="flex:1" onclick="setReopenRefStep(1)">&larr; Edit Reason</button>
        <button type="button" class="btn-primary" id="btnConfirmReopenRef" style="flex:1;background:#7e22ce;border-color:#6b21a8" onclick="submitReopenArchivedReferral()"><i class="fas fa-undo"></i> Confirm &amp; Reopen</button>
      </div>
    </div>
  </div>
</div>
HTML;

$content .= '
<script>
let refArchSearchTimer = null;

function syncRefArchSearchUrl(q) {
  try {
    const url = new URL(window.location);
    if (q) url.searchParams.set("q", q);
    else url.searchParams.delete("q");
    window.history.replaceState({}, "", url);
  } catch (e) {}
}

function filterArchiveRefsLive(query) {
  const q = (query || "").trim().toLowerCase();
  const rows = document.querySelectorAll(".data-table tbody tr.ref-archive-row");
  const countBadge = document.getElementById("archiveRefsCount");
  const tbody = document.querySelector(".data-table tbody");
  if (!tbody) return;

  const existingNoMatch = tbody.querySelector(".live-no-match-row");
  if (existingNoMatch) existingNoMatch.remove();
  if (!rows || rows.length === 0) return;

  if (!q) {
    rows.forEach(r => r.style.display = "");
    if (countBadge) countBadge.textContent = "(" + rows.length + " records)";
    clearTimeout(refArchSearchTimer);
    refArchSearchTimer = setTimeout(() => syncRefArchSearchUrl(""), 300);
    return;
  }

  const tokens = q.split(/\s+/).filter(Boolean);
  let visibleCount = 0;
  rows.forEach(r => {
    const hay = r.getAttribute("data-search") || "";
    const matched = tokens.every(tok => hay.includes(tok));
    r.style.display = matched ? "" : "none";
    if (matched) visibleCount++;
  });

  if (visibleCount === 0) {
    const tr = document.createElement("tr");
    tr.className = "live-no-match-row";
    tr.innerHTML = "<td colspan=\"9\" style=\"text-align:center;color:var(--text-3);padding:40px\">No archived referrals match your search.</td>";
    tbody.appendChild(tr);
  }

  if (countBadge) {
    countBadge.textContent = "(" + visibleCount + (visibleCount !== rows.length ? " of " + rows.length : "") + " records)";
  }

  clearTimeout(refArchSearchTimer);
  refArchSearchTimer = setTimeout(() => syncRefArchSearchUrl(q), 350);
}

function escapeArchiveRefHtml(str) {
  if (!str) return "";
  return String(str).replace(/[&<>"\x27]/g, function(m) {
    return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","\x27":"&#39;"}[m];
  });
}

function openReopenRefFromBtn(btn) {
  if (!btn) return;
  openReopenRefModal(btn.dataset.refId, btn.dataset.refNum, btn.dataset.studentName);
}

function doSearch() {
  const input = document.getElementById("searchQ");
  const val = input ? input.value.trim() : "";
  filterArchiveRefsLive(val);
  syncRefArchSearchUrl(val);
}

function applyFilter(key, val) {
  const url = new URL(window.location);
  if (val) url.searchParams.set(key, val);
  else url.searchParams.delete(key);
  const q = document.getElementById("searchQ")?.value.trim();
  if (q) url.searchParams.set("q", q);
  window.location = url;
}

document.addEventListener("DOMContentLoaded", function() {
  const input = document.getElementById("searchQ");
  if (input) {
    input.addEventListener("input", function() {
      filterArchiveRefsLive(this.value);
    });
    if (input.value.trim()) {
      filterArchiveRefsLive(input.value.trim());
    }
  }
});

function openArchivedRefModal(btn) {
  const ref = JSON.parse(btn.getAttribute("data-ref"));
  const urgMap   = {urgent:"priority-high", moderate:"priority-medium", low:"priority-low"};
  const typeLabel = ref.concern_type.replace(/_/g," ").replace(/\b\w/g, l=>l.toUpperCase());

  document.getElementById("archivedRefModalBody").innerHTML = `
    <div class="review-header-strip" style="display:flex;flex-wrap:wrap;gap:15px;background:var(--bg2);padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid var(--border)">
      <div style="flex:1;min-width:120px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Referral ID</label><strong style="color:var(--maroon);font-size:16px">#${ref.ref_number}</strong></div>
      <div style="flex:2;min-width:150px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Student</label><span style="font-size:15px;font-weight:600">${ref.student_name}</span></div>
      <div style="flex:1.5;min-width:130px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Concern</label><span class="status-badge status-ongoing">${typeLabel}</span></div>
      <div style="flex:1.5;min-width:130px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Grade/Sec</label><span style="font-size:14px">${ref.grade_section}</span></div>
      <div style="flex:1.5;min-width:130px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Submitted</label><span style="font-size:13px;color:var(--text-2)">${ref.submitted_at}</span></div>
    </div>
    
    <div class="review-grid" style="display:grid;grid-template-columns: 1.3fr 1fr; gap:25px; align-items:start">
      <div>
        ${ref.ai_summary ? `
          <div class="ai-box" style="margin-bottom:20px;border-left:4px solid var(--blue);background:rgba(37,99,235,0.05);padding:14px;border-radius:8px">
            <div class="ai-box-label" style="color:var(--blue);font-size:13px;font-weight:700;margin-bottom:6px">
              <i class="fas fa-magic"></i> AI Summary
            </div>
            <div class="ai-box-text" style="font-size:14px;line-height:1.6;color:var(--text)">${ref.ai_summary}</div>
          </div>
        ` : ""}
        
        <div style="background:var(--bg2);padding:18px;border-radius:8px;border:1px solid var(--border);margin-bottom:16px;">
          <div style="font-size:13px;font-weight:800;color:var(--text-2);margin-bottom:10px;text-transform:uppercase;letter-spacing:1px">
            <i class="fas fa-quote-left" style="color:var(--maroon)"></i> Teacher\'s Observations
          </div>
          <div style="font-size:14px;line-height:1.7;color:var(--text);white-space:pre-wrap;">${ref.observations}</div>
        </div>
      </div>
      
      <div style="background:var(--bg2);padding:20px;border-radius:8px;border:1px solid var(--border)">
        <h3 style="margin-bottom:16px;font-size:15px;color:var(--text);border-bottom:1px solid var(--border);padding-bottom:10px">
          <i class="fas fa-info-circle" style="color:var(--maroon)"></i> Archive Information
        </h3>

        <div class="form-group" style="margin-bottom:14px">
          <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:4px">Archive Status</label>
          <span class="status-badge status-resolved"><i class="fas fa-check-circle"></i> ${ref.status.toUpperCase()}</span>
        </div>

        <div class="form-group" style="margin-bottom:14px">
          <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:4px">Urgency Level</label>
          <span class="priority-badge ${urgMap[ref.urgency]||\'priority-low\'}"><i class="fas fa-circle"></i> ${ref.urgency.charAt(0).toUpperCase()+ref.urgency.slice(1)}</span>
        </div>

        <div class="form-group" style="margin-bottom:18px;border-top:1px solid var(--border);padding-top:14px">
          <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:4px">Referred By</label>
          <div style="font-weight:700;font-size:14px;color:var(--text)">${ref.teacher_name}</div>
          ${ref.teacher_email ? `<div style="font-size:12px;color:var(--text-3);margin-top:2px">${ref.teacher_email}</div>` : ""}
        </div>

        <div style="display:flex;flex-direction:column;gap:10px;">
          <button class="btn-primary" style="width:100%;background:var(--blue);display:flex;align-items:center;justify-content:center;gap:8px;" onclick="window.location.href=\'admin-referrals-chat.php?ref_id=${ref.id}\'">
            <i class="fas fa-comments"></i> View Chat History
          </button>

          <button type="button" class="btn-sm-outline" style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px;padding:8px;" onclick="printArchivedSlip(${ref.id})">
            <i class="fas fa-print"></i> Print Referral Slip
          </button>

          <div style="border-top:1px solid var(--border);margin-top:8px;padding-top:12px;">
            <label style="font-size:12px;font-weight:700;color:var(--text-2);display:block;margin-bottom:6px">Reopen Referral</label>
            <p style="font-size:11px;color:var(--text-3);margin-bottom:8px">If this referral needs follow-up, restore it to Active Referrals with your reason.</p>
            <button type="button" class="btn-sm-outline" data-ref-id="${ref.id}" data-ref-num="${escapeArchiveRefHtml(ref.ref_number)}" data-student-name="${escapeArchiveRefHtml(ref.student_name)}" style="width:100%;border-color:#7e22ce;color:#7e22ce;font-weight:700;display:flex;align-items:center;justify-content:center;gap:6px;padding:9px;cursor:pointer" onclick="openReopenRefFromBtn(this)">
              <i class="fas fa-undo"></i> Reopen Referral
            </button>
          </div>
        </div>
      </div>
    </div>
  `;

  document.getElementById("archivedRefModal").classList.remove("hidden");
}

let _reopenRefId = null;
let _reopenRefNum = "";

function openReopenRefModal(id, refNum, studentName) {
  _reopenRefId = id;
  _reopenRefNum = refNum;
  document.getElementById("reopenRefSub").textContent = "Referral #" + refNum + " • " + (studentName || "Student");
  document.getElementById("reopenRefReasonInput").value = "";
  const errEl = document.getElementById("rrErrMsg");
  if (errEl) { errEl.style.display = "none"; errEl.textContent = ""; }
  setReopenRefStep(1);
  const modal = document.getElementById("reopenRefModal");
  if (modal) {
    if (modal.parentElement !== document.body) document.body.appendChild(modal);
    modal.classList.remove("hidden");
  }
  setTimeout(() => document.getElementById("reopenRefReasonInput")?.focus(), 120);
}

function closeReopenRefModal() {
  document.getElementById("reopenRefModal")?.classList.add("hidden");
}

function setReopenRefStep(step) {
  const s1 = document.getElementById("rrStep1");
  const s2 = document.getElementById("rrStep2");
  const b1 = document.getElementById("rrTabBtn1");
  const b2 = document.getElementById("rrTabBtn2");
  if (step === 2) {
    s1.style.display = "none";
    s2.style.display = "block";
    b1.style.background = "transparent"; b1.style.color = "var(--text-3)";
    b2.style.background = "#7e22ce"; b2.style.color = "#fff";
  } else {
    s2.style.display = "none";
    s1.style.display = "block";
    b1.style.background = "var(--maroon)"; b1.style.color = "#fff";
    b2.style.background = "transparent"; b2.style.color = "var(--text-3)";
  }
}

function goReopenRefConfirmStep() {
  const reason = (document.getElementById("reopenRefReasonInput")?.value || "").trim();
  const errEl = document.getElementById("rrErrMsg");
  if (reason.length < 5) {
    if (errEl) {
      errEl.textContent = "Please provide a clear reason for reopening this referral (at least 5 characters).";
      errEl.style.display = "block";
    }
    document.getElementById("reopenRefReasonInput")?.focus();
    return;
  }
  if (errEl) { errEl.style.display = "none"; errEl.textContent = ""; }
  document.getElementById("rrConfirmNum").textContent = "#" + _reopenRefNum;
  document.getElementById("rrConfirmStatus").textContent = "Pending";
  document.getElementById("rrConfirmReason").textContent = reason;
  setReopenRefStep(2);
}

async function submitReopenArchivedReferral() {
  if (!_reopenRefId) return;
  const reason = (document.getElementById("reopenRefReasonInput")?.value || "").trim();
  const btn = document.getElementById("btnConfirmReopenRef");
  const errEl = document.getElementById("rrErrMsg");
  if (btn) { btn.disabled = true; btn.innerHTML = "<span class=\"spinner\"></span> Reopening…"; }
  try {
    const p = window.location.pathname || "";
    const apiBase = p.includes("/gnhs-guidance/gnhs-guidance/")
      ? "/gnhs-guidance/gnhs-guidance/api/"
      : (p.startsWith("/gnhs-guidance/") ? "/gnhs-guidance/api/" : "/api/");
    const r = await fetch(apiBase + "update_referral.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: _reopenRefId, status: "pending", reopen_reason: reason })
    });
    const d = await r.json();
    if (d.success) {
      showToast("Referral #" + _reopenRefNum + " reopened and restored to Active Referrals!", "success");
      closeReopenRefModal();
      document.getElementById("archivedRefModal").classList.add("hidden");
      setTimeout(() => window.location.href = "admin-referrals.php", 250);
    } else {
      if (errEl) { errEl.textContent = d.message || "Failed to reopen referral."; errEl.style.display = "block"; }
      setReopenRefStep(1);
    }
  } catch(e) {
    if (errEl) { errEl.textContent = "Error updating referral status."; errEl.style.display = "block"; }
    setReopenRefStep(1);
  } finally {
    if (btn) { btn.disabled = false; btn.innerHTML = "<i class=\"fas fa-undo\"></i> Confirm &amp; Reopen"; }
  }
}

function printArchivedSlip(id) {
  window.print();
}
</script>
';

renderLayout($user, 'Referrals Archive', 'referrals_archive', $content);
