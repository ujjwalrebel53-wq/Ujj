<?php

declare(strict_types=1);

final class LiveLogger
{
    private static function logDir(): string
    {
        $dir = DATA_PATH . '/logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    public static function logFile(): string
    {
        return self::logDir() . '/live.log';
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function success(string $message, array $context = []): void
    {
        self::write('SUCCESS', $message, $context);
    }

    public static function warn(string $message, array $context = []): void
    {
        self::write('WARN', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::write('DEBUG', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        $entry = [
            'id' => uniqid('log_', true),
            'time' => gmdate('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
        file_put_contents(
            self::logFile(),
            json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }

    public static function tail(int $limit = 200, int $offset = 0): array
    {
        $file = self::logFile();
        if (!is_file($file)) {
            return [];
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $entries = [];
        foreach ($lines as $line) {
            $row = json_decode($line, true);
            if (is_array($row)) {
                $entries[] = $row;
            }
        }
        if ($offset > 0) {
            $entries = array_values(array_filter($entries, fn($e) => ($e['id'] ?? '') > $offset));
        }
        return array_slice($entries, -$limit);
    }

    public static function clear(): void
    {
        file_put_contents(self::logFile(), '');
        self::info('Logs cleared');
    }

    public static function stream(): void
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        $file = self::logFile();
        $pos = is_file($file) ? filesize($file) : 0;
        $start = time();

        while (time() - $start < 90) {
            if (connection_aborted()) {
                break;
            }
            clearstatcache(true, $file);
            if (is_file($file)) {
                $size = filesize($file);
                if ($size > $pos) {
                    $fp = fopen($file, 'rb');
                    fseek($fp, $pos);
                    while (($line = fgets($fp)) !== false) {
                        $line = trim($line);
                        if ($line !== '') {
                            echo 'data: ' . $line . "\n\n";
                        }
                    }
                    $pos = ftell($fp);
                    fclose($fp);
                    @ob_flush();
                    flush();
                }
            }
            echo ": ping\n\n";
            @ob_flush();
            flush();
            sleep(1);
        }
    }
}
