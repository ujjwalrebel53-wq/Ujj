<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';
$PAGE_TITLE = 'About — IRIS AI';
$PAGE_DESC = 'The story behind IRIS — voice-first autonomous OS execution.';
$chapters = [
    ['01', 'The Passive Flaw', 'Most AI apps today are passive text boxes. You type, wait, and get text back. They do not DO anything. We realized the world does not need another chatbot.', 'PASSIVE AI'],
    ['02', 'Voice First', 'IRIS is designed for real-time conversation. Using custom WebSockets, your voice is streamed instantly to the engine without the painful lag of traditional APIs.', 'VOICE FIRST'],
    ['03', 'Autonomous Execution', 'Instead of giving you a summary, IRIS acts. It takes your voice command, processes the intent, and directly controls your files, applications, mouse, and keyboard.', 'EXECUTION'],
    ['04', 'The Cognitive Engine', 'IRIS maintains persistent memory across sessions — your preferences, projects, and identity stay wired into every command.', 'NEURAL MEMORY'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/includes/head.php'; ?>
</head>
<body class="bg-black text-white min-h-screen">
<?php require __DIR__ . '/includes/header.php'; ?>
<main class="pt-36 pb-24 px-6 max-w-4xl mx-auto">
  <p class="text-[#10b981] font-mono text-xs tracking-[0.35em] uppercase mb-4">IRIS // ORIGIN</p>
  <h1 class="text-4xl md:text-6xl font-black tracking-tighter mb-16 neon-title">About IRIS</h1>
  <?php foreach ($chapters as [$num, $title, $text, $tag]): ?>
  <article class="mb-20 border-l-2 border-[#10b981]/30 pl-8">
    <span class="text-[#10b981] font-mono text-sm"><?= esc($num) ?></span>
    <h2 class="text-2xl md:text-3xl font-bold mt-2 mb-4"><?= esc($title) ?></h2>
    <p class="text-gray-400 leading-relaxed mb-3"><?= esc($text) ?></p>
    <span class="text-[10px] font-mono uppercase tracking-widest text-zinc-600"><?= esc($tag) ?></span>
  </article>
  <?php endforeach; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
<script src="<?= assetUrl('js/site.js') ?>"></script>
</body>
</html>
