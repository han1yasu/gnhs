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
    <h3>Section: <strong><?= htmlspecialchars($advisoryClass ?: 'None Assigned') ?></strong></h3>
  </div>
  
  <?php if (!$advisoryClass): ?>
    <div style="text-align:center;padding:40px 20px;color:var(--text-3)">
      <i class="fas fa-chalkboard-teacher" style="font-size:48px;color:var(--border);margin-bottom:16px;"></i>
      <p>You do not have an assigned advisory class.</p>
    </div>
  <?php elseif (empty($students)): ?>
    <div style="text-align:center;padding:40px 20px;color:var(--text-3)">
      <i class="fas fa-user-graduate" style="font-size:48px;color:var(--border);margin-bottom:16px;"></i>
      <p>No students found for this section.</p>
    </div>
  <?php else: ?>
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
          <?php foreach (\$students as \$s): 
            \$name = htmlspecialchars(\$s['first_name'].' '.\$s['last_name']);
            \$photo = \$s['avatar_photo'];
            \$initials = htmlspecialchars(\$s['avatar_initials'] ?? '');
          ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:12px">
                  <div style="width:32px;height:32px;border-radius:8px;overflow:hidden;flex-shrink:0;background:var(--maroon-pale);color:var(--maroon);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;">
                    <?php if (\$photo): ?>
                      <img src="<?= htmlspecialchars(\$photo) ?>" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                      <?= \$initials ?>
                    <?php endif; ?>
                  </div>
                  <strong style="color:var(--text)"><?= \$name ?></strong>
                </div>
              </td>
              <td><?= htmlspecialchars(\$s['id_number']) ?></td>
              <td><a href="mailto:<?= htmlspecialchars(\$s['email']) ?>" style="color:var(--maroon);text-decoration:none;font-size:13px"><i class="fas fa-envelope"></i> <?= htmlspecialchars(\$s['email']) ?></a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
HTML;

renderLayout($user, 'My Advisory Class', 'advisory', $content);
