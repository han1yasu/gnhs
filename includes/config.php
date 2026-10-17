<?php
// ── GNHS Guidance System — Config & DB ────────────────────────
date_default_timezone_set('Asia/Manila');
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'gnhs_guidance');

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
            $pdo->exec("UPDATE users SET grade_section = REPLACE(grade_section, ' – ', ' - ') WHERE grade_section LIKE '%–%'");
            $pdo->exec("UPDATE referrals SET grade_section = REPLACE(grade_section, ' – ', ' - ') WHERE grade_section LIKE '%–%'");
        } catch(Exception $e) {}

        // Ensure password_resets table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(100) NOT NULL,
            token VARCHAR(10) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX(email)
        )");
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS middle_name VARCHAR(50) DEFAULT NULL"); } catch(Exception $e){}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS suffix VARCHAR(10) DEFAULT NULL"); } catch(Exception $e){}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS age INT DEFAULT NULL"); } catch(Exception $e){}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_setup_complete TINYINT(1) DEFAULT 0"); } catch(Exception $e){}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(64) DEFAULT NULL"); } catch(Exception $e){}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_enabled TINYINT(1) DEFAULT 0"); } catch(Exception $e){}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_reset_requested TINYINT(1) DEFAULT 0"); } catch(Exception $e){}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS failed_attempts INT DEFAULT 0"); } catch(Exception $e){}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS lockout_until DATETIME DEFAULT NULL"); } catch(Exception $e){}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS security_question_1 VARCHAR(255) DEFAULT NULL"); } catch(Exception $e){}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS security_answer_1 VARCHAR(255) DEFAULT NULL"); } catch(Exception $e){}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS security_question_2 VARCHAR(255) DEFAULT NULL"); } catch(Exception $e){}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS security_answer_2 VARCHAR(255) DEFAULT NULL"); } catch(Exception $e){}
        try { $pdo->exec("ALTER TABLE cases ADD COLUMN IF NOT EXISTS is_against_adviser TINYINT(1) DEFAULT 0"); } catch(Exception $e){}
        try { $pdo->exec("ALTER TABLE cases ADD COLUMN IF NOT EXISTS reported_teacher_name VARCHAR(255) DEFAULT NULL"); } catch(Exception $e){}
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                email VARCHAR(150) NOT NULL,
                id_number VARCHAR(50) DEFAULT NULL,
                full_name VARCHAR(150) NOT NULL,
                role VARCHAR(30) DEFAULT 'student',
                grade_section VARCHAR(100) DEFAULT NULL,
                security_verified TINYINT(1) DEFAULT 1,
                status VARCHAR(30) DEFAULT 'pending',
                temp_password VARCHAR(255) DEFAULT NULL,
                admin_notes TEXT DEFAULT NULL,
                reviewed_by INT DEFAULT NULL,
                reviewed_at DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX(user_id),
                INDEX(email),
                INDEX(status)
            )");
        } catch(Exception $e){}
    }
    return $pdo;
}

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    // Check 15-minute inactivity timeout if a user is logged in
    checkSessionTimeout();
}

function checkSessionTimeout(): void {
    if (session_status() === PHP_SESSION_NONE) return;
    if (!empty($_SESSION['user'])) {
        $timeout = 900; // 15 minutes = 900 seconds
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
            unset($_SESSION['user']);
            session_unset();
            session_destroy();
            $isJson = (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
                   || (!empty($_SERVER['PHP_SELF']) && str_contains($_SERVER['PHP_SELF'], '/api/'));
            if ($isJson) {
                jsonOut(['success' => false, 'message' => 'Your session has expired due to 15 minutes of inactivity. Please log in again.', 'session_expired' => true], 401);
            } else {
                header('Location: /gnhs-guidance/index.html?reason=timeout');
                exit;
            }
        }
        $_SESSION['last_activity'] = time();
    }
}

function requireLogin(string $role = ''): array {
    startSession();
    checkSessionTimeout();
    if (empty($_SESSION['user'])) {
        header('Location: /gnhs-guidance/index.html'); exit;
    }
    $u = $_SESSION['user'];
    if ($role && $u['role'] !== $role && !($role === 'staff' && in_array($u['role'], ['admin','teacher']))) {
        header('Location: /gnhs-guidance/index.html'); exit;
    }

    if ($u['role'] === 'student' && empty($u['is_setup_complete']) && basename($_SERVER['PHP_SELF']) !== 'student-setup.php' && strpos($_SERVER['PHP_SELF'], '/api/') === false) {
        header('Location: student-setup.php'); exit;
    }
    if ($u['role'] === 'teacher' && empty($u['is_setup_complete']) && basename($_SERVER['PHP_SELF']) !== 'teacher-setup.php' && strpos($_SERVER['PHP_SELF'], '/api/') === false) {
        header('Location: teacher-setup.php'); exit;
    }

    // Always refresh avatar_photo and advisory_class from DB so changes persist across pages
    try {
        $db   = getDB();
        // Ensure columns exist silently
        try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_photo VARCHAR(255) DEFAULT NULL"); } catch(Exception $e){}
        try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS advisory_class VARCHAR(60) DEFAULT NULL"); } catch(Exception $e){}
        try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(64) DEFAULT NULL"); } catch(Exception $e){}
        try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_enabled TINYINT(1) DEFAULT 0"); } catch(Exception $e){}
        try { $db->exec("ALTER TABLE referrals ADD COLUMN IF NOT EXISTS ai_summary TEXT DEFAULT NULL"); } catch(Exception $e){}
        try { $db->exec("ALTER TABLE cases ADD COLUMN IF NOT EXISTS is_follow_up TINYINT(1) DEFAULT 0"); } catch(Exception $e){}
        try { $db->exec("ALTER TABLE cases ADD COLUMN IF NOT EXISTS follow_up_msg TEXT DEFAULT NULL"); } catch(Exception $e){}
        try { $db->exec("ALTER TABLE cases ADD COLUMN IF NOT EXISTS follow_up_date DATETIME DEFAULT NULL"); } catch(Exception $e){}
        try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS middle_name VARCHAR(50) DEFAULT NULL"); } catch(Exception $e){}
        try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS suffix VARCHAR(10) DEFAULT NULL"); } catch(Exception $e){}
        try { $db->exec("ALTER TABLE counseling_sessions ADD COLUMN IF NOT EXISTS session_type VARCHAR(50) DEFAULT 'in-person'"); } catch(Exception $e){}
        try { $db->exec("ALTER TABLE counseling_sessions ADD COLUMN IF NOT EXISTS location VARCHAR(100) DEFAULT 'Guidance Office'"); } catch(Exception $e){}
        try { $db->exec("ALTER TABLE cases ADD COLUMN IF NOT EXISTS resolution TEXT DEFAULT NULL"); } catch(Exception $e){}
        try { $db->exec("ALTER TABLE cases ADD COLUMN IF NOT EXISTS resolution_process VARCHAR(255) DEFAULT NULL"); } catch(Exception $e){}
        try { $db->exec("ALTER TABLE cases ADD COLUMN IF NOT EXISTS sanctions TEXT DEFAULT NULL"); } catch(Exception $e){}
        try { $db->exec("ALTER TABLE cases ADD COLUMN IF NOT EXISTS resolved_at DATETIME DEFAULT NULL"); } catch(Exception $e){}
        try { $db->exec("ALTER TABLE counseling_sessions ADD COLUMN IF NOT EXISTS notes TEXT DEFAULT NULL"); } catch(Exception $e){}
        try { $db->exec("ALTER TABLE counseling_sessions ADD COLUMN IF NOT EXISTS started_at DATETIME DEFAULT NULL"); } catch(Exception $e){}
        try { $db->exec("ALTER TABLE counseling_sessions ADD COLUMN IF NOT EXISTS ended_at DATETIME DEFAULT NULL"); } catch(Exception $e){}

        // Ensure referral_messages table exists
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS referral_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                referral_id INT NOT NULL,
                user_id INT NOT NULL,
                message TEXT NOT NULL,
                is_read TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX(referral_id),
                INDEX(user_id)
            )");
            $db->exec("ALTER TABLE referral_messages ADD COLUMN IF NOT EXISTS is_read TINYINT(1) DEFAULT 0");
        } catch(Exception $e) {}

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

function containsProfanity(string $text): bool {
    // List of common profanities and bad words in English and Tagalog
    $badWords = [
        'fuck', 'shit', 'bitch', 'asshole', 'cunt', 'dick', 'pussy', 'whore',
        'slut', 'bastard', 'motherfucker', 'fag', 'nigger', 'nigga', 'bullshit',
        'putangina', 'gago', 'tarantado', 'bobo', 'tangina', 'puta', 'ulol', 'pota', 'gaga',
        'inamo', 'bobo', 'tanga', 'inutil'
    ];
    $text = strtolower($text);
    foreach ($badWords as $word) {
        if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $text)) {
            return true;
        }
    }
    return false;
}

/**
 * Validates text entries for concerns and referrals.
 * Enforces:
 * 1. Minimum character length (default 30 characters)
 * 2. Maximum word limit ceiling (default 400 words)
 * 3. Detection and rejection of spam, random letters, gibberish, repeated characters, and meaningless text
 * 4. Allows valid short reports in English and Filipino
 */
function validateContentQuality(string $text, int $minChars = 30, int $maxWords = 400): array {
    $clean = trim($text);
    $charCount = mb_strlen($clean);

    if ($charCount < $minChars) {
        return [
            'valid'   => false,
            'message' => "Description must be at least {$minChars} characters long to provide sufficient detail (currently {$charCount} characters)."
        ];
    }

    $words = preg_split('/\s+/u', $clean, -1, PREG_SPLIT_NO_EMPTY);
    $wordCount = count($words);
    if ($wordCount > $maxWords) {
        return [
            'valid'   => false,
            'message' => "Description must not exceed {$maxWords} words (currently {$wordCount} words)."
        ];
    }

    // 1. Single character repeated 5 or more times consecutively (e.g. 'aaaaa', 'zzzzz', '.....')
    if (preg_match('/(.)\1{4,}/u', $clean)) {
        return [
            'valid'   => false,
            'message' => 'Entry contains repeated characters or symbols. Please provide a clear and meaningful description.'
        ];
    }

    // 2. Repeating pattern / keyboard spam (e.g. 'asdfasdfasdf', '123123123', 'hahahahahaha')
    if (preg_match('/(.{2,8})\1{3,}/ui', $clean)) {
        return [
            'valid'   => false,
            'message' => 'Entry contains repeated patterns or spam text. Please describe your concern in natural words.'
        ];
    }

    // 3. Vowel-to-consonant ratio check for sufficiently long letter sequences
    $lettersOnly = preg_replace('/[^a-zA-Z]/', '', $clean);
    $letterLen = strlen($lettersOnly);
    if ($letterLen >= 20) {
        $vowelsCount = preg_match_all('/[aeiouAEIOU]/', $lettersOnly);
        $vowelRatio = $vowelsCount / $letterLen;
        if ($vowelRatio < 0.15) {
            return [
                'valid'   => false,
                'message' => 'Entry contains mostly random consonants or meaningless characters. Please write meaningful sentences.'
            ];
        }
        if ($vowelRatio > 0.85) {
            return [
                'valid'   => false,
                'message' => 'Entry contains abnormal character patterns. Please describe your concern clearly.'
            ];
        }
    }

    // 4. Repeated word ratio (e.g. 'help help help help help help help help')
    if ($wordCount >= 6) {
        $lowerWords = array_map('mb_strtolower', $words);
        $uniqueWords = array_unique($lowerWords);
        if (count($uniqueWords) / $wordCount < 0.35) {
            return [
                'valid'   => false,
                'message' => 'Entry contains excessively repeated words. Please provide a complete and meaningful report.'
            ];
        }
    }

    // 5. Individual word gibberish and keyboard mash inspection
    $gibberishWords = 0;
    $checkedWords = 0;
    $keyboardPatterns = [
        'asdfgh', 'sdfghj', 'dfghjk', 'fghjkl',
        'qwerty', 'wertyu', 'ertyui', 'rtyuio', 'tyuiop',
        'zxcvbn', 'xcvbnm', 'lkjhgf', 'kjhgfd', 'jhgfdsa'
    ];

    foreach ($words as $w) {
        $wClean = preg_replace('/[^a-zA-Z]/', '', $w);
        if (strlen($wClean) >= 4) {
            $checkedWords++;
            $wLower = strtolower($wClean);
            $isWordGibberish = false;

            // Check keyboard mash sequence
            foreach ($keyboardPatterns as $kp) {
                if (str_contains($wLower, $kp)) {
                    $isWordGibberish = true;
                    break;
                }
            }

            // Consonant cluster of 5 or more consonants in a row (e.g. 'sdfgh', 'kjsdhf')
            if (preg_match('/[bcdfghjklmnpqrstvwxyz]{5,}/i', $wLower)) {
                $isWordGibberish = true;
            }

            // Word of 5 or more letters with NO vowels whatsoever
            if (strlen($wLower) >= 5 && !preg_match('/[aeiouy]/i', $wLower)) {
                $isWordGibberish = true;
            }

            if ($isWordGibberish) {
                $gibberishWords++;
            }
        }
    }

    if ($checkedWords >= 2 && ($gibberishWords / $checkedWords) >= 0.40) {
        return [
            'valid'   => false,
            'message' => 'Entry contains random letters, gibberish words, or meaningless keyboard mash. Please submit a valid description.'
        ];
    }
    if ($checkedWords === 1 && $gibberishWords === 1) {
        return [
            'valid'   => false,
            'message' => 'Entry contains random letters, gibberish words, or meaningless text. Please submit a valid description.'
        ];
    }

    return ['valid' => true, 'message' => ''];
}
