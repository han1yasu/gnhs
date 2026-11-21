<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin('admin');
$db   = getDB();

$format   = trim($_GET['format'] ?? 'csv'); // 'csv', 'report', 'json'
$range    = trim($_GET['range'] ?? 'last_6_months');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to'] ?? '');
$incCases = isset($_GET['include_cases']) ? (int)$_GET['include_cases'] : 1;
$incRefs  = isset($_GET['include_referrals']) ? (int)$_GET['include_referrals'] : 1;

// Resolve date boundaries
$now = date('Y-m-d H:i:s');
$todayDate = date('Y-m-d');

switch ($range) {
    case 'last_7_days':
        $from = date('Y-m-d 00:00:00', strtotime('-6 days'));
        $to   = date('Y-m-d 23:59:59');
        $rangeLabel = 'Last 7 Days (' . date('M d, Y', strtotime($from)) . ' - ' . date('M d, Y', strtotime($to)) . ')';
        break;
    case 'last_30_days':
        $from = date('Y-m-d 00:00:00', strtotime('-29 days'));
        $to   = date('Y-m-d 23:59:59');
        $rangeLabel = 'Last 30 Days (' . date('M d, Y', strtotime($from)) . ' - ' . date('M d, Y', strtotime($to)) . ')';
        break;
    case 'this_month':
        $from = date('Y-m-01 00:00:00');
        $to   = date('Y-m-t 23:59:59');
        $rangeLabel = 'This Month (' . date('F Y') . ')';
        break;
    case 'last_8_weeks':
        $from = date('Y-m-d 00:00:00', strtotime('-8 weeks'));
        $to   = date('Y-m-d 23:59:59');
        $rangeLabel = 'Last 8 Weeks (' . date('M d, Y', strtotime($from)) . ' - ' . date('M d, Y', strtotime($to)) . ')';
        break;
    case 'all_time':
        $from = '2020-01-01 00:00:00';
        $to   = date('Y-m-d 23:59:59');
        $rangeLabel = 'All Time Record (Up to ' . date('M d, Y') . ')';
        break;
    case 'custom':
        if (!$dateFrom) $dateFrom = date('Y-m-01');
        if (!$dateTo)   $dateTo   = date('Y-m-d');
        $from = $dateFrom . ' 00:00:00';
        $to   = $dateTo . ' 23:59:59';
        $rangeLabel = 'Custom Period (' . date('M d, Y', strtotime($from)) . ' - ' . date('M d, Y', strtotime($to)) . ')';
        break;
    case 'last_6_months':
    default:
        $from = date('Y-m-01 00:00:00', strtotime('-5 months'));
        $to   = date('Y-m-d 23:59:59');
        $rangeLabel = 'Last 6 Months (' . date('M Y', strtotime($from)) . ' - ' . date('M Y', strtotime($to)) . ')';
        break;
}

// 1. KPI Statistics in Date Range
$stmtTotal = $db->prepare("SELECT COUNT(*) FROM cases WHERE submitted_at >= ? AND submitted_at <= ?");
$stmtTotal->execute([$from, $to]);
$totalCases = (int)$stmtTotal->fetchColumn();

$stmtResolved = $db->prepare("SELECT COUNT(*) FROM cases WHERE status IN ('resolved','closed') AND submitted_at >= ? AND submitted_at <= ?");
$stmtResolved->execute([$from, $to]);
$resolvedCases = (int)$stmtResolved->fetchColumn();

$stmtHigh = $db->prepare("SELECT COUNT(*) FROM cases WHERE priority='high' AND submitted_at >= ? AND submitted_at <= ?");
$stmtHigh->execute([$from, $to]);
$highPrioCases = (int)$stmtHigh->fetchColumn();

$stmtMed = $db->prepare("SELECT COUNT(*) FROM cases WHERE priority='medium' AND submitted_at >= ? AND submitted_at <= ?");
$stmtMed->execute([$from, $to]);
$medPrioCases = (int)$stmtMed->fetchColumn();

$stmtLow = $db->prepare("SELECT COUNT(*) FROM cases WHERE priority='low' AND submitted_at >= ? AND submitted_at <= ?");
$stmtLow->execute([$from, $to]);
$lowPrioCases = (int)$stmtLow->fetchColumn();

$resolutionRate = $totalCases > 0 ? round(($resolvedCases / $totalCases) * 100) : 0;

$stmtAvg = $db->prepare("SELECT ROUND(AVG(DATEDIFF(updated_at, submitted_at)), 1) FROM cases WHERE status IN ('resolved','closed') AND submitted_at >= ? AND submitted_at <= ?");
$stmtAvg->execute([$from, $to]);
$avgDays = $stmtAvg->fetchColumn();
if ($avgDays === null || $avgDays === false) $avgDays = 'N/A';

// 2. Cases by Concern Type in Date Range
$stmtType = $db->prepare("
    SELECT concern_type, COUNT(*) as cnt 
    FROM cases 
    WHERE submitted_at >= ? AND submitted_at <= ? 
    GROUP BY concern_type 
    ORDER BY cnt DESC
");
$stmtType->execute([$from, $to]);
$byType = $stmtType->fetchAll(PDO::FETCH_ASSOC);

// 3. Cases by Grade Level in Date Range
$stmtGrade = $db->prepare("
    SELECT 
        CASE 
            WHEN s.grade_section LIKE 'Grade 7%' THEN 'Grade 7'
            WHEN s.grade_section LIKE 'Grade 8%' THEN 'Grade 8'
            WHEN s.grade_section LIKE 'Grade 9%' THEN 'Grade 9'
            WHEN s.grade_section LIKE 'Grade 10%' THEN 'Grade 10'
            ELSE 'Other / Unknown'
        END AS grade_level,
        COUNT(*) as cnt
    FROM cases c
    LEFT JOIN users s ON c.student_id = s.id
    WHERE c.submitted_at >= ? AND c.submitted_at <= ?
    GROUP BY grade_level
    ORDER BY grade_level ASC
");
$stmtGrade->execute([$from, $to]);
$byGrade = $stmtGrade->fetchAll(PDO::FETCH_ASSOC);

// 4. Cases by Status in Date Range
$stmtStatus = $db->prepare("
    SELECT status, COUNT(*) as cnt 
    FROM cases 
    WHERE submitted_at >= ? AND submitted_at <= ? 
    GROUP BY status 
    ORDER BY cnt DESC
");
$stmtStatus->execute([$from, $to]);
$byStatus = $stmtStatus->fetchAll(PDO::FETCH_ASSOC);

// 5. Individual Case Records in Date Range
$caseRows = [];
if ($incCases) {
    $stmtCases = $db->prepare("
        SELECT 
            c.id, c.case_number, c.concern_type, c.priority, c.status, 
            c.submitted_at, c.updated_at, c.is_anonymous,
            s.first_name, s.last_name, s.grade_section, s.id_number
        FROM cases c
        LEFT JOIN users s ON c.student_id = s.id
        WHERE c.submitted_at >= ? AND c.submitted_at <= ?
        ORDER BY c.submitted_at DESC
    ");
    $stmtCases->execute([$from, $to]);
    $caseRows = $stmtCases->fetchAll(PDO::FETCH_ASSOC);
}

// 6. Referrals in Date Range
$referralRows = [];
$totalReferrals = 0;
if ($incRefs) {
    $stmtRefs = $db->prepare("
        SELECT 
            r.id, r.ref_number, r.student_name, r.grade_section, r.concern_type, 
            r.urgency, r.status, r.submitted_at,
            CONCAT(t.first_name, ' ', t.last_name) AS teacher_name
        FROM referrals r
        LEFT JOIN users t ON r.teacher_id = t.id
        WHERE r.submitted_at >= ? AND r.submitted_at <= ?
        ORDER BY r.submitted_at DESC
    ");
    $stmtRefs->execute([$from, $to]);
    $referralRows = $stmtRefs->fetchAll(PDO::FETCH_ASSOC);
    $totalReferrals = count($referralRows);
}

// Format Label Helpers
function formatLabel($val) {
    return ucwords(str_replace(['_', '-'], ' ', $val));
}

// -------------------------------------------------------------
// FORMAT: JSON (API query)
// -------------------------------------------------------------
if ($format === 'json') {
    jsonOut([
        'success' => true,
        'range_label' => $rangeLabel,
        'date_from' => $from,
        'date_to' => $to,
        'kpi' => [
            'total_cases' => $totalCases,
            'resolved_cases' => $resolvedCases,
            'resolution_rate' => $resolutionRate,
            'high_priority' => $highPrioCases,
            'medium_priority' => $medPrioCases,
            'low_priority' => $lowPrioCases,
            'avg_days_to_resolve' => $avgDays,
            'total_referrals' => $totalReferrals,
        ],
        'by_type' => $byType,
        'by_grade' => $byGrade,
        'by_status' => $byStatus,
        'cases' => $caseRows,
        'referrals' => $referralRows,
    ]);
    exit;
}

// -------------------------------------------------------------
// FORMAT: CSV (Excel-Compatible Spreadsheet Export)
// -------------------------------------------------------------
if ($format === 'csv') {
    $filename = 'GNHS_Guidance_Analytics_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // Output UTF-8 BOM so Excel opens accented & special chars properly
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    // Helper to prevent Excel from turning dates into ######## or 12-digit LRNs into scientific notation (1.09E+11)
    $csvText = function ($val): string {
        $val = trim((string)$val);
        if ($val === '' || $val === '—' || $val === 'Confidential') return $val;
        return '="' . str_replace('"', '""', $val) . '"';
    };
    $csvDate = function ($dt) use ($csvText): string {
        if (empty($dt)) return '—';
        $ts = strtotime($dt);
        if ($ts === false) return $csvText($dt);
        return $csvText(date('M d, Y h:i A', $ts));
    };

    // Section 1: Header & Metadata
    fputcsv($out, ['GUINAYANG NATIONAL HIGH SCHOOL - GUIDANCE SERVICES OFFICE']);
    fputcsv($out, ['ANALYTICS & INCIDENTS SUMMARY REPORT']);
    fputcsv($out, ['Coverage Period:', $rangeLabel]);
    fputcsv($out, ['Generated On:', $csvText(date('F d, Y h:i A'))]);
    fputcsv($out, ['Generated By:', $user['first_name'] . ' ' . $user['last_name'] . ' (Guidance Counselor)']);
    fputcsv($out, []);

    // Section 2: Executive Summary KPIs
    fputcsv($out, ['--- EXECUTIVE SUMMARY METRICS ---']);
    fputcsv($out, ['Metric', 'Value']);
    fputcsv($out, ['Total Cases Recorded', $totalCases]);
    fputcsv($out, ['Resolved / Closed Cases', $resolvedCases]);
    fputcsv($out, ['Overall Resolution Rate', $resolutionRate . '%']);
    fputcsv($out, ['High Priority / Urgent Cases', $highPrioCases]);
    fputcsv($out, ['Medium Priority Cases', $medPrioCases]);
    fputcsv($out, ['Low Priority Cases', $lowPrioCases]);
    fputcsv($out, ['Average Resolution Time (Days)', $avgDays]);
    fputcsv($out, ['Total Teacher Referrals in Period', $totalReferrals]);
    fputcsv($out, []);

    // Section 3: Concern Types Breakdown
    fputcsv($out, ['--- CONCERN TYPE BREAKDOWN ---']);
    fputcsv($out, ['Concern Category', 'Total Cases', 'Share (%)']);
    foreach ($byType as $bt) {
        $pct = $totalCases > 0 ? round(($bt['cnt'] / $totalCases) * 100, 1) : 0;
        fputcsv($out, [formatLabel($bt['concern_type']), $bt['cnt'], $pct . '%']);
    }
    fputcsv($out, []);

    // Section 4: Grade Level Breakdown
    fputcsv($out, ['--- GRADE LEVEL DISTRIBUTION ---']);
    fputcsv($out, ['Grade Level', 'Total Cases', 'Share (%)']);
    foreach ($byGrade as $bg) {
        $pct = $totalCases > 0 ? round(($bg['cnt'] / $totalCases) * 100, 1) : 0;
        fputcsv($out, [$bg['grade_level'], $bg['cnt'], $pct . '%']);
    }
    fputcsv($out, []);

    // Section 5: Case Records Log
    if ($incCases && !empty($caseRows)) {
        fputcsv($out, ['--- DETAILED INCIDENT & CASE RECORDS ---']);
        fputcsv($out, ['Case ID', 'Submitted Date', 'Student Name', 'LRN', 'Grade & Section', 'Concern Type', 'Priority', 'Status', 'Updated / Resolved Date']);
        foreach ($caseRows as $c) {
            $fullName = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
            $studentDisplay = $c['is_anonymous'] ? 'Anonymous Student' : ($fullName !== '' ? $fullName : 'Student #' . $c['id']);
            $lrnDisplay = $c['is_anonymous'] ? 'Confidential' : (!empty($c['id_number']) ? $csvText($c['id_number']) : '—');
            $secDisplay = $c['is_anonymous'] ? 'Confidential' : ($c['grade_section'] ?: '—');
            fputcsv($out, [
                $c['case_number'],
                $csvDate($c['submitted_at']),
                $studentDisplay,
                $lrnDisplay,
                $secDisplay,
                formatLabel($c['concern_type']),
                ucfirst($c['priority']),
                formatLabel($c['status']),
                $csvDate($c['updated_at'] ?? '')
            ]);
        }
        fputcsv($out, []);
    }

    // Section 6: Teacher Referrals Log
    if ($incRefs && !empty($referralRows)) {
        fputcsv($out, ['--- TEACHER REFERRALS LOG ---']);
        fputcsv($out, ['Referral ID', 'Submitted Date', 'Student Name', 'Grade & Section', 'Referring Teacher', 'Concern Type', 'Urgency', 'Status']);
        foreach ($referralRows as $r) {
            fputcsv($out, [
                $r['ref_number'],
                $csvDate($r['submitted_at']),
                $r['student_name'],
                $r['grade_section'],
                $r['teacher_name'] ?: 'Teacher',
                formatLabel($r['concern_type']),
                ucfirst($r['urgency']),
                formatLabel($r['status'])
            ]);
        }
    }

    fclose($out);
    exit;
}

// -------------------------------------------------------------
// FORMAT: REPORT / PRINT (Official Printable DepEd Guidance Report)
// -------------------------------------------------------------
$logoPath = '/gnhs-guidance/gnhs.png';
$counselorName = $user['first_name'] . ' ' . $user['last_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Guidance Analytics Report — <?= htmlspecialchars($rangeLabel) ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    :root {
      --maroon: #550000;
      --maroon-dark: #3b0000;
      --maroon-pale: #fff0f0;
      --border: #e2e8f0;
      --text: #0f172a;
      --text-2: #475569;
      --text-3: #64748b;
      --bg: #ffffff;
      --bg2: #f8fafc;
    }
    * { box-sizing: border-box; }
    body {
      font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
      margin: 0;
      padding: 30px;
      background: #f1f5f9;
      color: var(--text);
      line-height: 1.5;
    }
    .report-sheet {
      max-width: 960px;
      margin: 0 auto;
      background: #ffffff;
      padding: 44px 52px;
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.06);
      border: 1px solid var(--border);
    }
    /* Action Bar (hidden on print) */
    .print-actions {
      max-width: 960px;
      margin: 0 auto 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
    }
    .btn-action {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 18px;
      border-radius: 8px;
      font-size: 13.5px;
      font-weight: 700;
      cursor: pointer;
      text-decoration: none;
      border: none;
      transition: all .2s;
    }
    .btn-print {
      background: var(--maroon);
      color: #ffffff;
    }
    .btn-print:hover { background: var(--maroon-dark); transform: translateY(-1px); }
    .btn-pdf {
      background: #2563eb;
      color: #ffffff;
    }
    .btn-pdf:hover { background: #1d4ed8; transform: translateY(-1px); }
    .btn-pdf:disabled { opacity: 0.7; cursor: wait; transform: none; }
    .btn-csv {
      background: #16a34a;
      color: #ffffff;
    }
    .btn-csv:hover { background: #15803d; transform: translateY(-1px); }
    .btn-close {
      background: #f1f5f9;
      color: var(--text-2);
      border: 1px solid var(--border);
    }
    .btn-close:hover { background: #e2e8f0; }

    /* Official DepEd Header */
    .rep-header {
      text-align: center;
      border-bottom: 2px solid var(--maroon);
      padding-bottom: 22px;
      margin-bottom: 28px;
      position: relative;
    }
    .rep-logo-wrap {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 20px;
      margin-bottom: 8px;
    }
    .rep-logo {
      width: 72px;
      height: 72px;
      object-fit: contain;
    }
    .rep-deped-text {
      text-transform: uppercase;
      font-size: 11.5px;
      color: var(--text-3);
      letter-spacing: 1.5px;
      font-weight: 700;
      line-height: 1.35;
    }
    .rep-school-name {
      font-size: 20px;
      font-weight: 900;
      color: var(--maroon);
      letter-spacing: 0.5px;
      margin: 4px 0 2px;
    }
    .rep-office-name {
      font-size: 13.5px;
      font-weight: 800;
      color: var(--text-2);
      letter-spacing: 1px;
      text-transform: uppercase;
    }

    /* Document Title */
    .rep-title-block {
      text-align: center;
      margin-bottom: 30px;
      background: var(--bg2);
      padding: 16px 20px;
      border-radius: 8px;
      border: 1px solid var(--border);
    }
    .rep-title {
      font-size: 17px;
      font-weight: 900;
      color: var(--maroon);
      text-transform: uppercase;
      letter-spacing: 0.8px;
      margin: 0 0 6px;
    }
    .rep-meta {
      font-size: 12.5px;
      color: var(--text-2);
      display: flex;
      justify-content: center;
      gap: 24px;
      flex-wrap: wrap;
    }
    .rep-meta strong { color: var(--text); }

    /* KPI Summary Cards */
    .kpi-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 14px;
      margin-bottom: 30px;
    }
    .kpi-box {
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 14px;
      text-align: center;
      background: var(--bg2);
    }
    .kpi-val {
      font-size: 26px;
      font-weight: 900;
      color: var(--maroon);
      line-height: 1.1;
      margin-bottom: 4px;
    }
    .kpi-lbl {
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-weight: 700;
      color: var(--text-3);
    }

    /* Section Headers */
    .sec-head {
      font-size: 14px;
      font-weight: 900;
      text-transform: uppercase;
      color: var(--maroon);
      letter-spacing: 0.8px;
      margin: 28px 0 12px;
      padding-bottom: 6px;
      border-bottom: 1.5px solid var(--border);
      display: flex;
      align-items: center;
      gap: 8px;
      page-break-after: avoid;
      break-after: avoid;
    }

    /* Tables */
    .rep-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 12px;
      margin-bottom: 24px;
    }
    .rep-table th, .rep-table td {
      border: 1px solid var(--border);
      padding: 8px 10px;
      text-align: left;
    }
    .rep-table th {
      background: #f8fafc;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-size: 11px;
      color: var(--text-2);
    }
    .rep-table tr:nth-child(even) { background: #fafafa; }
    .badge {
      display: inline-block;
      padding: 2px 7px;
      border-radius: 4px;
      font-size: 10.5px;
      font-weight: 700;
      text-transform: capitalize;
    }
    .badge-urgent, .badge-high { background: #fee2e2; color: #dc2626; }
    .badge-medium, .badge-moderate { background: #fef3c7; color: #d97706; }
    .badge-low { background: #f0fdf4; color: #16a34a; }
    .badge-resolved { background: #dcfce7; color: #15803d; }
    .badge-pending { background: #f1f5f9; color: #475569; }

    /* Two-col layout */
    .two-col {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }

    /* Sign-off Block */
    .sign-block {
      margin-top: 45px;
      padding-top: 20px;
      display: flex;
      justify-content: space-between;
      page-break-inside: avoid;
    }
    .sign-col {
      width: 240px;
      text-align: center;
    }
    .sign-line {
      margin-top: 50px;
      border-top: 1px solid #000;
      padding-top: 4px;
      font-weight: 800;
      font-size: 13px;
    }
    .sign-title {
      font-size: 11.5px;
      color: var(--text-3);
    }

    body.pdf-exporting {
      padding: 0 !important;
      background: #ffffff !important;
    }
    body.pdf-exporting .print-actions { display: none !important; }
    body.pdf-exporting .report-sheet {
      width: 720px !important;
      max-width: 720px !important;
      margin: 0 auto !important;
      padding: 0 !important;
      border: none !important;
      box-shadow: none !important;
      border-radius: 0 !important;
    }
    body.pdf-exporting .rep-header { padding-bottom: 14px !important; margin-bottom: 16px !important; }
    body.pdf-exporting .rep-logo { width: 56px !important; height: 56px !important; }
    body.pdf-exporting .rep-deped-text { font-size: 10px !important; }
    body.pdf-exporting .rep-school-name { font-size: 17px !important; }
    body.pdf-exporting .rep-office-name { font-size: 11px !important; }
    body.pdf-exporting .rep-title-block { padding: 12px 16px !important; margin-bottom: 16px !important; }
    body.pdf-exporting .rep-title { font-size: 15px !important; }
    body.pdf-exporting .rep-meta { font-size: 11px !important; gap: 14px !important; }
    body.pdf-exporting .kpi-grid { gap: 8px !important; margin-bottom: 18px !important; }
    body.pdf-exporting .kpi-box { padding: 10px 6px !important; }
    body.pdf-exporting .kpi-val { font-size: 19px !important; }
    body.pdf-exporting .kpi-lbl { font-size: 9px !important; }
    body.pdf-exporting .two-col { gap: 14px !important; margin-bottom: 16px !important; }
    body.pdf-exporting .sec-head { font-size: 12px !important; margin: 14px 0 8px !important; }
    body.pdf-exporting .rep-table { font-size: 10px !important; table-layout: fixed; width: 100% !important; }
    body.pdf-exporting .rep-table th { padding: 6px 5px !important; font-size: 9px !important; }
    body.pdf-exporting .rep-table td { padding: 5px 5px !important; word-wrap: break-word; overflow-wrap: break-word; }
    body.pdf-exporting .badge { font-size: 8.5px !important; padding: 2px 5px !important; }
    body.pdf-exporting .sign-block { margin-top: 28px !important; }
    body.pdf-exporting .sign-col { width: 200px !important; }
    body.pdf-exporting .rep-table { page-break-inside: auto; }
    body.pdf-exporting .rep-table tr { page-break-inside: avoid; break-inside: avoid; }

    @media print {
      body {
        padding: 0;
        background: #ffffff;
      }
      .print-actions { display: none !important; }
      .report-sheet {
        border: none;
        box-shadow: none;
        padding: 0;
        max-width: 100%;
      }
      .rep-table { page-break-inside: auto; }
      .rep-table tr { page-break-inside: avoid; page-break-after: auto; }
      @page {
        size: A4 portrait;
        margin: 14mm 14mm 18mm 14mm;
        @top-left { content: ""; }
        @top-center { content: ""; }
        @top-right { content: ""; }
        @bottom-left { content: ""; }
        @bottom-center { content: ""; }
        @bottom-right {
          content: "Page " counter(page) " of " counter(pages);
          font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
          font-size: 9pt;
          font-weight: 600;
          color: #64748b;
        }
      }
    }
  </style>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body>

  <!-- Screen Actions Bar (hidden when printing) -->
  <div class="print-actions">
    <div>
      <button type="button" class="btn-action btn-close" onclick="window.close()"><i class="fas fa-arrow-left"></i> Back to System</button>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a href="export_analytics.php?format=csv&range=<?= urlencode($range) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&include_cases=<?= $incCases ?>&include_referrals=<?= $incRefs ?>" class="btn-action btn-csv">
        <i class="fas fa-file-csv"></i> Download CSV / Excel
      </a>
      <button type="button" class="btn-action btn-pdf" id="btnSavePdf" onclick="downloadReportPdf()">
        <i class="fas fa-file-pdf"></i> Save as PDF
      </button>
      <button type="button" class="btn-action btn-print" onclick="window.print()">
        <i class="fas fa-print"></i> Print Report
      </button>
    </div>
  </div>

  <!-- Printable Report Document Sheet -->
  <div class="report-sheet" id="reportSheet">
    <!-- Header -->
    <div class="rep-header">
      <div class="rep-logo-wrap">
        <img src="<?= htmlspecialchars($logoPath) ?>" alt="GNHS Logo" class="rep-logo" onerror="this.style.display='none'">
        <div>
          <div class="rep-deped-text">Republic of the Philippines &bull; Department of Education</div>
          <div class="rep-deped-text">Region IV-A CALABARZON &bull; Division of Quezon</div>
          <div class="rep-school-name">GUINAYANG NATIONAL HIGH SCHOOL</div>
          <div class="rep-office-name">Office of Guidance &amp; Counseling Services</div>
        </div>
      </div>
    </div>

    <!-- Title Block -->
    <div class="rep-title-block">
      <h1 class="rep-title">Guidance Analytics &amp; Incident Case Summary Report</h1>
      <div class="rep-meta">
        <div><strong>Period Covered:</strong> <?= htmlspecialchars($rangeLabel) ?></div>
        <div><strong>Date Generated:</strong> <?= date('F d, Y h:i A') ?></div>
        <div><strong>Counselor:</strong> <?= htmlspecialchars($counselorName) ?></div>
      </div>
    </div>

    <!-- Executive Summary KPIs -->
    <div class="kpi-grid">
      <div class="kpi-box">
        <div class="kpi-val"><?= $totalCases ?></div>
        <div class="kpi-lbl">Total Cases Handled</div>
      </div>
      <div class="kpi-box">
        <div class="kpi-val" style="color:#16a34a"><?= $resolutionRate ?>%</div>
        <div class="kpi-lbl">Resolution Rate (<?= $resolvedCases ?> resolved)</div>
      </div>
      <div class="kpi-box">
        <div class="kpi-val" style="color:#dc2626"><?= $highPrioCases ?></div>
        <div class="kpi-lbl">High Priority / Urgent</div>
      </div>
      <div class="kpi-box">
        <div class="kpi-val" style="color:#2563eb"><?= htmlspecialchars((string)$avgDays) ?></div>
        <div class="kpi-lbl">Avg. Days to Resolve</div>
      </div>
    </div>

    <!-- Tables: Concern Types & Grade Levels -->
    <div class="two-col">
      <div>
        <div class="sec-head"><i class="fas fa-chart-pie"></i> Cases by Concern Category</div>
        <table class="rep-table">
          <thead>
            <tr>
              <th>Category</th>
              <th style="width:50px;text-align:center">Count</th>
              <th style="width:65px;text-align:right">Share</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($byType)): ?>
              <tr><td colspan="3" style="text-align:center;color:var(--text-3)">No cases recorded in this period.</td></tr>
            <?php else: foreach ($byType as $bt): 
              $pct = $totalCases > 0 ? round(($bt['cnt'] / $totalCases) * 100, 1) : 0;
            ?>
              <tr>
                <td><strong><?= htmlspecialchars(formatLabel($bt['concern_type'])) ?></strong></td>
                <td style="text-align:center"><?= $bt['cnt'] ?></td>
                <td style="text-align:right"><?= $pct ?>%</td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <div>
        <div class="sec-head"><i class="fas fa-layer-group"></i> Cases by Grade Level</div>
        <table class="rep-table">
          <thead>
            <tr>
              <th>Grade Level</th>
              <th style="width:50px;text-align:center">Count</th>
              <th style="width:65px;text-align:right">Share</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($byGrade)): ?>
              <tr><td colspan="3" style="text-align:center;color:var(--text-3)">No grade data in this period.</td></tr>
            <?php else: foreach ($byGrade as $bg): 
              $pct = $totalCases > 0 ? round(($bg['cnt'] / $totalCases) * 100, 1) : 0;
            ?>
              <tr>
                <td><strong><?= htmlspecialchars($bg['grade_level']) ?></strong></td>
                <td style="text-align:center"><?= $bg['cnt'] ?></td>
                <td style="text-align:right"><?= $pct ?>%</td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Case Records Table -->
    <?php if ($incCases): ?>
      <div class="sec-head"><i class="fas fa-clipboard-list"></i> Incident Cases Log (<?= count($caseRows) ?> Records)</div>
      <table class="rep-table">
        <thead>
          <tr>
            <th style="width:75px">Case #</th>
            <th style="width:90px">Date</th>
            <th>Student / Identity</th>
            <th>Grade &amp; Section</th>
            <th>Concern Category</th>
            <th style="width:65px">Priority</th>
            <th style="width:80px">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($caseRows)): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--text-3);padding:14px">No case records found in selected date range.</td></tr>
          <?php else: foreach ($caseRows as $c): 
            $fullName = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
            $studentDisplay = $c['is_anonymous'] ? '<em>Anonymous Student</em>' : htmlspecialchars($fullName !== '' ? $fullName : 'Student #' . $c['id']);
            $secDisplay = $c['is_anonymous'] ? '<span style="color:var(--text-3)">Confidential</span>' : htmlspecialchars($c['grade_section'] ?: '—');
            $prioClass = 'badge-' . strtolower($c['priority']);
            $statClass = 'badge-' . strtolower($c['status']);
          ?>
            <tr>
              <td><strong>#<?= htmlspecialchars($c['case_number']) ?></strong></td>
              <td style="font-size:11px"><?= date('M d, Y', strtotime($c['submitted_at'])) ?></td>
              <td><?= $studentDisplay ?></td>
              <td><?= $secDisplay ?></td>
              <td><?= htmlspecialchars(formatLabel($c['concern_type'])) ?></td>
              <td><span class="badge <?= $prioClass ?>"><?= ucfirst($c['priority']) ?></span></td>
              <td><span class="badge <?= $statClass ?>"><?= htmlspecialchars(formatLabel($c['status'])) ?></span></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <!-- Teacher Referrals Table -->
    <?php if ($incRefs && !empty($referralRows)): ?>
      <div class="sec-head"><i class="fas fa-chalkboard-teacher"></i> Teacher Classroom Referrals (<?= count($referralRows) ?> Records)</div>
      <table class="rep-table">
        <thead>
          <tr>
            <th style="width:75px">Ref #</th>
            <th style="width:90px">Date</th>
            <th>Student Name</th>
            <th>Grade &amp; Section</th>
            <th>Referring Teacher</th>
            <th>Concern Type</th>
            <th style="width:70px">Urgency</th>
            <th style="width:80px">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($referralRows as $r): 
            $urgClass = 'badge-' . strtolower($r['urgency']);
            $statClass = 'badge-' . strtolower($r['status']);
          ?>
            <tr>
              <td><strong>#<?= htmlspecialchars($r['ref_number']) ?></strong></td>
              <td style="font-size:11px"><?= date('M d, Y', strtotime($r['submitted_at'])) ?></td>
              <td><strong><?= htmlspecialchars($r['student_name']) ?></strong></td>
              <td><?= htmlspecialchars($r['grade_section']) ?></td>
              <td><?= htmlspecialchars($r['teacher_name'] ?: 'Teacher') ?></td>
              <td><?= htmlspecialchars(formatLabel($r['concern_type'])) ?></td>
              <td><span class="badge <?= $urgClass ?>"><?= ucfirst($r['urgency']) ?></span></td>
              <td><span class="badge <?= $statClass ?>"><?= htmlspecialchars(formatLabel($r['status'])) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <!-- Formal DepEd Sign-off Block -->
    <div class="sign-block">
      <div class="sign-col">
        <div style="font-size:11px;color:var(--text-3);text-transform:uppercase">Prepared by:</div>
        <div class="sign-line"><?= htmlspecialchars($counselorName) ?></div>
        <div class="sign-title">Guidance Counselor, GNHS</div>
      </div>
      <div class="sign-col">
        <div style="font-size:11px;color:var(--text-3);text-transform:uppercase">Noted &amp; Received by:</div>
        <div class="sign-line">SCHOOL PRINCIPAL</div>
        <div class="sign-title">Principal IV, Guinayang NHS</div>
      </div>
    </div>
  </div>

  <script>
    function downloadReportPdf() {
      var btn = document.getElementById('btnSavePdf');
      var origHtml = btn ? btn.innerHTML : '';
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating PDF...';
      }

      document.body.classList.add('pdf-exporting');
      var element = document.getElementById('reportSheet');
      var filename = 'GNHS_Guidance_Analytics_Report_' + new Date().toISOString().slice(0, 10) + '.pdf';

      var opt = {
        margin:      [14, 14, 18, 14],
        filename:    filename,
        image:       { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, letterRendering: true, scrollX: 0, scrollY: 0 },
        jsPDF:       { unit: 'mm', format: 'a4', orientation: 'portrait' },
        pagebreak:   { mode: ['avoid-all', 'css', 'legacy'] }
      };

      html2pdf().set(opt).from(element).toPdf().get('pdf').then(function (pdf) {
        var totalPages = pdf.internal.getNumberOfPages();
        var pageWidth = pdf.internal.pageSize.getWidth();
        var pageHeight = pdf.internal.pageSize.getHeight();
        for (var i = 1; i <= totalPages; i++) {
          pdf.setPage(i);
          pdf.setFont('helvetica', 'normal');
          pdf.setFontSize(9);
          pdf.setTextColor(100, 116, 139);
          pdf.text('Page ' + i + ' of ' + totalPages, pageWidth - 14, pageHeight - 8, { align: 'right' });
        }
      }).save().then(function () {
        document.body.classList.remove('pdf-exporting');
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = origHtml;
        }
      }).catch(function (err) {
        console.error(err);
        document.body.classList.remove('pdf-exporting');
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = origHtml;
        }
        alert('Could not generate PDF directly. Please use the Print Report button instead.');
      });
    }

    document.addEventListener('DOMContentLoaded', function() {
      var params = new URLSearchParams(window.location.search);
      if (params.get('auto_pdf') === '1') {
        setTimeout(downloadReportPdf, 450);
      }
    });
  </script>
</body>
</html>

