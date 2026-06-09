<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        echo json_encode(Installer::runInstall());
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
  <title>RebelInsta Setup</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:system-ui,sans-serif;background:#0a0a0f;color:#f0f0f5;min-height:100vh;display:grid;place-items:center;padding:20px}
    .card{background:#12121a;border:1px solid #2a2a3a;border-radius:16px;padding:32px;max-width:520px;width:100%}
    h1{font-size:1.5rem;margin-bottom:8px}
    p{color:#888;margin-bottom:20px}
    .check{padding:6px 0;font-size:0.9rem}
    .ok{color:#22c55e}.bad{color:#ef4444}
    button{margin-top:20px;width:100%;padding:14px;border:none;border-radius:12px;background:#e1306c;color:#fff;font-size:1rem;font-weight:700;cursor:pointer}
    button:disabled{opacity:0.5}
    #msg{margin-top:14px;font-size:0.85rem}
  </style>
</head>
<body>
  <div class="card">
    <h1>RebelInsta Setup</h1>
    <p>Install dabao — sab automatic setup ho jayega.</p>
    <div class="check <?= $status['php_version']?'ok':'bad' ?>"><?= $status['php_version']?'✓':'✗' ?> PHP 8.1+</div>
    <div class="check <?= $status['pdo_sqlite']?'ok':'bad' ?>"><?= $status['pdo_sqlite']?'✓':'✗' ?> SQLite</div>
    <div class="check <?= $status['curl']?'ok':'bad' ?>"><?= $status['curl']?'✓':'✗' ?> cURL</div>
    <div class="check <?= $status['data_writable']?'ok':'bad' ?>"><?= $status['data_writable']?'✓':'✗' ?> Writable data/</div>
    <div class="check <?= $status['python']?'ok':'bad' ?>"><?= $status['python']?'✓':'✗' ?> Python</div>
    <button id="btn" <?= $status['ready']?'':'disabled' ?> onclick="go()">Install Now</button>
    <div id="msg"></div>
  </div>
  <script>
    async function go(){
      const b=document.getElementById('btn'),m=document.getElementById('msg');
      b.disabled=true;b.textContent='Installing...';
      try{
        const r=await fetch('/setup.php',{method:'POST'});
        const d=await r.json();
        if(d.success){m.textContent='Done!';setTimeout(()=>location.href='/',1500);}
        else{m.textContent=d.error||'Failed';b.disabled=false;b.textContent='Retry';}
      }catch(e){m.textContent=e.message;b.disabled=false;}
    }
  </script>
</body>
</html>
