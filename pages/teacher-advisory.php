<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('teacher');

$db = getDB();
$advisoryClass = $user['advisory_class'] ?? '';
$students = [];

if ($advisoryClass) {
    // Normalizing spacing around the hyphen just in case
    $stmt = $db->prepare("SELECT id, first_name, last_name, email, id_number, avatar_initials, avatar_photo FROM users WHERE role='student' AND REPLACE(grade_section, ' – ', ' - ') = REPLACE(?, ' – ', ' - ') ORDER BY last_name ASC, first_name ASC");
    $stmt->execute([$advisoryClass]);
    $students = $stmt->fetchAll();
}

$content = <<<HTML
<div class="header-card">
  <h2><i class="fas fa-users" style="color:var(--maroon);margin-right:10px"></i> My Advisory Class</h2>
  <p style="color:var(--text-2);margin-top:6px;">View the students under your assigned advisory section.</p>
</div>

<div class="content-card" style="margin-top:20px">
  <div class="card-header">
    <h3>Section: <strong>
HTML;
$content .= htmlspecialchars($advisoryClass ?: 'None Assigned');
$content .= "</strong></h3>\n  </div>\n";

if (!$advisoryClass) {
    $content .= <<<HTML
    <div style="text-align:center;padding:40px 20px;color:var(--text-3)">
      <i class="fas fa-chalkboard-teacher" style="font-size:48px;color:var(--border);margin-bottom:16px;"></i>
      <p>You do not have an assigned advisory class.</p>
    </div>
HTML;
} elseif (empty($students)) {
    $content .= <<<HTML
    <div style="text-align:center;padding:40px 20px;color:var(--text-3)">
      <i class="fas fa-user-graduate" style="font-size:48px;color:var(--border);margin-bottom:16px;"></i>
      <p>No students found for this section.</p>
    </div>
HTML;
} else {
    $content .= <<<HTML
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Student Name</th>
            <th>ID Number</th>
            <th>Email</th>
          </tr>
        </thead>
        <tbody>
HTML;
    foreach ($students as $s) {
        $name = htmlspecialchars($s['first_name'].' '.$s['last_name']);
        $photo = $s['avatar_photo'] ?? null;
        $initials = htmlspecialchars($s['avatar_initials'] ?? '');
        $idNum = htmlspecialchars($s['id_number']);
        $email = htmlspecialchars($s['email']);
        
        $avatarHtml = $photo 
            ? '<img src="'.htmlspecialchars($photo).'" style="width:100%;height:100%;object-fit:cover;">' 
            : $initials;

        $content .= <<<HTML
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:12px">
                  <div style="width:32px;height:32px;border-radius:8px;overflow:hidden;flex-shrink:0;background:var(--maroon-pale);color:var(--maroon);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;">
                    {$avatarHtml}
                  </div>
                  <strong style="color:var(--text)">{$name}</strong>
                </div>
              </td>
              <td>{$idNum}</td>
              <td><a href="mailto:{$email}" style="color:var(--maroon);text-decoration:none;font-size:13px"><i class="fas fa-envelope"></i> {$email}</a></td>
            </tr>
HTML;
    }
    $content .= <<<HTML
        </tbody>
      </table>
    </div>
HTML;
}

$content .= "\n</div>\n";

renderLayout($user, 'My Advisory Class', 'advisory', $content);
