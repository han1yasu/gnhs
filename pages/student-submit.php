<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('student');
$todayDate = date('Y-m-d');

$content = <<<HTML
<div class="form-layout">
  <div>
    <div class="content-card">
      <div class="card-header"><h3><i class="fas fa-file-medical"></i> Submit a Concern</h3></div>
      <div id="submitMsg" style="display:none"></div>

      <div class="form-group">
        <label>Type of Concern <span class="req">*</span></label>
        <div class="select-wrap"><i class="fas fa-tag"></i>
          <select id="concernType" onchange="onConcernTypeChange(this.value)">
            <option value="">Select Concern Type</option>
            <option value="bullying">Bullying</option>
            <option value="academic_stress">Academic Stress</option>
            <option value="emotional">Emotional / Mental Health</option>
            <option value="family">Family Issues</option>
            <option value="peer_conflict">Peer Conflict</option>
            <option value="behavioral">Behavioral</option>
            <option value="other">Other</option>
          </select>
        </div>
      </div>

      <div class="form-group" id="otherConcernGroup" style="display:none">
        <label>Please Specify <span class="req">*</span></label>
        <div class="input-wrap"><i class="fas fa-edit"></i><input type="text" id="otherConcern" placeholder="Specify your concern type"/></div>
      </div>

      <!-- Bullying Guidance Tip -->
      <div id="bullyingTip" class="evidence-tip-banner" style="display:none">
        <div class="etb-icon"><i class="fas fa-shield-alt"></i></div>
        <div class="etb-body">
          <strong>Notice for Bullying / Harassment Reports</strong>
          <p>If you have any evidence such as photos, screenshots of chat messages, or video clips, or if there were witnesses who saw what happened, you may provide them below. Both are <em>completely optional</em> — you do not have to provide any files or witnesses if you do not have them.</p>
        </div>
      </div>

      <div class="form-group">
        <label>Subject / Title <span class="req">*</span></label>
        <div class="input-wrap"><i class="fas fa-heading"></i><input type="text" id="subject" placeholder="Brief title of your concern"/></div>
      </div>

      <div class="form-group">
        <label>Incident Date <span class="opt-tag">(Cannot be future date)</span></label>
        <div class="input-wrap"><i class="fas fa-calendar"></i><input type="date" id="incidentDate" max="$todayDate"/></div>
      </div>

      <div class="form-group">
        <label>Describe Your Concern <span class="req">*</span></label>
        <textarea id="description" rows="6" maxlength="4000" placeholder="Please describe your concern in detail (max 400 words). Everything you share is confidential."></textarea>
        <div class="char-count"><span id="charCount">0</span> / 400 words</div>
      </div>

      <!-- Optional Witnesses Field -->
      <div class="form-group">
        <label style="display:flex;align-items:center;justify-content:space-between">
          <span><i class="fas fa-users" style="color:var(--maroon);margin-right:6px"></i> Witnesses <span class="opt-tag">(Optional)</span></span>
        </label>
        <div class="input-wrap"><i class="fas fa-user-friends"></i><input type="text" id="witnesses" placeholder="Names of any students, teachers, or staff who witnessed the incident (Optional)"/></div>
        <span class="field-hint">Leave blank if no one else was present or if you do not wish to specify.</span>
      </div>

      <!-- Optional Pictures & Videos Upload -->
      <div class="form-group">
        <label style="display:flex;align-items:center;justify-content:space-between">
          <span><i class="fas fa-photo-film" style="color:var(--maroon);margin-right:6px"></i> Photos &amp; Videos Evidence <span class="opt-tag">(Optional)</span></span>
          <span style="font-size:12px;font-weight:500;color:var(--text-3)">Max 100MB per file</span>
        </label>

        <input type="file" id="evidenceFiles" name="evidence[]" accept="image/*,video/*,.jpg,.jpeg,.png,.webp,.gif,.heic,.heif,.mp4,.mov,.webm,.mkv,.avi,.3gp,.m4v" multiple style="display:none" onchange="handleFileSelect(this.files)"/>
        <label for="evidenceFiles" class="evidence-dropzone" id="evidenceDropzone" style="display:block;cursor:pointer">
          <div class="ed-icon"><i class="fas fa-cloud-arrow-up"></i></div>
          <div class="ed-main"><strong>Choose files</strong>, drag &amp; drop, or <strong>paste (Ctrl+V)</strong> photos / videos here</div>
          <div class="ed-sub">Supports JPG, PNG, WEBP, GIF, MP4, MOV, WEBM, MKV, HEIC (Max 100MB per file)</div>
          <div style="margin-top:10px">
            <span class="btn-sm-primary" style="display:inline-flex;align-items:center;gap:6px"><i class="fas fa-folder-open"></i> Browse Files</span>
          </div>
          <div id="dropzoneCountBadge" style="display:none;margin-top:12px;">
            <span style="display:inline-flex;align-items:center;gap:6px;background:var(--maroon-pale);color:var(--maroon);font-weight:700;font-size:13px;padding:5px 14px;border-radius:20px;border:1px solid var(--maroon-tint)">
              <i class="fas fa-paperclip"></i> <span id="dropzoneCountText">0 file(s) attached</span>
            </span>
          </div>
        </label>

        <!-- Preview Grid -->
        <div id="evidencePreviewGrid" class="evidence-previews-grid" style="display:none"></div>
      </div>

      <div class="form-group">
        <label>Priority Level</label>
        <div class="prio-group">
          <div class="prio-opt low-opt" onclick="selectPrio(this,'low')"><i class="fas fa-circle"></i> Low</div>
          <div class="prio-opt med-opt selected" onclick="selectPrio(this,'medium')"><i class="fas fa-circle"></i> Medium</div>
          <div class="prio-opt high-opt" onclick="selectPrio(this,'high')"><i class="fas fa-circle"></i> High</div>
        </div>
      </div>

      <div class="form-group">
        <label>Preferred Contact Method</label>
        <div class="select-wrap"><i class="fas fa-comments"></i>
          <select id="preferredContact">
            <option value="in_person">In Person</option>
            <option value="online">Online</option>
            <option value="written">Written</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="checkbox-label" style="font-size:14px">
          <input type="checkbox" id="isAnonymous"> Submit anonymously (your name will not be attached)
        </label>
      </div>

      <div class="form-actions">
        <button class="btn-secondary" onclick="history.back()">Cancel</button>
        <button class="btn-primary" id="submitBtn" onclick="submitConcern()"><i class="fas fa-paper-plane"></i> Submit Concern <span id="submitBtnEvidenceCount" style="display:none;font-size:12px;opacity:0.9;margin-left:4px"></span></button>
      </div>
    </div>
  </div>

  <div class="form-sidebar">
    <div class="content-card info-card">
      <h4><i class="fas fa-shield-heart" style="color:var(--maroon)"></i> Confidentiality</h4>
      <p>Everything you share here, including photos, videos, and witness notes, is strictly confidential and accessible only to guidance counselors.</p>
    </div>
    <div class="content-card info-card urgent-card">
      <h4><i class="fas fa-exclamation-triangle"></i> In Immediate Danger?</h4>
      <p>If you or someone else is in immediate danger, please seek help right away.</p>
      <div class="urgent-contact"><i class="fas fa-phone"></i> Contact a teacher immediately</div>
    </div>
    <div class="content-card info-card">
      <h4><i class="fas fa-question-circle" style="color:var(--maroon)"></i> What Happens Next?</h4>
      <p>A counselor will review your concern and respond within 1-3 school days. You'll receive a notification when there's an update.</p>
    </div>
  </div>
</div>

<!-- Success Modal -->
<div class="modal-overlay hidden" id="successModal">
  <div class="modal-box success-modal" style="max-width:500px">
    <div class="success-icon"><i class="fas fa-check-circle"></i></div>
    <h2 class="modal-title">Concern Submitted!</h2>
    <p style="color:var(--text-2);margin-bottom:16px">Your concern has been submitted and a guidance counselor will review it shortly.</p>
    <div class="success-info">
      <div class="si-row"><label>Case Number</label><strong id="successCaseNum" style="color:var(--maroon)">—</strong></div>
      <div class="si-row"><label>Status</label><span class="status-badge status-pending">Pending</span></div>
      <div class="si-row" id="siEvidenceRow" style="display:none"><label>Attachments</label><span id="siEvidenceCount" style="font-size:13px;font-weight:700;color:var(--blue)">0 file(s)</span></div>
      <div class="si-row" id="siWitnessRow" style="display:none"><label>Witnesses</label><span style="font-size:13px;font-weight:700;color:var(--text-2)">Provided</span></div>
    </div>

    <!-- Attached Media Preview inside Confirmation Modal -->
    <div id="siEvidenceGallery" style="display:none;margin-top:14px;background:var(--bg2);padding:14px;border-radius:10px;border:1px solid var(--border);text-align:left">
      <div style="font-size:12px;font-weight:800;color:var(--text-2);margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px">
        <i class="fas fa-photo-film" style="color:var(--maroon)"></i> Attached Media (<span id="siEvidencePreviewCount">0</span>)
      </div>
      <div id="siEvidenceThumbs" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(80px, 1fr));gap:8px"></div>
    </div>

    <div style="display:flex;gap:10px;margin-top:18px">
      <button class="btn-secondary" style="flex:1" onclick="window.location.href='student-cases.php'">My Cases</button>
      <button class="btn-primary" style="flex:1;justify-content:center" id="successViewCaseBtn" onclick="window.location.href='student-cases.php'"><i class="fas fa-eye"></i> View Case Now</button>
    </div>
  </div>
</div>

<script>
let uploadedFiles = [];
window.uploadedFiles = uploadedFiles;

function onConcernTypeChange(val) {
  document.getElementById('otherConcernGroup').style.display = (val === 'other') ? 'block' : 'none';
  const bullyingTip = document.getElementById('bullyingTip');
  if (bullyingTip) {
    bullyingTip.style.display = (val === 'bullying') ? 'flex' : 'none';
  }
}

document.getElementById('description')?.addEventListener('input', function(){
  let words = this.value.trim().split(/\s+/).filter(w => w.length > 0);
  document.getElementById('charCount').textContent = words.length;
});

// Max date setup and prevent future dates
const incidentDateEl = document.getElementById('incidentDate');
if (incidentDateEl) {
  const maxToday = '$todayDate';
  incidentDateEl.max = maxToday;
  incidentDateEl.addEventListener('change', function() {
    if (this.value && this.value > maxToday) {
      alert('Incident date cannot be in the future. Please select today (' + maxToday + ') or an earlier date.');
      this.value = maxToday;
    }
  });
}

// Dropzone Drag & Drop
const dropzone = document.getElementById('evidenceDropzone');
if (dropzone) {
  ['dragenter', 'dragover'].forEach(eventName => {
    dropzone.addEventListener(eventName, (e) => {
      e.preventDefault(); e.stopPropagation();
      dropzone.classList.add('drag-active');
    }, false);
  });
  ['dragleave', 'drop'].forEach(eventName => {
    dropzone.addEventListener(eventName, (e) => {
      e.preventDefault(); e.stopPropagation();
      dropzone.classList.remove('drag-active');
    }, false);
  });
  dropzone.addEventListener('drop', (e) => {
    const dt = e.dataTransfer;
    if (dt && dt.files && dt.files.length) {
      handleFileSelect(dt.files);
    }
  }, false);
}

// Drag & drop directly onto description textarea
const descArea = document.getElementById('description');
if (descArea) {
  descArea.addEventListener('dragover', (e) => { e.preventDefault(); e.stopPropagation(); });
  descArea.addEventListener('drop', (e) => {
    e.preventDefault(); e.stopPropagation();
    if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
      handleFileSelect(e.dataTransfer.files);
    }
  });
}

// Global Clipboard Paste (Ctrl+V for Snipping Tool / copied screenshots / images)
document.addEventListener('paste', function(e) {
  const items = (e.clipboardData || window.clipboardData)?.items;
  if (!items) return;
  const pastedFiles = [];
  for (let i = 0; i < items.length; i++) {
    const item = items[i];
    if (item.kind === 'file' || (item.type && (item.type.startsWith('image/') || item.type.startsWith('video/')))) {
      const blob = item.getAsFile();
      if (blob) {
        const ext = (blob.type.split('/')[1] || 'png').replace('jpeg', 'jpg');
        const filename = 'pasted_evidence_' + Date.now() + '_' + (i + 1) + '.' + ext;
        const file = new File([blob], filename, { type: blob.type || 'image/png' });
        pastedFiles.push(file);
      }
    }
  }
  if (pastedFiles.length > 0) {
    handleFileSelect(pastedFiles);
    const dz = document.getElementById('evidenceDropzone');
    if (dz) {
      dz.classList.add('drag-active');
      setTimeout(() => dz.classList.remove('drag-active'), 800);
    }
  }
});

function updateFileCountBadge() {
  const badgeWrap = document.getElementById('dropzoneCountBadge');
  const countText = document.getElementById('dropzoneCountText');
  const submitCount = document.getElementById('submitBtnEvidenceCount');
  if (!badgeWrap || !countText) return;
  if (uploadedFiles.length > 0) {
    badgeWrap.style.display = 'block';
    countText.textContent = uploadedFiles.length + ' file' + (uploadedFiles.length > 1 ? 's' : '') + ' attached';
    if (submitCount) {
      submitCount.style.display = 'inline-flex';
      submitCount.textContent = '(' + uploadedFiles.length + ' attached)';
    }
  } else {
    badgeWrap.style.display = 'none';
    if (submitCount) {
      submitCount.style.display = 'none';
      submitCount.textContent = '';
    }
  }
}

function handleFileSelect(files) {
  if (!files || !files.length) return;
  const maxPerFile = 100 * 1024 * 1024; // 100MB
  for (let i = 0; i < files.length; i++) {
    const file = files[i];
    if (file.size > maxPerFile) {
      alert('File "' + file.name + '" exceeds the 100MB limit.');
      continue;
    }
    // Check if already added
    const exists = uploadedFiles.some(f => f.name === file.name && f.size === file.size);
    if (!exists) {
      uploadedFiles.push(file);
    }
  }
  window.uploadedFiles = uploadedFiles;
  const inputEl = document.getElementById('evidenceFiles');
  if (inputEl) inputEl.value = '';

  renderEvidencePreviews();
  updateFileCountBadge();
}

function removeEvidenceFile(index) {
  uploadedFiles.splice(index, 1);
  window.uploadedFiles = uploadedFiles;
  const inputEl = document.getElementById('evidenceFiles');
  if (inputEl) inputEl.value = '';
  renderEvidencePreviews();
  updateFileCountBadge();
}

function formatBytes(bytes) {
  if (bytes === 0) return '0 B';
  const k = 1024, dm = 1;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/[&<>"']/g, function(m) {
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
  });
}

function resolveEvidenceUrl(path) {
  if (!path) return '';
  if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:') || path.startsWith('blob:')) {
    return path;
  }
  const loc = window.location.pathname;
  let basePrefix = '/gnhs-guidance';
  if (loc.startsWith('/gnhs-guidance/gnhs-guidance/')) {
    basePrefix = '/gnhs-guidance/gnhs-guidance';
  }
  if (path.startsWith('/gnhs-guidance/uploads/')) {
    return basePrefix + path.substring('/gnhs-guidance'.length);
  }
  if (path.startsWith('uploads/')) {
    return basePrefix + '/' + path;
  }
  if (!path.startsWith('/')) {
    return basePrefix + '/' + path;
  }
  return path;
}

function renderEvidencePreviews() {
  const container = document.getElementById('evidencePreviewGrid');
  if (!uploadedFiles.length) {
    container.style.display = 'none';
    container.innerHTML = '';
    return;
  }
  container.style.display = 'grid';
  container.innerHTML = uploadedFiles.map((file, idx) => {
    const ext = (file.name.split('.').pop() || '').toLowerCase();
    const videoExts = ['mp4', 'mov', 'webm', 'mkv', 'avi', '3gp', 'ogg', 'm4v'];
    const isVideo = (file.type && file.type.startsWith('video/')) || videoExts.includes(ext);
    
    let thumbHtml;
    const previewUrl = URL.createObjectURL(file);
    if (isVideo) {
      thumbHtml = '<video src="' + previewUrl + '" muted preload="metadata" style="width:100%;height:100%;object-fit:cover;pointer-events:none"></video>'
                + '<div class="epc-video-badge"><i class="fas fa-play"></i></div>';
    } else {
      thumbHtml = '<img src="' + previewUrl + '" alt="' + escapeHtml(file.name) + '"/>';
    }
    const tagHtml = isVideo ? '<span class="epc-tag video">Video</span>' : '<span class="epc-tag image">Photo</span>';

    return '<div class="evidence-preview-card">'
      + '<div class="epc-thumb">' + thumbHtml + '</div>'
      + '<div class="epc-info">'
      + '  <div class="epc-name" title="' + escapeHtml(file.name) + '">' + escapeHtml(file.name) + '</div>'
      + '  <div class="epc-meta">' + tagHtml + ' ' + formatBytes(file.size) + '</div>'
      + '</div>'
      + '<button type="button" class="epc-remove" onclick="removeEvidenceFile(' + idx + ')" title="Remove file">&times;</button>'
      + '</div>';
  }).join('');
}

async function submitConcern() {
  let concernType = document.getElementById('concernType').value;
  if (concernType === 'other') {
    const otherVal = document.getElementById('otherConcern').value.trim();
    if (!otherVal) { showMsg('submitMsg', 'Please specify your other concern.'); return; }
    concernType = 'Other: ' + otherVal;
  }
  const subject     = document.getElementById('subject').value.trim();
  const description = document.getElementById('description').value.trim();
  const priority    = document.querySelector('.prio-opt.selected')?.classList.contains('high-opt') ? 'high' :
                      document.querySelector('.prio-opt.selected')?.classList.contains('low-opt')  ? 'low' : 'medium';
  const isAnonymous = document.getElementById('isAnonymous').checked;
  const incidentDate= document.getElementById('incidentDate').value;
  const preferredContact = document.getElementById('preferredContact').value;
  const witnesses   = document.getElementById('witnesses')?.value.trim() || '';

  if (!concernType || !subject || !description) {
    showMsg('submitMsg', 'Please fill in all required fields.'); return;
  }

  const todayStr = '$todayDate';
  if (incidentDate && incidentDate > todayStr) {
    showMsg('submitMsg', 'Incident date cannot be in the future. Please select today (' + todayStr + ') or an earlier date.');
    return;
  }

  const btn = document.getElementById('submitBtn');
  setLoading(btn, true); hideEl('submitMsg');

  try {
    const formData = new FormData();
    formData.append('concern_type', concernType);
    formData.append('subject', subject);
    formData.append('description', description);
    formData.append('priority', priority);
    formData.append('is_anonymous', isAnonymous ? '1' : '0');
    formData.append('incident_date', incidentDate);
    formData.append('preferred_contact', preferredContact);
    if (witnesses) {
      formData.append('witnesses', witnesses);
    }
    
    // Synchronize from input files if any are still pending
    const inputEl = document.getElementById('evidenceFiles');
    if (inputEl && inputEl.files && inputEl.files.length > 0) {
      for (let i = 0; i < inputEl.files.length; i++) {
        const f = inputEl.files[i];
        if (!uploadedFiles.some(u => u.name === f.name && u.size === f.size)) {
          uploadedFiles.push(f);
        }
      }
    }
    window.uploadedFiles = uploadedFiles;

    // Append all selected evidence files
    for (let i = 0; i < uploadedFiles.length; i++) {
      formData.append('evidence[]', uploadedFiles[i]);
    }

    const apiUrl = window.location.pathname.includes('/gnhs-guidance/gnhs-guidance/')
      ? '/gnhs-guidance/gnhs-guidance/api/submit_case.php'
      : '/gnhs-guidance/api/submit_case.php';

    const response = await fetch(apiUrl, {
      method: 'POST',
      body: formData
    });
    const res = await response.json();

    if (res.success) {
      document.getElementById('successCaseNum').textContent = res.case_number;
      const viewCaseBtn = document.getElementById('successViewCaseBtn');
      if (viewCaseBtn && res.case_id) {
        viewCaseBtn.onclick = function() {
          window.location.href = 'student-cases.php?open_case=' + res.case_id;
        };
      }
      if (res.evidence_count > 0) {
        document.getElementById('siEvidenceRow').style.display = 'flex';
        document.getElementById('siEvidenceCount').textContent = res.evidence_count + ' file(s) attached';

        const gallery = document.getElementById('siEvidenceGallery');
        const countPreview = document.getElementById('siEvidencePreviewCount');
        const thumbs = document.getElementById('siEvidenceThumbs');
        if (gallery && thumbs) {
          gallery.style.display = 'block';
          if (countPreview) countPreview.textContent = res.evidence_count;
          
          const filesToShow = (res.evidence_files && res.evidence_files.length) ? res.evidence_files : uploadedFiles;
          thumbs.innerHTML = filesToShow.map((item, idx) => {
            const isFile = item instanceof File;
            const isVideo = isFile 
              ? (item.type && item.type.startsWith('video/')) 
              : (item.file_type === 'video' || (item.mime_type && item.mime_type.startsWith('video/')));
            const srcUrl = isFile ? URL.createObjectURL(item) : resolveEvidenceUrl(item.file_path);
            const name = escapeHtml(item.name || ('Media ' + (idx + 1)));

            if (isVideo) {
              return '<div style="position:relative;height:75px;border-radius:8px;overflow:hidden;background:#0f172a;border:1px solid var(--border)">'
                + '<video src="' + srcUrl + '" style="width:100%;height:100%;object-fit:cover" muted></video>'
                + '<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#fff;background:rgba(0,0,0,0.35);font-size:11px"><i class="fas fa-play"></i></div>'
                + '</div>';
            } else {
              return '<div style="height:75px;border-radius:8px;overflow:hidden;border:1px solid var(--border);background:var(--bg)">'
                + '<img src="' + srcUrl + '" alt="' + name + '" style="width:100%;height:100%;object-fit:cover"/>'
                + '</div>';
            }
          }).join('');
        }
      }
      if (res.has_witnesses) {
        document.getElementById('siWitnessRow').style.display = 'flex';
      }
      document.getElementById('successModal').classList.remove('hidden');
    } else {
      showMsg('submitMsg', res.message || 'Submission failed. Please try again.');
      setLoading(btn, false);
    }
  } catch(e) {
    showMsg('submitMsg', 'Connection error. Please check your server and try again.');
    setLoading(btn, false);
  }
}

// Bind to window to ensure app.js cannot override it
window.submitStudentConcern = submitConcern;
window.submitConcern = submitConcern;
</script>
HTML;

renderLayout($user, 'Submit a Concern', 'submit', $content);
