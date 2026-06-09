<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('DATA_PATH', BASE_PATH . '/data');
define('SESSIONS_PATH', DATA_PATH . '/sessions');

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

require_once BASE_PATH . '/app/src/Installer.php';

date_default_timezone_set('UTC');

if (!is_dir(DATA_PATH)) {
    @mkdir(DATA_PATH, 0755, true);
}
if (!is_dir(SESSIONS_PATH)) {
    @mkdir(SESSIONS_PATH, 0755, true);
}

function appBoot(): void
{
    $src = BASE_PATH . '/app/src';
    require_once $src . '/Database.php';
    require_once $src . '/ProxyManager.php';
    require_once $src . '/Crypto.php';
    require_once $src . '/TempEmail.php';
    require_once $src . '/InstagramBridge.php';
    require_once $src . '/AccountManager.php';
    require_once $src . '/AccountCreator.php';
    require_once $src . '/Response.php';
    Database::init();
}
