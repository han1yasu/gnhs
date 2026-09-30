<?php
require_once __DIR__ . '/../includes/config.php';
$adminUser = requireLogin('admin');
header('Content-Type: application/json');

$type      = trim($_GET['type'] ?? 'timeline'); // 'timeline' or 'status'
$dateFrom  = trim($_GET['date_from'] ?? '');
$dateTo    = trim($_GET['date_to'] ?? '');
$label     = trim($_GET['label'] ?? '');
$status    = trim($_GET['status'] ?? '');

try {
    $db = getDB();
    
    $where = [];
    $params = [];
    $title = '';
    $subtitle = '';

    if ($type === 'status') {
        if (!$status) {
            jsonOut(['success' => false, 'message' => 'Status parameter required.'], 400);
        }
        $where[] = "c.status = ?";
        $params[] = $status;
        $formattedStatus = ucwords(str_replace('_', ' ', $status));
        $title = "Cases with Status: $formattedStatus";
        $subtitle = "Showing all cases currently marked as $formattedStatus";
    } else {
        // Timeline filter
        if ($dateFrom && $dateTo) {
            $where[] = "c.submitted_at BETWEEN ? AND ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
        } elseif ($dateFrom) {
            $where[] = "c.submitted_at >= ?";
            $params[] = $dateFrom;
        }
        $title = $label ? "Cases: $label" : "Cases in Selected Period";
        if ($dateFrom && $dateTo) {
            $subtitle = "Submitted between " . date('M j, Y g:i A', strtotime($dateFrom)) . " and " . date('M j, Y g:i A', strtotime($dateTo));
        } else {
            $subtitle = "Showing cases submitted for this timeframe";
        }
    }

    $whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    $sql = "
        SELECT c.*,
            CASE WHEN c.is_anonymous = 1 THEN 'Anonymous' ELSE CONCAT(s.first_name, ' ', s.last_name) END AS student_name,
            s.grade_section
        FROM cases c
        LEFT JOIN users s ON c.student_id = s.id
        $whereSql
        ORDER BY c.submitted_at DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $cases = [];
    foreach ($rows as $r) {
        $isArchive = in_array($r['status'], ['resolved', 'closed']);
        $targetPage = $isArchive ? 'admin-archive.php' : 'admin-cases.php';
        $targetUrl = $targetPage . '?q=' . urlencode($r['case_number']);

        $cases[] = [
            'id'             => (int)$r['id'],
            'case_number'    => $r['case_number'],
            'student_name'   => $r['student_name'] ?: 'Anonymous',
            'grade_section'  => $r['grade_section'] ?: '—',
            'concern_type'   => $r['concern_type'],
            'formatted_type' => ucwords(str_replace('_', ' ', $r['concern_type'])),
            'priority'       => $r['priority'],
            'status'         => $r['status'],
            'subject'        => $r['subject'],
            'description'    => $r['description'],
            'ai_summary'     => $r['ai_summary'],
            'submitted_at'   => $r['submitted_at'],
            'formatted_date' => date('M j, Y g:i A', strtotime($r['submitted_at'])),
            'target_page'    => $targetPage,
            'target_url'     => $targetUrl,
        ];
    }

    jsonOut([
        'success'   => true,
        'title'     => $title,
        'subtitle'  => $subtitle,
        'count'     => count($cases),
        'cases'     => $cases,
    ]);

} catch (Exception $e) {
    jsonOut(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}
