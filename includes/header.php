<?php
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
$navItems = [
    'about' => ['About', 'about.php'],
    'features' => ['Features', 'features.php'],
    'how-to-install' => ['how-to-install', 'how-to-install.php'],
    'guide' => ['Guide', 'guide.php'],
];
?>
<header id="site-header" class="fixed top-8 left-1/2 -translate-x-1/2 w-[90%] md:w-[85%] lg:w-[65%] px-8 py-6 flex justify-between items-center bg-black/40 backdrop-blur-lg z-[100] border border-[#10b981]/20 rounded-full text-white shadow-[0_4px_30px_rgba(16,185,129,0.15)] transition-transform duration-300">
  <a href="<?= esc(siteUrl('RebelIrisai.php')) ?>" class="flex items-center gap-2 group">
    <img src="<?= assetUrl('img/Logo.png') ?>" alt="Logo" width="30" height="30" class="rounded-full group-hover:scale-105 transition-transform">
    <span class="text-lg sm:text-xl font-black tracking-tighter text-[#10b981] drop-shadow-[0_0_8px_rgba(16,185,129,0.5)]">IRIS</span>
  </a>
  <nav class="hidden md:flex gap-6 text-[10px] lg:text-xs font-bold uppercase tracking-[0.2em]">
    <?php foreach ($navItems as $key => [$label, $href]): ?>
      <a href="<?= esc(siteUrl($href)) ?>" class="hover:text-[#10b981] transition-all duration-300 relative group">
        <?= esc($label) ?>
        <span class="absolute -bottom-1 left-0 w-0 h-px bg-[#10b981] group-hover:w-full transition-all duration-300"></span>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="flex items-center gap-4">
    <a href="https://github.com/201Harsh" target="_blank" rel="noopener noreferrer" class="hidden md:flex text-zinc-400 hover:text-[#10b981] transition-colors" title="GitHub">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"/><path d="M9 18c-4.51 2-5-2-7-2"/></svg>
    </a>
    <a href="<?= esc(siteUrl('download.php')) ?>" class="hidden md:block px-4 py-2 rounded-full border border-[#10b981]/50 bg-[#10b981]/10 text-white text-[10px] font-bold tracking-[0.3em] uppercase hover:bg-[#099443] hover:shadow-[0_0_20px_rgba(16,185,129,0.4)] transition-all">Download IRIS</a>
    <button type="button" id="menu-open" class="md:hidden text-[#10b981] p-1 rounded-full hover:bg-[#10b981]/10" aria-label="Menu">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 5h16M4 12h16M4 19h16"/></svg>
    </button>
  </div>
</header>

<div id="mobile-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[101] md:hidden"></div>
<aside id="mobile-drawer" class="fixed top-0 right-0 h-full w-[80%] max-w-[320px] bg-[#050505] border-l border-[#10b981]/20 z-[102] flex flex-col p-8 shadow-[-20px_0_50px_rgba(0,0,0,0.8)] md:hidden">
  <div class="flex justify-between items-center mb-16">
    <a href="<?= esc(siteUrl('RebelIrisai.php')) ?>" class="flex items-center gap-3">
      <img src="<?= assetUrl('img/Logo.png') ?>" alt="Logo" width="30" height="30" class="rounded-full">
      <span class="text-xl font-black tracking-tighter text-[#10b981]">IRIS</span>
    </a>
    <button type="button" id="menu-close" class="p-2 text-gray-400 hover:text-[#10b981]">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>
  </div>
  <nav class="flex flex-col gap-10 text-sm font-bold uppercase tracking-[0.2em]">
    <?php foreach ($navItems as [$label, $href]): ?>
      <a href="<?= esc(siteUrl($href)) ?>" class="text-gray-300 hover:text-[#10b981] border-b border-white/5 pb-4"><?= esc($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="mt-auto pt-8 flex flex-col gap-6">
    <a href="https://github.com/201Harsh" target="_blank" rel="noopener" class="flex items-center justify-center gap-3 text-zinc-400 hover:text-[#10b981] text-xs font-bold tracking-[0.2em] uppercase">Open Source</a>
    <a href="<?= esc(siteUrl('download.php')) ?>" class="w-full text-center px-4 py-4 rounded-full border border-[#10b981]/50 bg-[#10b981]/10 text-[#10b981] text-xs font-bold tracking-[0.2em] uppercase hover:bg-[#10b981] hover:text-black">Download IRIS</a>
  </div>
</aside>
