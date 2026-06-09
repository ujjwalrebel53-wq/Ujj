<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        $result = Installer::runInstall();
        echo json_encode($result);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if (Installer::isInstalled()) {
    header('Location: /');
    exit;
}

$status = Installer::status();
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>RebelInsta — Setup</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:system-ui,sans-serif;background:#0a0a0f;color:#f0f0f5;min-height:100vh;display:grid;place-items:center;padding:20px}
    .card{background:#12121a;border:1px solid #2a2a3a;border-radius:16px;padding:32px;max-width:520px;width:100%}
    h1{font-size:1.5rem;margin-bottom:8px;background:linear-gradient(135deg,#e1306c,#fcb045);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
    p{color:#8888a0;margin-bottom:24px;font-size:0.95rem}
    .check{display:flex;align-items:center;gap:10px;padding:8px 0;font-size:0.9rem}
    .ok{color:#22c55e}.bad{color:#ef4444}
    button{margin-top:24px;width:100%;padding:14px;border:none;border-radius:12px;background:linear-gradient(135deg,#e1306c,#fd1d1d);color:#fff;font-size:1rem;font-weight:700;cursor:pointer}
    button:disabled{opacity:0.5;cursor:not-allowed}
    #msg{margin-top:16px;font-size:0.85rem;white-space:pre-wrap}
  </style>
</head>
<body>
  <div class="card">
    <h1>RebelInsta Setup</h1>
    <p>Ek baar Install dabao — baaki sab automatic ho jayega.</p>
    <div>
      <div class="check <?= $status['php_version'] ? 'ok' : 'bad' ?>"><?= $status['php_version'] ? '✓' : '✗' ?> PHP 8.1+</div>
      <div class="check <?= $status['pdo_sqlite'] ? 'ok' : 'bad' ?>"><?= $status['pdo_sqlite'] ? '✓' : '✗' ?> SQLite</div>
      <div class="check <?= $status['curl'] ? 'ok' : 'bad' ?>"><?= $status['curl'] ? '✓' : '✗' ?> cURL</div>
      <div class="check <?= $status['data_writable'] ? 'ok' : 'bad' ?>"><?= $status['data_writable'] ? '✓' : '✗' ?> Data folder writable</div>
      <div class="check <?= $status['python'] ? 'ok' : 'bad' ?>"><?= $status['python'] ? '✓' : '✗' ?> Python (Instagram bridge)</div>
    </div>
    <button id="btn" <?= $status['ready'] ? '' : 'disabled' ?> onclick="install()">Install Now</button>
    <div id="msg"></div>
  </div>
  <script>
    async function install() {
      const btn = document.getElementById('btn');
      const msg = document.getElementById('msg');
      btn.disabled = true;
      btn.textContent = 'Installing...';
      msg.textContent = 'Database, Webshare proxy, Python bridge setup ho raha hai...';
      try {
        const res = await fetch('/setup.php', { method: 'POST' });
        const data = await res.json();
        if (data.success) {
          msg.textContent = 'Done! Redirecting...';
          setTimeout(() => location.href = '/', 1500);
        } else {
          msg.textContent = data.error || 'Install failed';
          btn.disabled = false;
          btn.textContent = 'Retry Install';
        }
      } catch (e) {
        msg.textContent = e.message;
        btn.disabled = false;
        btn.textContent = 'Retry Install';
      }
    }
  </script>
</body>
</html>
