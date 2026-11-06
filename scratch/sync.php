<?php
$pairs = [
    'src/PureOTP.php' => 'gnhs-guidance/src/PureOTP.php',
    'api/verify_totp.php' => 'gnhs-guidance/api/verify_totp.php',
    'pages/totp_verify.php' => 'gnhs-guidance/pages/totp_verify.php',
    'pages/admin-users.php' => 'gnhs-guidance/pages/admin-users.php',
    'includes/layout.php' => 'gnhs-guidance/includes/layout.php',
    'style.css' => 'gnhs-guidance/style.css',
    'api/register.php' => 'gnhs-guidance/api/register.php',
    'includes/config.php' => 'gnhs-guidance/includes/config.php',
    'app.js' => 'gnhs-guidance/app.js',
    'pages/admin-cases.php' => 'gnhs-guidance/pages/admin-cases.php',
    'pages/admin-followups.php' => 'gnhs-guidance/pages/admin-followups.php',
    'api/submit_appeal.php' => 'gnhs-guidance/api/submit_appeal.php',
    'pages/admin-reset-requests.php' => 'gnhs-guidance/pages/admin-reset-requests.php',
    'pages/admin-2fa-requests.php' => 'gnhs-guidance/pages/admin-2fa-requests.php',
    'api/admin_process_reset_request.php' => 'gnhs-guidance/api/admin_process_reset_request.php',
    'api/admin_reset_password.php' => 'gnhs-guidance/api/admin_reset_password.php',
    'api/admin_reset_totp.php' => 'gnhs-guidance/api/admin_reset_totp.php',
    'api/assign_advisory.php' => 'gnhs-guidance/api/assign_advisory.php',
    'api/update_user.php' => 'gnhs-guidance/api/update_user.php',
    'api/login.php' => 'gnhs-guidance/api/login.php',
    'setup.php' => 'gnhs-guidance/setup.php',
    'pages/admin-referrals.php' => 'gnhs-guidance/pages/admin-referrals.php',
    'index.html' => 'gnhs-guidance/index.html',
    'tour.js' => 'gnhs-guidance/tour.js',
];
foreach ($pairs as $src => $dst) {
    if (file_exists($src)) {
        $dir = dirname($dst);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        copy($src, $dst);
        echo "Copied $src -> $dst\n";
    }
}
