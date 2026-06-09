<?php

declare(strict_types=1);

final class InstagramBridge
{
    private static function pythonBin(): string
    {
        $fromEnv = trim(getenv('PYTHON_BRIDGE_BIN') ?: '');
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        $home = getenv('HOME') ?: '';
        $candidates = array_filter([
            BASE_PATH . '/python-bridge/venv/bin/python',
            getenv('PYTHON_BRIDGE_BIN') ?: null,
            $home ? $home . '/ig-handler/instagram-handler/venv/bin/python' : null,
            '/usr/bin/python',
            'python',
        ]);

        foreach ($candidates as $bin) {
            if ($bin === 'python' || $bin === 'python3' || is_executable($bin)) {
                return $bin;
            }
        }
        return 'python';
    }

    public static function call(string $action, array $params): array
    {
        $python = self::pythonBin();
        $script = BASE_PATH . '/python-bridge/ig_bridge.py';
        $payload = json_encode(['action' => $action, 'params' => $params], JSON_UNESCAPED_UNICODE);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $cmd = escapeshellarg($python) . ' ' . escapeshellarg($script);
        $proc = proc_open($cmd, $descriptors, $pipes, BASE_PATH);

        if (!is_resource($proc)) {
            throw new RuntimeException('Failed to start Instagram bridge');
        }

        fwrite($pipes[0], $payload);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        $result = json_decode($stdout ?: '{}', true);
        if (!is_array($result)) {
            throw new RuntimeException('Bridge invalid response: ' . ($stderr ?: $stdout));
        }
        return $result;
    }
}
