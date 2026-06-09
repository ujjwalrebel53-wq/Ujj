<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    header('Content-Type: application/json');
    try { echo json_encode(Install::run()); }
    catch (Throwable $e) { http_response_code(500); echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
    exit;
}
if (installed()) { header('Location: /'); exit; }
$s = Install::status();
?><!doctype html><html><head><meta charset=utf-8><meta name=viewport content="width=device-width,initial-scale=1"><title>RebelInsta Setup</title>
<style>*{box-sizing:border-box;margin:0}body{font:16px system-ui;background:#0a0a0f;color:#eee;min-height:100vh;display:grid;place-items:center;padding:20px}.c{background:#12121a;border:1px solid #333;border-radius:12px;padding:24px;max-width:420px;width:100%}h1{font-size:1.3rem;margin-bottom:12px}p{color:#888;margin-bottom:16px;font-size:.9rem}.ok{color:#22c55e}.bad{color:#ef4444}button{width:100%;padding:12px;border:0;border-radius:8px;background:#e1306c;color:#fff;font-weight:700;cursor:pointer;margin-top:12px}button:disabled{opacity:.5}#m{margin-top:12px;font-size:.85rem}</style></head><body>
<div class=c><h1>RebelInsta Setup</h1><p>Install dabao — DB + Python auto setup</p>
<div class="<?=$s['php']?'ok':'bad'?>"><?=$s['php']?'✓':'✗'?> PHP 8.1+</div>
<div class="<?=$s['sqlite']?'ok':'bad'?>"><?=$s['sqlite']?'✓':'✗'?> SQLite</div>
<div class="<?=$s['curl']?'ok':'bad'?>"><?=$s['curl']?'✓':'✗'?> cURL</div>
<div class="<?=$s['data']?'ok':'bad'?>"><?=$s['data']?'✓':'✗'?> data/ writable</div>
<button id=b <?=$s['ready']?'':'disabled'?> onclick="go()">Install</button><div id=m></div></div>
<script>async function go(){b.disabled=1;b.textContent='Installing...';try{const r=await fetch('/setup.php',{method:'POST'});const d=await r.json();if(d.success){m.textContent='Done!';setTimeout(()=>location.href='/',1200)}else{m.textContent=d.error||'Fail';b.disabled=0}}catch(e){m.textContent=e.message;b.disabled=0}}</script></body></html>
