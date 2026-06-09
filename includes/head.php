<?php
/** @var string $PAGE_TITLE */
/** @var string $PAGE_DESC */
$pageDesc = $PAGE_DESC ?? 'IRIS is a local-first AI Operating System layer that executes real-world actions across your system, apps, and devices.';
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?= esc($pageDesc) ?>">
<meta name="author" content="Harsh Pandey">
<title><?= esc($PAGE_TITLE) ?></title>
<link rel="icon" href="<?= assetUrl('img/Logo.png') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= assetUrl('css/iris-tailwind.css') ?>">
<link rel="stylesheet" href="<?= assetUrl('css/custom.css') ?>">
<style>
  body { font-family: 'Manrope', ui-sans-serif, system-ui, sans-serif; }
  .neon-title {
    background: url('<?= assetUrl('img/bright-neon-bg.png') ?>') center/cover;
    -webkit-background-clip: text; background-clip: text; color: transparent;
    filter: drop-shadow(0 0 15px rgba(57,255,20,1)) drop-shadow(0 0 50px rgba(57,255,20,.8));
  }
  .laptop-frame {
    max-width: 32rem; margin: 0 auto; padding: .5rem;
    border-radius: 1rem; background: #010101; border: 1px solid #272729;
    box-shadow: 0 25px 80px rgba(0,0,0,.8), 0 0 40px rgba(16,185,129,.1);
    transform: perspective(800px) rotateX(12deg);
  }
  .laptop-screen { border-radius: .5rem; overflow: hidden; border: 1px solid #1f1f23; }
  .terminal-frame {
    max-width: 56rem; margin: 2rem auto 0; padding: 1.5rem;
    border: 4px solid #6C6C6C; border-radius: 30px; background: #222;
    box-shadow: 0 37px 37px rgba(0,0,0,.26);
    transform: perspective(1000px) rotateX(12deg);
  }
  .marquee-track { display: flex; gap: 3.75rem; width: max-content; animation: marquee 30s linear infinite; }
  @keyframes marquee { to { transform: translateX(-50%); } }
  .bento-grid { display: grid; gap: 1px; background: rgba(16,185,129,.15); border: 1px solid rgba(16,185,129,.2); border-radius: 1rem; overflow: hidden; }
  @media (min-width: 768px) { .bento-grid { grid-template-columns: repeat(3, 1fr); } }
  .bento-card { background: #050505; padding: 2rem; transition: background .25s; }
  .bento-card:hover { background: rgba(16,185,129,.06); }
  #mobile-drawer { transform: translateX(100%); transition: transform .35s cubic-bezier(.22,1,.36,1); }
  #mobile-drawer.open { transform: translateX(0); }
  #mobile-overlay { opacity: 0; pointer-events: none; transition: opacity .3s; }
  #mobile-overlay.open { opacity: 1; pointer-events: auto; }
</style>
