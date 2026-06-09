<?php
declare(strict_types=1);

define('BASE', __DIR__);
define('DATA', BASE . '/data');
define('SESS', DATA . '/sessions');

function boot(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $env = BASE . '/.env';
    if (is_file($env)) {
        foreach (file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            putenv(trim($k) . '=' . trim($v, " \t\"'"));
        }
    }
    foreach ([DATA, SESS, DATA . '/logs', DATA . '/uploads'] as $d) {
        is_dir($d) || @mkdir($d, 0755, true);
    }
    date_default_timezone_set('UTC');
    DB::init();
    DB::resetStuckJobs();
    $done = true;
}

function installed(): bool
{
    return is_file(DATA . '/install.lock') && is_file(BASE . '/.env');
}

final class R
{
    public static function json(mixed $d, int $s = 200): never
    {
        http_response_code($s);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function err(string $m, int $s = 400): never
    {
        self::json(['error' => $m], $s);
    }

    public static function body(): array
    {
        $d = json_decode(file_get_contents('php://input') ?: '{}', true);
        return is_array($d) ? $d : [];
    }
}

final class Crypto
{
    private static function key(): string
    {
        return hash('sha256', getenv('ENCRYPTION_KEY') ?: getenv('SECRET_KEY') ?: 'rebel', true);
    }

    public static function enc(string $v): string
    {
        if ($v === '') {
            return '';
        }
        $iv = random_bytes(16);
        $c = openssl_encrypt($v, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $c);
    }

    public static function dec(string $v): string
    {
        if ($v === '') {
            return '';
        }
        $raw = base64_decode($v, true);
        if (!$raw || strlen($raw) < 17) {
            return '';
        }
        $out = openssl_decrypt(substr($raw, 16), 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 16));
        return $out === false ? '' : $out;
    }
}

final class Log
{
    private static function file(): string
    {
        return DATA . '/logs/live.log';
    }

    public static function write(string $lvl, string $msg, array $ctx = []): void
    {
        file_put_contents(self::file(), json_encode([
            'time' => gmdate('Y-m-d H:i:s'),
            'level' => $lvl,
            'message' => $msg,
            'context' => $ctx,
        ], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
    }

    public static function tail(int $n = 200): array
    {
        $f = self::file();
        if (!is_file($f)) {
            return [];
        }
        $rows = [];
        foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $j = json_decode($line, true);
            if (is_array($j)) {
                $rows[] = $j;
            }
        }
        return array_slice($rows, -$n);
    }
}

final class DB
{
    private static ?PDO $p = null;

    public static function pdo(): PDO
    {
        if (!self::$p) {
            self::$p = new PDO('sqlite:' . DATA . '/app.db');
            self::$p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$p->exec('PRAGMA busy_timeout=5000; PRAGMA journal_mode=WAL');
        }
        return self::$p;
    }

    public static function init(): void
    {
        self::pdo()->exec("
            CREATE TABLE IF NOT EXISTS accounts(
                id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE, password_enc TEXT, session_data TEXT,
                proxy TEXT, group_name TEXT DEFAULT 'default', notes TEXT, status TEXT DEFAULT 'inactive',
                followers INT DEFAULT 0, following INT DEFAULT 0, posts_count INT DEFAULT 0, profile_pic TEXT,
                full_name TEXT, is_verified INT DEFAULT 0, last_login TEXT, last_error TEXT,
                created_at TEXT, updated_at TEXT);
            CREATE TABLE IF NOT EXISTS jobs(
                id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT, email TEXT, password TEXT, full_name TEXT,
                proxy TEXT, group_name TEXT, status TEXT DEFAULT 'pending', error TEXT, account_id INT,
                batch_id TEXT, created_at TEXT, updated_at TEXT);
            CREATE TABLE IF NOT EXISTS activity(
                id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INT, action TEXT, details TEXT,
                status TEXT, created_at TEXT);
        ");
    }

    public static function now(): string
    {
        return gmdate('c');
    }

    public static function accounts(?string $group = null): array
    {
        if ($group) {
            $s = self::pdo()->prepare('SELECT * FROM accounts WHERE group_name=? ORDER BY username');
            $s->execute([$group]);
        } else {
            $s = self::pdo()->query('SELECT * FROM accounts ORDER BY group_name,username');
        }
        return array_map([self::class, 'clean'], $s->fetchAll());
    }

    public static function clean(?array $a): ?array
    {
        if (!$a) {
            return null;
        }
        unset($a['password_enc'], $a['session_data']);
        return $a;
    }

    public static function getAcc(int $id): ?array
    {
        $s = self::pdo()->prepare('SELECT * FROM accounts WHERE id=?');
        $s->execute([$id]);
        return self::clean($s->fetch() ?: null);
    }

    public static function sec(int $id): ?array
    {
        $s = self::pdo()->prepare('SELECT * FROM accounts WHERE id=?');
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    public static function byUser(string $u): ?array
    {
        $s = self::pdo()->prepare('SELECT * FROM accounts WHERE username=?');
        $s->execute([strtolower($u)]);
        return self::clean($s->fetch() ?: null);
    }

    public static function addAcc(array $d): array
    {
        $n = self::now();
        self::pdo()->prepare('INSERT INTO accounts(username,password_enc,session_data,proxy,group_name,notes,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)')
            ->execute([strtolower($d['username']), $d['password_enc'] ?? '', $d['session_data'] ?? '', $d['proxy'] ?? '', $d['group_name'] ?? 'default', $d['notes'] ?? '', $d['status'] ?? 'inactive', $n, $n]);
        return self::getAcc((int) self::pdo()->lastInsertId());
    }

    public static function updAcc(int $id, array $d): ?array
    {
        $ok = ['password_enc', 'session_data', 'proxy', 'group_name', 'notes', 'status', 'followers', 'following', 'posts_count', 'profile_pic', 'full_name', 'is_verified', 'last_login', 'last_error'];
        $f = $v = [];
        foreach ($d as $k => $x) {
            if (in_array($k, $ok, true)) {
                $f[] = "$k=?";
                $v[] = $x;
            }
        }
        if (!$f) {
            return self::getAcc($id);
        }
        $f[] = 'updated_at=?';
        $v[] = self::now();
        $v[] = $id;
        self::pdo()->prepare('UPDATE accounts SET ' . implode(',', $f) . ' WHERE id=?')->execute($v);
        return self::getAcc($id);
    }

    public static function delAcc(int $id): bool
    {
        return self::pdo()->prepare('DELETE FROM accounts WHERE id=?')->execute([$id]) && true;
    }

    public static function stats(): array
    {
        $p = self::pdo();
        return [
            'total_accounts' => (int) $p->query('SELECT COUNT(*) FROM accounts')->fetchColumn(),
            'active_accounts' => (int) $p->query("SELECT COUNT(*) FROM accounts WHERE status='active'")->fetchColumn(),
            'error_accounts' => (int) $p->query("SELECT COUNT(*) FROM accounts WHERE status='error'")->fetchColumn(),
            'accounts_created' => (int) $p->query("SELECT COUNT(*) FROM jobs WHERE status='success'")->fetchColumn(),
            'creation_in_progress' => (int) $p->query("SELECT COUNT(*) FROM jobs WHERE status IN('pending','creating','waiting_code')")->fetchColumn(),
        ];
    }

    public static function addJob(array $d): array
    {
        $n = self::now();
        self::pdo()->prepare('INSERT INTO jobs(username,email,password,full_name,proxy,group_name,status,batch_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?)')
            ->execute([$d['username'], $d['email'] ?? '', $d['password'] ?? '', $d['full_name'] ?? '', $d['proxy'] ?? '', $d['group_name'] ?? 'auto-created', $d['status'] ?? 'pending', $d['batch_id'] ?? '', $n, $n]);
        return self::job((int) self::pdo()->lastInsertId());
    }

    public static function job(int $id): ?array
    {
        $s = self::pdo()->prepare('SELECT * FROM jobs WHERE id=?');
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    public static function updJob(int $id, array $d): ?array
    {
        $ok = ['username', 'email', 'password', 'full_name', 'proxy', 'group_name', 'status', 'error', 'account_id', 'batch_id'];
        $f = $v = [];
        foreach ($d as $k => $x) {
            if (in_array($k, $ok, true)) {
                $f[] = "$k=?";
                $v[] = $x;
            }
        }
        if (!$f) {
            return self::job($id);
        }
        $f[] = 'updated_at=?';
        $v[] = self::now();
        $v[] = $id;
        self::pdo()->prepare('UPDATE jobs SET ' . implode(',', $f) . ' WHERE id=?')->execute($v);
        return self::job($id);
    }

    public static function jobs(int $limit = 30): array
    {
        $s = self::pdo()->prepare('SELECT * FROM jobs ORDER BY id DESC LIMIT ?');
        $s->execute([$limit]);
        return $s->fetchAll();
    }

    public static function claimJob(): ?array
    {
        $p = self::pdo();
        $p->beginTransaction();
        $j = $p->query("SELECT * FROM jobs WHERE status='pending' ORDER BY id LIMIT 1")->fetch();
        if (!$j) {
            $p->commit();
            return null;
        }
        $s = $p->prepare("UPDATE jobs SET status='creating',updated_at=? WHERE id=? AND status='pending'");
        $s->execute([self::now(), $j['id']]);
        if (!$s->rowCount()) {
            $p->rollBack();
            return null;
        }
        $p->commit();
        return self::job((int) $j['id']);
    }

    public static function pendingJobs(): array
    {
        return self::pdo()->query("SELECT id FROM jobs WHERE status='pending'")->fetchAll();
    }

    public static function resetStuckJobs(): int
    {
        $cut = gmdate('c', time() - 1200);
        $s = self::pdo()->prepare("UPDATE jobs SET status='failed',error='Timeout — Retry dabao',updated_at=? WHERE status='creating' AND updated_at<?");
        $s->execute([self::now(), $cut]);
        return $s->rowCount();
    }

    public static function activity(int $n = 40): array
    {
        $s = self::pdo()->prepare('SELECT a.*,acc.username FROM activity a LEFT JOIN accounts acc ON a.account_id=acc.id ORDER BY a.id DESC LIMIT ?');
        $s->execute([$n]);
        return $s->fetchAll();
    }

    public static function log(?int $aid, string $act, string $det = ''): void
    {
        self::pdo()->prepare('INSERT INTO activity(account_id,action,details,status,created_at) VALUES(?,?,?,?,?)')
            ->execute([$aid, $act, $det, 'success', self::now()]);
    }
}

final class Proxy
{
    private static function url(): string
    {
        return trim(getenv('WEBSHARE_PROXY_URL') ?: '');
    }

    private static function cache(): array
    {
        $f = DATA . '/proxies.json';
        if (is_file($f) && (time() - filemtime($f)) < 300) {
            $j = json_decode((string) file_get_contents($f), true);
            return is_array($j) ? $j : [];
        }
        $u = self::url();
        if ($u === '') {
            return [];
        }
        $ch = curl_init($u);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
        $body = curl_exec($ch);
        curl_close($ch);
        $list = [];
        foreach (explode("\n", (string) $body) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $p = explode(':', $line);
            if (count($p) >= 4) {
                $list[] = 'http://' . $p[2] . ':' . implode(':', array_slice($p, 3)) . '@' . $p[0] . ':' . $p[1];
            }
        }
        file_put_contents($f, json_encode($list));
        return $list;
    }

    public static function pick(string $explicit = '', bool $auto = true): string
    {
        if (trim($explicit) !== '') {
            return trim($explicit);
        }
        $all = self::cache();
        return $all && $auto && self::url() ? $all[array_rand($all)] : '';
    }

    public static function pool(int $n = 5): array
    {
        $all = self::cache();
        shuffle($all);
        return array_slice($all, 0, min($n, count($all)));
    }

    public static function stats(): array
    {
        $all = self::cache();
        return ['enabled' => self::url() !== '', 'total_proxies' => count($all)];
    }
}

final class Bridge
{
    public static function call(string $action, array $params): array
    {
        $py = trim(getenv('PYTHON_BIN') ?: '');
        if ($py === '' || !is_executable($py)) {
            foreach ([BASE . '/venv/bin/python', '/usr/bin/python3', 'python3', 'python'] as $c) {
                if ($c === 'python3' || $c === 'python' || is_executable($c)) {
                    $py = $c;
                    break;
                }
            }
        }
        $cmd = escapeshellarg($py) . ' ' . escapeshellarg(BASE . '/ig.py');
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, BASE);
        if (!is_resource($proc)) {
            throw new RuntimeException('Python start fail');
        }
        fwrite($pipes[0], json_encode(['action' => $action, 'params' => $params]));
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        $r = json_decode($out ?: '{}', true);
        return is_array($r) ? $r : ['success' => false, 'error' => 'Bad bridge response'];
    }
}

final class Accounts
{
    private static function sess(int $id): string
    {
        return SESS . "/$id.json";
    }

    public static function login(int $id, ?string $pwd = null, ?string $code = null): array
    {
        $s = DB::sec($id);
        if (!$s) {
            throw new RuntimeException('Not found');
        }
        $p = $pwd ?: Crypto::dec($s['password_enc'] ?? '');
        $r = Bridge::call('login', [
            'username' => $s['username'], 'password' => $p,
            'proxy' => Proxy::pick($s['proxy'] ?? ''), 'session_path' => self::sess($id),
            'verification_code' => $code,
        ]);
        if (empty($r['success'])) {
            throw new RuntimeException($r['error'] ?? 'Login fail');
        }
        $d = $r['data'];
        if ($pwd) {
            DB::updAcc($id, ['password_enc' => Crypto::enc($pwd)]);
        }
        if (!empty($d['session_settings'])) {
            DB::updAcc($id, ['session_data' => Crypto::enc(json_encode($d['session_settings']))]);
        }
        DB::updAcc($id, [
            'status' => 'active', 'followers' => $d['followers'] ?? 0, 'following' => $d['following'] ?? 0,
            'posts_count' => $d['posts_count'] ?? 0, 'profile_pic' => $d['profile_pic'] ?? '',
            'full_name' => $d['full_name'] ?? '', 'is_verified' => !empty($d['is_verified']) ? 1 : 0,
            'last_login' => DB::now(), 'last_error' => '',
        ]);
        return DB::getAcc($id);
    }

    public static function logout(int $id): void
    {
        $f = self::sess($id);
        is_file($f) && unlink($f);
        DB::updAcc($id, ['status' => 'inactive', 'session_data' => '']);
    }

    public static function post(int $id, string $img, string $cap = ''): array
    {
        $s = DB::sec($id);
        if (!$s) {
            throw new RuntimeException('Not found');
        }
        $r = Bridge::call('post_photo', [
            'username' => $s['username'], 'proxy' => Proxy::pick($s['proxy'] ?? ''),
            'session_path' => self::sess($id), 'image_path' => $img, 'caption' => $cap,
        ]);
        if (empty($r['success'])) {
            throw new RuntimeException($r['error'] ?? 'Post fail');
        }
        $a = DB::getAcc($id);
        DB::updAcc($id, ['posts_count' => ($a['posts_count'] ?? 0) + 1]);
        return $r['data'];
    }
}

final class Creator
{
    private const FN = ['Aarav', 'Rahul', 'Arjun', 'Priya', 'Sneha', 'Kavya', 'Rohan', 'Amit', 'Neha', 'Vikram', 'Ananya', 'Divya'];
    private const LN = ['Sharma', 'Patel', 'Singh', 'Kumar', 'Gupta', 'Verma', 'Reddy', 'Joshi', 'Mehta', 'Shah', 'Rao', 'Nair'];

    public static function pwd(): string
    {
        $c = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#';
        do {
            $p = '';
            for ($i = 0; $i < 14; $i++) {
                $p .= $c[random_int(0, strlen($c) - 1)];
            }
        } while (!preg_match('/[A-Z]/', $p) || !preg_match('/[a-z]/', $p) || !preg_match('/[0-9]/', $p));
        return $p;
    }

    public static function name(): string
    {
        return self::FN[array_rand(self::FN)] . ' ' . self::LN[array_rand(self::LN)];
    }

    public static function user(string $pre = ''): string
    {
        if (trim($pre) !== '') {
            return substr(preg_replace('/[^a-z0-9_]/', '', strtolower($pre)) . '_' . random_int(1000, 9999), 0, 30);
        }
        return substr(strtolower(self::FN[array_rand(self::FN)] . '_' . self::LN[array_rand(self::LN)] . random_int(10, 9999)), 0, 30);
    }

    public static function preview(int $n = 5, string $pre = ''): array
    {
        $o = [];
        for ($i = 0; $i < min($n, 20); $i++) {
            $o[] = ['username' => self::user($pre), 'password' => self::pwd(), 'full_name' => self::name()];
        }
        return $o;
    }

    private static function code(int $jid): string
    {
        $f = DATA . "/code_$jid.txt";
        return is_file($f) ? trim((string) file_get_contents($f)) : '';
    }

    public static function setCode(int $jid, string $c): void
    {
        file_put_contents(DATA . "/code_$jid.txt", trim($c));
    }

    public static function signup(array $job): array
    {
        $jid = (int) $job['id'];
        Log::write('INFO', "Signup @$jid {$job['username']}");
        $proxy = Proxy::pick($job['proxy'] ?? '');
        $pool = Proxy::pool(5);
        if ($proxy && !in_array($proxy, $pool, true)) {
            array_unshift($pool, $proxy);
        }
        $r = Bridge::call('signup', [
            'username' => $job['username'], 'password' => $job['password'], 'full_name' => $job['full_name'],
            'proxy' => $proxy ?: ($pool[0] ?? ''), 'proxy_list' => $pool,
            'email' => $job['email'] ?: null, 'verification_code' => self::code($jid),
            'year' => random_int(1994, 2002), 'month' => random_int(1, 12), 'day' => random_int(1, 28),
        ]);
        if (empty($r['success'])) {
            $e = $r['error'] ?? 'Fail';
            if (!empty($r['rate_limited'])) {
                $e = '429 rate limit — 30-60 min wait + proxy check';
            }
            if (!empty($r['needs_code'])) {
                DB::updJob($jid, ['status' => 'waiting_code', 'error' => $e]);
                throw new RuntimeException($e, 1001);
            }
            throw new RuntimeException($e);
        }
        $email = $r['data']['email'] ?? $job['email'] ?? '';
        $acc = DB::addAcc([
            'username' => $r['data']['username'] ?? $job['username'],
            'password_enc' => Crypto::enc($job['password']), 'proxy' => $proxy,
            'group_name' => $job['group_name'], 'notes' => "Auto | $email", 'status' => 'active',
        ]);
        DB::updAcc($acc['id'], ['full_name' => $job['full_name'], 'last_login' => DB::now()]);
        DB::log($acc['id'], 'auto_create', '@' . $job['username']);
        @unlink(DATA . "/code_$jid.txt");
        return ['account' => $acc, 'email' => $email, 'password' => $job['password']];
    }

    public static function queueSingle(array $d): array
    {
        $j = DB::addJob([
            'username' => $d['username'] ?? self::user($d['username_prefix'] ?? ''),
            'password' => $d['password'] ?? self::pwd(), 'full_name' => $d['full_name'] ?? self::name(),
            'proxy' => Proxy::pick($d['proxy'] ?? '', (bool) ($d['use_webshare'] ?? true)),
            'group_name' => $d['group_name'] ?? 'auto-created', 'email' => $d['email'] ?? '', 'batch_id' => 'single',
        ]);
        self::kick(90);
        return ['queued' => true, 'job_id' => (int) $j['id'], 'job' => $j];
    }

    public static function queueBatch(array $d): array
    {
        $n = min((int) ($d['count'] ?? 1), 10);
        $bid = bin2hex(random_bytes(4));
        $jobs = [];
        for ($i = 0; $i < $n; $i++) {
            $jobs[] = DB::addJob([
                'username' => self::user($d['username_prefix'] ?? ''),
                'password' => self::pwd(), 'full_name' => self::name(),
                'proxy' => Proxy::pick($d['proxy'] ?? '', (bool) ($d['use_webshare'] ?? true)),
                'group_name' => $d['group_name'] ?? 'auto-created', 'batch_id' => $bid,
            ]);
        }
        self::kick(max((int) ($d['delay_seconds'] ?? 90), 60));
        return ['queued' => true, 'batch_id' => $bid, 'count' => $n, 'jobs' => $jobs];
    }

    public static function kick(int $delay = 90): void
    {
        $stamp = DATA . '/worker.ts';
        if (is_file($stamp) && (time() - filemtime($stamp)) < 45) {
            return;
        }
        touch($stamp);
        $delay = max($delay, 60);
        if (function_exists('exec')) {
            $dis = array_map('trim', explode(',', (string) ini_get('disable_functions')));
            if (!in_array('exec', $dis, true)) {
                exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(BASE . '/worker.php') . ' ' . $delay . ' > /dev/null 2>&1 &');
                return;
            }
        }
        register_shutdown_function(static function () use ($delay): void {
            function_exists('fastcgi_finish_request') && @fastcgi_finish_request();
            ignore_user_abort(true);
            @set_time_limit(600);
            self::runLoop($delay, 1);
        });
    }

    public static function runLoop(int $delay = 90, int $max = 0): int
    {
        $delay = max($delay, 60);
        $lock = DATA . '/worker.lock';
        $fp = @fopen($lock, 'c');
        if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) {
            $fp && fclose($fp);
            return 0;
        }
        $done = 0;
        try {
            while ($max === 0 || $done < $max) {
                if (!self::step($delay, false)) {
                    break;
                }
                $done++;
                if ($max === 0 || $done < $max) {
                    sleep($delay);
                }
            }
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
            @unlink($lock);
        }
        return $done;
    }

    public static function step(int $delay = 90, bool $chain = true): bool
    {
        DB::resetStuckJobs();
        $job = DB::claimJob();
        if (!$job) {
            return false;
        }
        $jid = (int) $job['id'];
        try {
            $r = self::signup($job);
            DB::updJob($jid, ['status' => 'success', 'account_id' => $r['account']['id'], 'email' => $r['email'], 'error' => '']);
        } catch (RuntimeException $e) {
            if ($e->getCode() !== 1001) {
                DB::updJob($jid, ['status' => 'failed', 'error' => $e->getMessage()]);
            }
        } catch (Throwable $e) {
            DB::updJob($jid, ['status' => 'failed', 'error' => $e->getMessage()]);
        }
        if ($chain && DB::pendingJobs()) {
            self::kick($delay);
        }
        return true;
    }

    public static function retry(int $jid): array
    {
        if (!DB::job($jid)) {
            throw new RuntimeException('Job not found');
        }
        DB::updJob($jid, ['status' => 'pending', 'error' => '']);
        self::kick(90);
        return ['queued' => true, 'job_id' => $jid];
    }

    public static function verify(int $jid, string $code): array
    {
        self::setCode($jid, $code);
        return self::retry($jid);
    }
}

final class Install
{
    public static function status(): array
    {
        return [
            'php' => version_compare(PHP_VERSION, '8.1.0', '>='),
            'sqlite' => extension_loaded('pdo_sqlite'),
            'curl' => extension_loaded('curl'),
            'data' => is_writable(DATA) || @mkdir(DATA, 0755, true),
            'ready' => version_compare(PHP_VERSION, '8.1.0', '>=') && extension_loaded('pdo_sqlite') && extension_loaded('curl'),
            'installed' => installed(),
        ];
    }

    public static function run(): array
    {
        if (installed()) {
            return ['success' => true, 'message' => 'Already installed'];
        }
        $s = self::status();
        if (!$s['ready']) {
            throw new RuntimeException('PHP 8.1+, SQLite, cURL chahiye');
        }
        $secret = bin2hex(random_bytes(24));
        $enc = bin2hex(random_bytes(24));
        $webshare = '';
        $py = 'python3';
        foreach (['python3', '/usr/bin/python3', 'python'] as $c) {
            if ($c === 'python3' || is_executable($c)) {
                $py = $c;
                break;
            }
        }
        $venv = BASE . '/venv/bin/python';
        if (!is_dir(BASE . '/venv')) {
            @shell_exec(escapeshellarg($py) . ' -m venv ' . escapeshellarg(BASE . '/venv') . ' 2>&1');
        }
        if (is_file($venv)) {
            $py = $venv;
            @shell_exec(escapeshellarg($py) . ' -m pip install -q -r ' . escapeshellarg(BASE . '/req.txt') . ' 2>&1');
        }
        file_put_contents(BASE . '/.env', implode("\n", [
            'SECRET_KEY=' . $secret,
            'ENCRYPTION_KEY=' . $enc,
            'WEBSHARE_PROXY_URL=' . $webshare,
            'PYTHON_BIN=' . $py,
            'SITE_URL=https://rebelinsta.alwaysdata.net',
        ]));
        putenv('SECRET_KEY=' . $secret);
        putenv('ENCRYPTION_KEY=' . $enc);
        putenv('PYTHON_BIN=' . $py);
        boot();
        file_put_contents(DATA . '/install.lock', date('c'));
        return ['success' => true, 'python' => $py];
    }
}
