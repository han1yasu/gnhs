<?php
$ctx = stream_context_create(['http' => ['timeout' => 3]]);
$data = @file_get_contents('http://127.0.0.1/gnhs-guidance/app.js?v=3.0', false, $ctx);
if ($data !== false) {
    echo "SUCCESS: Read " . strlen($data) . " bytes\n";
} else {
    echo "FAILED to fetch\n";
}
