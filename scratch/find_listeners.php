<?php
$content = file_get_contents('app.js');
// Find lines outside functions that execute
$lines = explode("\n", $content);
foreach ($lines as $num => $line) {
    $trimmed = trim($line);
    if (strpos($trimmed, 'document.') !== false || strpos($trimmed, 'window.') !== false) {
        echo ($num+1) . ": " . $trimmed . "\n";
    }
}
