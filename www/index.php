<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

if (!Installer::isInstalled()) {
    header('Location: /setup.php');
    exit;
}

appBoot();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uri = rtrim($uri, '/') ?: '/';

if (str_starts_with($uri, '/static/')) {
    $file = BASE_PATH . $uri;
    if (is_file($file)) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $types = ['css' => 'text/css', 'js' => 'application/javascript'];
        header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
        readfile($file);
        exit;
    }
    http_response_code(404);
    exit;
}

if ($uri === '/api/logs/stream') {
    appBoot();
    LiveLogger::stream();
    exit;
}

if (str_starts_with($uri, '/api/')) {
    require_once BASE_PATH . '/app/src/routes.php';
    routeApi($method, $uri);
    exit;
}

if ($uri === '/') {
    readfile(BASE_PATH . '/views/index.html');
    exit;
}

http_response_code(404);
echo 'Not Found';
