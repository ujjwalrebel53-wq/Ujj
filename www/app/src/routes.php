<?php

declare(strict_types=1);

function routeApi(string $method, string $uri): void
{
    // /api/stats
    if ($uri === '/api/stats' && $method === 'GET') {
        Response::json(Database::getStats());
    }

    // /api/accounts
    if ($uri === '/api/accounts' && $method === 'GET') {
        $group = $_GET['group'] ?? null;
        Response::json(Database::listAccounts($group ?: null));
    }
    if ($uri === '/api/accounts' && $method === 'POST') {
        $data = Response::body();
        $username = ltrim(trim($data['username'] ?? ''), '@');
        if ($username === '') {
            Response::error('Username required');
        }
        if (Database::getAccountByUsername($username)) {
            Response::error('Account already exists', 409);
        }
        $password = $data['password'] ?? '';
        $proxy = ProxyManager::resolveProxy($data['proxy'] ?? '', (bool) ($data['use_webshare'] ?? true));
        $account = Database::createAccount([
            'username' => $username,
            'password_enc' => $password ? Crypto::encrypt($password) : '',
            'proxy' => $proxy,
            'group_name' => $data['group_name'] ?? 'default',
            'notes' => $data['notes'] ?? '',
        ]);
        if ($password) {
            try {
                $account = AccountManager::login($account['id'], $password);
            } catch (Throwable $e) {
                Response::json(['account' => $account, 'warning' => $e->getMessage()], 201);
            }
        }
        Response::json($account, 201);
    }

    // /api/accounts/bulk/login & refresh
    if ($uri === '/api/accounts/bulk/login' && $method === 'POST') {
        $ids = Response::body()['account_ids'] ?? [];
        Response::json(AccountManager::bulkLogin($ids));
    }
    if ($uri === '/api/accounts/bulk/refresh' && $method === 'POST') {
        $ids = Response::body()['account_ids'] ?? [];
        Response::json(AccountManager::bulkRefresh($ids));
    }

    // /api/accounts/{id}
    if (preg_match('#^/api/accounts/(\d+)$#', $uri, $m)) {
        $id = (int) $m[1];
        if ($method === 'GET') {
            $account = Database::getAccount($id);
            $account ? Response::json($account) : Response::error('Not found', 404);
        }
        if ($method === 'PUT') {
            if (!Database::getAccount($id)) {
                Response::error('Not found', 404);
            }
            $data = Response::body();
            $update = [];
            foreach (['proxy', 'group_name', 'notes'] as $f) {
                if (array_key_exists($f, $data)) {
                    $update[$f] = $data[$f];
                }
            }
            if (!empty($data['password'])) {
                $update['password_enc'] = Crypto::encrypt($data['password']);
            }
            Response::json(Database::updateAccount($id, $update));
        }
        if ($method === 'DELETE') {
            AccountManager::logout($id);
            Database::deleteAccount($id) ? Response::json(['ok' => true]) : Response::error('Not found', 404);
        }
    }

    if (preg_match('#^/api/accounts/(\d+)/login$#', $uri, $m) && $method === 'POST') {
        $data = Response::body();
        try {
            Response::json(AccountManager::login((int) $m[1], $data['password'] ?? null, $data['verification_code'] ?? null));
        } catch (Throwable $e) {
            $needs2fa = str_contains($e->getMessage(), '2FA');
            Response::json(['error' => $e->getMessage(), 'needs_2fa' => $needs2fa], 400);
        }
    }
    if (preg_match('#^/api/accounts/(\d+)/logout$#', $uri, $m) && $method === 'POST') {
        AccountManager::logout((int) $m[1]);
        Response::json(['ok' => true]);
    }
    if (preg_match('#^/api/accounts/(\d+)/refresh$#', $uri, $m) && $method === 'POST') {
        try {
            Response::json(AccountManager::refresh((int) $m[1]));
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
    if (preg_match('#^/api/accounts/(\d+)/post$#', $uri, $m) && $method === 'POST') {
        $id = (int) $m[1];
        if (empty($_FILES['media']['tmp_name'])) {
            Response::error('Media file required');
        }
        $ext = strtolower(pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            Response::error('Invalid file type');
        }
        $dir = DATA_PATH . '/uploads';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
        move_uploaded_file($_FILES['media']['tmp_name'], $path);
        try {
            $result = AccountManager::postPhoto($id, $path, $_POST['caption'] ?? '');
            Response::json($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    if ($uri === '/api/groups' && $method === 'GET') {
        Response::json(Database::listGroups());
    }
    if ($uri === '/api/activity' && $method === 'GET') {
        $limit = (int) ($_GET['limit'] ?? 50);
        Response::json(Database::listActivity($limit));
    }
    if ($uri === '/api/export' && $method === 'GET') {
        Response::json(['data' => Database::exportAccounts()]);
    }

    if ($uri === '/api/proxies/stats' && $method === 'GET') {
        try {
            Response::json(ProxyManager::getStats());
        } catch (Throwable $e) {
            Response::json(['error' => $e->getMessage(), 'enabled' => ProxyManager::getProxyUrl() !== ''], 500);
        }
    }
    if ($uri === '/api/proxies/refresh' && $method === 'POST') {
        try {
            $list = ProxyManager::fetchProxies(true);
            Response::json(['ok' => true, 'total_proxies' => count($list)]);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    // Creator routes
    if ($uri === '/api/creator/preview' && $method === 'GET') {
        $count = min((int) ($_GET['count'] ?? 5), 20);
        $prefix = $_GET['prefix'] ?? '';
        Response::json(AccountCreator::previewProfiles($count, $prefix));
    }
    if ($uri === '/api/creator/create' && $method === 'POST') {
        set_time_limit(300);
        $result = AccountCreator::startSingle(Response::body());
        Response::json($result, !empty($result['success']) ? 201 : 400);
    }
    if ($uri === '/api/creator/batch' && $method === 'POST') {
        $data = Response::body();
        if ((int) ($data['count'] ?? 0) < 1) {
            Response::error('count must be at least 1');
        }
        Response::json(AccountCreator::startBatch($data), 201);
    }
    if ($uri === '/api/creator/jobs' && $method === 'GET') {
        $batch = $_GET['batch_id'] ?? null;
        $limit = (int) ($_GET['limit'] ?? 50);
        Response::json(Database::listCreationJobs($batch ?: null, $limit));
    }
    if (preg_match('#^/api/creator/jobs/(\d+)$#', $uri, $m) && $method === 'GET') {
        $job = Database::getCreationJob((int) $m[1]);
        $job ? Response::json($job) : Response::error('Not found', 404);
    }
    if (preg_match('#^/api/creator/jobs/(\d+)/verify$#', $uri, $m) && $method === 'POST') {
        $data = Response::body();
        $code = trim($data['code'] ?? '');
        if ($code === '') {
            Response::error('Verification code required');
        }
        try {
            Response::json(AccountCreator::verifyJob((int) $m[1], $code));
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
    if (preg_match('#^/api/creator/jobs/(\d+)/retry$#', $uri, $m) && $method === 'POST') {
        try {
            Response::json(AccountCreator::retryJob((int) $m[1]));
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    Response::error('Not found', 404);
}
