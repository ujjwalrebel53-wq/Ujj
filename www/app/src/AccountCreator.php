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
        $proxyPool = ProxyManager::getProxyPool(5);
        if ($proxy && !in_array($proxy, $proxyPool, true)) {
            array_unshift($proxyPool, $proxy);
        }
        if (!$proxy && empty($proxyPool)) {
            LiveLogger::warn('PROXY NAHI HAI — 429 error aayega! Webshare URL check karo .env mein');
        } else {
            LiveLogger::info('Proxy pool ready', ['count' => count($proxyPool)]);
        }

        $bridgeParams = [
            'username' => $username,
            'password' => $password,
            'full_name' => $fullName,
            'proxy' => $proxy ?: ($proxyPool[0] ?? ''),
            'proxy_list' => $proxyPool,
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
            if (!empty($result['rate_limited'])) {
                $err = 'Instagram rate limit (429) — 30-60 min wait karo, phir dubara try. Alag proxy use karo.';
            }
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
        LiveLogger::info('Single account creation queued (async)');
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
        if (!empty($data['verification_code'])) {
            self::submitVerificationCode((int) $job['id'], (string) $data['verification_code']);
        }
        self::triggerWorker(90);
        return [
            'queued' => true,
            'job_id' => (int) $job['id'],
            'job' => $job,
            'message' => 'Queue mein add ho gaya — neeche progress dikhega (2-5 min lag sakta hai)',
        ];
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
        $delay = max((int) ($data['delay_seconds'] ?? 90), 60);
        LiveLogger::info("Batch delay: {$delay}s per account (429 avoid)");
        self::triggerWorker($delay);
        return ['batch_id' => $batchId, 'count' => $count, 'jobs' => $jobs, 'queued' => true];
    }

    public static function triggerWorker(int $delay = 90): void
    {
        $delay = max($delay, 60);
        if (self::tryStartCliWorker($delay)) {
            return;
        }
        self::scheduleInlineWorker($delay);
    }

    private static function workerLockPath(): string
    {
        return DATA_PATH . '/worker.lock';
    }

    private static function tryStartCliWorker(int $delay): bool
    {
        if (!function_exists('exec')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (in_array('exec', $disabled, true)) {
            return false;
        }
        $php = PHP_BINARY;
        $script = BASE_PATH . '/cli/worker.php';
        LiveLogger::info("CLI worker start (delay {$delay}s)");
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            pclose(popen("start /B \"\" $php " . escapeshellarg($script) . ' ' . $delay, 'r'));
        } else {
            exec(escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . (int) $delay . ' > /dev/null 2>&1 &');
        }
        return true;
    }

    private static function scheduleInlineWorker(int $delay): void
    {
        LiveLogger::info('Inline worker schedule (shared hosting fallback)');
        register_shutdown_function(static function () use ($delay): void {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            ignore_user_abort(true);
            @set_time_limit(600);
            self::runWorkerLoop($delay, 1);
        });
    }

    public static function runWorkerLoop(int $delay = 90, int $maxJobs = 0): int
    {
        $delay = max($delay, 60);
        $lock = self::workerLockPath();
        $fp = @fopen($lock, 'c');
        if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) {
            if ($fp) {
                fclose($fp);
            }
            return 0;
        }
        fwrite($fp, (string) getmypid());
        fflush($fp);

        $processed = 0;
        try {
            while ($maxJobs === 0 || $processed < $maxJobs) {
                $done = self::processNextJob($delay, false);
                if ($done === null) {
                    break;
                }
                $processed++;
                if ($maxJobs === 0 || $processed < $maxJobs) {
                    sleep($delay);
                }
            }
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
            @unlink($lock);
        }
        return $processed;
    }

    public static function processNextJob(int $delay = 90, bool $chain = true): ?array
    {
        Database::resetStuckCreationJobs();
        $job = Database::claimNextPendingJob();
        if (!$job) {
            return null;
        }

        $jobId = (int) $job['id'];
        LiveLogger::info("Worker processing job #$jobId @{$job['username']}");
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
            $out = array_merge(['success' => true, 'job_id' => $jobId], $result);
        } catch (RuntimeException $e) {
            $status = $e->getCode() === 1001 ? 'waiting_code' : 'failed';
            Database::updateCreationJob($jobId, ['status' => $status, 'error' => $e->getMessage()]);
            $out = [
                'success' => false,
                'job_id' => $jobId,
                'error' => $e->getMessage(),
                'needs_code' => $e->getCode() === 1001,
            ];
        } catch (Throwable $e) {
            LiveLogger::error('Worker error: ' . $e->getMessage(), ['job_id' => $jobId]);
            Database::updateCreationJob($jobId, ['status' => 'failed', 'error' => $e->getMessage()]);
            $out = ['success' => false, 'job_id' => $jobId, 'error' => $e->getMessage()];
        }

        if ($chain && Database::listPendingCreationJobs()) {
            self::triggerWorker($delay);
        }
        return $out;
    }

    /** @deprecated Use runWorkerLoop */
    public static function processPendingJobs(int $delay = 30): void
    {
        self::runWorkerLoop(max($delay, 60));
    }

    public static function verifyJob(int $jobId, string $code): array
    {
        self::submitVerificationCode($jobId, $code);
        return self::queueRetry($jobId);
    }

    public static function queueRetry(int $jobId): array
    {
        $job = Database::getCreationJob($jobId);
        if (!$job) {
            throw new RuntimeException('Job not found');
        }
        LiveLogger::info('Job retry queued', ['job_id' => $jobId, 'username' => $job['username']]);
        Database::updateCreationJob($jobId, ['status' => 'pending', 'error' => '']);
        self::triggerWorker(90);
        return [
            'queued' => true,
            'job_id' => $jobId,
            'job' => Database::getCreationJob($jobId),
        ];
    }

    public static function retryJob(int $jobId): array
    {
        return self::queueRetry($jobId);
    }
}
