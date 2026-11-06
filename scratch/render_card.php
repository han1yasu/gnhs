<?php
$_SESSION = [
    'user' => [
        'id' => 13,
        'email' => 'jacob@gmail.com',
        'first_name' => 'Milena',
        'last_name' => 'Jacob',
        'role' => 'admin',
        'is_active' => 1,
        'is_setup_complete' => 1
    ],
    'last_activity' => time()
];
ob_start();
require_once __DIR__ . '/../pages/admin-users.php';
$html = ob_get_clean();

// Find the button for user Joaquin Gabriel Santos or id 11
preg_match('/<div class=\'user-card\' data-user-id=\'11\'.*?<\/div>\s*<\/div>/s', $html, $matches);
if (!empty($matches[0])) {
    echo "CARD FOR USER 11:\n" . $matches[0] . "\n\n";
} else {
    echo "USER 11 CARD NOT FOUND. Let's find Joaquin:\n";
    preg_match('/Joaquin.*?<\/div>\s*<\/div>/s', $html, $m2);
    echo substr($m2[0] ?? 'NONE', 0, 500) . "\n";
}
