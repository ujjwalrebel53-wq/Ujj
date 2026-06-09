<?php

declare(strict_types=1);

final class Installer
{
    public static function lockFile(): string
    {
        return DATA_PATH . '/install.lock';
    }

    public static function isInstalled(): bool
    {
        return is_file(self::lockFile()) && is_file(BASE_PATH . '/.env');
    }

    public static function status(): array
    {
        $checks = [
            'php_version' => version_compare(PHP_VERSION, '8.1.0', '>='),
            'pdo_sqlite' => extension_loaded('pdo_sqlite'),
            'curl' => extension_loaded('curl'),
            'openssl' => extension_loaded('openssl'),
            'data_writable' => is_writable(DATA_PATH) || @mkdir(DATA_PATH, 0755, true),
            'proc_open' => function_exists('proc_open'),
            'python' => self::findPython() !== '',
            'installed' => self::isInstalled(),
        ];
        $checks['ready'] = $checks['php_version'] && $checks['pdo_sqlite'] && $checks['curl'] && $checks['data_writable'];
        return $checks;
    }

    public static function findPython(): string
    {
        $candidates = ['python', '/usr/bin/python', '/usr/local/bin/python'];
        $home = getenv('HOME') ?: '';
        if ($home) {
            array_unshift($candidates, $home . '/.local/bin/python');
        }
        foreach ($candidates as $bin) {
            if ($bin === 'python') {
                $out = shell_exec('which python 2>/dev/null');
                if ($out && trim($out)) {
                    return trim($out);
                }
            } elseif (is_executable($bin)) {
                return $bin;
            }
        }
        return '';
    }

    public static function runInstall(): array
    {
        if (self::isInstalled()) {
            return ['success' => true, 'message' => 'Already installed'];
        }

        $status = self::status();
        if (!$status['ready']) {
            throw new RuntimeException('Server requirements not met. Check PHP extensions and data folder permissions.');
        }

        $secret = bin2hex(random_bytes(32));
        $encryption = bin2hex(random_bytes(32));

        $defaults = file_get_contents(BASE_PATH . '/config/defaults.env');
        $webshare = '';
        if (preg_match('/^WEBSHARE_PROXY_URL=(.+)$/m', $defaults, $m)) {
            $webshare = trim($m[1]);
        }

        $pythonBin = BASE_PATH . '/python-bridge/venv/bin/python';
        $python = self::findPython();

        $logs = [];

        if ($python && is_dir(BASE_PATH . '/python-bridge')) {
            $venvDir = BASE_PATH . '/python-bridge/venv';
            if (!is_dir($venvDir)) {
                $logs[] = self::execCmd("$python -m venv " . escapeshellarg($venvDir));
            }
            if (is_file($venvDir . '/bin/python')) {
                $pythonBin = $venvDir . '/bin/python';
                $pip = escapeshellarg($pythonBin) . ' -m pip install -r ' . escapeshellarg(BASE_PATH . '/python-bridge/requirements.txt') . ' 2>&1';
                $logs[] = self::execCmd($pip);
            }
        }

        $envContent = implode("\n", [
            'SECRET_KEY=' . $secret,
            'ENCRYPTION_KEY=' . $encryption,
            'WEBSHARE_PROXY_URL=' . $webshare,
            'PYTHON_BRIDGE_BIN=' . $pythonBin,
            'SITE_URL=https://rebelinsta.alwaysdata.net',
            '',
        ]);

        if (file_put_contents(BASE_PATH . '/.env', $envContent) === false) {
            throw new RuntimeException('Could not write .env file');
        }

        foreach ([DATA_PATH, SESSIONS_PATH, DATA_PATH . '/uploads'] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        putenv('SECRET_KEY=' . $secret);
        putenv('ENCRYPTION_KEY=' . $encryption);
        putenv('WEBSHARE_PROXY_URL=' . $webshare);
        putenv('PYTHON_BRIDGE_BIN=' . $pythonBin);

        require_once BASE_PATH . '/src/Database.php';
        Database::init();

        file_put_contents(self::lockFile(), date('c'));

        return [
            'success' => true,
            'message' => 'Installation complete!',
            'python' => $pythonBin,
            'logs' => implode("\n", array_filter($logs)),
        ];
    }

    private static function execCmd(string $cmd): string
    {
        $out = shell_exec($cmd);
        return $out ?: '';
    }
}
