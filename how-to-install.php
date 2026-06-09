<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';
$PAGE_TITLE = 'How to Install — IRIS AI';
$steps = [
    'Download the IRIS installer from the official download page.',
    'Run the setup executable (iris-ai-setup.exe on Windows).',
    'If Windows Defender shows a warning, click "More info" then "Run anyway" — IRIS is open source without a corporate certificate.',
    'Open IRIS from the desktop shortcut and complete the onboarding flow.',
    'Open Command Center → API Keys and add your Gemini and Groq keys.',
    'Grant microphone permissions when prompted for voice control.',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/includes/head.php'; ?>
</head>
<body class="bg-black text-white min-h-screen">
<?php require __DIR__ . '/includes/header.php'; ?>
<main class="pt-36 pb-24 px-6 max-w-3xl mx-auto">
  <p class="text-[#10b981] font-mono text-xs tracking-[0.35em] uppercase mb-4">DEPLOYMENT</p>
  <h1 class="text-4xl md:text-5xl font-black tracking-tighter mb-8">How to Install</h1>
  <ol class="space-y-6">
    <?php foreach ($steps as $i => $step): ?>
    <li class="flex gap-4">
      <span class="flex-shrink-0 w-8 h-8 rounded-full border border-[#10b981]/50 flex items-center justify-center text-[#10b981] font-mono text-sm"><?= $i + 1 ?></span>
      <p class="text-gray-300 leading-relaxed pt-1"><?= esc($step) ?></p>
    </li>
    <?php endforeach; ?>
  </ol>
  <div class="mt-12 p-6 rounded-xl border border-amber-500/30 bg-amber-500/5">
    <p class="text-amber-200/90 text-sm"><strong>System requirements:</strong> Windows 10/11, 8GB RAM recommended, ~5GB disk space for app and vector storage.</p>
  </div>
  <a href="<?= esc(siteUrl('download.php')) ?>" class="inline-flex mt-10 px-8 py-4 rounded-2xl bg-emerald-500/20 border border-emerald-500/30 text-white font-bold hover:bg-emerald-500 hover:text-black transition-all">Download IRIS</a>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
<script src="<?= assetUrl('js/site.js') ?>"></script>
</body>
</html>
