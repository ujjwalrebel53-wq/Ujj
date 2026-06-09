<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('DATA_PATH', BASE_PATH . '/data');
define('SESSIONS_PATH', DATA_PATH . '/sessions');

// Load .env if present
$envFile = BASE_PATH . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value, " \t\"'"));
    }
}

require_once BASE_PATH . '/src/Database.php';
require_once BASE_PATH . '/src/ProxyManager.php';
require_once BASE_PATH . '/src/Crypto.php';
require_once BASE_PATH . '/src/TempEmail.php';
require_once BASE_PATH . '/src/InstagramBridge.php';
require_once BASE_PATH . '/src/AccountManager.php';
require_once BASE_PATH . '/src/AccountCreator.php';
require_once BASE_PATH . '/src/Response.php';

date_default_timezone_set('UTC');

if (!is_dir(DATA_PATH)) {
    mkdir(DATA_PATH, 0755, true);
}
if (!is_dir(SESSIONS_PATH)) {
    mkdir(SESSIONS_PATH, 0755, true);
}

Database::init();
