<?php
declare(strict_types=1);
require __DIR__ . '/includes/config.php';
$PAGE_TITLE = 'Features — IRIS AI';
$features = [
    ['The OS Layer', 'IRIS does not just chat; it controls. It natively manages your file system, opens applications, and sorts directories automatically.', 'SYSTEM CONTROL'],
    ['Voice First', 'Typing is friction. IRIS streams audio over WebSockets for sub-second latency real-time conversation.', 'ZERO LATENCY'],
    ['Biometric Vault', 'Local face recognition verifies your presence before sensitive OS-level commands.', 'VISION SECURITY'],
    ['The Mobile Bridge', 'IRIS connects via ADB to read notifications, toggle hardware, and control your Android screen.', 'ECOSYSTEM LINK'],
    ['Neural Communication', 'Draft emails, send WhatsApp messages, or schedule delayed texts without touching the keyboard.', 'AUTONOMOUS COMMS'],
    ['Deep Research Agent', 'Autonomous web crawling and synthesis — research reports synced to your workflow.', 'RAG ENGINE'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/includes/head.php'; ?>
</head>
<body class="bg-black text-white min-h-screen">
<?php require __DIR__ . '/includes/header.php'; ?>
<main class="pt-36 pb-24 px-6 max-w-6xl mx-auto">
  <p class="text-[#10b981] font-mono text-xs tracking-[0.35em] uppercase mb-4 text-center">IRIS_OS // MODULES</p>
  <h1 class="text-4xl md:text-6xl font-black tracking-tighter mb-6 text-center neon-title">Features</h1>
  <p class="text-gray-400 text-center max-w-2xl mx-auto mb-16 font-mono text-sm">Full-spectrum agentic control — desktop, voice, mobile, and web.</p>
  <div class="grid md:grid-cols-2 gap-6">
    <?php foreach ($features as [$title, $text, $tag]): ?>
    <div class="p-8 rounded-2xl border border-[#10b981]/20 bg-black/60 backdrop-blur-md hover:border-[#10b981]/50 transition-colors">
      <span class="text-[10px] font-mono text-[#10b981] tracking-widest uppercase"><?= esc($tag) ?></span>
      <h2 class="text-xl font-bold mt-3 mb-3"><?= esc($title) ?></h2>
      <p class="text-gray-400 text-sm leading-relaxed"><?= esc($text) ?></p>
    </div>
    <?php endforeach; ?>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
<script src="<?= assetUrl('js/site.js') ?>"></script>
</body>
</html>
