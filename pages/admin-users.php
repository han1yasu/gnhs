<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin');
$db   = getDB();

$filterRole = $_GET['role'] ?? '';
$where = "WHERE 1=1";
if ($filterRole) $where .= " AND role='".addslashes($filterRole)."'";

$users = $db->query("SELECT * FROM users $where ORDER BY role, first_name ASC")->fetchAll();

$cards = '';
foreach ($users as $u) {
    $initials  = htmlspecialchars($u['avatar_initials'] ?? strtoupper($u['first_name'][0]));
    $name      = htmlspecialchars($u['first_name'].' '.$u['last_name']);
    $email     = htmlspecialchars($u['email']);
    $idNum     = htmlspecialchars($u['id_number']);
    $roleClass = 'role-'.$u['role'];
    $roleLabel = ucfirst($u['role']==='admin' ? 'Counselor' : $u['role']);
    $gradeInfo    = $u['grade_section'] ? "<div style='font-size:12px;color:var(--text-3);margin-top:2px'><i class='fas fa-school' style='color:var(--maroon);width:12px'></i> ".htmlspecialchars($u['grade_section'])."</div>" : '';
    $advisoryInfo = (!empty($u['advisory_class']) && $u['role']==='teacher') ? "<div style='font-size:12px;color:var(--maroon);margin-top:2px;font-weight:700'><i class='fas fa-chalkboard-teacher' style='width:12px'></i> ".htmlspecialchars($u['advisory_class'])."</div>" : '';
    $activeLabel  = $u['is_active'] ? '' : "<span style='font-size:11px;color:#dc2626;font-weight:700'>[Inactive]</span>";
    $advisoryJson = json_encode($u['advisory_class'] ?? '', JSON_HEX_QUOT|JSON_HEX_APOS);
    $assignBtn    = $u['role']==='teacher'
        ? "<button class='btn-icon' onclick='openAssignAdvisory({$u['id']},\"$name\",$advisoryJson)' title='Assign Advisory Class'><i class='fas fa-chalkboard-teacher'></i></button>"
        : '';
    $avatarEl = !empty($u['avatar_photo'])
        ? "<div class='user-card-avatar' style='background:none;padding:0;overflow:hidden'><img src='".htmlspecialchars($u['avatar_photo'])."' style='width:60px;height:60px;object-fit:cover;object-position:center top;border-radius:16px;display:block;'/></div>"
        : "<div class='user-card-avatar'>$initials</div>";
    $cards .= "<div class='user-card' data-user-id='{$u['id']}'>
        $avatarEl
        <div class='user-card-name'>$name $activeLabel</div>
        <span class='user-card-role $roleClass'>$roleLabel</span>
        $gradeInfo
        $advisoryInfo
        <div style='font-size:12px;color:var(--text-3)'>$email</div>
        <div style='font-size:12px;color:var(--text-3)'>ID: $idNum</div>
        <div class='user-card-actions'>
          $assignBtn
          <button class='btn-icon danger' onclick='deleteUser({$u['id']},\"$name\")' title='Deactivate'><i class='fas fa-user-slash'></i></button>
        </div>
    </div>";
}
if (!$cards) $cards = "<div style='grid-column:1/-1;text-align:center;color:var(--text-3);padding:40px'>No users found.</div>";

$rAll  = $filterRole===''        ? 'selected' : '';
$rStu  = $filterRole==='student' ? 'selected' : '';
$rTea  = $filterRole==='teacher' ? 'selected' : '';
$rAdm  = $filterRole==='admin'   ? 'selected' : '';

$content = <<<HTML
<div class="content-card" style="margin-bottom:22px">
  <div class="card-header">
    <h3><i class="fas fa-users"></i> User Management</h3>
    <div style="display:flex;gap:10px;align-items:center">
      <select class="status-select" onchange="window.location='admin-users.php?role='+this.value">
        <option value=""        $rAll>All Roles</option>
        <option value="student" $rStu>Students</option>
        <option value="teacher" $rTea>Teachers</option>
        <option value="admin"   $rAdm>Counselors</option>
      </select>
      <button class="btn-sm-primary" onclick="openAddUserModal()"><i class="fas fa-user-plus"></i> Add User</button>
    </div>
  </div>
  <div class="users-grid" style="padding-top:8px">$cards</div>
</div>

<div class="modal-overlay hidden" id="addUserModal" onclick="closeAddUser(event)">
  <div class="modal-box" style="max-width:520px">
    <button class="modal-close" onclick="document.getElementById('addUserModal').classList.add('hidden')"><i class="fas fa-times"></i></button>
    <div class="modal-logo"><i class="fas fa-user-plus"></i></div>
    <h2 class="modal-title">Add New User</h2>
    <div id="nuMsg" style="display:none"></div>
    <div id="addUserForm">
      <div class="form-row">
        <div class="form-group"><label>First Name <span class="req">*</span></label><div class="input-wrap"><i class="fas fa-user"></i><input type="text" id="nu-first" placeholder="First Name"/></div></div>
        <div class="form-group"><label>Last Name <span class="req">*</span></label><div class="input-wrap"><i class="fas fa-user"></i><input type="text" id="nu-last" placeholder="Last Name"/></div></div>
      </div>
      <div class="form-group"><label>Role <span class="req">*</span></label>
        <div class="select-wrap"><i class="fas fa-tag"></i>
          <select id="nu-role" onchange="toggleGrade(this.value)">
            <option value="student">Student</option>
            <option value="teacher">Teacher</option>
            <option value="admin">Guidance Counselor</option>
          </select>
        </div>
      </div>
      <div id="nu-grade-group">
        <div class="form-row">
          <div class="form-group">
            <label>Grade</label>
            <div class="select-wrap"><i class="fas fa-layer-group"></i>
              <select id="nu-grade-level" onchange="nuUpdateSections()">
                <option value="">Select Grade</option>
                <option value="7">Grade 7</option>
                <option value="8">Grade 8</option>
                <option value="9">Grade 9</option>
                <option value="10">Grade 10</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label>Section</label>
            <div class="select-wrap"><i class="fas fa-users"></i>
              <select id="nu-section">
                <option value="">Select Grade First</option>
              </select>
            </div>
          </div>
        </div>
      </div>
      <div class="form-group"><label>ID Number / LRN <span class="req">*</span></label><div class="input-wrap"><i class="fas fa-id-card"></i><input type="text" id="nu-idnum" placeholder="Employee ID or LRN"/></div></div>
      <div class="form-group"><label>Email Address <span class="req">*</span></label><div class="input-wrap"><i class="fas fa-envelope"></i><input type="email" id="nu-email" placeholder="email@example.com"/></div></div>
      <div class="form-group"><label>Password <span class="req">*</span></label><div class="input-wrap"><i class="fas fa-lock"></i><input type="password" id="nu-pass" placeholder="Minimum 8 characters"/></div></div>
      <div class="form-actions">
        <button class="btn-secondary" onclick="document.getElementById('addUserModal').classList.add('hidden')">Cancel</button>
        <button class="btn-primary" id="saveUserBtn" onclick="saveNewUser()"><i class="fas fa-save"></i> Create User</button>
      </div>
    </div>
  </div>
</div>

<!-- Assign Advisory Modal -->
<div class="modal-overlay hidden" id="advisoryModal" onclick="if(event.target.id==='advisoryModal')this.classList.add('hidden')">
  <div class="modal-box" style="max-width:460px">
    <button class="modal-close" onclick="document.getElementById('advisoryModal').classList.add('hidden')"><i class="fas fa-times"></i></button>
    <div class="modal-logo"><i class="fas fa-chalkboard-teacher"></i></div>
    <h2 class="modal-title">Assign Advisory Class</h2>
    <p class="modal-sub" id="advisoryTeacherName"></p>
    <div id="advisoryMsg" style="display:none"></div>
    <div class="form-row">
      <div class="form-group">
        <label>Grade <span class="req">*</span></label>
        <div class="select-wrap"><i class="fas fa-layer-group"></i>
          <select id="advisory-grade" onchange="advisoryUpdateSections()">
            <option value="">Select Grade</option>
            <option value="7">Grade 7</option>
            <option value="8">Grade 8</option>
            <option value="9">Grade 9</option>
            <option value="10">Grade 10</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>Section <span class="req">*</span></label>
        <div class="select-wrap"><i class="fas fa-users"></i>
          <select id="advisory-section">
            <option value="">Select Grade First</option>
          </select>
        </div>
      </div>
    </div>
    <div class="form-actions">
      <button class="btn-secondary" onclick="document.getElementById('advisoryModal').classList.add('hidden')">Cancel</button>
      <button class="btn-primary" id="saveAdvisoryBtn" onclick="saveAdvisory()"><i class="fas fa-save"></i> Assign Class</button>
    </div>
  </div>
</div>

<script>

let _advisoryUserId = null;
const ADVISORY_SECTIONS = {
  '7':  ['Bonifacio','Luna','Rizal','Mabini','Aguinaldo','Jacinto'],
  '8':  ['Confucius','Goswami','Mandela','Socrates','Aristotle','Plato'],
  '9':  ['Dalton','Bohr','Lavoisier','Newton','Curie','Einstein'],
  '10': ['Osmena','Quezon','Magsaysay','Aguinaldo','Macapagal','Marcos']
};
function advisoryUpdateSections() {
  const grade = document.getElementById('advisory-grade').value;
  const sel   = document.getElementById('advisory-section');
  const secs  = ADVISORY_SECTIONS[grade]||[];
  sel.innerHTML = secs.length
    ? '<option value="">Select Section</option>'+secs.map(s=>'<option value="'+s+'">'+s+'</option>').join('')
    : '<option value="">Select Grade First</option>';
}
function openAssignAdvisory(userId, name, current) {
  _advisoryUserId = userId;
  document.getElementById('advisoryTeacherName').textContent = 'Teacher: '+name;
  if (current && current.includes(' - ')) {
    const parts = current.split(' - ');
    const grade = parts[0].replace('Grade ','');
    document.getElementById('advisory-grade').value = grade;
    advisoryUpdateSections();
    setTimeout(()=>{ document.getElementById('advisory-section').value=parts[1]||''; },80);
  } else {
    document.getElementById('advisory-grade').value='';
    document.getElementById('advisory-section').innerHTML='<option value="">Select Grade First</option>';
  }
  hideEl('advisoryMsg');
  document.getElementById('advisoryModal').classList.remove('hidden');
}
async function saveAdvisory() {
  const grade=document.getElementById('advisory-grade').value;
  const section=document.getElementById('advisory-section').value;
  if(!grade||!section){showMsg('advisoryMsg','Please select both grade and section.');return;}
  const cls='Grade '+grade+' - '+section;
  const btn=document.getElementById('saveAdvisoryBtn');
  setLoading(btn,true); hideEl('advisoryMsg');
  try {
    const r=await fetch('/api/assign_advisory.php?user_id='+_advisoryUserId+'&class='+encodeURIComponent(cls));
    const d=await r.json();
    if(d.success){showToast('Advisory class assigned!','success');document.getElementById('advisoryModal').classList.add('hidden');setTimeout(()=>location.reload(),1200);}
    else{showMsg('advisoryMsg',d.message||'Failed.');setLoading(btn,false);}
  }catch{showToast('Error. Check server.','error');setLoading(btn,false);}
}

const NU_GRADE_SECTIONS = {
  '7':  ['Bonifacio', 'Luna', 'Rizal', 'Mabini', 'Aguinaldo', 'Jacinto'],
  '8':  ['Confucius', 'Goswami', 'Mandela', 'Socrates', 'Aristotle', 'Plato'],
  '9':  ['Dalton', 'Bohr', 'Lavoisier', 'Newton', 'Curie', 'Einstein'],
  '10': ['Osmena', 'Quezon', 'Magsaysay', 'Aguinaldo', 'Macapagal', 'Marcos']
};
function toggleGrade(role) {
  document.getElementById('nu-grade-group').style.display = role==='student' ? 'block' : 'none';
}
function nuUpdateSections() {
  const grade = document.getElementById('nu-grade-level').value;
  const sel   = document.getElementById('nu-section');
  const secs  = NU_GRADE_SECTIONS[grade] || [];
  sel.innerHTML = secs.length
    ? '<option value="">Select Section</option>' + secs.map(s=>'<option value="'+s+'">'+s+'</option>').join('')
    : '<option value="">Select Grade First</option>';
}
</script>
HTML;

renderLayout($user, 'User Management', 'users', $content);



