<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('DATA_PATH', BASE_PATH . '/data');
define('SESSIONS_PATH', DATA_PATH . '/sessions');

require_once BASE_PATH . '/src/Database.php';
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
