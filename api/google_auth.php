<?php
require_once __DIR__ . '/../includes/config.php';
startSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$raw = json_decode(file_get_contents('php://input'), true);
$idToken = trim($raw['credential'] ?? '');

if (!$idToken) {
    jsonOut(['success' => false, 'message' => 'Google credential token is required.']);
}

// Verify Google ID Token via Google's tokeninfo endpoint
$verifyUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);

$context = stream_context_create([
    'http' => [
        'method'  => 'GET',
        'timeout' => 8,
        'ignore_errors' => true
    ]
]);

$response = @file_get_contents($verifyUrl, false, $context);

if (!$response) {
    jsonOut(['success' => false, 'message' => 'Unable to verify Google credential with Google servers. Please try again or use email code.']);
}

$tokenData = json_decode($response, true);

if (!isset($tokenData['email']) || empty($tokenData['email'])) {
    $err = $tokenData['error_description'] ?? 'Invalid Google token.';
    jsonOut(['success' => false, 'message' => 'Google verification failed: ' . $err]);
}

$email = strtolower(trim($tokenData['email']));
$emailVerified = !empty($tokenData['email_verified']) && ($tokenData['email_verified'] === true || $tokenData['email_verified'] === 'true');

if (!$emailVerified) {
    jsonOut(['success' => false, 'message' => 'Your Google email is not verified by Google.']);
}

$fullName   = trim($tokenData['name'] ?? '');
$givenName  = trim($tokenData['given_name'] ?? '');
$familyName = trim($tokenData['family_name'] ?? '');
$picture    = trim($tokenData['picture'] ?? '');

if (!$givenName && $fullName) {
    $parts = explode(' ', $fullName);
    $givenName = $parts[0];
    $familyName = count($parts) > 1 ? end($parts) : '';
}

$detectedRole = str_ends_with($email, 'deped.gov.ph') ? 'teacher' : 'student';

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(email) = ? AND (is_active = 1 OR is_active IS NULL) LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // User already registered in GNHS: Initialize 2FA and route directly to 2FA page
        $secret = $user['totp_secret'] ?? '';
        if (empty($secret)) {
            require_once __DIR__ . '/../src/PureOTP.php';
            $secret = PureOTP::generateSecret();
            $db->prepare("UPDATE users SET totp_secret = ? WHERE id = ?")->execute([$secret, $user['id']]);
        }

        $_SESSION['pending_user'] = [
            'id'              => $user['id'],
            'email'           => $user['email'],
            'first_name'      => $user['first_name'],
            'last_name'       => $user['last_name'],
            'id_number'       => $user['id_number'],
            'role'            => $user['role'],
            'grade_section'   => $user['grade_section'],
            'department'      => $user['department'] ?? null,
            'avatar_initials' => $user['avatar_initials'] ?? strtoupper($user['first_name'][0].($user['last_name'][0]??'')),
            'avatar_photo'    => $user['avatar_photo'] ?? ($picture ?: null),
            'advisory_class'  => $user['advisory_class'] ?? null,
            'is_setup_complete' => $user['is_setup_complete'] ?? 0,
            'age'             => $user['age'] ?? null,
        ];

        jsonOut([
            'success'     => true,
            'is_new'      => false,
            'require_2fa' => true,
            'redirect'    => '/gnhs-guidance/pages/totp_verify.php',
            'user'        => [
                'email'    => $user['email'],
                'name'     => trim($user['first_name'] . ' ' . $user['last_name']),
                'role'     => $user['role'],
                'initials' => $user['avatar_initials'] ?? strtoupper($user['first_name'][0].($user['last_name'][0]??''))
            ]
        ]);
    } else {
        // First-time user: store verified Google profile in session so they can finish registration with LRN
        $_SESSION['verified_email_signup'] = [
            'email'      => $email,
            'first_name' => $givenName,
            'last_name'  => $familyName,
            'role'       => $detectedRole,
            'picture'    => $picture,
            'verified_at'=> time()
        ];

        jsonOut([
            'success'    => true,
            'is_new'     => true,
            'email'      => $email,
            'first_name' => $givenName,
            'last_name'  => $familyName,
            'role'       => $detectedRole,
            'message'    => 'Google account verified! Please enter your LRN and grade to complete account setup.'
        ]);
    }

} catch (Exception $e) {
    jsonOut(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
