<?php

declare(strict_types=1);

final class InstagramBridge
{
    private static function pythonBin(): string
    {
        $candidates = [
            dirname(BASE_PATH) . '/instagram-handler/venv/bin/python3',
            dirname(BASE_PATH) . '/instagram-handler/venv/bin/python',
            'python3',
            'python',
        ];
        foreach ($candidates as $bin) {
            if ($bin === 'python3' || $bin === 'python' || is_executable($bin)) {
                return $bin;
            }
        }
        return 'python3';
    }

    public static function call(string $action, array $params): array
    {
        $python = self::pythonBin();
        $script = BASE_PATH . '/bridge/ig_bridge.py';
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
