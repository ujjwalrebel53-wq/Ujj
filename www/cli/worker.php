<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
appBoot();

$delay = max((int) ($argv[1] ?? 90), 60);
AccountCreator::runWorkerLoop($delay);
