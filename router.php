<?php
// Router para PHP built-in server: bloquea acceso HTTP a archivos privados.
// Uso: php -S 0.0.0.0:8080 router.php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = ltrim($uri, '/');

$blocked = [
    '#^materiales(/|$)#i',
    '#^question_cache\.json$#i',
    '#^config\.php$#i',
    '#(^|/)\.env$#i',
    '#(^|/)\.htaccess$#i',
    '#(^|/)\.idea(/|$)#i',
    '#(^|/)\.git(/|$)#i',
    '#\.(py|sql|md|yaml|yml|original)$#i',
];
foreach ($blocked as $rx) {
    if (preg_match($rx, $path)) {
        http_response_code(403);
        header('Content-Type: text/plain');
        echo '403 Forbidden';
        return true;
    }
}

if ($path === '' || $path === 'index.php' || $path === 'profebot.php' || $path === 'profebot.html') {
    include __DIR__.'/profebot.php';
    return true;
}

$full = __DIR__.'/'.$path;
if (is_file($full)) {
    return false;
}

include __DIR__.'/profebot.php';
return true;
