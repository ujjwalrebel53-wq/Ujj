<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir) || !is_writable($dataDir)) {
    http_response_code(503);
    echo 'data/ not writable';
    exit;
}

echo 'OK';
