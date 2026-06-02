<?php
declare(strict_types=1);

$configFile = dirname(__DIR__) . '/rebel_apk_config.json';
$apkDefaults = [
    'app_name'       => 'IRIS AI',
    'tagline'        => 'Integrated Responsive Intelligence System',
    'version'        => '1.3.0',
    'creator'        => 'Harsh Pandey',
    'apk_download'   => 'https://github.com/201Harsh/IRIS-AI/releases/download/v1.3.0/iris-ai-1.3.0-setup.exe',
    'play_store'     => '',
    'telegram'       => '',
    'instagram'      => 'https://www.instagram.com/irisx.ai/',
    'github'         => 'https://github.com/201Harsh',
    'support_email'  => '',
    'primary_color'  => '#10b981',
    'accent_color'   => '#06b6d4',
    'cli_command'    => 'npm install -g iris-ai',
    'mini_cli'       => 'npm install -g iris-mini',
];

function rebelLoadApkConfig(string $file, array $defaults): array
{
    if (!is_file($file)) {
        return $defaults;
    }
    $raw = json_decode((string) file_get_contents($file), true);
    return is_array($raw) ? array_merge($defaults, $raw) : $defaults;
}

$cfg = rebelLoadApkConfig($configFile, $apkDefaults);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = ($scriptDir === '/' || $scriptDir === '.') ? '' : rtrim($scriptDir, '/');

define('SITE_BASE', $scriptDir);
define('SITE_URL', $scheme . '://' . $host . $scriptDir);
define('ASSETS_URL', SITE_BASE . '/assets');

function siteUrl(string $path = ''): string
{
    $path = ltrim($path, '/');
    return SITE_URL . ($path !== '' ? '/' . $path : '');
}

function assetUrl(string $path): string
{
    return ASSETS_URL . '/' . ltrim($path, '/');
}

function esc(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$APP_NAME = (string) ($cfg['app_name'] ?? 'IRIS AI');
$PAGE_TITLE = $APP_NAME . ' — The Autonomous Neural OS Agent';
