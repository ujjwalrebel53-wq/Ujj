<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

if (!Installer::isInstalled()) {
    header('Location: /setup.php');
    exit;
}

appBoot();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uri = rtrim($uri, '/') ?: '/';

if (str_starts_with($uri, '/static/')) {
    $file = dirname(__DIR__) . '/public' . $uri;
    if (is_file($file)) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $types = ['css' => 'text/css', 'js' => 'application/javascript', 'png' => 'image/png', 'jpg' => 'image/jpeg'];
        header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
        readfile($file);
        exit;
    }
    http_response_code(404);
    exit;
}

if (str_starts_with($uri, '/api/')) {
    require_once dirname(__DIR__) . '/src/routes.php';
    routeApi($method, $uri);
    exit;
}

if ($uri === '/') {
    readfile(dirname(__DIR__) . '/views/index.html');
    exit;
}

http_response_code(404);
echo 'Not Found';
