<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
boot();

$m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$u = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';

if ($u === '/api/stats' && $m === 'GET') R::json(DB::stats());
if ($u === '/api/accounts' && $m === 'GET') R::json(DB::accounts($_GET['group'] ?? null));
if ($u === '/api/accounts' && $m === 'POST') {
    $d = R::body();
    $user = ltrim(trim($d['username'] ?? ''), '@');
    if ($user === '') R::err('Username required');
    if (DB::byUser($user)) R::err('Exists', 409);
    $pwd = $d['password'] ?? '';
    $acc = DB::addAcc(['username' => $user, 'password_enc' => $pwd ? Crypto::enc($pwd) : '', 'proxy' => Proxy::pick($d['proxy'] ?? '', (bool) ($d['use_webshare'] ?? true)), 'group_name' => $d['group_name'] ?? 'default', 'notes' => $d['notes'] ?? '']);
    if ($pwd) {
        try { $acc = Accounts::login($acc['id'], $pwd); } catch (Throwable $e) { R::json(['account' => $acc, 'warning' => $e->getMessage()], 201); }
    }
    R::json($acc, 201);
}
if (preg_match('#^/api/accounts/(\d+)$#', $u, $x)) {
    $id = (int) $x[1];
    if ($m === 'GET') { DB::getAcc($id) ? R::json(DB::getAcc($id)) : R::err('Not found', 404); }
    if ($m === 'PUT') {
        $d = R::body(); $up = [];
        foreach (['proxy', 'group_name', 'notes'] as $f) { if (array_key_exists($f, $d)) $up[$f] = $d[$f]; }
        if (!empty($d['password'])) $up['password_enc'] = Crypto::enc($d['password']);
        R::json(DB::updAcc($id, $up));
    }
    if ($m === 'DELETE') { Accounts::logout($id); DB::delAcc($id) ? R::json(['ok' => true]) : R::err('Not found', 404); }
}
if (preg_match('#^/api/accounts/(\d+)/login$#', $u, $x) && $m === 'POST') {
    $d = R::body();
    try { R::json(Accounts::login((int) $x[1], $d['password'] ?? null, $d['verification_code'] ?? null)); }
    catch (Throwable $e) { R::json(['error' => $e->getMessage()], 400); }
}
if (preg_match('#^/api/accounts/(\d+)/logout$#', $u, $x) && $m === 'POST') { Accounts::logout((int) $x[1]); R::json(['ok' => true]); }
if (preg_match('#^/api/accounts/(\d+)/refresh$#', $u, $x) && $m === 'POST') {
    try { R::json(Accounts::login((int) $x[1])); } catch (Throwable $e) { R::err($e->getMessage()); }
}
if (preg_match('#^/api/accounts/(\d+)/post$#', $u, $x) && $m === 'POST') {
    $id = (int) $x[1];
    if (empty($_FILES['media']['tmp_name'])) R::err('Photo required');
    $ext = strtolower(pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) R::err('Bad file');
    $path = DATA . '/uploads/' . bin2hex(random_bytes(8)) . ".$ext";
    move_uploaded_file($_FILES['media']['tmp_name'], $path);
    try { R::json(Accounts::post($id, $path, $_POST['caption'] ?? '')); }
    catch (Throwable $e) { R::err($e->getMessage()); }
    finally { is_file($path) && unlink($path); }
}
if ($u === '/api/activity' && $m === 'GET') R::json(DB::activity((int) ($_GET['limit'] ?? 40)));
if ($u === '/api/proxies/stats' && $m === 'GET') R::json(Proxy::stats());
if ($u === '/api/proxies/refresh' && $m === 'POST') { @unlink(DATA . '/proxies.json'); R::json(Proxy::stats()); }
if ($u === '/api/creator/preview' && $m === 'GET') R::json(Creator::preview((int) ($_GET['count'] ?? 5), $_GET['prefix'] ?? ''));
if ($u === '/api/creator/create' && $m === 'POST') R::json(Creator::queueSingle(R::body()), 202);
if ($u === '/api/creator/batch' && $m === 'POST') R::json(Creator::queueBatch(R::body()), 202);
if ($u === '/api/creator/jobs' && $m === 'GET') R::json(DB::jobs((int) ($_GET['limit'] ?? 30)));
if (preg_match('#^/api/creator/jobs/(\d+)$#', $u, $x) && $m === 'GET') { DB::job((int) $x[1]) ? R::json(DB::job((int) $x[1])) : R::err('Not found', 404); }
if (preg_match('#^/api/creator/jobs/(\d+)/verify$#', $u, $x) && $m === 'POST') {
    $c = trim(R::body()['code'] ?? '');
    if ($c === '') R::err('Code required');
    try { R::json(Creator::verify((int) $x[1], $c)); } catch (Throwable $e) { R::err($e->getMessage()); }
}
if (preg_match('#^/api/creator/jobs/(\d+)/retry$#', $u, $x) && $m === 'POST') {
    try { R::json(Creator::retry((int) $x[1])); } catch (Throwable $e) { R::err($e->getMessage()); }
}
if ($u === '/api/creator/tick' && in_array($m, ['GET', 'POST'], true)) {
    $key = $_GET['key'] ?? '';
    $sec = getenv('SECRET_KEY') ?: '';
    if ($sec !== '' && $key !== $sec) R::err('Unauthorized', 403);
    @set_time_limit(600);
    R::json(['processed' => Creator::runLoop(max((int) ($_GET['delay'] ?? 90), 60), 1)]);
}
if ($u === '/api/logs' && $m === 'GET') R::json(Log::tail((int) ($_GET['limit'] ?? 200)));
if ($u === '/api/logs/clear' && $m === 'POST') { file_put_contents(DATA . '/logs/live.log', ''); R::json(['ok' => true]); }

R::err('Not found', 404);
