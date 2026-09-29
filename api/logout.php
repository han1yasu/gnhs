<?php
require_once __DIR__ . '/../includes/config.php';
startSession();
session_destroy();
header('Location: /index.html');
exit;



