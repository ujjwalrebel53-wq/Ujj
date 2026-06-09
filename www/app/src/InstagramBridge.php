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
            $home ? $home . '/ig-handler/instagram-handler/venv/bin/python' : null,
            '/usr/bin/python',
            'python',
        ]);
        foreach ($candidates as $bin) {
            if ($bin === 'python' || is_executable($bin)) {
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

        LiveLogger::debug("Bridge call: $action", ['python' => $python]);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $cmd = escapeshellarg($python) . ' ' . escapeshellarg($script);
        $proc = proc_open($cmd, $descriptors, $pipes, BASE_PATH);

        if (!is_resource($proc)) {
            LiveLogger::error('Python bridge start fail');
            throw new RuntimeException('Failed to start Instagram bridge');
        }

        fwrite($pipes[0], $payload);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);

        if ($stderr) {
            foreach (explode("\n", trim($stderr)) as $line) {
                if ($line !== '') {
                    LiveLogger::debug('Bridge stderr: ' . $line);
                }
            }
        }

        $result = json_decode($stdout ?: '{}', true);
        if (!is_array($result)) {
            LiveLogger::error('Bridge invalid response', ['stdout' => substr($stdout ?: '', 0, 500), 'exit' => $exit]);
            throw new RuntimeException('Bridge invalid response: ' . ($stderr ?: $stdout));
        }

        if (!empty($result['success'])) {
            LiveLogger::success("Bridge OK: $action");
        } else {
            LiveLogger::error("Bridge fail: $action — " . ($result['error'] ?? 'unknown'), $result);
        }

        return $result;
    }
}
