<?php
header('Content-Type: text/plain; charset=utf-8');
echo "OK — RebelInsta files sahi jagah hain!\n\n";
echo "PHP: " . PHP_VERSION . "\n";
echo "Path: " . __DIR__ . "\n";
echo "setup.php exists: " . (file_exists(__DIR__ . '/setup.php') ? 'YES' : 'NO') . "\n";
echo "\nAb kholo: https://rebelinsta.alwaysdata.net/setup.php\n";
