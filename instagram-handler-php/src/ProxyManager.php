<?php

declare(strict_types=1);

final class ProxyManager
{
    private const CACHE_TTL = 300;
    private static int $index = 0;

    public static function getProxyUrl(): string
    {
        return trim(getenv('WEBSHARE_PROXY_URL') ?: '');
    }

    public static function cacheFile(): string
    {
        return DATA_PATH . '/proxy_cache.json';
    }

    public static function parseLine(string $line): string
    {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            return '';
        }
        $parts = explode(':', $line);
        if (count($parts) < 4) {
            return '';
        }
        $host = $parts[0];
        $port = $parts[1];
        $user = $parts[2];
        $password = implode(':', array_slice($parts, 3));
        return "http://{$user}:{$password}@{$host}:{$port}";
    }

    public static function fetchProxies(bool $force = false): array
    {
        $url = self::getProxyUrl();
        if ($url === '') {
            return [];
        }

        $cacheFile = self::cacheFile();
        if (!$force && is_file($cacheFile)) {
            $cache = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cache) && (time() - ($cache['fetched_at'] ?? 0)) < self::CACHE_TTL) {
                return $cache['proxies'] ?? [];
            }
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['User-Agent: IG-Handler-PHP/1.0'],
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            throw new RuntimeException('Webshare fetch failed: ' . curl_error($ch));
        }
        curl_close($ch);

        $proxies = [];
        foreach (explode("\n", $body) as $line) {
            $parsed = self::parseLine($line);
            if ($parsed !== '') {
                $proxies[] = $parsed;
            }
        }

        if (!is_dir(DATA_PATH)) {
            mkdir(DATA_PATH, 0755, true);
        }
        file_put_contents($cacheFile, json_encode([
            'fetched_at' => time(),
            'proxies' => $proxies,
        ]));

        self::$index = 0;
        return $proxies;
    }

    public static function getNextProxy(): string
    {
        $proxies = self::fetchProxies();
        if (!$proxies) {
            return '';
        }
        $proxy = $proxies[self::$index % count($proxies)];
        self::$index++;
        return $proxy;
    }

    public static function getRandomProxy(): string
    {
        $proxies = self::fetchProxies();
        if (!$proxies) {
            return '';
        }
        return $proxies[array_rand($proxies)];
    }

    public static function resolveProxy(string $explicit = '', bool $auto = true): string
    {
        $explicit = trim($explicit);
        if ($explicit !== '') {
            return $explicit;
        }
        if ($auto && self::getProxyUrl() !== '') {
            return self::getNextProxy();
        }
        return '';
    }

    public static function getStats(): array
    {
        $proxies = self::fetchProxies();
        return [
            'enabled' => self::getProxyUrl() !== '',
            'total_proxies' => count($proxies),
            'cache_ttl_seconds' => self::CACHE_TTL,
            'provider' => 'webshare',
        ];
    }
}
