<?php
$lines = file('pages/admin-users.php');
foreach ($lines as $num => $line) {
    if ($num >= 107 && strpos($line, '$') !== false) {
        echo ($num + 1) . ': ' . trim($line) . "\n";
    }
}

