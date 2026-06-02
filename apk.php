<?php
/**
 * IRIS AI — Site & APK Configuration Panel
 * Matches irisaiw.vercel.app admin styling
 *
 * Browser: apk.php | JSON: apk.php?format=json
 * Default password: rebel2026 (change after first login)
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/includes/config.php';

const REBEL_CONFIG_FILE = __DIR__ . '/rebel_apk_config.json';
const REBEL_ADMIN_PASS_FILE = __DIR__ . '/.rebel_apk_admin_pass';

/** @return array<string, mixed> */
function rebelDefaultConfig(): array
{
    return [
        // ── Branding ──
        'app_name'        => 'IRIS AI',
        'app_label'       => 'IRIS',
        'package_name'    => 'com.iris.neuralos',
        'tagline'         => 'Integrated Responsive Intelligence System',
        'creator'         => 'Harsh Pandey',
        'creator_handle'  => '@201Harsh',
        'cli_command'     => 'npm install -g iris-ai',
        'mini_cli'        => 'npm install -g iris-mini',

        // ── Version (APK build mein ye values use karo) ──
        'version'         => '1.0.0',
        'version_code'    => 1,

        // ── URLs (Android WebView / app load karega) ──
        'website_url'     => '',  // e.g. https://yoursite.com/RebelIrisai.php
        'server_url'      => '',  // WebView home URL — khali ho to website_url use hoga
        'config_api'      => '',  // optional: full URL to this file ?format=json

        // ── Download & links ──
        'apk_download'    => '',  // direct .apk link for website button
        'play_store'      => '',
        'telegram'        => '',
        'instagram'       => '',
        'github'          => '',
        'support_email'   => '',

        // ── UI Theme (website + optional WebView tint) ──
        'primary_color'   => '#10b981',
        'accent_color'    => '#06b6d4',
        'background_color'=> '#030303',
        'splash_title'    => 'IRIS AI',
        'splash_subtitle' => 'Initializing neural uplink…',

        // ── App behavior flags ──
        'enable_voice'    => true,
        'enable_firebase' => false,
        'firebase_db_path'=> 'rebel_ai_users',
        'show_exit_dialog'=> true,
        'allow_external_links' => false,
        'user_agent_suffix' => 'RebelAI/1.0',

        // ── Offline page text ──
        'offline_title'   => 'Connection Error',
        'offline_message' => 'Unable to connect to server. Check your internet connection and try again.',

        'about_text'      => 'IRIS AI — The Autonomous Neural OS Agent. Local-first execution layer for your device.',

        'build_notes'     => "Build checklist:\n1. app_name → strings.xml\n2. server_url → MainActivity SERVER_URL\n3. version / version_code → build.gradle\n4. package_name → applicationId",
    ];
}

/** @return array<string, mixed> */
function rebelLoadConfig(): array
{
    $defaults = rebelDefaultConfig();
    if (!is_file(REBEL_CONFIG_FILE)) {
        return $defaults;
    }
    $data = json_decode((string) file_get_contents(REBEL_CONFIG_FILE), true);
    return is_array($data) ? array_merge($defaults, $data) : $defaults;
}

/** @param array<string, mixed> $cfg */
function rebelSaveConfig(array $cfg): bool
{
    $merged = array_merge(rebelDefaultConfig(), $cfg);
    return (bool) file_put_contents(
        REBEL_CONFIG_FILE,
        json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

function rebelAdminPassword(): string
{
    if (is_file(REBEL_ADMIN_PASS_FILE)) {
        return trim((string) file_get_contents(REBEL_ADMIN_PASS_FILE));
    }
    return getenv('REBEL_APK_ADMIN_PASS') ?: 'rebel2026';
}

function rebelSetAdminPassword(string $pass): void
{
    file_put_contents(REBEL_ADMIN_PASS_FILE, $pass, LOCK_EX);
}

function rebelIsLoggedIn(): bool
{
    return !empty($_SESSION['rebel_apk_admin']);
}

function rebelRequireLogin(): void
{
    if (!rebelIsLoggedIn()) {
        rebelShowLogin();
        exit;
    }
}

function rebelBool(mixed $v): bool
{
    if (is_bool($v)) {
        return $v;
    }
    $s = strtolower(trim((string) $v));
    return in_array($s, ['1', 'true', 'yes', 'on'], true);
}

/** @return array<string, mixed> */
function rebelPublicConfig(array $cfg): array
{
    $out = $cfg;
    unset($out['admin_notes']);
    if (empty($out['server_url']) && !empty($out['website_url'])) {
        $out['server_url'] = $out['website_url'];
    }
    if (empty($out['config_api'])) {
        $out['config_api'] = rebelGuessSelfJsonUrl();
    }
    $out['updated_at'] = is_file(REBEL_CONFIG_FILE)
        ? date('c', (int) filemtime(REBEL_CONFIG_FILE))
        : date('c');
    return $out;
}

function rebelGuessSelfJsonUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = $_SERVER['SCRIPT_NAME'] ?? '/apk.php';
    return $scheme . '://' . $host . $path . '?format=json';
}

// ─── JSON API ───────────────────────────────────────────────────────────────
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: no-cache, must-revalidate');
    echo json_encode([
        'ok'     => true,
        'brand'  => 'IRIS AI',
        'creator'=> 'Harsh Pandey',
        'config' => rebelPublicConfig(rebelLoadConfig()),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ─── Logout ─────────────────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    unset($_SESSION['rebel_apk_admin']);
    header('Location: apk.php');
    exit;
}

// ─── Login POST ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    if (hash_equals(rebelAdminPassword(), (string) ($_POST['password'] ?? ''))) {
        $_SESSION['rebel_apk_admin'] = true;
        header('Location: apk.php');
        exit;
    }
    rebelShowLogin('Galat password. Default: rebel2026');
    exit;
}

// ─── Save config POST ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    rebelRequireLogin();

    $cfg = rebelLoadConfig();
    $textFields = [
        'app_name', 'app_label', 'package_name', 'tagline', 'creator', 'creator_handle',
        'version', 'website_url', 'server_url', 'config_api', 'apk_download', 'play_store',
        'telegram', 'instagram', 'github', 'support_email',
        'primary_color', 'accent_color', 'background_color',
        'splash_title', 'splash_subtitle',
        'offline_title', 'offline_message', 'about_text', 'build_notes',
        'user_agent_suffix', 'firebase_db_path', 'cli_command', 'mini_cli',
    ];
    foreach ($textFields as $key) {
        if (isset($_POST[$key])) {
            $cfg[$key] = trim((string) $_POST[$key]);
        }
    }
    $cfg['version_code'] = max(1, (int) ($_POST['version_code'] ?? 1));
    $cfg['enable_voice'] = rebelBool($_POST['enable_voice'] ?? false);
    $cfg['enable_firebase'] = rebelBool($_POST['enable_firebase'] ?? false);
    $cfg['show_exit_dialog'] = rebelBool($_POST['show_exit_dialog'] ?? false);
    $cfg['allow_external_links'] = rebelBool($_POST['allow_external_links'] ?? false);

    if (!empty($_POST['new_admin_pass'])) {
        rebelSetAdminPassword((string) $_POST['new_admin_pass']);
    }

    rebelSaveConfig($cfg);
    header('Location: apk.php?saved=1');
    exit;
}

rebelRequireLogin();
$cfg = rebelLoadConfig();
$saved = isset($_GET['saved']);
$jsonUrl = rebelGuessSelfJsonUrl();
$websiteUrl = siteUrl('RebelIrisai.php');

function rebelShowLogin(string $error = ''): void
{
    header('Content-Type: text/html; charset=utf-8');
    $logo = assetUrl('img/Logo.png');
    ?>
    <!DOCTYPE html>
    <html lang="en"><head>
      <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>IRIS — Config Login</title>
      <link rel="stylesheet" href="<?= assetUrl('css/iris-tailwind.css') ?>">
      <link rel="stylesheet" href="<?= assetUrl('css/custom.css') ?>">
    </head>
    <body class="min-h-screen bg-black text-white flex items-center justify-center p-6 font-sans">
      <form method="post" class="w-full max-w-sm bg-black/60 backdrop-blur-xl border border-[#10b981]/20 rounded-2xl p-8 shadow-[0_0_40px_rgba(16,185,129,0.12)]">
        <div class="flex items-center gap-3 mb-6">
          <img src="<?= esc($logo) ?>" alt="IRIS" width="36" height="36" class="rounded-full">
          <span class="text-xl font-black text-[#10b981] tracking-tighter">IRIS</span>
        </div>
        <h1 class="text-lg font-bold mb-1">Config Panel</h1>
        <p class="text-sm text-zinc-500 mb-6 font-mono">IRIS_OS // ADMIN_VAULT</p>
        <?php if ($error !== ''): ?><p class="text-red-400 text-sm mb-4"><?= esc($error) ?></p><?php endif; ?>
        <input type="hidden" name="action" value="login">
        <input type="password" name="password" placeholder="Admin password" required autofocus
          class="w-full px-4 py-3 rounded-lg bg-zinc-900 border border-zinc-800 text-white mb-4 focus:border-[#10b981] outline-none">
        <button type="submit" class="w-full py-3 rounded-full bg-[#10b981]/20 border border-[#10b981]/50 text-white font-bold text-xs tracking-[0.2em] uppercase hover:bg-[#10b981] hover:text-black transition-all">Authenticate</button>
        <p class="text-[10px] text-zinc-600 mt-4 font-mono text-center">Default: rebel2026</p>
      </form>
    </body></html>
    <?php
}

// ─── Admin panel HTML ─────────────────────────────────────────────────────────
header('Content-Type: text/html; charset=utf-8');

function rebelEsc(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function rebelChecked(bool $v): string
{
    return $v ? 'checked' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>IRIS — Configuration</title>
  <link rel="stylesheet" href="<?= assetUrl('css/iris-tailwind.css') ?>">
  <link rel="stylesheet" href="<?= assetUrl('css/custom.css') ?>">
</head>
<body class="bg-black text-white min-h-screen font-sans">
  <header class="sticky top-0 z-50 flex flex-wrap items-center justify-between gap-4 px-6 py-4 border-b border-[#10b981]/20 bg-black/90 backdrop-blur-lg">
    <div class="flex items-center gap-3">
      <img src="<?= assetUrl('img/Logo.png') ?>" alt="IRIS" width="32" height="32" class="rounded-full">
      <h1 class="text-lg font-black text-[#10b981] tracking-tighter">IRIS Config</h1>
    </div>
    <div class="flex flex-wrap gap-4 text-xs font-mono uppercase tracking-widest text-zinc-500">
      <a href="<?= rebelEsc($jsonUrl) ?>" target="_blank" class="hover:text-[#10b981]">JSON API</a>
      <a href="<?= rebelEsc($websiteUrl) ?>" target="_blank" class="hover:text-[#10b981]">Website</a>
      <a href="apk.php?logout=1" class="hover:text-[#10b981]">Logout</a>
    </div>
  </header>

  <div class="max-w-3xl mx-auto px-6 py-10">
    <?php if ($saved): ?>
      <div class="mb-6 p-4 rounded-lg border border-[#10b981]/40 bg-[#10b981]/10 text-[#34d399] font-mono text-sm">Configuration saved successfully.</div>
    <?php endif; ?>

    <p class="text-zinc-500 text-sm font-mono mb-8">
      Writes <code class="text-[#10b981]">rebel_apk_config.json</code> — used by <code class="text-[#10b981]">RebelIrisai.php</code> and Android WebView via <code class="text-[#10b981]">server_url</code>.
    </p>

    <form method="post">
      <input type="hidden" name="action" value="save">

      <fieldset class="border border-zinc-800 rounded-xl p-6 mb-6">
        <legend class="text-[#10b981] font-bold px-2">Branding</legend>
        <div class="grid md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs text-zinc-500 mt-2 mb-1">App Name (display)</label>
            <input type="text" name="app_name" value="<?= rebelEsc($cfg['app_name'] ?? '') ?>" class="w-full px-3 py-2 rounded-lg bg-zinc-900 border border-zinc-800 text-white">
          </div>
          <div>
            <label>App Label (launcher)</label>
            <input type="text" name="app_label" value="<?= rebelEsc($cfg['app_label'] ?? '') ?>">
          </div>
        </div>
        <label>Package Name (applicationId)</label>
        <input type="text" name="package_name" value="<?= rebelEsc($cfg['package_name'] ?? '') ?>">
        <label>Tagline</label>
        <input type="text" name="tagline" value="<?= rebelEsc($cfg['tagline'] ?? '') ?>">
        <label>Creator</label>
        <input type="text" name="creator" value="<?= rebelEsc($cfg['creator'] ?? '') ?>">
        <label>Creator Handle</label>
        <input type="text" name="creator_handle" value="<?= rebelEsc($cfg['creator_handle'] ?? '') ?>">
      </fieldset>

      <fieldset>
        <legend>Version (APK build)</legend>
        <div class="row2">
          <div>
            <label>versionName (e.g. 1.0.0)</label>
            <input type="text" name="version" value="<?= rebelEsc($cfg['version'] ?? '') ?>">
          </div>
          <div>
            <label>versionCode (integer)</label>
            <input type="number" name="version_code" min="1" value="<?= rebelEsc($cfg['version_code'] ?? 1) ?>">
          </div>
        </div>
      </fieldset>

      <fieldset>
        <legend>URLs</legend>
        <label>Website URL (RebelIrisai.php full link)</label>
        <input type="url" name="website_url" value="<?= rebelEsc($cfg['website_url'] ?? '') ?>" placeholder="https://domain.com/RebelIrisai.php">
        <label>Server URL (WebView home — APK MainActivity)</label>
        <input type="url" name="server_url" value="<?= rebelEsc($cfg['server_url'] ?? '') ?>" placeholder="Khali = website_url use hoga">
        <label>Config API (optional override)</label>
        <input type="url" name="config_api" value="<?= rebelEsc($cfg['config_api'] ?? '') ?>" placeholder="<?= rebelEsc($jsonUrl) ?>">
        <label>APK Download Link (website button)</label>
        <input type="url" name="apk_download" value="<?= rebelEsc($cfg['apk_download'] ?? '') ?>">
        <label>Play Store</label>
        <input type="url" name="play_store" value="<?= rebelEsc($cfg['play_store'] ?? '') ?>">
      </fieldset>

      <fieldset>
        <legend>Social & Support</legend>
        <div class="row2">
          <div><label>Telegram</label><input type="url" name="telegram" value="<?= rebelEsc($cfg['telegram'] ?? '') ?>"></div>
          <div><label>Instagram</label><input type="url" name="instagram" value="<?= rebelEsc($cfg['instagram'] ?? '') ?>"></div>
        </div>
        <div class="row2">
          <div><label>GitHub</label><input type="url" name="github" value="<?= rebelEsc($cfg['github'] ?? '') ?>"></div>
          <div><label>Support Email</label><input type="email" name="support_email" value="<?= rebelEsc($cfg['support_email'] ?? '') ?>"></div>
        </div>
      </fieldset>

      <fieldset>
        <legend>Theme & Splash</legend>
        <div class="row2">
          <div><label>Primary Color</label><input type="text" name="primary_color" value="<?= rebelEsc($cfg['primary_color'] ?? '') ?>"></div>
          <div><label>Accent Color</label><input type="text" name="accent_color" value="<?= rebelEsc($cfg['accent_color'] ?? '') ?>"></div>
        </div>
        <label>Background Color</label>
        <input type="text" name="background_color" value="<?= rebelEsc($cfg['background_color'] ?? '') ?>">
        <div class="row2">
          <div><label>Splash Title</label><input type="text" name="splash_title" value="<?= rebelEsc($cfg['splash_title'] ?? '') ?>"></div>
          <div><label>Splash Subtitle</label><input type="text" name="splash_subtitle" value="<?= rebelEsc($cfg['splash_subtitle'] ?? '') ?>"></div>
        </div>
      </fieldset>

      <fieldset>
        <legend>App Behavior</legend>
        <div class="chk"><input type="checkbox" name="enable_voice" value="1" <?= rebelChecked(rebelBool($cfg['enable_voice'] ?? true)) ?> id="v1"><label for="v1">Enable Voice (future native module)</label></div>
        <div class="chk"><input type="checkbox" name="enable_firebase" value="1" <?= rebelChecked(rebelBool($cfg['enable_firebase'] ?? false)) ?> id="v2"><label for="v2">Enable Firebase logging</label></div>
        <div class="chk"><input type="checkbox" name="show_exit_dialog" value="1" <?= rebelChecked(rebelBool($cfg['show_exit_dialog'] ?? true)) ?> id="v3"><label for="v3">Show exit dialog on back</label></div>
        <div class="chk"><input type="checkbox" name="allow_external_links" value="1" <?= rebelChecked(rebelBool($cfg['allow_external_links'] ?? false)) ?> id="v4"><label for="v4">Allow external browser links</label></div>
        <label>Firebase DB path</label>
        <input type="text" name="firebase_db_path" value="<?= rebelEsc($cfg['firebase_db_path'] ?? '') ?>">
        <label>User-Agent suffix</label>
        <input type="text" name="user_agent_suffix" value="<?= rebelEsc($cfg['user_agent_suffix'] ?? '') ?>">
      </fieldset>

      <fieldset>
        <legend>Offline & About</legend>
        <label>Offline Title</label>
        <input type="text" name="offline_title" value="<?= rebelEsc($cfg['offline_title'] ?? '') ?>">
        <label>Offline Message</label>
        <textarea name="offline_message"><?= rebelEsc($cfg['offline_message'] ?? '') ?></textarea>
        <label>About Text</label>
        <textarea name="about_text"><?= rebelEsc($cfg['about_text'] ?? '') ?></textarea>
        <label>Build Notes (tumhare liye — APK modify checklist)</label>
        <textarea name="build_notes"><?= rebelEsc($cfg['build_notes'] ?? '') ?></textarea>
      </fieldset>

      <fieldset>
        <legend>Security</legend>
        <label>Naya admin password (khali chhodo = no change)</label>
        <input type="password" name="new_admin_pass" placeholder="Optional">
        <p class="hint">Password file: <code>.rebel_apk_admin_pass</code></p>
      </fieldset>

      <div class="flex flex-wrap gap-4 mt-8">
        <button type="submit" class="px-8 py-3 rounded-full bg-[#10b981]/20 border border-[#10b981]/50 text-white font-bold text-xs tracking-[0.2em] uppercase hover:bg-[#10b981] hover:text-black transition-all">Save Config</button>
        <a href="<?= rebelEsc($jsonUrl) ?>" target="_blank" class="px-8 py-3 rounded-full border border-zinc-800 text-zinc-400 font-bold text-xs tracking-widest uppercase hover:border-[#10b981] hover:text-[#10b981] transition-all">Preview JSON</a>
      </div>
    </form>
  </div>
</body>
</html>
