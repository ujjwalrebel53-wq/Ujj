<footer class="border-t border-white/5 bg-black text-zinc-500 py-16 px-6">
  <div class="max-w-6xl mx-auto flex flex-col md:flex-row justify-between items-center gap-8">
    <div class="flex items-center gap-3">
      <img src="<?= assetUrl('img/Logo.png') ?>" alt="IRIS" width="36" height="36" class="rounded-full opacity-80">
      <div>
        <p class="text-white font-bold tracking-tight">IRIS AI</p>
        <p class="text-xs font-mono text-zinc-600 mt-1">IRIS_OS // NEURAL_EXTENSION</p>
      </div>
    </div>
    <div class="flex flex-wrap justify-center gap-6 text-xs font-bold uppercase tracking-[0.15em]">
      <a href="<?= esc(siteUrl('about.php')) ?>" class="hover:text-[#10b981] transition-colors">About</a>
      <a href="<?= esc(siteUrl('features.php')) ?>" class="hover:text-[#10b981] transition-colors">Features</a>
      <a href="<?= esc(siteUrl('guide.php')) ?>" class="hover:text-[#10b981] transition-colors">Guide</a>
      <a href="<?= esc(siteUrl('download.php')) ?>" class="hover:text-[#10b981] transition-colors">Download</a>
      <a href="<?= esc(siteUrl('apk.php')) ?>" class="hover:text-[#10b981] transition-colors">APK Config</a>
    </div>
    <p class="text-[10px] font-mono text-zinc-600">© <?= date('Y') ?> Harsh Pandey</p>
  </div>
</footer>
