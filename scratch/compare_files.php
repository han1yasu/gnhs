<?php
$f1 = file_get_contents('pages/admin-users.php');
$f2 = file_get_contents('gnhs-guidance/pages/admin-users.php');
if ($f1 === $f2) {
    echo "admin-users.php files are IDENTICAL\n";
} else {
    echo "admin-users.php files DIFFER! Lengths: " . strlen($f1) . " vs " . strlen($f2) . "\n";
}
