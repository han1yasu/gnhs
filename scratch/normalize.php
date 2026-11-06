<?php
$files = [
    'includes/config.php',
    'api/register.php',
    'app.js',
    'pages/admin-users.php',
    'style.css',
    'gnhs-guidance/includes/config.php',
    'gnhs-guidance/api/register.php',
    'gnhs-guidance/app.js',
    'gnhs-guidance/pages/admin-users.php',
    'gnhs-guidance/style.css'
];
foreach ($files as $f) {
    if (file_exists($f)) {
        file_put_contents($f, str_replace("\r\n", "\n", file_get_contents($f)));
    }
}
echo "Normalized " . count($files) . " files.\n";

