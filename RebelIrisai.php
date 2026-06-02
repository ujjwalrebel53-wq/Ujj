<?php
/**
 * Rebel AI — Official Website
 * Created by Rebel Bhaiya (Ujjwal Tiwari)
 *
 * Deploy: upload to your server and open RebelIrisai.php
 * APK config: apk.php (same folder)
 */

declare(strict_types=1);

$configFile = __DIR__ . '/rebel_apk_config.json';
$apkDefaults = [
    'app_name'       => 'Rebel AI',
    'tagline'        => 'Voice se phone par execution — Rebel Bhaiya ka assistant',
    'version'        => '1.0.0',
    'version_code'   => 1,
    'creator'        => 'Rebel Bhaiya (Ujjwal Tiwari)',
    'website_url'    => '',
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

$selfUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    . strtok($_SERVER['REQUEST_URI'] ?? '/RebelIrisai.php', '?');

$apkPageUrl = dirname($selfUrl) . '/apk.php';
$apkJsonUrl = $apkPageUrl . '?format=json';

$appName   = htmlspecialchars((string) ($cfg['app_name'] ?? 'Rebel AI'), ENT_QUOTES, 'UTF-8');
$tagline   = htmlspecialchars((string) ($cfg['tagline'] ?? ''), ENT_QUOTES, 'UTF-8');
$creator   = htmlspecialchars((string) ($cfg['creator'] ?? 'Rebel Bhaiya (Ujjwal Tiwari)'), ENT_QUOTES, 'UTF-8');
$version   = htmlspecialchars((string) ($cfg['version'] ?? '1.0.0'), ENT_QUOTES, 'UTF-8');
$primary   = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($cfg['primary_color'] ?? ''))
    ? (string) $cfg['primary_color'] : '#10b981';
$accent    = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($cfg['accent_color'] ?? ''))
    ? (string) $cfg['accent_color'] : '#06b6d4';

$apkLink = trim((string) ($cfg['apk_download'] ?? ''));
$playLink = trim((string) ($cfg['play_store'] ?? ''));
$tg = trim((string) ($cfg['telegram'] ?? ''));
$ig = trim((string) ($cfg['instagram'] ?? ''));
$gh = trim((string) ($cfg['github'] ?? ''));
$email = trim((string) ($cfg['support_email'] ?? ''));

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= $appName ?> — Voice-first AI assistant. Created by <?= $creator ?>.">
  <meta name="author" content="Ujjwal Tiwari">
  <title><?= $appName ?> | Rebel Bhaiya</title>
  <style>
    :root {
      --bg: #030303;
      --panel: rgba(0,0,0,.45);
      --border: rgba(255,255,255,.08);
      --text: #e4e4e7;
      --muted: #71717a;
      --primary: <?= $primary ?>;
      --accent: <?= $accent ?>;
      --glow: color-mix(in srgb, var(--primary) 35%, transparent);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body {
      font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
      background: var(--bg);
      color: var(--text);
      line-height: 1.6;
      min-height: 100vh;
      overflow-x: hidden;
    }
    body::before {
      content: "";
      position: fixed;
      inset: 0;
      background:
        radial-gradient(ellipse 80% 50% at 50% -20%, var(--glow), transparent),
        radial-gradient(circle at 90% 80%, color-mix(in srgb, var(--accent) 15%, transparent), transparent 40%);
      pointer-events: none;
      z-index: 0;
    }
    .wrap { position: relative; z-index: 1; max-width: 1100px; margin: 0 auto; padding: 0 20px 80px; }
    nav {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 20px 0;
      border-bottom: 1px solid var(--border);
      margin-bottom: 48px;
    }
    .logo {
      font-weight: 800;
      font-size: 1.25rem;
      letter-spacing: .04em;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .logo span {
      width: 36px; height: 36px;
      border-radius: 12px;
      background: linear-gradient(135deg, var(--primary), var(--accent));
      display: grid;
      place-items: center;
      font-size: 1.1rem;
    }
    nav a {
      color: var(--muted);
      text-decoration: none;
      font-size: .9rem;
      margin-left: 20px;
      transition: color .2s;
    }
    nav a:hover { color: var(--primary); }
    .hero { text-align: center; padding: 40px 0 64px; }
    .badge {
      display: inline-block;
      font-size: .7rem;
      text-transform: uppercase;
      letter-spacing: .15em;
      color: var(--primary);
      border: 1px solid color-mix(in srgb, var(--primary) 40%, transparent);
      padding: 6px 14px;
      border-radius: 999px;
      margin-bottom: 20px;
      background: var(--panel);
      backdrop-filter: blur(12px);
    }
    h1 {
      font-size: clamp(2.2rem, 6vw, 3.5rem);
      font-weight: 800;
      line-height: 1.15;
      margin-bottom: 16px;
      background: linear-gradient(135deg, #fff 30%, var(--primary));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .hero p.sub {
      font-size: 1.1rem;
      color: var(--muted);
      max-width: 560px;
      margin: 0 auto 28px;
    }
    .creator {
      font-size: .95rem;
      color: var(--accent);
      margin-bottom: 32px;
    }
    .btns { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; }
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 14px 28px;
      border-radius: 12px;
      font-weight: 700;
      font-size: .95rem;
      text-decoration: none;
      transition: transform .15s, box-shadow .2s;
    }
    .btn-primary {
      background: linear-gradient(135deg, var(--primary), color-mix(in srgb, var(--primary) 70%, #000));
      color: #000;
      box-shadow: 0 8px 32px var(--glow);
    }
    .btn-primary:hover { transform: translateY(-2px); }
    .btn-ghost {
      border: 1px solid var(--border);
      color: var(--text);
      background: var(--panel);
      backdrop-filter: blur(8px);
    }
    .btn-ghost:hover { border-color: var(--primary); color: var(--primary); }
    .version { margin-top: 16px; font-size: .8rem; color: var(--muted); font-family: ui-monospace, monospace; }
    section { margin-top: 72px; }
    h2 {
      font-size: 1.5rem;
      margin-bottom: 24px;
      text-align: center;
    }
    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 16px;
    }
    .card {
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 24px;
      backdrop-filter: blur(16px);
    }
    .card h3 { font-size: 1rem; margin-bottom: 8px; color: var(--primary); }
    .card p { font-size: .9rem; color: var(--muted); }
    .about {
      text-align: center;
      max-width: 640px;
      margin: 0 auto;
      padding: 32px;
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: 20px;
      backdrop-filter: blur(16px);
    }
    .about strong { color: var(--primary); }
    footer {
      margin-top: 80px;
      padding-top: 24px;
      border-top: 1px solid var(--border);
      text-align: center;
      font-size: .85rem;
      color: var(--muted);
    }
    footer a { color: var(--primary); text-decoration: none; }
    .links { display: flex; flex-wrap: wrap; gap: 16px; justify-content: center; margin-top: 12px; }
  </style>
</head>
<body>
  <div class="wrap">
    <nav>
      <div class="logo"><span>👁</span> <?= $appName ?></div>
      <div>
        <a href="#features">Features</a>
        <a href="#about">About</a>
        <a href="#download">Download</a>
        <a href="<?= htmlspecialchars($apkPageUrl, ENT_QUOTES, 'UTF-8') ?>">APK Config</a>
      </div>
    </nav>

    <header class="hero" id="top">
      <div class="badge">Neural OS · Mobile First</div>
      <h1><?= $appName ?></h1>
      <p class="sub"><?= $tagline ?: 'Voice-first AI — bol ke kaam karwao. Files, apps, notes, aur zyada.' ?></p>
      <p class="creator">Created by <strong><?= $creator ?></strong></p>
      <div class="btns">
        <?php if ($apkLink !== ''): ?>
          <a class="btn btn-primary" href="<?= htmlspecialchars($apkLink, ENT_QUOTES, 'UTF-8') ?>" download>📲 APK Download</a>
        <?php else: ?>
          <a class="btn btn-primary" href="#download">📲 Get App</a>
        <?php endif; ?>
        <?php if ($playLink !== ''): ?>
          <a class="btn btn-ghost" href="<?= htmlspecialchars($playLink, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Play Store</a>
        <?php endif; ?>
        <a class="btn btn-ghost" href="<?= htmlspecialchars($apkJsonUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">{ } API Config</a>
      </div>
      <p class="version">v<?= $version ?> · config via apk.php</p>
    </header>

    <section id="features">
      <h2>Core Features</h2>
      <div class="grid">
        <article class="card">
          <h3>🎙️ Voice First</h3>
          <p>Bolo — Rebel AI samjhega aur action lega. Hands-free digital control.</p>
        </article>
        <article class="card">
          <h3>📂 Smart Files</h3>
          <p>Notes, files, aur phone par roz ke kaam — ek assistant se.</p>
        </article>
        <article class="card">
          <h3>🧠 Memory</h3>
          <p>Important baatein yaad rakhe — tumhari personal context ke saath.</p>
        </article>
        <article class="card">
          <h3>🔐 BYOK Secure</h3>
          <p>Apni API keys tumhare device par — privacy first approach.</p>
        </article>
        <article class="card">
          <h3>📱 Mobile Native</h3>
          <p>Android APK — WebView + server config se customize karo.</p>
        </article>
        <article class="card">
          <h3>⚡ Rebel Energy</h3>
          <p>IRIS-inspired execution mindset — Rebel Bhaiya ke vision se built.</p>
        </article>
      </div>
    </section>

    <section id="about">
      <h2>About</h2>
      <div class="about">
        <p>
          <strong><?= $appName ?></strong> ek intelligent voice assistant hai jo sirf baat nahi karta —
          <strong>execute</strong> karta hai. Ye project <strong>Rebel Bhaiya (Ujjwal Tiwari)</strong> ne banaya hai,
          desktop IRIS-style AI power ko mobile-friendly experience mein lane ke liye.
        </p>
        <p style="margin-top:16px;color:var(--muted);font-size:.9rem;">
          APK settings change karni ho? <a href="<?= htmlspecialchars($apkPageUrl, ENT_QUOTES, 'UTF-8') ?>" style="color:var(--primary)">apk.php</a> kholo —
          app name, colors, download link, server URL sab wahan se edit ho sakta hai.
        </p>
      </div>
    </section>

    <section id="download">
      <h2>Download</h2>
      <div class="about">
        <?php if ($apkLink !== ''): ?>
          <p>Latest build <strong>v<?= $version ?></strong> ready hai.</p>
          <div class="btns" style="margin-top:20px">
            <a class="btn btn-primary" href="<?= htmlspecialchars($apkLink, ENT_QUOTES, 'UTF-8') ?>">⬇️ Download APK</a>
          </div>
        <?php else: ?>
          <p style="color:var(--muted)">
            APK link abhi set nahi hai. Admin <a href="<?= htmlspecialchars($apkPageUrl, ENT_QUOTES, 'UTF-8') ?>">apk.php</a> mein
            <code>apk_download</code> field bharega — yahan button automatic aa jayega.
          </p>
        <?php endif; ?>
      </div>
    </section>

    <footer>
      <p>© <?= date('Y') ?> <?= $appName ?> · <?= $creator ?></p>
      <div class="links">
        <?php if ($tg !== ''): ?><a href="<?= htmlspecialchars($tg, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Telegram</a><?php endif; ?>
        <?php if ($ig !== ''): ?><a href="<?= htmlspecialchars($ig, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Instagram</a><?php endif; ?>
        <?php if ($gh !== ''): ?><a href="<?= htmlspecialchars($gh, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">GitHub</a><?php endif; ?>
        <?php if ($email !== ''): ?><a href="mailto:<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">Email</a><?php endif; ?>
      </div>
    </footer>
  </div>
</body>
</html>
