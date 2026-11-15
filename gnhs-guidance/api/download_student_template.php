<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin('admin,teacher');

$filename = 'gnhs_students_import_template.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
// UTF-8 BOM for Microsoft Excel compatibility
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

// Header row - grade_level and section are explicitly separated
fputcsv($out, [
    'first_name',
    'last_name',
    'middle_name',
    'suffix',
    'lrn',
    'email',
    'grade_level',
    'section',
    'temporary_password'
]);

// If logged in as teacher with an advisory class, customize the sample row to their section
$sampleGrade = '9';
$sampleSection = 'Dalton';
if ($user['role'] === 'teacher' && !empty($user['advisory_class'])) {
    $parts = explode(' - ', $user['advisory_class'], 2);
    $gNum = preg_replace('/[^0-9]/', '', $parts[0] ?? '');
    if ($gNum) $sampleGrade = $gNum;
    if (!empty($parts[1])) $sampleSection = trim($parts[1]);
}

// Sample Rows
fputcsv($out, [
    'Juan',
    'Dela Cruz',
    'Santos',
    '',
    '109283746501',
    'juan.delacruz@deped.gov.ph',
    $sampleGrade,
    $sampleSection,
    'Student@2026'
]);

fputcsv($out, [
    'Maria',
    'Reyes',
    'Bautista',
    '',
    '109283746502',
    'maria.reyes@deped.gov.ph',
    $sampleGrade,
    $sampleSection,
    'Student@2026'
]);

fputcsv($out, [
    'Mark Andrei',
    'Aquino',
    'Ramos',
    'Jr.',
    '109283746503',
    'mark.aquino@deped.gov.ph',
    $sampleGrade,
    $sampleSection,
    'Student@2026'
]);

fclose($out);
exit;

