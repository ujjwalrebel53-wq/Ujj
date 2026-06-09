<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';
$PAGE_TITLE = 'IRIS AI — The Autonomous Neural OS Agent';
$PAGE_DESC = 'IRIS is a local-first AI Operating System layer that executes real-world actions across your system, apps, and devices.';
$dlUrl = trim((string) ($cfg['apk_download'] ?? ''));
if ($dlUrl === '') {
    $dlUrl = 'https://github.com/201Harsh/IRIS-AI/releases/download/v1.3.0/iris-ai-1.3.0-setup.exe';
}
$cliCmd = (string) ($cfg['cli_command'] ?? 'npm install -g iris-ai');
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
<?php require __DIR__ . '/includes/head.php'; ?>
</head>
<body class="min-h-full flex flex-col font-sans antialiased bg-[#050505] text-white">
<div class="bg-black text-white relative">
<?php require __DIR__ . '/includes/header.php'; ?>

<section class="hero-section sticky top-0 h-screen w-full flex flex-col justify-center items-center z-0 overflow-hidden bg-black">
  <div class="hidden md:block w-full h-full absolute inset-0 z-0 bg-black">
    <canvas id="ripple-canvas" class="w-full h-full block"></canvas>
  </div>
  <div class="absolute inset-0 z-[90] pointer-events-none hidden md:block bg-black/15"></div>

  <div class="relative z-20 flex flex-col items-center justify-center text-center px-6 w-full max-w-5xl">
    <h1 class="text-[28vw] sm:text-[20vw] md:text-[13vw] font-black tracking-tighter leading-none select-none text-white" style="text-shadow:0 0 40px rgba(16,185,129,0.85),0 0 80px rgba(16,185,129,0.4),0 2px 6px rgba(0,0,0,1)">IRIS AI</h1>
    <hr class="iris-rule w-16 my-6">
    <p class="text-[11px] md:text-sm font-mono tracking-[0.35em] uppercase text-white mb-2 font-bold" style="text-shadow:0 0 8px #000,0 0 16px #000">Integrated Responsive Intelligence System</p>
    <p class="mt-5 max-w-xl text-sm md:text-lg text-white font-mono leading-relaxed" style="text-shadow:0 1px 8px #000,0 0 20px rgba(0,0,0,.95)">
      Your device. Fully under command. <span class="text-[#a7f3d0] font-bold">Speak once</span> — IRIS handles the rest. From files and apps to browser and beyond, <span class="text-white font-bold">real-time, zero friction.</span>
    </p>
    <div class="flex items-center gap-3 mt-6 mb-10">
      <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 19v3M19 10v2a7 7 0 0 1-14 0v-2"/><rect x="9" y="2" width="6" height="13" rx="3"/></svg>
      <div class="flex items-end gap-0.5 h-3.5">
        <?php for ($i = 0; $i < 7; $i++): ?><span class="iris-wave-bar"></span><?php endfor; ?>
      </div>
      <span class="text-[11px] font-mono text-white tracking-widest uppercase font-semibold">Voice-native AI</span>
    </div>
    <div class="flex md:flex-row flex-col justify-center items-center gap-5 w-full sm:w-auto">
      <a href="<?= esc(siteUrl('download.php')) ?>" class="group relative flex items-center justify-between px-8 py-5 rounded-2xl font-bold text-lg overflow-hidden w-full sm:w-auto min-w-[320px] bg-emerald-500/20 border border-emerald-500/30 text-white shadow-[0_0_30px_rgba(16,185,129,0.2)] hover:shadow-[0_0_60px_rgba(16,185,129,0.5)] hover:bg-emerald-500 hover:text-black transition-all">
        <span class="relative z-10 flex flex-col items-start leading-tight text-left">
          <span>Download IRIS</span>
          <span class="text-[11px] font-mono opacity-80 uppercase tracking-wider">Get the App</span>
        </span>
        <span class="relative z-10 w-10 h-10 rounded-full bg-black/10 flex items-center justify-center group-hover:bg-black">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 15V3m9 12v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4m7-4 5 5 5-5"/></svg>
        </span>
        <span class="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/40 to-transparent group-hover:animate-[shimmer_1.5s_infinite]"></span>
      </a>
      <a href="<?= esc(siteUrl('how-to-install.php')) ?>" class="group relative flex items-center justify-between px-8 py-5 rounded-2xl font-bold text-lg overflow-hidden w-full sm:w-auto min-w-[320px] bg-transparent border border-white/15 text-white hover:bg-white/80 hover:text-black backdrop-blur-sm">
        <span class="relative z-10 flex flex-col items-start leading-tight text-left">
          <span>How to Install</span>
          <span class="text-[11px] font-mono opacity-80 uppercase tracking-wider">Watch Tutorial</span>
        </span>
        <span class="relative z-10 w-10 h-10 rounded-full bg-black/10 flex items-center justify-center group-hover:bg-black">
          <svg class="w-5 h-5 text-[#10b981] group-hover:text-[#10b981]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14m-7-7 7 7-7 7"/></svg>
        </span>
      </a>
    </div>
    <div class="flex items-center gap-3 mt-10 flex-wrap justify-center">
      <?php foreach ([['Latency','<1.5s'],['Context','128k+'],['Uptime','24/7'],['Local AI','On-device']] as [$l,$v]): ?>
      <div class="flex items-center gap-2 px-3 py-1.5 rounded-md border border-[#10b981]/30 bg-black/80 backdrop-blur-md">
        <span class="text-[10px] font-mono text-gray-300 uppercase tracking-widest"><?= esc($l) ?></span>
        <span class="text-[11px] font-mono text-[#34d399] font-bold"><?= esc($v) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="absolute bottom-8 left-1/2 -translate-x-1/2 z-20 flex flex-col items-center gap-3 pointer-events-none">
    <span class="text-[10px] font-mono uppercase tracking-[0.35em] text-white/70">Scroll to Explore</span>
    <svg width="20" height="30" viewBox="0 0 20 30" fill="none" class="opacity-50"><rect x="1" y="1" width="18" height="28" rx="9" stroke="#10b981" stroke-width="1.5"/><rect x="9" y="6" width="2" height="6" rx="1" fill="#10b981" class="iris-scroll-dot"/></svg>
  </div>
</section>

<div class="relative z-10 bg-black shadow-[0_-20px_50px_rgba(0,0,0,0.8)]">

<section class="hidden md:flex min-h-screen bg-black justify-center items-center py-24 px-6">
  <div class="text-center max-w-5xl">
    <h2 class="text-6xl md:text-8xl font-bold tracking-tight neon-title mb-4 pb-2">Your AI. Your Rules.</h2>
    <p class="text-2xl md:text-4xl text-gray-100 font-normal tracking-tight" style="text-shadow:0 0 10px rgba(0,0,0,.8)">One Voice. Total Control Over Your Device.</p>
    <div class="laptop-frame mt-16">
      <div class="laptop-screen">
        <img src="<?= assetUrl('img/screen.png') ?>" alt="IRIS Screen" class="w-full h-auto object-cover object-left-top">
      </div>
      <a href="https://www.instagram.com/irisx.ai/" class="inline-block mt-6 opacity-90 hover:opacity-100">
        <img src="<?= assetUrl('img/Logo.png') ?>" alt="IRIS" width="80" height="80" class="rounded-full">
      </a>
    </div>
  </div>
</section>

<section class="hidden md:block min-h-screen bg-black relative z-20 py-24 px-6">
  <div class="max-w-5xl mx-auto text-center">
    <h2 class="text-4xl font-semibold text-white">Run IRIS straight from your <br><span class="text-4xl md:text-[6rem] font-bold neon-title inline-block pb-2">Terminal</span></h2>
    <div class="mt-8 flex items-center justify-center">
      <div class="relative flex items-center justify-between w-full max-w-sm bg-zinc-900 border border-zinc-800 rounded-lg p-3 shadow-xl">
        <code class="text-sm font-mono text-[#10b981]"><?= esc($cliCmd) ?></code>
        <button type="button" data-copy="<?= esc($cliCmd) ?>" class="copy-btn ml-4 p-2 rounded-md hover:bg-zinc-800 text-gray-400 hover:text-white" title="Copy">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
        </button>
      </div>
    </div>
    <div class="terminal-frame">
      <div class="rounded-2xl overflow-hidden bg-zinc-900 p-2">
        <img src="<?= assetUrl('img/cli.png') ?>" alt="IRIS CLI" class="w-full rounded-xl object-cover">
      </div>
    </div>
  </div>
</section>

<section class="min-h-screen bg-black flex flex-col items-center pt-32 pb-20 relative overflow-hidden">
  <div class="absolute top-[30%] left-1/2 -translate-x-1/2 w-[28rem] h-[28rem] bg-[#10b981]/15 rounded-full blur-[150px] pointer-events-none mix-blend-screen"></div>
  <div class="text-center z-20 px-4">
    <h2 class="text-6xl md:text-8xl lg:text-[9rem] font-bold tracking-tight neon-title mb-4 pb-2">Meet IRIS AI</h2>
    <p class="text-2xl md:text-4xl text-gray-100 font-normal tracking-tight">The Agentic Assistant Built for the Future</p>
  </div>
  <img src="<?= assetUrl('img/iris-future.png') ?>" alt="" class="absolute left-6 sm:left-44 top-52 w-40 h-40 object-contain pointer-events-none opacity-90 hidden sm:block" style="filter:drop-shadow(0 0 15px rgba(16,185,129,.6))">
  <div class="relative w-full max-w-6xl mt-16 flex justify-center z-10 px-4">
    <img src="<?= assetUrl('img/graphic.webp') ?>" alt="IRIS" class="w-[85%] object-contain" style="filter:drop-shadow(0 0 15px rgba(16,185,129,.6)) drop-shadow(0 0 45px rgba(16,185,129,.4))">
  </div>
  <div class="flex gap-4 sm:gap-6 relative z-20 mt-12 flex-wrap justify-center">
    <?php foreach ([['24/7','Autonomous'],['<1.5s','Latency'],['128K+','Context Window']] as [$v,$l]): ?>
    <div class="flex flex-col items-center justify-center w-28 h-28 sm:w-36 sm:h-36 rounded-3xl border border-[#10b981] bg-black/60 shadow-[0_0_20px_rgba(16,185,129,0.2)] backdrop-blur-md">
      <span class="text-3xl sm:text-5xl font-bold text-transparent bg-clip-text bg-gradient-to-b from-[#4ADE80] to-[#14532D]"><?= $v ?></span>
      <span class="text-[#10b981] text-sm sm:text-lg font-medium mt-1"><?= esc($l) ?></span>
    </div>
    <?php endforeach; ?>
    <img src="<?= assetUrl('img/tryiris.png') ?>" alt="" class="absolute -right-4 sm:-right-24 -top-24 w-32 sm:w-40 pointer-events-none hidden sm:block" style="filter:drop-shadow(0 0 15px rgba(16,185,129,.5))">
  </div>
</section>

<section class="w-full py-12 relative overflow-hidden flex flex-col items-center">
  <p class="text-[#10b981] text-sm tracking-widest uppercase mb-8 font-semibold drop-shadow-[0_0_10px_rgba(16,185,129,0.5)]">Built with a bleeding-edge modern stack</p>
  <div class="w-full overflow-hidden" style="mask-image:linear-gradient(90deg,transparent,#000 10%,#000 90%,transparent)">
    <div class="marquee-track text-2xl font-bold text-zinc-600">
      <span class="mx-8">Gemini</span><span class="mx-8 text-white">◆</span><span class="mx-8">Groq</span><span class="mx-8 text-white">◆</span><span class="mx-8">Electron</span><span class="mx-8 text-white">◆</span><span class="mx-8">React</span><span class="mx-8 text-white">◆</span><span class="mx-8">Tavily</span><span class="mx-8 text-white">◆</span><span class="mx-8">Hugging Face</span><span class="mx-8 text-white">◆</span><span class="mx-8">GSAP</span><span class="mx-8 text-white">◆</span>
      <span class="mx-8">Gemini</span><span class="mx-8 text-white">◆</span><span class="mx-8">Groq</span><span class="mx-8 text-white">◆</span><span class="mx-8">Electron</span><span class="mx-8 text-white">◆</span><span class="mx-8">React</span><span class="mx-8 text-white">◆</span><span class="mx-8">Tavily</span><span class="mx-8 text-white">◆</span><span class="mx-8">Hugging Face</span><span class="mx-8 text-white">◆</span><span class="mx-8">GSAP</span><span class="mx-8 text-white">◆</span>
    </div>
  </div>
</section>

<section id="systems" class="min-h-screen w-full px-6 md:px-20 py-32 border-b border-white/5 flex flex-col justify-center relative">
  <div class="max-w-7xl mx-auto w-full">
    <div class="text-center max-w-3xl mx-auto mb-16">
      <div class="inline-flex items-center gap-3 px-4 py-1.5 mb-6 border border-[#10b981]/20 bg-[#10b981]/5">
        <span class="w-1.5 h-1.5 bg-[#10b981] animate-pulse rounded-full"></span>
        <span class="text-[#10b981] font-mono text-[10px] md:text-xs tracking-[0.4em] uppercase font-bold">IRIS_OS // ACTIVE_MODULES</span>
      </div>
      <h2 class="text-4xl md:text-6xl font-black text-white tracking-tighter mb-6">System <span class="text-[#10b981] drop-shadow-[0_0_25px_rgba(16,185,129,0.3)]">Capabilities.</span></h2>
      <p class="text-gray-400 text-sm md:text-base leading-relaxed font-mono">IRIS is not a chatbot; it is a deep-system neural extension. By weaponizing <span class="text-white font-bold">kernel-level execution hooks</span>, autonomous keystroke injection, and a persistent memory matrix, IRIS bridges the gap between human thought and OS execution.</p>
    </div>
    <div class="bento-grid">
      <?php
      $caps = [
        ['Execution','Phantom Coder','Automated keystroke injection and real-time script generation.'],
        ['Integration','Deep OS Hooks','Direct kernel-level integration bypassing standard system APIs.'],
        ['Cognitive','Neural Memory Buffer','Persistent context recall across multiple sessions and tasks.'],
        ['Security','Zero-Trust Encryption','Hardware ID validation with biometric handshake protocols.'],
        ['Input','Acoustic Array','Sub-10ms voice intent interpretation and execution.'],
        ['Control','Process Override','Absolute control over background tasks and resource allocation.'],
      ];
      foreach ($caps as [$cat, $title, $desc]): ?>
      <article class="bento-card">
        <p class="text-[#10b981] text-[10px] font-bold uppercase tracking-[0.2em] mb-2"><?= esc($cat) ?></p>
        <h3 class="text-lg font-bold text-white mb-2"><?= esc($title) ?></h3>
        <p class="text-sm text-gray-500 leading-relaxed"><?= esc($desc) ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
</div>
</div>
<script src="<?= assetUrl('js/ripple-grid.js') ?>"></script>
<script src="<?= assetUrl('js/site.js') ?>"></script>
</body>
</html>
