<?php

declare(strict_types=1);

final class Crypto
{
    private static function key(): string
    {
        return getenv('ENCRYPTION_KEY') ?: getenv('SECRET_KEY') ?: 'instagram-handler-php-default-key';
    }

    public static function encrypt(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $key = hash('sha256', self::key(), true);
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }

    public static function decrypt(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $raw = base64_decode($value, true);
        if ($raw === false || strlen($raw) < 17) {
            return '';
        }
        $iv = substr($raw, 0, 16);
        $data = substr($raw, 16);
        $key = hash('sha256', self::key(), true);
        $decrypted = openssl_decrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $decrypted === false ? '' : $decrypted;
    }
}
