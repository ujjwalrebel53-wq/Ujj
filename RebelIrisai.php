<?php
/**
 * Rebel AI — Official Website (IRIS-style premium UI)
 * Created by Rebel Bhaiya (Ujjwal Tiwari)
 */
declare(strict_types=1);

$configFile = __DIR__ . '/rebel_apk_config.json';
$apkDefaults = [
    'app_name'       => 'Rebel AI',
    'tagline'        => 'Voice se phone par execution — Rebel Bhaiya ka assistant',
    'version'        => '1.0.0',
    'creator'        => 'Rebel Bhaiya (Ujjwal Tiwari)',
    'apk_download'   => '',
    'play_store'     => '',
    'telegram'       => '',
    'instagram'      => '',
    'github'         => '',
    'support_email'  => '',
    'primary_color'  => '#10b981',
    'accent_color'   => '#06b6d4',
];

function rebelLoadApkConfig(string $file, array $defaults): array
{
    if (!is_file($file)) {
        return $defaults;
    }
    $raw = json_decode((string) file_get_contents($file), true);
    return is_array($raw) ? array_merge($defaults, $raw) : $defaults;
}

$cfg = rebelLoadApkConfig($configFile, $apkDefaults);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$apkPageUrl = $scheme . '://' . $host . $basePath . '/apk.php';
$apkJsonUrl = $apkPageUrl . '?format=json';

$appName   = htmlspecialchars((string) ($cfg['app_name'] ?? 'Rebel AI'), ENT_QUOTES, 'UTF-8');
$tagline   = htmlspecialchars((string) ($cfg['tagline'] ?? ''), ENT_QUOTES, 'UTF-8');
$creator   = htmlspecialchars((string) ($cfg['creator'] ?? 'Rebel Bhaiya (Ujjwal Tiwari)'), ENT_QUOTES, 'UTF-8');
$version   = htmlspecialchars((string) ($cfg['version'] ?? '1.0.0'), ENT_QUOTES, 'UTF-8');
$primary   = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($cfg['primary_color'] ?? ''))
    ? (string) $cfg['primary_color'] : '#10b981';
$accent    = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($cfg['accent_color'] ?? ''))
    ? (string) $cfg['accent_color'] : '#06b6d4';

$apkLink  = trim((string) ($cfg['apk_download'] ?? ''));
$playLink = trim((string) ($cfg['play_store'] ?? ''));
$tg = trim((string) ($cfg['telegram'] ?? ''));
$ig = trim((string) ($cfg['instagram'] ?? ''));
$gh = trim((string) ($cfg['github'] ?? ''));
$email = trim((string) ($cfg['support_email'] ?? ''));

$dlHref = $apkLink !== '' ? htmlspecialchars($apkLink, ENT_QUOTES, 'UTF-8') : '#download';
$apkEsc = htmlspecialchars($apkPageUrl, ENT_QUOTES, 'UTF-8');
$jsonEsc = htmlspecialchars($apkJsonUrl, ENT_QUOTES, 'UTF-8');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= $appName ?> — Autonomous Neural OS for Mobile. Created by <?= $creator ?>.">
  <meta name="author" content="Ujjwal Tiwari">
  <title><?= $appName ?> — The Autonomous Neural OS Agent</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --emerald: <?= $primary ?>;
      --emerald-dim: #34d399;
      --emerald-glow: rgba(16, 185, 129, 0.45);
      --cyan: <?= $accent ?>;
      --bg: #050505;
      --black: #000;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body {
      font-family: 'Manrope', system-ui, sans-serif;
      background: var(--bg);
      color: #fff;
      overflow-x: hidden;
      -webkit-font-smoothing: antialiased;
    }
    .mono { font-family: 'JetBrains Mono', ui-monospace, monospace; }

    /* ── Nav pill ── */
    .nav-pill {
      position: fixed; top: 1.75rem; left: 50%; transform: translateX(-50%);
      z-index: 200; width: min(92%, 68rem);
      display: flex; align-items: center; justify-content: space-between;
      padding: 1rem 1.75rem;
      background: rgba(0,0,0,.45); backdrop-filter: blur(20px);
      border: 1px solid rgba(16,185,129,.22);
      border-radius: 999px;
      box-shadow: 0 4px 30px rgba(16,185,129,.12);
    }
    .nav-logo {
      display: flex; align-items: center; gap: .6rem;
      font-weight: 800; font-size: 1.15rem; letter-spacing: -.03em;
      color: var(--emerald); text-decoration: none;
      text-shadow: 0 0 12px var(--emerald-glow);
    }
    .nav-logo-icon {
      width: 2rem; height: 2rem; border-radius: 50%;
      background: linear-gradient(135deg, var(--emerald), var(--cyan));
      display: grid; place-items: center; font-size: .85rem;
      box-shadow: 0 0 20px var(--emerald-glow);
    }
    .nav-links { display: none; gap: 1.5rem; }
    @media (min-width: 768px) { .nav-links { display: flex; } }
    .nav-links a {
      font-size: .65rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .2em; color: #a1a1aa; text-decoration: none;
      position: relative; transition: color .3s;
    }
    .nav-links a::after {
      content: ''; position: absolute; bottom: -.25rem; left: 0;
      width: 0; height: 1px; background: var(--emerald);
      transition: width .3s;
    }
    .nav-links a:hover { color: var(--emerald); }
    .nav-links a:hover::after { width: 100%; }
    .nav-cta {
      padding: .45rem 1rem; border-radius: 999px;
      border: 1px solid rgba(16,185,129,.5);
      background: rgba(16,185,129,.1);
      color: #fff; font-size: .6rem; font-weight: 800;
      letter-spacing: .25em; text-transform: uppercase;
      text-decoration: none; transition: all .3s;
    }
    .nav-cta:hover {
      background: #099443; box-shadow: 0 0 24px rgba(16,185,129,.4);
    }
    .menu-btn {
      display: flex; background: none; border: none; color: var(--emerald);
      cursor: pointer; padding: .25rem;
    }
    @media (min-width: 768px) { .menu-btn { display: none; } }
    .mobile-menu {
      display: none; position: fixed; inset: 0; z-index: 190;
      background: rgba(0,0,0,.92); backdrop-filter: blur(12px);
      flex-direction: column; align-items: center; justify-content: center; gap: 2rem;
    }
    .mobile-menu.open { display: flex; }
    .mobile-menu a {
      font-size: 1.25rem; font-weight: 700; color: #fff; text-decoration: none;
      letter-spacing: .1em; text-transform: uppercase;
    }

    /* ── Hero ── */
    .hero {
      position: sticky; top: 0; height: 100vh; min-height: 600px;
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      overflow: hidden; background: var(--black);
    }
    #hero-canvas {
      position: absolute; inset: 0; width: 100%; height: 100%; z-index: 0;
    }
    .hero-overlay {
      position: absolute; inset: 0; z-index: 1;
      background: radial-gradient(ellipse 70% 50% at 50% 40%, rgba(16,185,129,.08), transparent 70%),
                  linear-gradient(180deg, transparent 60%, rgba(0,0,0,.8) 100%);
      pointer-events: none;
    }
    .hero-content {
      position: relative; z-index: 10; text-align: center;
      padding: 0 1.5rem; max-width: 56rem;
    }
    .hero-title {
      font-size: clamp(3.5rem, 16vw, 11rem);
      font-weight: 800; line-height: .95; letter-spacing: -.04em;
      color: #fff; user-select: none;
      text-shadow: 0 0 40px rgba(16,185,129,.85), 0 0 80px rgba(16,185,129,.35), 0 2px 6px #000;
    }
    .iris-rule {
      width: 4rem; height: 2px; border: none; margin: 1.25rem auto;
      background: linear-gradient(90deg, transparent, var(--emerald), transparent);
      box-shadow: 0 0 12px var(--emerald-glow);
    }
    .hero-subtitle {
      font-family: 'JetBrains Mono', monospace;
      font-size: clamp(.65rem, 2vw, .85rem);
      font-weight: 700; text-transform: uppercase;
      letter-spacing: .35em; color: #fff;
      text-shadow: 0 0 8px #000, 0 0 20px rgba(0,0,0,.9);
    }
    .hero-desc {
      margin-top: 1.25rem; max-width: 36rem; margin-left: auto; margin-right: auto;
      font-family: 'JetBrains Mono', monospace;
      font-size: clamp(.8rem, 2.5vw, 1.05rem); line-height: 1.7; color: #fff;
      text-shadow: 0 1px 8px #000, 0 0 20px rgba(0,0,0,.95);
    }
    .hero-desc .hl { color: #a7f3d0; font-weight: 700; }
    .voice-row {
      display: flex; align-items: center; justify-content: center;
      gap: .75rem; margin: 1.5rem 0 2rem;
    }
    .wave-bars { display: flex; align-items: flex-end; gap: 3px; height: 1.25rem; }
    .iris-wave-bar {
      width: 3px; border-radius: 2px; background: var(--emerald);
      animation: wave 1.2s ease-in-out infinite;
      box-shadow: 0 0 8px var(--emerald-glow);
    }
    .iris-wave-bar:nth-child(1) { animation-delay: 0s; height: 40%; }
    .iris-wave-bar:nth-child(2) { animation-delay: .1s; height: 70%; }
    .iris-wave-bar:nth-child(3) { animation-delay: .2s; height: 100%; }
    .iris-wave-bar:nth-child(4) { animation-delay: .3s; height: 60%; }
    .iris-wave-bar:nth-child(5) { animation-delay: .4s; height: 90%; }
    .iris-wave-bar:nth-child(6) { animation-delay: .5s; height: 50%; }
    .iris-wave-bar:nth-child(7) { animation-delay: .6s; height: 80%; }
    @keyframes wave {
      0%, 100% { transform: scaleY(.4); }
      50% { transform: scaleY(1); }
    }
  .voice-label {
      font-family: 'JetBrains Mono', monospace;
      font-size: .68rem; letter-spacing: .2em; text-transform: uppercase;
      color: #fff; font-weight: 600;
    }

    /* ── Buttons ── */
    .btn-row {
      display: flex; flex-direction: column; align-items: stretch;
      gap: 1rem; width: 100%; max-width: 22rem; margin: 0 auto;
    }
    @media (min-width: 640px) {
      .btn-row { flex-direction: row; max-width: none; width: auto; justify-content: center; }
    }
    .btn-iris {
      position: relative; display: flex; align-items: center; justify-content: space-between;
      gap: 1rem; padding: 1.1rem 1.75rem; border-radius: 1rem;
      font-weight: 700; font-size: 1rem; text-decoration: none;
      overflow: hidden; min-width: min(100%, 20rem);
      transition: box-shadow .3s, background .3s, color .3s;
    }
    .btn-iris-primary {
      background: rgba(16,185,129,.2); border: 1px solid rgba(16,185,129,.35);
      color: #fff; box-shadow: 0 0 30px rgba(16,185,129,.2);
    }
    .btn-iris-primary:hover {
      background: var(--emerald); color: #000;
      box-shadow: 0 0 60px rgba(16,185,129,.5);
    }
    .btn-iris-ghost {
      background: transparent; border: 1px solid rgba(255,255,255,.12);
      color: #fff; backdrop-filter: blur(8px);
    }
    .btn-iris-ghost:hover { background: rgba(255,255,255,.85); color: #000; }
    .btn-iris-inner { display: flex; align-items: center; gap: .75rem; z-index: 2; }
    .btn-iris-sub {
      display: block; font-family: 'JetBrains Mono', monospace;
      font-size: .65rem; font-weight: 500; opacity: .8;
      text-transform: uppercase; letter-spacing: .12em;
    }
    .btn-icon-circle {
      width: 2.5rem; height: 2.5rem; border-radius: 50%;
      background: rgba(0,0,0,.15); display: grid; place-items: center;
      flex-shrink: 0; transition: background .3s;
    }
    .btn-iris:hover .btn-icon-circle { background: rgba(0,0,0,.85); }
    .btn-shimmer {
      position: absolute; inset: 0;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,.25), transparent);
      transform: translateX(-100%);
    }
    .btn-iris:hover .btn-shimmer { animation: shimmer 1.5s infinite; }
    @keyframes shimmer { 100% { transform: translateX(100%); } }

    /* ── Stat pills ── */
    .stat-pills {
      display: flex; flex-wrap: wrap; justify-content: center; gap: .6rem; margin-top: 2rem;
    }
    .stat-pill {
      display: flex; align-items: center; gap: .5rem;
      padding: .4rem .75rem; border-radius: .4rem;
      border: 1px solid rgba(16,185,129,.3);
      background: rgba(0,0,0,.8); backdrop-filter: blur(12px);
    }
    .stat-pill span:first-child {
      font-family: 'JetBrains Mono', monospace;
      font-size: .58rem; color: #9ca3af; text-transform: uppercase; letter-spacing: .15em;
    }
    .stat-pill span:last-child {
      font-family: 'JetBrains Mono', monospace;
      font-size: .68rem; color: var(--emerald-dim); font-weight: 700;
    }

    /* ── Scroll hint ── */
    .scroll-hint {
      position: absolute; bottom: 2rem; left: 50%; transform: translateX(-50%);
      z-index: 20; display: flex; flex-direction: column; align-items: center; gap: .6rem;
      pointer-events: none;
    }
    .scroll-hint span {
      font-family: 'JetBrains Mono', monospace;
      font-size: .58rem; text-transform: uppercase; letter-spacing: .35em;
      color: rgba(255,255,255,.65);
    }
    .scroll-mouse {
      width: 20px; height: 30px; border-radius: 12px;
      border: 1.5px solid var(--emerald); position: relative; opacity: .6;
    }
    .scroll-dot {
      position: absolute; top: 6px; left: 50%; transform: translateX(-50%);
      width: 2px; height: 6px; border-radius: 1px; background: var(--emerald);
      animation: scrollDot 2s ease-in-out infinite;
    }
    @keyframes scrollDot {
      0%, 100% { opacity: 1; transform: translateX(-50%) translateY(0); }
      50% { opacity: .3; transform: translateX(-50%) translateY(10px); }
    }

    /* ── Main content ── */
    .main-wrap {
      position: relative; z-index: 20; background: var(--black);
      box-shadow: 0 -24px 60px rgba(0,0,0,.85);
    }
    .neon-text {
      font-weight: 800; letter-spacing: -.03em;
      background: linear-gradient(180deg, #4ade80 0%, #14532d 50%, #22c55e 100%);
      -webkit-background-clip: text; background-clip: text; color: transparent;
      filter: drop-shadow(0 0 15px rgba(57,255,20,.9)) drop-shadow(0 0 50px rgba(57,255,20,.5));
    }
    .section {
      padding: 6rem 1.5rem; position: relative; overflow: hidden;
    }
    .section-center { text-align: center; max-width: 72rem; margin: 0 auto; }
    .section-label {
      color: var(--emerald); font-size: .8rem; font-weight: 600;
      letter-spacing: .25em; text-transform: uppercase;
      text-shadow: 0 0 10px var(--emerald-glow); margin-bottom: 1.5rem;
    }
    .section-h2 {
      font-size: clamp(1.75rem, 5vw, 3rem); font-weight: 400;
      color: #f4f4f5; letter-spacing: -.02em; line-height: 1.2;
    }

    /* Phone mockup */
    .phone-section { min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; }
    .phone-wrap {
      margin-top: 3rem; perspective: 1200px;
    }
    .phone-device {
      width: min(280px, 75vw); margin: 0 auto;
      padding: 12px; border-radius: 2.5rem;
      background: linear-gradient(145deg, #2a2a2e, #0a0a0a);
      border: 3px solid #3f3f46;
      box-shadow: 0 0 0 1px rgba(16,185,129,.2), 0 40px 80px rgba(0,0,0,.8),
                  0 0 60px rgba(16,185,129,.15);
      transform: rotateX(8deg) rotateY(-4deg);
      transition: transform .6s ease;
    }
    .phone-device:hover { transform: rotateX(2deg) rotateY(0deg) scale(1.02); }
    .phone-notch {
      width: 90px; height: 22px; background: #000; border-radius: 0 0 14px 14px;
      margin: 0 auto 8px;
    }
    .phone-screen {
      border-radius: 1.5rem; overflow: hidden; aspect-ratio: 9/19;
      background: linear-gradient(180deg, #0a0f0d 0%, #050505 50%, #0a1512 100%);
      border: 1px solid rgba(16,185,129,.25);
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      padding: 1.5rem; position: relative;
    }
    .phone-screen::before {
      content: ''; position: absolute; inset: 0;
      background: radial-gradient(circle at 50% 30%, rgba(16,185,129,.15), transparent 60%);
    }
    .phone-orb {
      width: 5rem; height: 5rem; border-radius: 50%;
      background: radial-gradient(circle at 30% 30%, var(--emerald-dim), var(--emerald) 40%, #064e3b);
      box-shadow: 0 0 40px var(--emerald-glow), 0 0 80px rgba(16,185,129,.3);
      animation: pulseOrb 3s ease-in-out infinite;
      position: relative; z-index: 1;
    }
    @keyframes pulseOrb {
      0%, 100% { transform: scale(1); box-shadow: 0 0 40px var(--emerald-glow); }
      50% { transform: scale(1.06); box-shadow: 0 0 60px rgba(16,185,129,.6); }
    }
    .phone-label {
      margin-top: 1rem; font-family: 'JetBrains Mono', monospace;
      font-size: .65rem; letter-spacing: .2em; color: var(--emerald-dim);
      text-transform: uppercase; z-index: 1;
    }
    .phone-status {
      margin-top: .5rem; font-size: .75rem; color: #71717a; z-index: 1;
    }

    /* Meet section */
    .meet-glow {
      position: absolute; top: 30%; left: 50%; transform: translate(-50%, -50%);
      width: 28rem; height: 28rem; border-radius: 50%;
      background: rgba(16,185,129,.12); filter: blur(100px);
      pointer-events: none; mix-blend-mode: screen;
    }
    .meet-title { font-size: clamp(3rem, 12vw, 7rem); margin-bottom: 1rem; }
    .bento-stats {
      display: flex; flex-wrap: wrap; justify-content: center; gap: 1rem;
      margin-top: 3rem;
    }
    .bento-box {
      width: 7rem; height: 7rem;
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      border-radius: 1.25rem; border: 1px solid var(--emerald);
      background: rgba(0,0,0,.55); backdrop-filter: blur(12px);
      box-shadow: 0 0 24px rgba(16,185,129,.15);
    }
    @media (min-width: 640px) { .bento-box { width: 9rem; height: 9rem; border-radius: 1.5rem; } }
    .bento-val {
      font-size: 1.75rem; font-weight: 800;
      background: linear-gradient(180deg, #4ade80, #14532d);
      -webkit-background-clip: text; background-clip: text; color: transparent;
    }
    @media (min-width: 640px) { .bento-val { font-size: 2.25rem; } }
    .bento-lbl { color: var(--emerald); font-size: .75rem; font-weight: 500; margin-top: .25rem; }

    /* Capabilities */
    .cap-header { margin-bottom: 3rem; }
    .cap-tag {
      font-family: 'JetBrains Mono', monospace;
      font-size: .7rem; color: var(--emerald-dim);
      letter-spacing: .15em; margin-bottom: .5rem;
    }
    .cap-grid {
      display: grid; grid-template-columns: 1fr;
      gap: 1px; background: rgba(16,185,129,.15);
      border: 1px solid rgba(16,185,129,.2); border-radius: 1rem; overflow: hidden;
    }
    @media (min-width: 768px) { .cap-grid { grid-template-columns: repeat(3, 1fr); } }
    .cap-card {
      background: rgba(5,5,5,.95); padding: 2rem;
      transition: background .3s;
    }
    .cap-card:hover { background: rgba(16,185,129,.06); }
    .cap-cat {
      font-size: .65rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .2em; color: var(--emerald); margin-bottom: .75rem;
    }
    .cap-title { font-size: 1.15rem; font-weight: 700; margin-bottom: .5rem; }
    .cap-desc { font-size: .9rem; color: #71717a; line-height: 1.6; }

    /* Guide */
    .guide-grid {
      display: grid; gap: 1rem; margin-top: 2rem;
      grid-template-columns: 1fr;
    }
    @media (min-width: 640px) { .guide-grid { grid-template-columns: repeat(2, 1fr); } }
  .guide-card {
      padding: 1.5rem; border-radius: 1rem;
      border: 1px solid rgba(255,255,255,.08);
      background: rgba(0,0,0,.5); backdrop-filter: blur(12px);
      text-align: left;
    }
    .guide-card h4 { color: var(--emerald); font-size: .95rem; margin-bottom: .5rem; }
    .guide-card code {
      display: block; font-family: 'JetBrains Mono', monospace;
      font-size: .7rem; color: #6b7280; margin-bottom: .75rem;
    }
    .guide-card p { font-size: .85rem; color: #9ca3af; line-height: 1.5; }
    .badge-req {
      display: inline-block; font-size: .6rem; font-weight: 700;
      padding: .2rem .5rem; border-radius: 4px; margin-bottom: .5rem;
      text-transform: uppercase; letter-spacing: .08em;
    }
    .badge-req.yes { background: rgba(16,185,129,.2); color: var(--emerald-dim); }
    .badge-req.opt { background: rgba(113,113,122,.2); color: #a1a1aa; }

    /* Terminal block */
    .terminal-block {
      max-width: 28rem; margin: 2rem auto 0;
      display: flex; align-items: center; justify-content: space-between;
      background: #18181b; border: 1px solid #27272a;
      border-radius: .5rem; padding: .85rem 1rem;
      box-shadow: 0 20px 50px rgba(0,0,0,.5);
    }
    .terminal-block code { font-family: 'JetBrains Mono', monospace; font-size: .8rem; color: var(--emerald); }
    .copy-btn {
      background: none; border: none; color: #9ca3af; cursor: pointer; padding: .35rem;
      border-radius: .35rem; transition: color .2s, background .2s;
    }
    .copy-btn:hover { color: #fff; background: #27272a; }

    /* Download CTA */
    .download-box {
      margin-top: 2rem; padding: 3rem 2rem; border-radius: 1.5rem;
      border: 1px solid rgba(16,185,129,.25);
      background: radial-gradient(ellipse at center, rgba(16,185,129,.08), transparent 70%),
                  rgba(0,0,0,.6);
    }

    /* Footer */
    footer {
      padding: 3rem 1.5rem; border-top: 1px solid rgba(255,255,255,.06);
      text-align: center; color: #52525b; font-size: .85rem;
    }
    footer a { color: var(--emerald); text-decoration: none; }
    footer .foot-links { display: flex; flex-wrap: wrap; gap: 1.25rem; justify-content: center; margin-top: 1rem; }

    /* Logo marquee */
    .marquee-wrap {
      overflow: hidden; width: 100%; padding: 3rem 0;
      mask-image: linear-gradient(90deg, transparent, #000 10%, #000 90%, transparent);
    }
    .marquee-track {
      display: flex; gap: 3rem; width: max-content;
      animation: marquee 25s linear infinite;
    }
    @keyframes marquee {
      0% { transform: translateX(0); }
      100% { transform: translateX(-50%); }
    }
    .marquee-item {
      font-family: 'JetBrains Mono', monospace;
      font-size: 1.1rem; font-weight: 700; color: #3f3f46;
      white-space: nowrap; letter-spacing: .05em;
    }
    .marquee-item span { color: var(--emerald); }
  </style>
</head>
<body>

  <!-- Nav -->
  <header class="nav-pill">
    <a href="#" class="nav-logo">
      <span class="nav-logo-icon">⚡</span>
      REBEL
    </a>
    <nav class="nav-links">
      <a href="#about">About</a>
      <a href="#features">Features</a>
      <a href="#capabilities">Capabilities</a>
      <a href="#guide">Guide</a>
      <a href="#download">Download</a>
    </nav>
    <a href="<?= $dlHref ?>" class="nav-cta">Download</a>
    <button class="menu-btn" id="menuBtn" aria-label="Menu">
      <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5h16M4 12h16M4 19h16"/></svg>
    </button>
  </header>

  <div class="mobile-menu" id="mobileMenu">
    <a href="#about" class="mob-link">About</a>
    <a href="#features" class="mob-link">Features</a>
    <a href="#capabilities" class="mob-link">Capabilities</a>
    <a href="#guide" class="mob-link">Guide</a>
    <a href="#download" class="mob-link">Download</a>
    <a href="<?= $apkEsc ?>">APK Config</a>
  </div>

  <!-- Hero -->
  <section class="hero" id="top">
    <canvas id="hero-canvas"></canvas>
    <div class="hero-overlay"></div>
    <div class="hero-content">
      <h1 class="hero-title"><?= strtoupper($appName) ?></h1>
      <hr class="iris-rule">
      <p class="hero-subtitle">Rebel Integrated Responsive Intelligence System</p>
      <p class="hero-desc">
        Your phone. Fully under command.
        <span class="hl">Speak once</span> — Rebel handles the rest.
        Voice, files, apps, and beyond —
        <strong>real-time, zero friction.</strong>
      </p>
      <div class="voice-row">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 19v3M19 10v2a7 7 0 0 1-14 0v-2"/><rect x="9" y="2" width="6" height="13" rx="3"/></svg>
        <div class="wave-bars">
          <span class="iris-wave-bar"></span><span class="iris-wave-bar"></span><span class="iris-wave-bar"></span>
          <span class="iris-wave-bar"></span><span class="iris-wave-bar"></span><span class="iris-wave-bar"></span>
          <span class="iris-wave-bar"></span>
        </div>
        <span class="voice-label">Voice-native AI</span>
      </div>
      <div class="btn-row">
        <a href="<?= $dlHref ?>" class="btn-iris btn-iris-primary">
          <span class="btn-iris-inner">
            <span>
              <span>Download Rebel AI</span>
              <span class="btn-iris-sub">Get the App</span>
            </span>
          </span>
          <span class="btn-icon-circle">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 15V3m9 12v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4m7-4 5 5 5-5"/></svg>
          </span>
          <span class="btn-shimmer"></span>
        </a>
        <a href="<?= $apkEsc ?>" class="btn-iris btn-iris-ghost">
          <span class="btn-iris-inner">
            <span>
              <span>APK Config</span>
              <span class="btn-iris-sub">Customize Build</span>
            </span>
          </span>
          <span class="btn-icon-circle">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14m-7-7 7 7-7 7"/></svg>
          </span>
          <span class="btn-shimmer"></span>
        </a>
      </div>
      <div class="stat-pills">
        <div class="stat-pill"><span>Latency</span><span>&lt;1.5s</span></div>
        <div class="stat-pill"><span>Context</span><span>128k+</span></div>
        <div class="stat-pill"><span>Uptime</span><span>24/7</span></div>
        <div class="stat-pill"><span>Mobile</span><span>On-device</span></div>
      </div>
      <p class="mono" style="margin-top:1rem;font-size:.7rem;color:#52525b;">v<?= $version ?> · <?= $creator ?></p>
    </div>
    <div class="scroll-hint">
      <span>Scroll to Explore</span>
      <div class="scroll-mouse"><div class="scroll-dot"></div></div>
    </div>
  </section>

  <div class="main-wrap">

    <!-- Your AI -->
    <section class="section phone-section" id="about">
      <div class="section-center">
        <h2 class="neon-text" style="font-size:clamp(2.5rem,8vw,5rem);">Your AI. Your Rules.</h2>
        <p class="section-h2" style="margin-top:1rem;">One Voice. Total Control Over Your Phone.</p>
        <div class="phone-wrap">
          <div class="phone-device">
            <div class="phone-notch"></div>
            <div class="phone-screen">
              <div class="phone-orb"></div>
              <p class="phone-label"><?= $appName ?></p>
              <p class="phone-status">Neural uplink active</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Terminal -->
    <section class="section" style="padding-top:2rem;padding-bottom:4rem;">
      <div class="section-center">
        <h2 class="section-h2">Configure Rebel from <span class="neon-text" style="font-size:inherit;">Server</span></h2>
        <div class="terminal-block">
          <code id="configCmd">apk.php?format=json</code>
          <button class="copy-btn" id="copyBtn" title="Copy">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
          </button>
        </div>
        <p style="margin-top:1rem;color:#71717a;font-size:.9rem;">APK settings · colors · download link · <a href="<?= $apkEsc ?>" style="color:var(--emerald)">apk.php</a></p>
      </div>
    </section>

    <!-- Meet Rebel -->
    <section class="section" id="features" style="padding-top:8rem;">
      <div class="meet-glow"></div>
      <div class="section-center" style="position:relative;z-index:2;">
        <h2 class="meet-title neon-text">Meet <?= $appName ?></h2>
        <p class="section-h2">The Agentic Assistant Built for Mobile</p>
        <p style="margin-top:1rem;color:#71717a;max-width:32rem;margin-left:auto;margin-right:auto;">
          <?= $tagline ?: 'Execution over conversation. Rebel Bhaiya ka vision — phone par JARVIS-level control.' ?>
        </p>
        <div class="bento-stats">
          <div class="bento-box"><span class="bento-val">24/7</span><span class="bento-lbl">Autonomous</span></div>
          <div class="bento-box"><span class="bento-val">&lt;1.5s</span><span class="bento-lbl">Latency</span></div>
          <div class="bento-box"><span class="bento-val">128K+</span><span class="bento-lbl">Context</span></div>
        </div>
      </div>
    </section>

    <!-- Marquee -->
    <div class="marquee-wrap">
      <div class="marquee-track">
        <span class="marquee-item"><span>◆</span> GEMINI</span>
        <span class="marquee-item"><span>◆</span> GROQ</span>
        <span class="marquee-item"><span>◆</span> VOICE LIVE</span>
        <span class="marquee-item"><span>◆</span> ANDROID</span>
        <span class="marquee-item"><span>◆</span> BYOK SECURE</span>
        <span class="marquee-item"><span>◆</span> REBEL OS</span>
        <span class="marquee-item"><span>◆</span> GEMINI</span>
        <span class="marquee-item"><span>◆</span> GROQ</span>
        <span class="marquee-item"><span>◆</span> VOICE LIVE</span>
        <span class="marquee-item"><span>◆</span> ANDROID</span>
        <span class="marquee-item"><span>◆</span> BYOK SECURE</span>
        <span class="marquee-item"><span>◆</span> REBEL OS</span>
      </div>
    </div>

    <!-- Capabilities -->
    <section class="section" id="capabilities">
      <div class="section-center cap-header">
        <p class="cap-tag mono">REBEL_OS // ACTIVE_MODULES</p>
        <h2 class="section-h2" style="font-size:clamp(1.5rem,4vw,2.5rem);">System <span class="neon-text" style="font-size:inherit;">Capabilities.</span></h2>
        <p style="margin-top:1rem;color:#71717a;max-width:40rem;margin:0 auto;line-height:1.7;">
          Rebel is not a chatbot; it is a neural extension for your phone.
          Voice intent becomes real execution — files, apps, memory, and automation.
        </p>
      </div>
      <div class="cap-grid" style="max-width:72rem;margin:0 auto;">
        <article class="cap-card">
          <p class="cap-cat">Execution</p>
          <h3 class="cap-title">Voice Command Engine</h3>
          <p class="cap-desc">Sub-second voice interpretation. Bol ke kaam — WhatsApp, notes, apps, search.</p>
        </article>
        <article class="cap-card">
          <p class="cap-cat">Integration</p>
          <h3 class="cap-title">Deep Mobile Hooks</h3>
          <p class="cap-desc">Android-native layer. Intents, notifications, gallery, aur smart file access.</p>
        </article>
        <article class="cap-card">
          <p class="cap-cat">Cognitive</p>
          <h3 class="cap-title">Neural Memory Buffer</h3>
          <p class="cap-desc">Persistent context across sessions. Rebel tumhari baatein yaad rakhta hai.</p>
        </article>
        <article class="cap-card">
          <p class="cap-cat">Security</p>
          <h3 class="cap-title">Zero-Trust BYOK</h3>
          <p class="cap-desc">API keys device par encrypted. Tumhari keys, tumhare rules.</p>
        </article>
        <article class="cap-card">
          <p class="cap-cat">Input</p>
          <h3 class="cap-title">Acoustic Array</h3>
          <p class="cap-desc">Gemini Live pipeline. Natural Hinglish, instant response, hands-free.</p>
        </article>
        <article class="cap-card">
          <p class="cap-cat">Control</p>
          <h3 class="cap-title">Server Config Sync</h3>
          <p class="cap-desc">apk.php se APK customize — name, URL, theme, version ek jagah.</p>
        </article>
      </div>
    </section>

    <!-- Guide -->
    <section class="section" id="guide">
      <div class="section-center">
        <p class="section-label">System Keys</p>
        <h2 class="section-h2">Initialize the <span class="neon-text" style="font-size:inherit;">Neural Engine</span></h2>
        <p style="margin-top:1rem;color:#71717a;max-width:36rem;margin:0 auto;">
          Rebel operates locally on your phone. Bring your own API keys — stored on device, never on our servers.
        </p>
        <div class="guide-grid" style="max-width:48rem;margin-left:auto;margin-right:auto;">
          <div class="guide-card">
            <span class="badge-req yes">Required</span>
            <h4>Google Gemini API</h4>
            <code>GEMINI_API_KEY</code>
            <p>Primary reasoning and voice engine for Rebel AI.</p>
          </div>
          <div class="guide-card">
            <span class="badge-req yes">Required</span>
            <h4>Groq API</h4>
            <code>GROQ_API_KEY</code>
            <p>Ultra-fast routing and low-latency decisions.</p>
          </div>
          <div class="guide-card">
            <span class="badge-req opt">Optional</span>
            <h4>Tavily Search</h4>
            <code>TAVILY_API_KEY</code>
            <p>Deep web research and live search agent.</p>
          </div>
          <div class="guide-card">
            <span class="badge-req opt">Optional</span>
            <h4>Hugging Face</h4>
            <code>HUGGINGFACE_API_KEY</code>
            <p>Local model downloads when needed.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Download -->
    <section class="section" id="download">
      <div class="section-center">
        <p class="section-label">Deploy</p>
        <h2 class="section-h2">Download <span class="neon-text" style="font-size:inherit;"><?= $appName ?></span></h2>
        <div class="download-box">
          <?php if ($apkLink !== ''): ?>
            <p style="color:#a1a1aa;margin-bottom:1.5rem;">Latest build <strong style="color:#fff;">v<?= $version ?></strong> ready.</p>
            <a href="<?= $dlHref ?>" class="btn-iris btn-iris-primary" style="margin:0 auto;max-width:20rem;">
              <span class="btn-iris-inner"><span>Download APK</span></span>
              <span class="btn-icon-circle">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 15V3m9 12v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4m7-4 5 5 5-5"/></svg>
              </span>
              <span class="btn-shimmer"></span>
            </a>
          <?php else: ?>
            <p style="color:#71717a;margin-bottom:1rem;">APK link set nahi hai.</p>
            <a href="<?= $apkEsc ?>" class="btn-iris btn-iris-primary" style="margin:0 auto;max-width:20rem;">
              <span class="btn-iris-inner"><span>Open APK Config Panel</span></span>
              <span class="btn-shimmer"></span>
            </a>
            <p style="margin-top:1rem;font-size:.85rem;color:#52525b;">apk.php → <code class="mono">apk_download</code> field bharo</p>
          <?php endif; ?>
          <?php if ($playLink !== ''): ?>
            <p style="margin-top:1.5rem;"><a href="<?= htmlspecialchars($playLink, ENT_QUOTES, 'UTF-8') ?>" style="color:var(--emerald)">Play Store →</a></p>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <footer>
      <p>© <?= date('Y') ?> <?= $appName ?> · Created by <strong style="color:#a1a1aa;"><?= $creator ?></strong></p>
      <p style="margin-top:.5rem;font-size:.75rem;" class="mono">REBEL_OS // SYSTEM ONLINE</p>
      <div class="foot-links">
        <a href="<?= $apkEsc ?>">APK Config</a>
        <a href="<?= $jsonEsc ?>">JSON API</a>
        <?php if ($tg): ?><a href="<?= htmlspecialchars($tg, ENT_QUOTES, 'UTF-8') ?>">Telegram</a><?php endif; ?>
        <?php if ($ig): ?><a href="<?= htmlspecialchars($ig, ENT_QUOTES, 'UTF-8') ?>">Instagram</a><?php endif; ?>
        <?php if ($gh): ?><a href="<?= htmlspecialchars($gh, ENT_QUOTES, 'UTF-8') ?>">GitHub</a><?php endif; ?>
        <?php if ($email): ?><a href="mailto:<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">Email</a><?php endif; ?>
      </div>
    </footer>
  </div>

  <script>
    (function () {
      const canvas = document.getElementById('hero-canvas');
      const ctx = canvas.getContext('2d');
      let w, h, particles = [], animId;

      function resize() {
        w = canvas.width = window.innerWidth;
        h = canvas.height = window.innerHeight;
      }

      function initParticles() {
        particles = [];
        const n = Math.min(120, Math.floor((w * h) / 12000));
        for (let i = 0; i < n; i++) {
          particles.push({
            x: Math.random() * w,
            y: Math.random() * h,
            r: Math.random() * 1.5 + 0.3,
            vx: (Math.random() - 0.5) * 0.35,
            vy: (Math.random() - 0.5) * 0.35,
            a: Math.random() * 0.5 + 0.15
          });
        }
      }

      function draw() {
        ctx.fillStyle = 'rgba(0,0,0,0.15)';
        ctx.fillRect(0, 0, w, h);
        const emerald = '16, 185, 129';
        particles.forEach((p, i) => {
          p.x += p.vx; p.y += p.vy;
          if (p.x < 0) p.x = w; if (p.x > w) p.x = 0;
          if (p.y < 0) p.y = h; if (p.y > h) p.y = 0;
          ctx.beginPath();
          ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
          ctx.fillStyle = 'rgba(' + emerald + ',' + p.a + ')';
          ctx.fill();
          for (let j = i + 1; j < particles.length; j++) {
            const q = particles[j];
            const dx = p.x - q.x, dy = p.y - q.y;
            const dist = Math.sqrt(dx * dx + dy * dy);
            if (dist < 100) {
              ctx.beginPath();
              ctx.moveTo(p.x, p.y);
              ctx.lineTo(q.x, q.y);
              ctx.strokeStyle = 'rgba(' + emerald + ',' + (0.12 * (1 - dist / 100)) + ')';
              ctx.lineWidth = 0.5;
              ctx.stroke();
            }
          }
        });
        animId = requestAnimationFrame(draw);
      }

      resize();
      initParticles();
      draw();
      window.addEventListener('resize', () => { resize(); initParticles(); });

      document.getElementById('menuBtn').addEventListener('click', () => {
        document.getElementById('mobileMenu').classList.toggle('open');
      });
      document.querySelectorAll('.mob-link').forEach(a => {
        a.addEventListener('click', () => document.getElementById('mobileMenu').classList.remove('open'));
      });

      document.getElementById('copyBtn').addEventListener('click', () => {
        const url = <?= json_encode($apkJsonUrl) ?>;
        navigator.clipboard.writeText(url).then(() => {
          const btn = document.getElementById('copyBtn');
          btn.style.color = '#10b981';
          setTimeout(() => { btn.style.color = ''; }, 1500);
        });
      });
    })();
  </script>
</body>
</html>
