<?php
require_once __DIR__ . '/../includes/config.php';
startSession();
session_destroy();
$reason = isset($_GET['reason']) && $_GET['reason'] === 'timeout' ? '?reason=timeout' : '';
header('Location: /gnhs-guidance/index.html' . $reason);
exit;



