<?php
// Local PHP server router. Do not serve workspace metadata or local database files.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (preg_match('~(^|/)\.[^/]+~', $path) || str_starts_with($path, '/database/') || str_starts_with($path, '/tools/') || in_array($path, ['/router.php'], true)) {
    http_response_code(404);
    exit;
}
return false;
