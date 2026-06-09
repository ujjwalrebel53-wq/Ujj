<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $path = DATA_PATH . '/accounts.db';
            self::$pdo = new PDO('sqlite:' . $path);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$pdo->exec('PRAGMA busy_timeout = 5000');
            self::$pdo->exec('PRAGMA journal_mode = WAL');
        }
        return self::$pdo;
    }

    public static function init(): void
    {
        self::pdo()->exec("
            CREATE TABLE IF NOT EXISTS accounts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_enc TEXT DEFAULT '',
                session_data TEXT DEFAULT '',
                proxy TEXT DEFAULT '',
                group_name TEXT DEFAULT 'default',
                notes TEXT DEFAULT '',
                status TEXT DEFAULT 'inactive',
                followers INTEGER DEFAULT 0,
                following INTEGER DEFAULT 0,
                posts_count INTEGER DEFAULT 0,
                profile_pic TEXT DEFAULT '',
                full_name TEXT DEFAULT '',
                is_verified INTEGER DEFAULT 0,
                last_login TEXT DEFAULT '',
                last_error TEXT DEFAULT '',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS activity_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                account_id INTEGER,
                action TEXT NOT NULL,
                details TEXT DEFAULT '',
                status TEXT DEFAULT 'success',
                created_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS scheduled_posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                account_id INTEGER NOT NULL,
                caption TEXT DEFAULT '',
                media_path TEXT NOT NULL,
                scheduled_at TEXT NOT NULL,
                status TEXT DEFAULT 'pending',
                posted_at TEXT DEFAULT '',
                error TEXT DEFAULT '',
                created_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS creation_jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                email TEXT DEFAULT '',
                password TEXT DEFAULT '',
                full_name TEXT DEFAULT '',
                proxy TEXT DEFAULT '',
                group_name TEXT DEFAULT 'auto-created',
                status TEXT DEFAULT 'pending',
                error TEXT DEFAULT '',
                account_id INTEGER,
                job_batch_id TEXT DEFAULT '',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );
        ");
    }

    public static function now(): string
    {
        return gmdate('c');
    }

    public static function logActivity(?int $accountId, string $action, string $details = '', string $status = 'success'): void
    {
        $stmt = self::pdo()->prepare(
            'INSERT INTO activity_log (account_id, action, details, status, created_at) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$accountId, $action, $details, $status, self::now()]);
    }

    public static function sanitizeAccount(?array $account): ?array
    {
        if (!$account) {
            return null;
        }
        unset($account['password_enc'], $account['session_data']);
        return $account;
    }

    public static function listAccounts(?string $group = null): array
    {
        if ($group) {
            $stmt = self::pdo()->prepare('SELECT * FROM accounts WHERE group_name = ? ORDER BY username');
            $stmt->execute([$group]);
        } else {
            $stmt = self::pdo()->query('SELECT * FROM accounts ORDER BY group_name, username');
        }
        return array_map([self::class, 'sanitizeAccount'], $stmt->fetchAll());
    }

    public static function getAccount(int $id): ?array
    {
        $stmt = self::pdo()->prepare('SELECT * FROM accounts WHERE id = ?');
        $stmt->execute([$id]);
        return self::sanitizeAccount($stmt->fetch() ?: null);
    }

    public static function getAccountByUsername(string $username): ?array
    {
        $stmt = self::pdo()->prepare('SELECT * FROM accounts WHERE username = ?');
        $stmt->execute([strtolower($username)]);
        return self::sanitizeAccount($stmt->fetch() ?: null);
    }

    public static function getAccountSecrets(int $id): ?array
    {
        $stmt = self::pdo()->prepare('SELECT * FROM accounts WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function createAccount(array $data): array
    {
        $now = self::now();
        $stmt = self::pdo()->prepare(
            'INSERT INTO accounts (username, password_enc, session_data, proxy, group_name, notes, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            strtolower($data['username']),
            $data['password_enc'] ?? '',
            $data['session_data'] ?? '',
            $data['proxy'] ?? '',
            $data['group_name'] ?? 'default',
            $data['notes'] ?? '',
            $data['status'] ?? 'inactive',
            $now,
            $now,
        ]);
        $id = (int) self::pdo()->lastInsertId();
        self::logActivity($id, 'account_created', 'Added @' . $data['username']);
        return self::getAccount($id);
    }

    public static function updateAccount(int $id, array $data): ?array
    {
        $allowed = [
            'password_enc', 'session_data', 'proxy', 'group_name', 'notes', 'status',
            'followers', 'following', 'posts_count', 'profile_pic', 'full_name',
            'is_verified', 'last_login', 'last_error',
        ];
        $fields = [];
        $values = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
        }
        if (!$fields) {
            return self::getAccount($id);
        }
        $fields[] = 'updated_at = ?';
        $values[] = self::now();
        $values[] = $id;
        $sql = 'UPDATE accounts SET ' . implode(', ', $fields) . ' WHERE id = ?';
        self::pdo()->prepare($sql)->execute($values);
        return self::getAccount($id);
    }

    public static function deleteAccount(int $id): bool
    {
        $stmt = self::pdo()->prepare('DELETE FROM accounts WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            self::logActivity(null, 'account_deleted', "Account ID $id removed");
            return true;
        }
        return false;
    }

    public static function listActivity(int $limit = 50): array
    {
        $stmt = self::pdo()->prepare(
            'SELECT a.*, acc.username FROM activity_log a
             LEFT JOIN accounts acc ON a.account_id = acc.id
             ORDER BY a.id DESC LIMIT ?'
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public static function listGroups(): array
    {
        $rows = self::pdo()->query('SELECT DISTINCT group_name FROM accounts ORDER BY group_name')->fetchAll();
        return array_column($rows, 'group_name');
    }

    public static function getStats(): array
    {
        $pdo = self::pdo();
        return [
            'total_accounts' => (int) $pdo->query('SELECT COUNT(*) FROM accounts')->fetchColumn(),
            'active_accounts' => (int) $pdo->query("SELECT COUNT(*) FROM accounts WHERE status = 'active'")->fetchColumn(),
            'error_accounts' => (int) $pdo->query("SELECT COUNT(*) FROM accounts WHERE status = 'error'")->fetchColumn(),
            'pending_posts' => (int) $pdo->query("SELECT COUNT(*) FROM scheduled_posts WHERE status = 'pending'")->fetchColumn(),
            'groups' => count(self::listGroups()),
            'accounts_created' => (int) $pdo->query("SELECT COUNT(*) FROM creation_jobs WHERE status = 'success'")->fetchColumn(),
            'creation_in_progress' => (int) $pdo->query("SELECT COUNT(*) FROM creation_jobs WHERE status IN ('pending','creating','waiting_code')")->fetchColumn(),
        ];
    }

    public static function createCreationJob(array $data): array
    {
        $now = self::now();
        $stmt = self::pdo()->prepare(
            'INSERT INTO creation_jobs (username, email, password, full_name, proxy, group_name, status, job_batch_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['username'],
            $data['email'] ?? '',
            $data['password'] ?? '',
            $data['full_name'] ?? '',
            $data['proxy'] ?? '',
            $data['group_name'] ?? 'auto-created',
            $data['status'] ?? 'pending',
            $data['job_batch_id'] ?? '',
            $now,
            $now,
        ]);
        return self::getCreationJob((int) self::pdo()->lastInsertId());
    }

    public static function getCreationJob(int $id): ?array
    {
        $stmt = self::pdo()->prepare('SELECT * FROM creation_jobs WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function updateCreationJob(int $id, array $data): ?array
    {
        $allowed = ['username', 'email', 'password', 'full_name', 'proxy', 'group_name', 'status', 'error', 'account_id', 'job_batch_id'];
        $fields = [];
        $values = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
        }
        if (!$fields) {
            return self::getCreationJob($id);
        }
        $fields[] = 'updated_at = ?';
        $values[] = self::now();
        $values[] = $id;
        self::pdo()->prepare('UPDATE creation_jobs SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($values);
        return self::getCreationJob($id);
    }

    public static function listCreationJobs(?string $batchId = null, int $limit = 50): array
    {
        if ($batchId) {
            $stmt = self::pdo()->prepare('SELECT * FROM creation_jobs WHERE job_batch_id = ? ORDER BY id DESC LIMIT ?');
            $stmt->execute([$batchId, $limit]);
        } else {
            $stmt = self::pdo()->prepare('SELECT * FROM creation_jobs ORDER BY id DESC LIMIT ?');
            $stmt->execute([$limit]);
        }
        return $stmt->fetchAll();
    }

    public static function listPendingCreationJobs(): array
    {
        return self::pdo()->query("SELECT * FROM creation_jobs WHERE status = 'pending' ORDER BY id ASC")->fetchAll();
    }

    public static function claimNextPendingJob(): ?array
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $job = $pdo->query("SELECT * FROM creation_jobs WHERE status = 'pending' ORDER BY id ASC LIMIT 1")->fetch();
            if (!$job) {
                $pdo->commit();
                return null;
            }
            $stmt = $pdo->prepare("UPDATE creation_jobs SET status = 'creating', updated_at = ? WHERE id = ? AND status = 'pending'");
            $stmt->execute([self::now(), $job['id']]);
            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                return null;
            }
            $pdo->commit();
            return self::getCreationJob((int) $job['id']);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function resetStuckCreationJobs(int $minutes = 20): int
    {
        $cutoff = gmdate('c', time() - ($minutes * 60));
        $stmt = self::pdo()->prepare(
            "UPDATE creation_jobs SET status = 'failed', error = 'Server timeout — dubara Retry dabao', updated_at = ?
             WHERE status = 'creating' AND updated_at < ?"
        );
        $stmt->execute([self::now(), $cutoff]);
        return $stmt->rowCount();
    }

    public static function countActiveCreationJobs(): int
    {
        return (int) self::pdo()->query(
            "SELECT COUNT(*) FROM creation_jobs WHERE status IN ('pending','creating','waiting_code')"
        )->fetchColumn();
    }

    public static function exportAccounts(): string
    {
        $rows = self::pdo()->query(
            'SELECT username, proxy, group_name, notes, status, followers, following, posts_count, full_name FROM accounts'
        )->fetchAll();
        return json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
