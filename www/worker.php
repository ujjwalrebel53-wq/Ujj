<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
boot();
$delay = max((int) ($argv[1] ?? 90), 60);
Creator::runLoop($delay);
