<?php
/**
 * GNHS Guidance System — One-time Setup Script
 * Run this ONCE after importing the SQL schema to:
 *   1. Create the database tables
 *   2. Insert seed users with proper bcrypt-hashed passwords
 *
 * Access via browser: http://localhost/gnhs-guidance/setup.php
 * DELETE this file after running!
 */

require_once __DIR__ . '/includes/config.php';

// Safety: only allow if no users exist yet
$db = getDB();
$existing = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($existing > 0) {
  die('<h2 style="color:red;font-family:sans-serif">Setup already done! Delete setup.php for security.</h2>');
}

$users = [
  ['Maria',   'Reyes',     'mreyes@gnhs.edu.ph',    'EMP-001',       'Admin@1234',    'admin',   null,               'MR'],
  ['Ana',     'Santos',    'asantos@gnhs.edu.ph',   'EMP-002',       'Teacher@1234',  'teacher', null,               'AS'],
  ['Juan',    'Dela Cruz', 'jdelacruz@gnhs.edu.ph', '123456789012',  'Student@1234',  'student', 'Grade 9 - Mabini', 'JD'],
  ['Anna',    'Lopez',     'alopez@gnhs.edu.ph',    '123456789013',  'Student@1234',  'student', 'Grade 9 - Mabini', 'AL'],
  ['Rico',    'Mendoza',   'rmendoza@gnhs.edu.ph',  '123456789014',  'Student@1234',  'student', 'Grade 8 - Rizal',  'RM'],
];

$stmt = $db->prepare("INSERT INTO users (first_name,last_name,email,id_number,password,role,grade_section,avatar_initials) VALUES (?,?,?,?,?,?,?,?)");

foreach ($users as [$fn,$ln,$em,$id,$pw,$role,$grade,$init]) {
  $hash = password_hash($pw, PASSWORD_BCRYPT);
  $stmt->execute([$fn,$ln,$em,$id,$hash,$role,$grade,$init]);
}

// Insert sample cases for demo
$u3 = $db->lastInsertId() - 2; // Juan's ID (3rd from last)
$db->exec("
  INSERT INTO cases (case_number,student_id,is_anonymous,concern_type,subject,description,ai_summary,priority,status,assigned_to) VALUES
  ('C-0001',$u3,0,'bullying','Bullying by classmates','Some of my classmates have been taking my belongings and making fun of me for three weeks now. It happens mostly during lunch.','Student reports a 3-week bullying incident involving theft and verbal harassment during lunch. Significant emotional impact noted.','high','under_review',1),
  ('C-0002',$u3,0,'academic_stress','Failing grades this quarter','I have been struggling with Math and Science this quarter and my grades have been declining significantly.','Student struggling with two core subjects. Seeks academic support.','medium','resolved',1),
  ('C-0003',$u3,0,'emotional','Feeling overwhelmed and anxious','I feel very anxious lately especially during exams. I cannot concentrate properly and I lose sleep over it.','Student experiencing anxiety and concentration difficulties. May benefit from counseling sessions.','medium','resolved',1)
");

$db->exec("
  INSERT INTO notifications (user_id,title,message,type,is_read) VALUES
  ($u3,'Counselor Replied','Ms. Reyes has replied to your case #C-0001.','case_update',0),
  ($u3,'Session Scheduled','A counseling session has been scheduled for this week.','session_scheduled',0),
  ($u3,'Case Resolved','Your case #C-0002 has been marked as Resolved.','case_update',1),
  (1,'New Case Submitted','A new High Priority case (bullying) has been submitted.','case_update',0)
");

echo '<!DOCTYPE html><html><head><style>body{font-family:sans-serif;max-width:600px;margin:60px auto;padding:20px}h2{color:#550000}.table{width:100%;border-collapse:collapse;margin:16px 0}.table td,.table th{padding:10px 14px;border:1px solid #e4e4e7;font-size:14px}.table th{background:#fff0f0;font-weight:700}.btn{display:inline-block;padding:12px 24px;background:#550000;color:#fff;border-radius:10px;text-decoration:none;font-weight:700;margin-top:16px}</style></head><body>
<h2>✅ GNHS Guidance System Setup Complete!</h2>
<p>The following test accounts have been created:</p>
<table class="table">
<tr><th>Role</th><th>Email</th><th>Password</th></tr>
<tr><td>Admin (Counselor)</td><td>mreyes@gnhs.edu.ph</td><td>Admin@1234</td></tr>
<tr><td>Teacher</td><td>asantos@gnhs.edu.ph</td><td>Teacher@1234</td></tr>
<tr><td>Student</td><td>jdelacruz@gnhs.edu.ph</td><td>Student@1234</td></tr>
<tr><td>Student</td><td>alopez@gnhs.edu.ph</td><td>Student@1234</td></tr>
<tr><td>Student</td><td>rmendoza@gnhs.edu.ph</td><td>Student@1234</td></tr>
</table>
<p style="color:red;font-weight:700">⚠️ IMPORTANT: Delete setup.php from your server now!</p>
<a class="btn" href="index.html">Go to Login Page →</a>
</body></html>';



