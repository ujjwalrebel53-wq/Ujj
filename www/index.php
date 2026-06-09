<?php
declare(strict_types=1);

$uri = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';

if ($uri === '/ping.html' || $uri === '/ping.php') {
    require __DIR__ . ($uri === '/ping.html' ? '/ping.html' : '/ping.php');
    exit;
}

if (str_starts_with($uri, '/a.')) {
    $f = __DIR__ . $uri;
    if (is_file($f)) {
        header('Content-Type: ' . (str_ends_with($uri, '.css') ? 'text/css' : 'application/javascript'));
        readfile($f);
        exit;
    }
}

require __DIR__ . '/lib.php';

if ($uri === '/setup.php' || basename($uri) === 'setup.php') {
    require __DIR__ . '/setup.php';
    exit;
}

if (!installed()) {
    header('Location: /setup.php');
    exit;
}

if ($uri === '/' || $uri === '/ui.html') {
    readfile(__DIR__ . '/ui.html');
    exit;
}

if (str_starts_with($uri, '/api/')) {
    require __DIR__ . '/api.php';
    exit;
}

http_response_code(404);
echo 'Not found';
