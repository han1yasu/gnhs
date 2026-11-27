<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin');
$db   = getDB();

$filterStatus  = $_GET['status']  ?? '';
$filterUrgency = $_GET['urgency'] ?? '';
$search        = trim($_GET['q']  ?? '');

if ($filterStatus) {
    $where = "WHERE r.status='".addslashes($filterStatus)."'";
} else {
    // Default to active referrals (Pending and Under Review)
    $where = "WHERE r.status NOT IN ('resolved','closed')";
}

if ($filterUrgency) $where .= " AND r.urgency='".addslashes($filterUrgency)."'";

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

$refs = $db->query("SELECT r.*, CONCAT(t.first_name,' ',t.last_name) AS teacher_name, t.email AS teacher_email,
    cs.id AS session_id, cs.session_date, cs.session_type, cs.location, cs.meeting_link, cs.status AS session_status
    FROM referrals r 
    JOIN users t ON r.teacher_id=t.id
    LEFT JOIN counseling_sessions cs ON cs.id = (
        SELECT id FROM counseling_sessions 
        WHERE referral_id = r.id AND status IN ('scheduled', 'in_progress', 'ongoing') 
        ORDER BY session_date DESC LIMIT 1
    )
    $where ORDER BY r.submitted_at DESC, r.id DESC")->fetchAll();

$archivedCount = (int)$db->query("SELECT COUNT(*) FROM referrals WHERE status IN ('resolved','closed')")->fetchColumn();
$activeCount   = (int)$db->query("SELECT COUNT(*) FROM referrals WHERE status NOT IN ('resolved','closed')")->fetchColumn();

if (!function_exists('urgencyBadge')) {
    function urgencyBadge(string $u): string {
        $map=['urgent'=>'priority-high','moderate'=>'priority-medium','low'=>'priority-low'];
        return "<span class='priority-badge ".($map[$u]??'priority-low')."'><i class='fas fa-circle'></i> ".ucfirst($u)."</span>";
    }
}
if (!function_exists('statusBadge')) {
    function statusBadge(string $s): string {
        $map=['pending'=>'status-pending','ongoing'=>'status-ongoing','resolved'=>'status-resolved'];
        return "<span class='status-badge ".($map[$s]??'status-pending')."'>".ucfirst($s)."</span>";
    }
}

// Build rows — pass full data as JSON to onclick
$rows = '';
foreach ($refs as $r) {
    $rawType  = strtolower($r['concern_type']);
    $type     = ucfirst(str_replace('_',' ',$r['concern_type']));
    $date     = date('M d, Y', strtotime($r['submitted_at']));
    $reopenBadge = !empty($r['reopen_reason'])
        ? " <span class='badge-witness' style='background:#fdf4ff;color:#7e22ce;border-color:#e9d5ff;margin-left:6px' title='".htmlspecialchars($r['reopen_reason'], ENT_QUOTES)."'><i class='fas fa-undo'></i> Reopened</span>"
        : '';
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
        'reopen_reason'=> $r['reopen_reason'] ?? null,
        'reopened_at'  => !empty($r['reopened_at']) ? date('M d, Y g:i A', strtotime($r['reopened_at'])) : null,
        'teacher_name' => $r['teacher_name'],
        'teacher_email'=> $r['teacher_email'] ?? '',
        'submitted_at' => date('M d, Y g:i A', strtotime($r['submitted_at'])),
        'session_id'   => !empty($r['session_id']) ? (int)$r['session_id'] : null,
        'session_date' => $r['session_date'] ?? null,
        'session_type' => $r['session_type'] ?? null,
        'location'     => $r['location'] ?? null,
        'meeting_link' => $r['meeting_link'] ?? null,
        'session_status' => $r['session_status'] ?? null,
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
        str_replace('_', ' ', $r['status']),
        $date,
        !empty($r['reopen_reason']) ? 'reopened' : '',
        substr((string)$r['observations'], 0, 200),
    ];
    $searchData = htmlspecialchars(strtolower(implode(' ', array_filter($searchTokens))), ENT_QUOTES);

    $rows .= "<tr class='ref-row' data-search=\"$searchData\">
        <td><strong style='color:var(--maroon)'>{$r['ref_number']}</strong>$reopenBadge</td>
        <td>".htmlspecialchars($r['student_name'])."</td>
        <td>".htmlspecialchars($r['grade_section'])."</td>
        <td>$type</td>
        <td>".urgencyBadge($r['urgency'])."</td>
        <td>".htmlspecialchars($r['teacher_name'])."</td>
        <td>".statusBadge($r['status'])."</td>
        <td style='color:var(--text-3);font-size:12px'>$date</td>
        <td><button class='btn-sm-outline' onclick='openRefModal(this)' data-ref='$refData'>
          <i class='fas fa-eye'></i> View</button></td>
    </tr>";
}
if (!$rows) $rows = "<tr class='no-referrals-row'><td colspan='9' style='text-align:center;color:var(--text-3);padding:40px'>".($search ? "No referrals matching \"".htmlspecialchars($search)."\"." : "No referrals found.")."</td></tr>";

$sqVal = htmlspecialchars($search);
$ss1 = $filterStatus==='pending'      ? 'selected' : '';
$ss3 = $filterStatus==='ongoing'      ? 'selected' : '';
$ss4 = $filterStatus==='resolved'     ? 'selected' : '';
$su1 = $filterUrgency==='urgent'      ? 'selected' : '';
$su2 = $filterUrgency==='moderate'    ? 'selected' : '';
$su3 = $filterUrgency==='low'         ? 'selected' : '';

$content = <<<HTML
<div class="content-card">
  <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div>
      <h3 style="margin:0;display:flex;align-items:center;gap:10px;">
        <i class="fas fa-exchange-alt" style="color:var(--maroon)"></i> Active Referrals
        <span id="activeReferralsCount" style="font-size:13px;font-weight:600;color:var(--text-3);">($activeCount active)</span>
      </h3>
    </div>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <a href="admin-referrals-archive.php" class="btn-sm-outline" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fas fa-archive"></i> Referrals Archive <span style="font-weight:700">($archivedCount)</span>
      </a>
    </div>
  </div>

  <div class="filter-bar" style="margin-bottom:18px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
    <div style="display:flex;gap:8px;flex:1;min-width:240px">
      <div class="input-wrap" style="flex:1">
        <i class="fas fa-search"></i>
        <input type="text" id="searchQ" value="$sqVal" placeholder="Search referrals by student, teacher, ref #…" autocomplete="off"
          oninput="filterReferralsLive(this.value)"
          onkeydown="if(event.key==='Enter'){event.preventDefault();doSearch();}"/>
      </div>
      <button class="btn-sm-primary" onclick="doSearch()"><i class="fas fa-search"></i> Search</button>
    </div>

    <select class="status-select" onchange="applyFilter('status',this.value)">
      <option value="">All Status (Active)</option>
      <option value="pending"  $ss1>Pending</option>
      <option value="ongoing"  $ss3>Ongoing</option>
      <option value="resolved" $ss4>Resolved</option>
    </select>

    <select class="status-select" onchange="applyFilter('urgency',this.value)">
      <option value="">All Urgency</option>
      <option value="urgent"   $su1>Urgent</option>
      <option value="moderate" $su2>Moderate</option>
      <option value="low"      $su3>Low</option>
    </select>

    <button class="btn-sm-outline" onclick="window.location='admin-referrals.php'"><i class="fas fa-times"></i> Clear</button>
  </div>

  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Ref #</th><th>Student</th><th>Grade/Section</th><th>Concern</th><th>Urgency</th><th>Referred By</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
      <tbody>$rows</tbody>
    </table>
  </div>
</div>

<!-- Referral Detail Modal -->
<div class="modal-overlay hidden" id="refModal" onclick="if(event.target.id==='refModal')this.classList.add('hidden')">
  <div class="modal-box" style="max-width:1000px; width:95%">
    <button class="modal-close" onclick="document.getElementById('refModal').classList.add('hidden')"><i class="fas fa-times"></i></button>
    <h2 class="modal-title"><i class="fas fa-exchange-alt" style="color:var(--maroon);margin-right:8px"></i>Referral Detail</h2>
    <div id="refModalBody"></div>
  </div>
</div>

<!-- Schedule Referral Counseling Session Modal -->
<div class="modal-overlay hidden" id="scheduleRefModal" onclick="if(event.target.id==='scheduleRefModal')this.classList.add('hidden')">
  <div class="modal-box" style="max-width:560px;width:95%">
    <button class="modal-close" onclick="document.getElementById('scheduleRefModal').classList.add('hidden')"><i class="fas fa-times"></i></button>
    <div style="width:48px;height:48px;border-radius:12px;background:rgba(85,0,0,0.08);color:var(--maroon);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:20px">
      <i class="fas fa-calendar-plus"></i>
    </div>
    <h2 class="modal-title" style="text-align:center">Schedule Counseling Session</h2>
    <p class="modal-sub" id="schedRefSub" style="text-align:center;margin-bottom:18px"></p>

    <input type="hidden" id="schedRefId">

    <div class="form-group" style="margin-bottom:14px">
      <label>Session Date &amp; Time <span class="req">*</span></label>
      <div class="input-wrap"><i class="fas fa-calendar-alt"></i>
        <input type="datetime-local" id="schedRefDate">
      </div>
    </div>

    <div class="form-row" style="display:flex;gap:12px;margin-bottom:14px">
      <div class="form-group" style="flex:1">
        <label>Session Mode <span class="req">*</span></label>
        <div class="select-wrap"><i class="fas fa-video"></i>
          <select id="schedRefType" onchange="toggleRefMeetingField(this.value)">
            <option value="in-person">In-Person</option>
            <option value="virtual">Virtual / Online</option>
          </select>
        </div>
      </div>
      <div class="form-group" style="flex:1" id="schedRefLocGroup">
        <label>Location / Room</label>
        <div class="input-wrap"><i class="fas fa-map-marker-alt"></i>
          <input type="text" id="schedRefLocation" value="Guidance Office" placeholder="Guidance Office Room 204">
        </div>
      </div>
    </div>

    <div class="form-group" id="schedRefMeetingLinkGroup" style="display:none;margin-bottom:14px">
      <label>Virtual Meeting Link <span class="req">*</span> <span style="font-size:11px;font-weight:normal;color:var(--text-3)">(Google Meet, Zoom, Teams, etc.)</span></label>
      <div class="input-wrap"><i class="fas fa-link"></i>
        <input type="url" id="schedRefMeetingLink" placeholder="e.g. https://meet.google.com/xyz-abc">
      </div>
      <small style="font-size:11.5px;color:var(--text-3);margin-top:4px;display:block">The student and teacher will receive this link directly in their notification and counseling dashboard.</small>
    </div>

    <div class="form-group" style="margin-bottom:18px">
      <label>Counselor Remarks / Objectives (Optional)</label>
      <textarea id="schedRefNotes" rows="3" class="form-control" style="width:100%;padding:10px;border-radius:8px;border:1.5px solid var(--border);font-size:13.5px;box-sizing:border-box" placeholder="Session goals, preparation instructions, or notes for the student..."></textarea>
    </div>

    <div style="display:flex;gap:12px">
      <button type="button" class="btn-secondary" style="flex:1" onclick="document.getElementById('scheduleRefModal').classList.add('hidden')">Cancel</button>
      <button type="button" class="btn-primary" id="btnConfirmSchedRef" style="flex:1" onclick="submitScheduleReferral()"><i class="fas fa-calendar-check"></i> Set Schedule</button>
    </div>
  </div>
</div>
HTML;

// JS is outside heredoc to prevent PHP variable interpolation issues
$content .= '
<div class="modal-overlay hidden" id="archiveRefConfirmModal" style="z-index:100020" onclick="if(event.target.id===\'archiveRefConfirmModal\')this.classList.add(\'hidden\')">
  <div class="modal-box" style="max-width:440px;text-align:center;padding:28px 24px">
    <button class="modal-close" onclick="document.getElementById(\'archiveRefConfirmModal\').classList.add(\'hidden\')"><i class="fas fa-times"></i></button>
    <div style="width:56px;height:56px;border-radius:16px;background:#dcfce7;color:#16a34a;display:flex;align-items:center;justify-content:center;font-size:24px;margin:0 auto 14px">
      <i class="fas fa-check-circle"></i>
    </div>
    <h3 style="margin:0 0 8px;font-size:18px;font-weight:800;color:var(--text)">Resolve &amp; Archive Referral</h3>
    <p style="font-size:13.5px;color:var(--text-2);line-height:1.5;margin-bottom:20px">Are you sure you want to mark this referral as <strong>Resolved</strong> and move it to the Referrals Archive?</p>
    <input type="hidden" id="archiveConfirmRefId" value="">
    <div style="display:flex;gap:10px;justify-content:center">
      <button type="button" class="btn-secondary" style="flex:1" onclick="document.getElementById(\'archiveRefConfirmModal\').classList.add(\'hidden\')">Cancel</button>
      <button type="button" class="btn-primary" id="btnConfirmArchiveRef" style="flex:1;background:#16a34a;border-color:#15803d" onclick="confirmArchiveReferralDirect()"><i class="fas fa-check"></i> Resolve &amp; Archive</button>
    </div>
  </div>
</div>

<script>
let refSearchTimer = null;

function escapeRefHtml(str) {
  if (!str) return "";
  return String(str).replace(/[&<>"\x27]/g, function(m) {
    return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","\x27":"&#39;"}[m];
  });
}

function syncRefSearchUrl(q) {
  try {
    const url = new URL(window.location);
    if (q) url.searchParams.set("q", q);
    else url.searchParams.delete("q");
    window.history.replaceState({}, "", url);
  } catch (e) {}
}

function filterReferralsLive(query) {
  const q = (query || "").trim().toLowerCase();
  const rows = document.querySelectorAll(".data-table tbody tr.ref-row");
  const countBadge = document.getElementById("activeReferralsCount");
  const tbody = document.querySelector(".data-table tbody");
  if (!tbody) return;

  const existingNoMatch = tbody.querySelector(".live-no-match-row");
  if (existingNoMatch) existingNoMatch.remove();
  if (!rows || rows.length === 0) return;

  if (!q) {
    rows.forEach(r => r.style.display = "");
    if (countBadge) countBadge.textContent = "(" + rows.length + " active)";
    clearTimeout(refSearchTimer);
    refSearchTimer = setTimeout(() => syncRefSearchUrl(""), 300);
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
    tr.innerHTML = "<td colspan=\"9\" style=\"text-align:center;color:var(--text-3);padding:40px\">" +
      "<i class=\"fas fa-search\" style=\"margin-right:8px;opacity:0.5\"></i>No referrals matching \"<strong>" + escapeRefHtml(query) + "</strong>\".</td>";
    tbody.appendChild(tr);
  }

  if (countBadge) {
    countBadge.textContent = "(" + visibleCount + (visibleCount !== rows.length ? " of " + rows.length : "") + " active)";
  }

  clearTimeout(refSearchTimer);
  refSearchTimer = setTimeout(() => syncRefSearchUrl(q), 350);
}

function doSearch() {
  const input = document.getElementById("searchQ");
  const val = input ? input.value.trim() : "";
  filterReferralsLive(val);
  syncRefSearchUrl(val);
}

function applyFilter(key, val) {
  const url = new URL(window.location);
  if (val) url.searchParams.set(key, val); else url.searchParams.delete(key);
  const q = document.getElementById("searchQ")?.value.trim();
  if (q) url.searchParams.set("q", q);
  window.location = url;
}

document.addEventListener("DOMContentLoaded", function() {
  const input = document.getElementById("searchQ");
  if (input) {
    input.addEventListener("input", function() {
      filterReferralsLive(this.value);
    });
    if (input.value.trim()) {
      filterReferralsLive(input.value.trim());
    }
  }
});

function openRefModal(btn) {
  const ref = JSON.parse(btn.getAttribute("data-ref"));
  const urgMap   = {urgent:"priority-high", moderate:"priority-medium", low:"priority-low"};
  const statLabel = {pending:"Pending", ongoing:"Ongoing", resolved:"Resolved"};
  const typeLabel = ref.concern_type.replace(/_/g," ").replace(/\b\w/g, l=>l.toUpperCase());

  const currentSt = ["pending","ongoing","resolved"].includes(ref.status) ? ref.status : "pending";
  const selOpts = ["pending","ongoing","resolved"].map(s =>
    "<option value=\""+s+"\""+(s===currentSt?" selected":"")+">"+statLabel[s]+"</option>"
  ).join("");

  const reopenBanner = ref.reopen_reason ? `
    <div style="background:#fdf4ff;border:1.5px solid #d8b4fe;border-radius:8px;padding:12px 16px;margin-bottom:18px;display:flex;align-items:flex-start;gap:12px">
      <div style="width:34px;height:34px;border-radius:50%;background:#a855f7;color:#fff;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0"><i class="fas fa-undo"></i></div>
      <div style="flex:1">
        <div style="font-size:12px;font-weight:800;color:#7e22ce;text-transform:uppercase;letter-spacing:0.5px">Reopened by Guidance Counselor ${ref.reopened_at ? `(${escapeRefHtml(ref.reopened_at)})` : ""}</div>
        <div style="font-size:13.5px;color:#581c87;margin-top:4px;line-height:1.5"><strong>Reason:</strong> ${escapeRefHtml(ref.reopen_reason)}</div>
      </div>
    </div>` : "";

  document.getElementById("refModalBody").innerHTML = `
    <div class="review-header-strip" style="display:flex;flex-wrap:wrap;gap:15px;background:var(--bg2);padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid var(--border)">
      <div style="flex:1;min-width:120px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Referral ID</label><strong style="color:var(--maroon);font-size:16px">#${ref.ref_number}</strong></div>
      <div style="flex:2;min-width:150px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Student</label><span style="font-size:15px;font-weight:600">${ref.student_name}</span></div>
      <div style="flex:1.5;min-width:130px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Type</label><span class="status-badge status-ongoing">${typeLabel}</span></div>
      <div style="flex:1.5;min-width:130px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Grade/Sec</label><span style="font-size:14px">${ref.grade_section}</span></div>
      <div style="flex:1.5;min-width:130px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Submitted</label><span style="font-size:13px;color:var(--text-2)">${ref.submitted_at}</span></div>
    </div>
    ${reopenBanner}
    <div class="review-grid" style="display:grid;grid-template-columns: 1.2fr 1fr; gap:25px; align-items:start">
      <div>
        ${ref.ai_summary ? `<div class="ai-box" style="margin-bottom:20px;border-left:4px solid var(--blue);background:rgba(37,99,235,0.05)"><div class="ai-box-label" style="color:var(--blue);font-size:14px;margin-bottom:8px"><i class="fas fa-magic"></i> AI Summary</div><div class="ai-box-text" style="font-size:15px;line-height:1.6">${ref.ai_summary}</div></div>` : \'\'}
        <div style="margin-bottom:14px;background:var(--bg2);padding:20px;border-radius:8px;border:1px solid var(--border)">
          <div style="font-size:13px;font-weight:800;color:var(--text-2);margin-bottom:12px;text-transform:uppercase;letter-spacing:1px"><i class="fas fa-quote-left" style="color:var(--maroon)"></i> Teacher\'s Observations</div>
          <div class="concern-text" style="font-size:15px;line-height:1.7;color:var(--text);background:transparent;padding:0;border:none;white-space:pre-wrap;">${ref.observations}</div>
        </div>
      </div>
      
      <div style="background:var(--bg2);padding:20px;border-radius:8px;border:1px solid var(--border)">
        <h3 style="margin-bottom:20px;font-size:16px;color:var(--text);border-bottom:1px solid var(--border);padding-bottom:10px"><i class="fas fa-sliders-h" style="color:var(--maroon)"></i> Action Panel</h3>
        <div class="form-group">
          <label>Urgency</label>
          <div style="margin-bottom:15px;"><span class="priority-badge ${urgMap[ref.urgency]||\'priority-low\'}"><i class="fas fa-circle"></i> ${ref.urgency.charAt(0).toUpperCase()+ref.urgency.slice(1)}</span></div>
        </div>
        <div class="form-group">
          <label>Update Status</label>
          <div class="select-wrap"><i class="fas fa-tag"></i>
            <select id="ref-status-sel">${selOpts}</select>
          </div>
        </div>
        <div class="form-group" style="margin-top:20px;border-top:1px solid var(--border);padding-top:20px">
          <label>Referred By</label>
          <div style="font-weight:600;margin-bottom:15px;">${ref.teacher_name}</div>
          <button class="btn-primary" style="width:100%;background:var(--blue);display:flex;align-items:center;justify-content:center;gap:8px" onclick="goToReferralChat(${ref.id})">
            <i class="fas fa-comment-dots"></i> Message Teacher
          </button>
        </div>

        <!-- Counseling Session Scheduling -->
        <div class="form-group" style="margin-top:20px;border-top:1px solid var(--border);padding-top:18px">
          <label style="display:flex;align-items:center;gap:6px;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-3);margin-bottom:8px">
            <i class="fas fa-calendar-alt" style="color:var(--maroon)"></i> Counseling Session
          </label>
          ${ref.session_id ? `
            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px;margin-bottom:10px">
              <div style="font-size:11px;font-weight:700;color:#1d4ed8;text-transform:uppercase;margin-bottom:3px">
                <i class="fas fa-calendar-check"></i> Session Appointed (${ref.session_status || "Scheduled"})
              </div>
              <div style="font-size:13px;font-weight:700;color:var(--text)">${ref.session_date ? new Date(ref.session_date).toLocaleString([], {month:"short", day:"numeric", year:"numeric", hour:"2-digit", minute:"2-digit"}) : "Scheduled"}</div>
              <div style="font-size:12px;color:var(--text-2);margin-top:3px">
                Mode: <strong>${ref.session_type || "In-person"}</strong> &bull; ${ref.meeting_link ? `<a href="${ref.meeting_link}" target="_blank" style="color:#2563eb;font-weight:700"><i class="fas fa-video"></i> Join Virtual Link</a>` : (ref.location || "Guidance Office")}
              </div>
            </div>
          ` : ""}
          <button type="button" class="btn-primary" style="width:100%;background:var(--maroon);display:flex;align-items:center;justify-content:center;gap:8px;padding:10px 14px;font-size:13px" onclick="openScheduleRefModal(${ref.id}, \`${ref.student_name.replace(/[`\\\"]/g, "")}\`, \`${ref.grade_section.replace(/[`\\\"]/g, "")}\`)">
            <i class="fas fa-calendar-plus"></i> ${ref.session_id ? "Reschedule / Set Session" : "Set Schedule (Counseling)"}
          </button>
        </div>

        <div class="review-actions" style="margin-top:16px;display:flex;gap:10px;">
          <button class="btn-secondary" style="flex:1" onclick="document.getElementById(\'refModal\').classList.add(\'hidden\')"><i class="fas fa-times"></i> Cancel</button>
          <button class="btn-primary" style="flex:1" id="saveRefBtn" onclick="updateRefStatus(${ref.id})"><i class="fas fa-save"></i> Save</button>
        </div>
        <div style="margin-top:12px;border-top:1px solid var(--border);padding-top:12px;">
          <button type="button" class="btn-sm-outline" style="width:100%;border-color:#16a34a;color:#16a34a;display:flex;align-items:center;justify-content:center;gap:6px;padding:8px;" onclick="archiveReferralDirect(${ref.id})">
            <i class="fas fa-check-circle"></i> Mark Resolved & Archive
          </button>
        </div>
      </div>
    </div>`;

  document.getElementById("refModal").classList.remove("hidden");
}

function refApiUrl(endpoint) {
  const p = window.location.pathname || "";
  if (p.includes("/gnhs-guidance/gnhs-guidance/")) return "/gnhs-guidance/gnhs-guidance/api/" + endpoint;
  if (p.startsWith("/gnhs-guidance/")) return "/gnhs-guidance/api/" + endpoint;
  return "/api/" + endpoint;
}

async function updateRefStatus(id) {
  const status = document.getElementById("ref-status-sel").value;
  const btn    = document.getElementById("saveRefBtn");
  if (btn) { btn.disabled=true; btn.innerHTML="<span class=\"spinner\"></span> Saving…"; }
  try {
    const r = await fetch(refApiUrl("update_referral.php?id="+id+"&status="+encodeURIComponent(status)+"&_t="+Date.now()), { cache: "no-store" });
    const d = await r.json();
    if (d.success) {
      if (status === "resolved") {
        showToast("Referral resolved and moved to Referrals Archive!","success");
      } else {
        showToast("Referral status updated!","success");
      }
      document.getElementById("refModal").classList.add("hidden");
      setTimeout(()=>window.location.reload(), 150);
    } else {
      showToast(d.message||"Update failed.","error");
      if (btn) { btn.disabled=false; btn.innerHTML="<i class=\"fas fa-save\"></i> Save"; }
    }
  } catch(e) {
    showToast("Error updating referral.","error");
    if (btn) { btn.disabled=false; btn.innerHTML="<i class=\"fas fa-save\"></i> Save"; }
  }
}

function archiveReferralDirect(id) {
  const inp = document.getElementById("archiveConfirmRefId");
  const modal = document.getElementById("archiveRefConfirmModal");
  if (inp && modal) {
    inp.value = id;
    modal.classList.remove("hidden");
  }
}

async function confirmArchiveReferralDirect() {
  const id = document.getElementById("archiveConfirmRefId")?.value;
  const btn = document.getElementById("btnConfirmArchiveRef");
  if (!id) return;
  if (btn) btn.disabled = true;
  try {
    const r = await fetch(refApiUrl("update_referral.php?id="+id+"&status=resolved&_t="+Date.now()), { cache: "no-store" });
    const d = await r.json();
    if (d.success) {
      showToast("Referral marked as resolved and moved to archive!", "success");
      document.getElementById("archiveRefConfirmModal").classList.add("hidden");
      document.getElementById("refModal").classList.add("hidden");
      setTimeout(() => window.location.reload(), 150);
    } else {
      showToast(d.message || "Failed to archive referral.", "error");
      if (btn) btn.disabled = false;
    }
  } catch(e) {
    showToast("Error archiving referral.", "error");
    if (btn) btn.disabled = false;
  }
}

function openScheduleRefModal(refId, studentName, gradeSection) {
  document.getElementById("schedRefId").value = refId;
  document.getElementById("schedRefSub").textContent = "Student: " + studentName + " (" + gradeSection + ")";
  
  const nowLocal = new Date(Date.now() - new Date().getTimezoneOffset()*60000).toISOString().slice(0, 16);
  const dtInput = document.getElementById("schedRefDate");
  dtInput.min = nowLocal;
  dtInput.value = "";
  
  document.getElementById("schedRefType").value = "in-person";
  document.getElementById("schedRefLocation").value = "Guidance Office";
  document.getElementById("schedRefMeetingLink").value = "";
  document.getElementById("schedRefNotes").value = "";
  document.getElementById("schedRefMeetingLinkGroup").style.display = "none";
  document.getElementById("schedRefLocGroup").style.display = "block";
  
  document.getElementById("scheduleRefModal").classList.remove("hidden");
}

function toggleRefMeetingField(val) {
  const isOnline = (val === "virtual" || val === "online");
  document.getElementById("schedRefMeetingLinkGroup").style.display = isOnline ? "block" : "none";
  if (isOnline) {
    if (document.getElementById("schedRefLocation").value === "Guidance Office") {
      document.getElementById("schedRefLocation").value = "Virtual Online Meeting";
    }
  } else {
    if (document.getElementById("schedRefLocation").value === "Virtual Online Meeting") {
      document.getElementById("schedRefLocation").value = "Guidance Office";
    }
  }
}

async function submitScheduleReferral() {
  const refId = document.getElementById("schedRefId").value;
  const sessionDate = document.getElementById("schedRefDate").value;
  const sessionType = document.getElementById("schedRefType").value;
  const location = document.getElementById("schedRefLocation").value.trim() || "Guidance Office";
  const meetingLink = document.getElementById("schedRefMeetingLink").value.trim();
  const notes = document.getElementById("schedRefNotes").value.trim();

  if (!sessionDate) {
    showToast("Please select a session date and time.", "error");
    document.getElementById("schedRefDate").focus();
    return;
  }

  const selectedDt = new Date(sessionDate);
  if (selectedDt < new Date()) {
    showToast("Session cannot be scheduled in the past. Please select a present or future date and time.", "error");
    document.getElementById("schedRefDate").focus();
    return;
  }

  if ((sessionType === "virtual" || sessionType === "online") && !meetingLink) {
    showToast("Please provide a virtual meeting link (e.g. Google Meet or Zoom) for online counseling.", "error");
    document.getElementById("schedRefMeetingLink").focus();
    return;
  }

  const btn = document.getElementById("btnConfirmSchedRef");
  btn.disabled = true;
  btn.innerHTML = "<span class=\"spinner\"></span> Scheduling…";

  try {
    const r = await fetch(refApiUrl("schedule_referral_session.php"), {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        referral_id: refId,
        session_date: sessionDate,
        session_type: sessionType,
        location: location,
        meeting_link: meetingLink,
        notes: notes
      })
    });
    const d = await r.json();
    if (d.success) {
      showToast(d.message || "Counseling session scheduled successfully!", "success");
      document.getElementById("scheduleRefModal").classList.add("hidden");
      document.getElementById("refModal").classList.add("hidden");
      setTimeout(() => window.location.reload(), 200);
    } else {
      showToast(d.message || "Failed to schedule session.", "error");
      btn.disabled = false;
      btn.innerHTML = "<i class=\"fas fa-calendar-check\"></i> Set Schedule";
    }
  } catch(e) {
    showToast("Error scheduling session.", "error");
    btn.disabled = false;
    btn.innerHTML = "<i class=\"fas fa-calendar-check\"></i> Set Schedule";
  }
}
</script>';

renderLayout($user, 'Referrals', 'referrals', $content);
