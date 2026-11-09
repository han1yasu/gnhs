<?php
require_once __DIR__ . '/../includes/config.php';
$adminUser = requireLogin('admin');
header('Content-Type: application/json');

$range       = trim($_GET['range'] ?? 'last_6_months');
$granularity = trim($_GET['granularity'] ?? 'day'); // 'day' or 'week'
$monthParam  = trim($_GET['month'] ?? date('Y-m'));

try {
    $db = getDB();

    // ── 1. Gather Available Months for Dropdown ────────────────────────
    $caseMonths = $db->query("
        SELECT DISTINCT DATE_FORMAT(submitted_at, '%Y-%m') as ym, 
                        DATE_FORMAT(submitted_at, '%M %Y') as label 
        FROM cases 
        WHERE submitted_at IS NOT NULL 
        ORDER BY ym DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $availableMonthsMap = [];
    foreach ($caseMonths as $cm) {
        $availableMonthsMap[$cm['ym']] = $cm['label'];
    }

    // Always include past 12 months up to current month
    for ($i = 0; $i < 12; $i++) {
        $ym = date('Y-m', strtotime("-$i month"));
        $lbl = date('F Y', strtotime("-$i month"));
        if (!isset($availableMonthsMap[$ym])) {
            $availableMonthsMap[$ym] = $lbl;
        }
    }
    krsort($availableMonthsMap); // Sort descending by year-month

    $availableMonths = [];
    foreach ($availableMonthsMap as $ym => $lbl) {
        $availableMonths[] = ['value' => $ym, 'label' => $lbl];
    }

    $labels = [];
    $data = [];
    $pointsMeta = [];
    $title = 'Cases Over Time';
    $subtitle = '';

    if ($range === 'last_6_months') {
        // ── Last 6 Months (Monthly) ──────────────────────────────────
        $title = 'Cases Over Last 6 Months';
        $sixMonthsAgo = date('Y-m-01 00:00:00', strtotime('-5 months'));
        
        $stmt = $db->prepare("
            SELECT DATE_FORMAT(submitted_at, '%Y-%m') as ym, COUNT(*) as cnt 
            FROM cases 
            WHERE submitted_at >= ? 
            GROUP BY ym
        ");
        $stmt->execute([$sixMonthsAgo]);
        $counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        for ($i = 5; $i >= 0; $i--) {
            $ym = date('Y-m', strtotime("-$i month"));
            $lbl = date('M Y', strtotime("-$i month"));
            $labels[] = $lbl;
            $data[] = (int)($counts[$ym] ?? 0);
            $pointsMeta[] = [
                'date_from' => date('Y-m-01 00:00:00', strtotime("-$i month")),
                'date_to'   => date('Y-m-t 23:59:59', strtotime("-$i month")),
                'label'     => date('F Y', strtotime("-$i month")),
                'period'    => 'month'
            ];
        }
        $subtitle = 'Monthly cases from ' . $labels[0] . ' to ' . end($labels);

    } elseif ($range === 'last_30_days') {
        // ── Last 30 Days (Daily) ─────────────────────────────────────
        $title = 'Cases Over Last 30 Days';
        $thirtyDaysAgo = date('Y-m-d 00:00:00', strtotime('-29 days'));

        $stmt = $db->prepare("
            SELECT DATE_FORMAT(submitted_at, '%Y-%m-%d') as ymd, COUNT(*) as cnt 
            FROM cases 
            WHERE submitted_at >= ? 
            GROUP BY ymd
        ");
        $stmt->execute([$thirtyDaysAgo]);
        $counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        for ($i = 29; $i >= 0; $i--) {
            $ymd = date('Y-m-d', strtotime("-$i day"));
            $lbl = date('M j', strtotime("-$i day"));
            $labels[] = $lbl;
            $data[] = (int)($counts[$ymd] ?? 0);
            $pointsMeta[] = [
                'date_from' => "$ymd 00:00:00",
                'date_to'   => "$ymd 23:59:59",
                'label'     => date('l, M j, Y', strtotime("-$i day")),
                'period'    => 'day'
            ];
        }
        $subtitle = 'Daily cases from ' . $labels[0] . ' to ' . end($labels);

    } elseif ($range === 'last_8_weeks') {
        // ── Last 8 Weeks (Weekly) ────────────────────────────────────
        $title = 'Cases Over Last 8 Weeks';
        for ($i = 7; $i >= 0; $i--) {
            $weekStart = date('Y-m-d 00:00:00', strtotime("-" . (($i * 7) + 6) . " days"));
            $weekEnd   = date('Y-m-d 23:59:59', strtotime("-" . ($i * 7) . " days"));
            
            $stmt = $db->prepare("SELECT COUNT(*) FROM cases WHERE submitted_at BETWEEN ? AND ?");
            $stmt->execute([$weekStart, $weekEnd]);
            $cnt = (int)$stmt->fetchColumn();

            $lbl = date('M j', strtotime($weekStart)) . ' – ' . date('M j', strtotime($weekEnd));
            $labels[] = $lbl;
            $data[] = $cnt;
            $pointsMeta[] = [
                'date_from' => $weekStart,
                'date_to'   => $weekEnd,
                'label'     => "Week of " . date('M j, Y', strtotime($weekStart)) . " to " . date('M j, Y', strtotime($weekEnd)),
                'period'    => 'week'
            ];
        }
        $subtitle = 'Weekly cases from ' . $labels[0] . ' to ' . end($labels);

    } elseif ($range === 'last_7_days') {
        // ── Last 7 Days (Daily) ──────────────────────────────────────
        $title = 'Cases Over Last 7 Days';
        $sevenDaysAgo = date('Y-m-d 00:00:00', strtotime('-6 days'));

        $stmt = $db->prepare("
            SELECT DATE_FORMAT(submitted_at, '%Y-%m-%d') as ymd, COUNT(*) as cnt 
            FROM cases 
            WHERE submitted_at >= ? 
            GROUP BY ymd
        ");
        $stmt->execute([$sevenDaysAgo]);
        $counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        for ($i = 6; $i >= 0; $i--) {
            $ymd = date('Y-m-d', strtotime("-$i day"));
            $lbl = date('D, M j', strtotime("-$i day"));
            $labels[] = $lbl;
            $data[] = (int)($counts[$ymd] ?? 0);
            $pointsMeta[] = [
                'date_from' => "$ymd 00:00:00",
                'date_to'   => "$ymd 23:59:59",
                'label'     => date('l, M j, Y', strtotime("-$i day")),
                'period'    => 'day'
            ];
        }
        $subtitle = 'Daily cases from ' . $labels[0] . ' to ' . end($labels);

    } elseif ($range === 'specific_month') {
        // ── Specific Month ───────────────────────────────────────────
        if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
            $monthParam = date('Y-m');
        }

        $monthTimestamp = strtotime("$monthParam-01");
        $monthName = date('F Y', $monthTimestamp);
        $daysInMonth = (int)date('t', $monthTimestamp);

        if ($granularity === 'week') {
            // Group by Week inside the month (Days 1-7, 8-14, 15-21, 22-28, 29-end)
            $title = "Cases in $monthName (Weekly)";
            $weeks = [
                ['start' => 1, 'end' => 7, 'label' => 'Week 1 (' . date('M 1', $monthTimestamp) . '–7)'],
                ['start' => 8, 'end' => 14, 'label' => 'Week 2 (' . date('M 8', $monthTimestamp) . '–14)'],
                ['start' => 15, 'end' => 21, 'label' => 'Week 3 (' . date('M 15', $monthTimestamp) . '–21)'],
                ['start' => 22, 'end' => 28, 'label' => 'Week 4 (' . date('M 22', $monthTimestamp) . '–28)'],
            ];
            if ($daysInMonth > 28) {
                $weeks[] = ['start' => 29, 'end' => $daysInMonth, 'label' => 'Week 5 (' . date('M 29', $monthTimestamp) . '–' . $daysInMonth . ')'];
            }

            foreach ($weeks as $w) {
                $sDate = sprintf('%s-%02d 00:00:00', $monthParam, $w['start']);
                $eDate = sprintf('%s-%02d 23:59:59', $monthParam, $w['end']);

                $stmt = $db->prepare("SELECT COUNT(*) FROM cases WHERE submitted_at BETWEEN ? AND ?");
                $stmt->execute([$sDate, $eDate]);
                $cnt = (int)$stmt->fetchColumn();

                $labels[] = $w['label'];
                $data[] = $cnt;
                $pointsMeta[] = [
                    'date_from' => $sDate,
                    'date_to'   => $eDate,
                    'label'     => $w['label'] . ", $monthName",
                    'period'    => 'week'
                ];
            }
            $subtitle = "Weekly breakdown for $monthName";

        } else {
            // Group by Day (1 to $daysInMonth)
            $title = "Cases in $monthName (Daily)";
            
            $stmt = $db->prepare("
                SELECT DAY(submitted_at) as d, COUNT(*) as cnt 
                FROM cases 
                WHERE DATE_FORMAT(submitted_at, '%Y-%m') = ? 
                GROUP BY d
            ");
            $stmt->execute([$monthParam]);
            $dayCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            $shortMo = date('M', $monthTimestamp);
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $lbl = "$shortMo $d";
                $labels[] = $lbl;
                $data[] = (int)($dayCounts[$d] ?? 0);
                $ymd = sprintf('%s-%02d', $monthParam, $d);
                $pointsMeta[] = [
                    'date_from' => "$ymd 00:00:00",
                    'date_to'   => "$ymd 23:59:59",
                    'label'     => date('l, F j, Y', strtotime($ymd)),
                    'period'    => 'day'
                ];
            }
            $subtitle = "Daily distribution across all $daysInMonth days of $monthName";
        }
    }

    $totalCases = array_sum($data);
    $maxCases = !empty($data) ? max($data) : 0;

    jsonOut([
        'success'         => true,
        'title'           => $title,
        'subtitle'        => $subtitle,
        'range'           => $range,
        'granularity'     => $granularity,
        'selected_month'  => $monthParam,
        'labels'          => $labels,
        'data'            => $data,
        'points_meta'     => $pointsMeta,
        'total'           => $totalCases,
        'max'             => $maxCases,
        'available_months'=> $availableMonths,
    ]);

} catch (Exception $e) {
    jsonOut(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}
