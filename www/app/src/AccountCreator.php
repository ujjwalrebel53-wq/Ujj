<?php

declare(strict_types=1);

final class AccountCreator
{
    // Sirf Indian names
    private const FIRST_NAMES = [
        'Aarav', 'Vihaan', 'Arjun', 'Rohan', 'Kabir', 'Ishaan', 'Aditya', 'Rahul', 'Amit', 'Vikram',
        'Suresh', 'Rajesh', 'Deepak', 'Manoj', 'Karan', 'Nikhil', 'Ravi', 'Sanjay', 'Ajay', 'Vivek',
        'Priya', 'Ananya', 'Diya', 'Sneha', 'Riya', 'Meera', 'Kavya', 'Nisha', 'Pooja', 'Shruti',
        'Neha', 'Kajal', 'Divya', 'Swati', 'Anjali', 'Rekha', 'Sunita', 'Lakshmi', 'Kavita', 'Geeta',
    ];
    private const LAST_NAMES = [
        'Sharma', 'Patel', 'Singh', 'Kumar', 'Gupta', 'Verma', 'Reddy', 'Joshi', 'Mehta', 'Shah',
        'Rao', 'Nair', 'Das', 'Roy', 'Mishra', 'Pandey', 'Yadav', 'Chauhan', 'Thakur', 'Malhotra',
        'Kapoor', 'Bhatia', 'Saxena', 'Tiwari', 'Dubey', 'Shukla', 'Agarwal', 'Banerjee', 'Iyer', 'Menon',
    ];

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
        if (trim($prefix) !== '') {
            $base = preg_replace('/[^a-z0-9_]/', '', strtolower($prefix));
            return substr($base . '_' . random_int(1000, 9999), 0, 30);
        }
        $first = strtolower(self::FIRST_NAMES[array_rand(self::FIRST_NAMES)]);
        $last = strtolower(self::LAST_NAMES[array_rand(self::LAST_NAMES)]);
        return substr($first . '_' . $last . random_int(10, 9999), 0, 30);
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
        file_put_contents(DATA_PATH . "/pending_code_{$jobId}.txt", trim($code));
    }

    private static function getVerificationCode(int $jobId): string
    {
        if (isset(self::$pendingCodes[$jobId])) {
            return self::$pendingCodes[$jobId];
        }
        $file = DATA_PATH . "/pending_code_{$jobId}.txt";
        return is_file($file) ? trim((string) file_get_contents($file)) : '';
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
        LiveLogger::info("Account create shuru: @$username", ['job_id' => $jobId, 'name' => $fullName]);

        $year = random_int(1994, 2002);
        $month = random_int(1, 12);
        $day = random_int(1, 28);
        $proxy = ProxyManager::resolveProxy($proxy);

        LiveLogger::debug('Proxy assign hua', ['proxy' => $proxy ? 'yes' : 'no']);

        $bridgeParams = [
            'username' => $username,
            'password' => $password,
            'full_name' => $fullName,
            'proxy' => $proxy,
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'auto_email' => true,
            'job_id' => $jobId,
        ];
        if ($email) {
            $bridgeParams['email'] = $email;
            LiveLogger::info('Custom email use ho rahi hai', ['email' => $email]);
        } else {
            LiveLogger::info('Temp email auto banegi + OTP auto fetch');
        }
        $manualCode = $verificationCode ?: self::getVerificationCode($jobId);
        if ($manualCode) {
            $bridgeParams['verification_code'] = $manualCode;
        }

        LiveLogger::info('Instagram signup bridge call...');
        $result = InstagramBridge::call('signup', $bridgeParams);

        if (empty($result['success'])) {
            $err = $result['error'] ?? 'Signup failed';
            LiveLogger::error('Signup fail: ' . $err, ['job_id' => $jobId]);
            if (!empty($result['needs_code'])) {
                Database::updateCreationJob($jobId, ['status' => 'waiting_code', 'error' => $err]);
                throw new RuntimeException($err, 1001);
            }
            throw new RuntimeException($err);
        }

        $usedEmail = $result['data']['email'] ?? $email ?? '';
        LiveLogger::success("Account ban gaya: @$username", ['email' => $usedEmail]);

        $account = Database::createAccount([
            'username' => $result['data']['username'] ?? $username,
            'password_enc' => Crypto::encrypt($password),
            'proxy' => $proxy,
            'group_name' => $groupName,
            'notes' => "Auto-created | $usedEmail",
            'status' => 'active',
        ]);
        Database::updateAccount($account['id'], [
            'full_name' => $fullName,
            'last_login' => Database::now(),
        ]);
        Database::logActivity($account['id'], 'auto_create', "Account @$username created");
        @unlink(DATA_PATH . "/pending_code_{$jobId}.txt");

        return [
            'success' => true,
            'account' => $account,
            'username' => $result['data']['username'] ?? $username,
            'password' => $password,
            'email' => $usedEmail,
            'full_name' => $fullName,
        ];
    }

    public static function startSingle(array $data): array
    {
        LiveLogger::info('Single account creation request');
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
        LiveLogger::info("Batch creation shuru: $count accounts");
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
        LiveLogger::info("Background worker start (delay {$delay}s)");
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
                LiveLogger::error('Worker error: ' . $e->getMessage(), ['job_id' => $jobId]);
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
        LiveLogger::info('Job retry', ['job_id' => $jobId, 'username' => $job['username']]);
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
