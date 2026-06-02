<?php
/**
 * Rebel AI — APK Build Configuration
 * Created by Rebel Bhaiya (Ujjwal Tiwari)
 *
 * Usage:
 *   - Browser: apk.php          → admin panel (edit settings)
 *   - JSON API: apk.php?format=json  → Android app / build scripts read this
 *   - Website: RebelIrisai.php reads same rebel_apk_config.json
 *
 * First login default password: rebel2026  (change in panel immediately)
 */

declare(strict_types=1);

session_start();

const REBEL_CONFIG_FILE = __DIR__ . '/rebel_apk_config.json';
const REBEL_ADMIN_PASS_FILE = __DIR__ . '/.rebel_apk_admin_pass';

/** @return array<string, mixed> */
function rebelDefaultConfig(): array
{
    return [
        // ── Branding ──
        'app_name'        => 'Rebel AI',
        'app_label'       => 'Rebel AI',
        'package_name'    => 'com.rebelbhaiya.rebelai',
        'tagline'         => 'Voice se phone par execution — Rebel Bhaiya ka assistant',
        'creator'         => 'Rebel Bhaiya (Ujjwal Tiwari)',
        'creator_handle'  => '@RebelBhaiya',

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
        'splash_title'    => 'Rebel AI',
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
        'offline_message' => 'Server se connect nahi ho paaya. Internet check karke dubara try karo.',

        // ── About (JSON mein app "About" screen ke liye) ──
        'about_text'      => 'Rebel AI — Created by Rebel Bhaiya (Ujjwal Tiwari). Voice-first intelligent assistant for your phone.',

        // ── Build notes (sirf admin panel — JSON mein bhi jaata hai) ──
        'build_notes'     => "APK banate waqt:\n1. app_name → strings.xml\n2. server_url → MainActivity SERVER_URL\n3. version / version_code → build.gradle\n4. package_name → applicationId",
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
        'brand'  => 'Rebel AI',
        'creator'=> 'Rebel Bhaiya (Ujjwal Tiwari)',
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
        'user_agent_suffix', 'firebase_db_path',
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
$websiteFile = dirname($_SERVER['SCRIPT_NAME'] ?? '') . '/RebelIrisai.php';
$websiteUrl = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $websiteFile;

function rebelShowLogin(string $error = ''): void
{
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="hi"><head>
      <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Rebel AI — APK Config Login</title>
      <style>
        body{margin:0;min-height:100vh;display:grid;place-items:center;background:#030303;color:#e4e4e7;font-family:system-ui,sans-serif}
        form{background:rgba(0,0,0,.5);border:1px solid rgba(255,255,255,.1);padding:32px;border-radius:16px;width:min(360px,92vw)}
        h1{font-size:1.2rem;margin:0 0 8px;color:#10b981}
        p{font-size:.85rem;color:#71717a;margin:0 0 20px}
        input{width:100%;padding:12px;border-radius:8px;border:1px solid #333;background:#111;color:#fff;margin-bottom:12px}
        button{width:100%;padding:12px;border:0;border-radius:8px;background:#10b981;color:#000;font-weight:700;cursor:pointer}
        .err{color:#f87171;font-size:.85rem;margin-bottom:12px}
      </style>
    </head><body>
      <form method="post">
        <h1>👁 Rebel AI APK Config</h1>
        <p>Created by Rebel Bhaiya (Ujjwal Tiwari)</p>
        <?php if ($error !== ''): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <input type="hidden" name="action" value="login">
        <input type="password" name="password" placeholder="Admin password" required autofocus>
        <button type="submit">Login</button>
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
<html lang="hi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Rebel AI — APK Configuration</title>
  <style>
    :root { --bg:#0a0a0a; --card:#111; --border:#222; --text:#e4e4e7; --muted:#71717a; --pri:#10b981; }
    * { box-sizing:border-box; }
    body { margin:0; font-family:system-ui,sans-serif; background:var(--bg); color:var(--text); line-height:1.5; }
    .top { display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between;
      padding:16px 20px; border-bottom:1px solid var(--border); position:sticky; top:0; background:rgba(10,10,10,.95); z-index:10; }
    .top h1 { margin:0; font-size:1.1rem; color:var(--pri); }
    .top a { color:var(--muted); font-size:.85rem; }
    .wrap { max-width:900px; margin:0 auto; padding:20px; }
    .ok { background:#052e1e; border:1px solid var(--pri); color:var(--pri); padding:12px; border-radius:8px; margin-bottom:16px; }
    fieldset { border:1px solid var(--border); border-radius:12px; padding:16px; margin-bottom:20px; }
    legend { color:var(--pri); font-weight:700; padding:0 8px; }
    label { display:block; font-size:.8rem; color:var(--muted); margin:12px 0 4px; }
    input[type=text], input[type=url], input[type=email], input[type=number], input[type=password], textarea {
      width:100%; padding:10px; border-radius:8px; border:1px solid var(--border); background:#000; color:var(--text); font-size:.9rem;
    }
    textarea { min-height:72px; resize:vertical; font-family:inherit; }
    .row2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    @media(max-width:600px){ .row2 { grid-template-columns:1fr; } }
    .chk { display:flex; align-items:center; gap:8px; margin-top:12px; }
    .chk input { width:auto; }
    .chk label { margin:0; color:var(--text); }
    .actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:24px; }
    button, .btn-link {
      padding:12px 20px; border-radius:8px; font-weight:700; cursor:pointer; text-decoration:none; font-size:.9rem; border:none;
    }
    .btn-save { background:var(--pri); color:#000; }
    .btn-json { background:#1e293b; color:#94a3b8; }
    .hint { font-size:.75rem; color:var(--muted); margin-top:4px; }
    code { background:#1a1a1a; padding:2px 6px; border-radius:4px; font-size:.8rem; }
  </style>
</head>
<body>
  <div class="top">
    <h1>👁 Rebel AI — APK Config Panel</h1>
    <div>
      <a href="<?= rebelEsc($jsonUrl) ?>" target="_blank">JSON API</a> ·
      <a href="<?= rebelEsc($websiteUrl) ?>" target="_blank">Website</a> ·
      <a href="apk.php?logout=1">Logout</a>
    </div>
  </div>

  <div class="wrap">
    <?php if ($saved): ?>
      <div class="ok">✅ Config save ho gayi. APK build / app dubara config pull karega.</div>
    <?php endif; ?>

    <p style="color:var(--muted);font-size:.9rem">
      Ye file <strong>rebel_apk_config.json</strong> banati hai — <code>RebelIrisai.php</code> website bhi isi se data leti hai.
      Android app mein <code>SERVER_URL</code> = <code>server_url</code> field.
    </p>

    <form method="post">
      <input type="hidden" name="action" value="save">

      <fieldset>
        <legend>Branding</legend>
        <div class="row2">
          <div>
            <label>App Name (display)</label>
            <input type="text" name="app_name" value="<?= rebelEsc($cfg['app_name'] ?? '') ?>">
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

      <div class="actions">
        <button type="submit" class="btn-save">💾 Save Config</button>
        <a class="btn-link btn-json" href="<?= rebelEsc($jsonUrl) ?>" target="_blank">{ } Preview JSON</a>
      </div>
    </form>
  </div>
</body>
</html>
