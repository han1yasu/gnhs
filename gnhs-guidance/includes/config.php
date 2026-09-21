<?php
// ── GNHS Guidance System — Config & DB ────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'gnhs_guidance');

function getDB(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        // Normalize em dash (–) to hyphen (-) in grade_section — run silently once
        try {
            $pdo->exec("UPDATE users SET grade_section = REPLACE(grade_section, ' \xe2\x80\x93 ', ' - ') WHERE grade_section LIKE '%\xe2\x80\x93%'");
            $pdo->exec("UPDATE referrals SET grade_section = REPLACE(grade_section, ' \xe2\x80\x93 ', ' - ') WHERE grade_section LIKE '%\xe2\x80\x93%'");
        } catch(Exception $e) {}
    }
    return $pdo;
}

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
}

function requireLogin(string $role = ''): array {
    startSession();
    if (empty($_SESSION['user'])) {
        header('Location: /gnhs-guidance/index.html'); exit;
    }
    $u = $_SESSION['user'];
    if ($role && $u['role'] !== $role && !($role === 'staff' && in_array($u['role'], ['admin','teacher']))) {
        header('Location: /gnhs-guidance/index.html'); exit;
    }

    // Always refresh avatar_photo and advisory_class from DB so changes persist across pages
    try {
        $db   = getDB();
        // Ensure columns exist silently
        try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_photo VARCHAR(255) DEFAULT NULL"); } catch(Exception $e){}
        try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS advisory_class VARCHAR(60) DEFAULT NULL"); } catch(Exception $e){}

        $fresh = $db->prepare("SELECT avatar_photo, advisory_class, avatar_initials FROM users WHERE id=? LIMIT 1");
        $fresh->execute([$u['id']]);
        $row = $fresh->fetch();
        if ($row) {
            $_SESSION['user']['avatar_photo']   = $row['avatar_photo']   ?? null;
            $_SESSION['user']['advisory_class'] = $row['advisory_class'] ?? null;
            $_SESSION['user']['avatar_initials']= $row['avatar_initials'] ?? $u['avatar_initials'] ?? '';
            $u = $_SESSION['user'];
        }
    } catch(Exception $e) {
        // Fail silently — don't break login over this
    }

    return $u;
}

function jsonOut(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function generateCaseNumber(): string {
    $db = getDB();
    // Use MAX of existing number part to avoid duplicates even after deletions
    $max = (int)$db->query("SELECT COALESCE(MAX(CAST(SUBSTRING(case_number, 3) AS UNSIGNED)), 0) FROM cases")->fetchColumn();
    $next = $max + 1;
    // Loop until we find a number not already in use (safety check)
    do {
        $candidate = 'C-' . str_pad($next, 4, '0', STR_PAD_LEFT);
        $exists = (int)$db->prepare("SELECT COUNT(*) FROM cases WHERE case_number=?")->execute([$candidate])
                  ? $db->query("SELECT COUNT(*) FROM cases WHERE case_number='$candidate'")->fetchColumn()
                  : 0;
        if ($exists) $next++;
    } while ($exists);
    return $candidate;
}

function generateRefNumber(): string {
    $db = getDB();
    // Use MAX of existing number part to avoid duplicates even after deletions
    $max = (int)$db->query("SELECT COALESCE(MAX(CAST(SUBSTRING(ref_number, 3) AS UNSIGNED)), 0) FROM referrals")->fetchColumn();
    $next = $max + 1;
    // Loop until we find a number not already in use (safety check)
    do {
        $candidate = 'R-' . str_pad($next, 4, '0', STR_PAD_LEFT);
        $exists = $db->query("SELECT COUNT(*) FROM referrals WHERE ref_number='$candidate'")->fetchColumn();
        if ($exists) $next++;
    } while ($exists);
    return $candidate;
}



