<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';
$PAGE_TITLE = 'System Keys — IRIS AI';
$keys = [
    ['Google Gemini API', 'Required', 'GEMINI_API_KEY', 'The primary reasoning and generative engine for IRIS.', 'https://aistudio.google.com/app/apikey', 'Get Gemini Key'],
    ['Groq API', 'Required', 'GROQ_API_KEY', 'Ultra-fast, low-latency agent routing and quick decisions.', 'https://console.groq.com/keys', 'Get Groq Key'],
    ['Tavily Search API', 'Optional', 'TAVILY_API_KEY', 'Powers the Deep Research agent for real-time web crawling.', 'https://app.tavily.com/home', 'Get Tavily Key'],
    ['Hugging Face Token', 'Optional', 'HUGGINGFACE_API_KEY', 'Required only if downloading local inference models.', 'https://huggingface.co/settings/tokens', 'Get HF Token'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/includes/head.php'; ?>
</head>
<body class="bg-black text-emerald-50 min-h-screen selection:bg-emerald-500/30">
<?php require __DIR__ . '/includes/header.php'; ?>
<main class="grow p-6 md:p-12 lg:p-24 pt-36 max-w-4xl mx-auto">
  <div class="flex items-center gap-3 mb-4 text-emerald-500">
    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 17l6-6-6-6M12 19h8"/></svg>
    <h1 class="text-4xl md:text-5xl font-bold tracking-tight text-white">System Keys</h1>
  </div>
  <p class="text-lg text-emerald-100/60 leading-relaxed mb-12">IRIS operates locally, but requires specific API keys to bridge the gap to large language models and search engines. Your keys are stored locally on your machine and never sent to our servers.</p>
  <div class="space-y-6">
    <?php foreach ($keys as [$name, $status, $env, $desc, $link, $btn]): ?>
    <article class="rounded-2xl border border-emerald-500/20 bg-emerald-950/20 p-6 md:p-8 hover:border-emerald-500/40 transition-colors">
      <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
        <h2 class="text-xl font-bold text-white"><?= esc($name) ?></h2>
        <span class="text-[10px] font-bold uppercase tracking-widest px-2 py-1 rounded <?= $status === 'Required' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-zinc-800 text-zinc-400' ?>"><?= esc($status) ?></span>
      </div>
      <code class="block text-sm font-mono text-emerald-500/80 mb-3">.env: <?= esc($env) ?></code>
      <p class="text-emerald-100/50 text-sm mb-6"><?= esc($desc) ?></p>
      <a href="<?= esc($link) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 text-sm font-bold text-emerald-400 hover:text-emerald-300"><?= esc($btn) ?> →</a>
    </article>
    <?php endforeach; ?>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
<script src="<?= assetUrl('js/site.js') ?>"></script>
</body>
</html>
