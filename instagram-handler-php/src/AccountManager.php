<?php

declare(strict_types=1);

final class AccountManager
{
    private static function sessionPath(int $accountId): string
    {
        return SESSIONS_PATH . "/account_{$accountId}.json";
    }

    public static function login(int $accountId, ?string $password = null, ?string $verificationCode = null): array
    {
        $secrets = Database::getAccountSecrets($accountId);
        if (!$secrets) {
            throw new RuntimeException('Account not found');
        }

        $pwd = $password ?: Crypto::decrypt($secrets['password_enc'] ?? '');
        if ($pwd === '' && empty($secrets['session_data'])) {
            throw new RuntimeException('Password required');
        }

        $result = InstagramBridge::call('login', [
            'username' => $secrets['username'],
            'password' => $pwd,
            'proxy' => ProxyManager::resolveProxy($secrets['proxy'] ?? ''),
            'session_path' => self::sessionPath($accountId),
            'verification_code' => $verificationCode,
        ]);

        if (empty($result['success'])) {
            if (!empty($result['needs_2fa'])) {
                throw new RuntimeException('2FA code required. Enter the verification code.');
            }
            throw new RuntimeException($result['error'] ?? 'Login failed');
        }

        $data = $result['data'];
        if ($password) {
            Database::updateAccount($accountId, ['password_enc' => Crypto::encrypt($password)]);
        }
        if (!empty($data['session_settings'])) {
            Database::updateAccount($accountId, [
                'session_data' => Crypto::encrypt(json_encode($data['session_settings'])),
            ]);
        }

        Database::updateAccount($accountId, [
            'status' => 'active',
            'followers' => $data['followers'] ?? 0,
            'following' => $data['following'] ?? 0,
            'posts_count' => $data['posts_count'] ?? 0,
            'profile_pic' => $data['profile_pic'] ?? '',
            'full_name' => $data['full_name'] ?? '',
            'is_verified' => !empty($data['is_verified']) ? 1 : 0,
            'last_login' => Database::now(),
            'last_error' => '',
        ]);
        Database::logActivity($accountId, 'login', 'Logged in as @' . $secrets['username']);
        return Database::getAccount($accountId);
    }

    public static function logout(int $accountId): void
    {
        $path = self::sessionPath($accountId);
        if (is_file($path)) {
            unlink($path);
        }
        Database::updateAccount($accountId, ['status' => 'inactive', 'session_data' => '']);
        Database::logActivity($accountId, 'logout', 'Session cleared');
    }

    public static function refresh(int $accountId): array
    {
        return self::login($accountId);
    }

    public static function bulkLogin(array $ids): array
    {
        $results = [];
        foreach ($ids as $id) {
            try {
                $account = self::login((int) $id);
                $results[] = ['id' => (int) $id, 'success' => true, 'account' => $account];
            } catch (Throwable $e) {
                $results[] = ['id' => (int) $id, 'success' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    public static function bulkRefresh(array $ids): array
    {
        return self::bulkLogin($ids);
    }

    public static function postPhoto(int $accountId, string $imagePath, string $caption = ''): array
    {
        $secrets = Database::getAccountSecrets($accountId);
        if (!$secrets) {
            throw new RuntimeException('Account not found');
        }
        $result = InstagramBridge::call('post_photo', [
            'username' => $secrets['username'],
            'proxy' => ProxyManager::resolveProxy($secrets['proxy'] ?? ''),
            'session_path' => self::sessionPath($accountId),
            'image_path' => $imagePath,
            'caption' => $caption,
        ]);
        if (empty($result['success'])) {
            throw new RuntimeException($result['error'] ?? 'Post failed');
        }
        Database::logActivity($accountId, 'post_photo', 'Posted media');
        $account = Database::getAccount($accountId);
        Database::updateAccount($accountId, ['posts_count' => ($account['posts_count'] ?? 0) + 1]);
        return $result['data'];
    }
}
