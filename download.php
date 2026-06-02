<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';
$PAGE_TITLE = 'Download — IRIS AI';
$dlUrl = trim((string) ($cfg['apk_download'] ?? ''));
if ($dlUrl === '') {
    $dlUrl = 'https://github.com/201Harsh/IRIS-AI/releases/download/v1.3.0/iris-ai-1.3.0-setup.exe';
}
$miniCli = (string) ($cfg['mini_cli'] ?? 'npm install -g iris-mini');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/includes/head.php'; ?>
</head>
<body class="min-h-screen bg-black text-zinc-100 pt-24">
<?php require __DIR__ . '/includes/header.php'; ?>
<main class="max-w-4xl mx-auto px-6 py-16 text-center relative">
  <div class="absolute inset-0 bg-[#10b981]/5 blur-[120px] pointer-events-none"></div>
  <div class="relative z-10">
    <h1 class="text-5xl md:text-7xl font-black tracking-tighter mb-6 neon-title">Download IRIS</h1>
    <p class="text-zinc-400 font-mono text-sm mb-12 max-w-xl mx-auto">Local-first neural OS for Windows. Open source — bring your own API keys.</p>
    <a href="<?= esc($dlUrl) ?>" id="dl-btn" class="inline-flex items-center gap-3 px-10 py-5 rounded-2xl bg-emerald-500/20 border border-emerald-500/30 text-white font-bold text-lg hover:bg-emerald-500 hover:text-black shadow-[0_0_40px_rgba(16,185,129,0.25)] transition-all">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 15V3m9 12v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4m7-4 5 5 5-5"/></svg>
      Download for Windows
    </a>
    <p class="text-xs text-zinc-600 font-mono mt-6">v<?= esc((string) ($cfg['version'] ?? '1.3.0')) ?> · Setup .exe</p>
    <div class="mt-16 p-6 rounded-xl border border-zinc-800 bg-zinc-950 max-w-md mx-auto">
      <p class="text-sm text-zinc-400 mb-4 font-mono">IRIS Mini (CLI)</p>
      <div class="flex items-center justify-between bg-zinc-900 border border-zinc-800 rounded-lg p-3">
        <code class="text-sm text-[#10b981]"><?= esc($miniCli) ?></code>
        <button type="button" data-copy="<?= esc($miniCli) ?>" class="copy-btn p-2 text-gray-400 hover:text-white">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
        </button>
      </div>
    </div>
    <div id="warn-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
      <div class="bg-zinc-950 border border-[#10b981]/30 rounded-2xl p-8 max-w-md w-full">
        <h3 class="text-2xl font-bold text-emerald-400 mb-4">Installation Notice</h3>
        <p class="text-zinc-300 mb-6 text-sm leading-relaxed">Because IRIS is open-source without a corporate code certificate, Windows Defender might flag the installer. Click "More info" then "Run anyway".</p>
        <p class="text-2xl font-mono text-white mb-6" id="countdown">5</p>
        <button type="button" id="warn-cancel" class="text-sm text-zinc-500 hover:text-white">Cancel</button>
      </div>
    </div>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
<script src="<?= assetUrl('js/site.js') ?>"></script>
<script>
(function(){
  const btn=document.getElementById('dl-btn'),modal=document.getElementById('warn-modal'),cd=document.getElementById('countdown'),cancel=document.getElementById('warn-cancel');
  const url=btn.getAttribute('href'); let t;
  btn.addEventListener('click',function(e){e.preventDefault();modal.classList.remove('hidden');let n=5;cd.textContent=n;
    t=setInterval(function(){n--;cd.textContent=n;if(n<=0){clearInterval(t);window.location.href=url;setTimeout(function(){modal.classList.add('hidden')},800);}},1000);
  });
  cancel.addEventListener('click',function(){clearInterval(t);modal.classList.add('hidden');});
})();
</script>
</body>
</html>
