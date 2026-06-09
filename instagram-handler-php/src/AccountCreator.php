<?php

declare(strict_types=1);

final class AccountCreator
{
    private const FIRST_NAMES = ['Aarav', 'Vihaan', 'Arjun', 'Priya', 'Ananya', 'Diya', 'Alex', 'Jordan', 'Sam', 'Taylor'];
    private const LAST_NAMES = ['Sharma', 'Patel', 'Singh', 'Kumar', 'Gupta', 'Verma', 'Smith', 'Johnson', 'Williams', 'Brown'];
    private const ADJECTIVES = ['cool', 'real', 'daily', 'life', 'the', 'its', 'hey', 'just'];
    private const NOUNS = ['vibes', 'world', 'soul', 'dream', 'life', 'mood', 'zone', 'hub'];

    private static array $pendingCodes = [];

    public static function generatePassword(int $length = 14): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
        do {
            $pwd = '';
            for ($i = 0; $i < $length; $i++) {
                $pwd .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (!preg_match('/[A-Z]/', $pwd) || !preg_match('/[a-z]/', $pwd) || !preg_match('/[0-9]/', $pwd));
        return $pwd;
    }

    public static function generateFullName(): string
    {
        return self::FIRST_NAMES[array_rand(self::FIRST_NAMES)] . ' ' . self::LAST_NAMES[array_rand(self::LAST_NAMES)];
    }

    public static function generateUsername(string $prefix = ''): string
    {
        $base = preg_replace('/[^a-z0-9_]/', '', strtolower($prefix)) ?: (self::ADJECTIVES[array_rand(self::ADJECTIVES)] . '_' . self::NOUNS[array_rand(self::NOUNS)]);
        $suffix = '';
        for ($i = 0; $i < random_int(4, 6); $i++) {
            $suffix .= chr(random_int(97, 122));
        }
        if (random_int(0, 1)) {
            $suffix .= random_int(10, 99);
        }
        return substr($base . '_' . $suffix, 0, 30);
    }

    public static function previewProfiles(int $count = 5, string $prefix = ''): array
    {
        $profiles = [];
        $count = min($count, 20);
        for ($i = 0; $i < $count; $i++) {
            $profiles[] = [
                'username' => self::generateUsername($prefix),
                'password' => self::generatePassword(),
                'full_name' => self::generateFullName(),
            ];
        }
        return $profiles;
    }

    public static function submitVerificationCode(int $jobId, string $code): void
    {
        self::$pendingCodes[$jobId] = trim($code);
        $file = DATA_PATH . "/pending_code_{$jobId}.txt";
        file_put_contents($file, trim($code));
    }

    private static function getVerificationCode(int $jobId): string
    {
        if (isset(self::$pendingCodes[$jobId])) {
            return self::$pendingCodes[$jobId];
        }
        $file = DATA_PATH . "/pending_code_{$jobId}.txt";
        if (is_file($file)) {
            return trim((string) file_get_contents($file));
        }
        return '';
    }

    public static function createInstagramAccount(
        int $jobId,
        string $username,
        string $password,
        string $fullName,
        string $proxy,
        string $groupName,
        ?string $email = null,
        ?string $verificationCode = null
    ): array {
        $tempMail = null;
        if (!$email) {
            $tempMail = new TempEmail();
            $email = $tempMail->address;
        }

        $code = $verificationCode ?: self::getVerificationCode($jobId);
        if (!$code && $tempMail) {
            try {
                $code = $tempMail->waitForCode(90);
            } catch (Throwable) {
                Database::updateCreationJob($jobId, ['status' => 'waiting_code']);
                throw new RuntimeException('Verification code required', 1001);
            }
        }

        $year = random_int(1994, 2002);
        $month = random_int(1, 12);
        $day = random_int(1, 28);

        $proxy = ProxyManager::resolveProxy($proxy);
        $result = InstagramBridge::call('signup', [
            'username' => $username,
            'password' => $password,
            'email' => $email,
            'full_name' => $fullName,
            'proxy' => $proxy,
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'verification_code' => $code,
        ]);

        if (empty($result['success'])) {
            if (!empty($result['needs_code'])) {
                Database::updateCreationJob($jobId, ['status' => 'waiting_code', 'error' => $result['error'] ?? '']);
                throw new RuntimeException($result['error'] ?? 'Verification code required', 1001);
            }
            throw new RuntimeException($result['error'] ?? 'Signup failed');
        }

        $account = Database::createAccount([
            'username' => $result['data']['username'] ?? $username,
            'password_enc' => Crypto::encrypt($password),
            'proxy' => $proxy,
            'group_name' => $groupName,
            'notes' => "Auto-created | $email",
            'status' => 'active',
        ]);
        Database::updateAccount($account['id'], [
            'full_name' => $fullName,
            'last_login' => Database::now(),
        ]);
        Database::logActivity($account['id'], 'auto_create', "Account @$username created via auto-creator");

        @unlink(DATA_PATH . "/pending_code_{$jobId}.txt");

        return [
            'success' => true,
            'account' => $account,
            'username' => $result['data']['username'] ?? $username,
            'password' => $password,
            'email' => $email,
            'full_name' => $fullName,
        ];
    }

    public static function startSingle(array $data): array
    {
        $jobProxy = ProxyManager::resolveProxy($data['proxy'] ?? '', (bool) ($data['use_webshare'] ?? true));
        $job = Database::createCreationJob([
            'username' => $data['username'] ?? self::generateUsername($data['username_prefix'] ?? ''),
            'password' => $data['password'] ?? self::generatePassword(),
            'full_name' => $data['full_name'] ?? self::generateFullName(),
            'proxy' => $jobProxy,
            'group_name' => $data['group_name'] ?? 'auto-created',
            'email' => $data['email'] ?? '',
            'job_batch_id' => 'single',
        ]);
        Database::updateCreationJob($job['id'], ['status' => 'creating']);

        try {
            $result = self::createInstagramAccount(
                (int) $job['id'],
                $job['username'],
                $job['password'],
                $job['full_name'],
                $job['proxy'],
                $job['group_name'],
                $job['email'] ?: null,
                $data['verification_code'] ?? null
            );
            Database::updateCreationJob($job['id'], [
                'status' => 'success',
                'account_id' => $result['account']['id'],
                'email' => $result['email'],
            ]);
            return array_merge(['job' => Database::getCreationJob($job['id'])], $result);
        } catch (RuntimeException $e) {
            $needsCode = $e->getCode() === 1001;
            Database::updateCreationJob($job['id'], [
                'status' => $needsCode ? 'waiting_code' : 'failed',
                'error' => $e->getMessage(),
            ]);
            return [
                'job' => Database::getCreationJob($job['id']),
                'success' => false,
                'error' => $e->getMessage(),
                'needs_code' => $needsCode,
            ];
        }
    }

    public static function startBatch(array $data): array
    {
        $count = min((int) ($data['count'] ?? 1), 20);
        $batchId = bin2hex(random_bytes(6));
        $jobs = [];
        $explicitProxy = $data['proxy'] ?? '';
        $autoProxy = (bool) ($data['use_webshare'] ?? true);
        for ($i = 0; $i < $count; $i++) {
            $jobs[] = Database::createCreationJob([
                'username' => self::generateUsername($data['username_prefix'] ?? ''),
                'password' => self::generatePassword(),
                'full_name' => self::generateFullName(),
                'proxy' => ProxyManager::resolveProxy($explicitProxy, $autoProxy),
                'group_name' => $data['group_name'] ?? 'auto-created',
                'job_batch_id' => $batchId,
            ]);
        }
        self::startWorker((int) ($data['delay_seconds'] ?? 30));
        return ['batch_id' => $batchId, 'count' => $count, 'jobs' => $jobs];
    }

    public static function startWorker(int $delay = 30): void
    {
        $php = PHP_BINARY;
        $script = BASE_PATH . '/cli/worker.php';
        $delay = max($delay, 10);
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            pclose(popen("start /B \"\" $php " . escapeshellarg($script) . ' ' . $delay, 'r'));
        } else {
            exec(escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . (int) $delay . ' > /dev/null 2>&1 &');
        }
    }

    public static function processPendingJobs(int $delay = 30): void
    {
        while ($jobs = Database::listPendingCreationJobs()) {
            $job = $jobs[0];
            $jobId = (int) $job['id'];
            Database::updateCreationJob($jobId, ['status' => 'creating']);
            try {
                $result = self::createInstagramAccount(
                    $jobId,
                    $job['username'],
                    $job['password'],
                    $job['full_name'],
                    $job['proxy'],
                    $job['group_name'],
                    $job['email'] ?: null
                );
                Database::updateCreationJob($jobId, [
                    'status' => 'success',
                    'account_id' => $result['account']['id'],
                    'email' => $result['email'],
                    'error' => '',
                ]);
            } catch (RuntimeException $e) {
                $status = $e->getCode() === 1001 ? 'waiting_code' : 'failed';
                Database::updateCreationJob($jobId, ['status' => $status, 'error' => $e->getMessage()]);
            } catch (Throwable $e) {
                Database::updateCreationJob($jobId, ['status' => 'failed', 'error' => $e->getMessage()]);
            }
            sleep($delay);
        }
    }

    public static function verifyJob(int $jobId, string $code): array
    {
        self::submitVerificationCode($jobId, $code);
        return self::retryJob($jobId);
    }

    public static function retryJob(int $jobId): array
    {
        $job = Database::getCreationJob($jobId);
        if (!$job) {
            throw new RuntimeException('Job not found');
        }
        Database::updateCreationJob($jobId, ['status' => 'creating', 'error' => '']);
        try {
            $result = self::createInstagramAccount(
                $jobId,
                $job['username'],
                $job['password'],
                $job['full_name'],
                $job['proxy'],
                $job['group_name'],
                $job['email'] ?: null
            );
            Database::updateCreationJob($jobId, [
                'status' => 'success',
                'account_id' => $result['account']['id'],
                'email' => $result['email'],
            ]);
            return array_merge(['success' => true, 'job' => Database::getCreationJob($jobId)], $result);
        } catch (RuntimeException $e) {
            $needsCode = $e->getCode() === 1001;
            Database::updateCreationJob($jobId, [
                'status' => $needsCode ? 'waiting_code' : 'failed',
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'job' => Database::getCreationJob($jobId),
                'error' => $e->getMessage(),
                'needs_code' => $needsCode,
            ];
        }
    }
}
