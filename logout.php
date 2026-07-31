<?php
require_once __DIR__ . '/../includes/config.php';
startSession();
session_destroy();
header('Location: /gnhs-guidance/index.html');
exit;
