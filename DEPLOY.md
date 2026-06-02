# IRIS Website — VPS Deploy & Wget Commands

Branch: `cursor/rebel-ai-php-files-5787`  
Base URL: `https://raw.githubusercontent.com/ujjwalrebel53-wq/Ujj/cursor/rebel-ai-php-files-5787`

## One-shot deploy (recommended)

```bash
mkdir -p ~/public_html/iris && cd ~/public_html/iris

BASE="https://raw.githubusercontent.com/ujjwalrebel53-wq/Ujj/cursor/rebel-ai-php-files-5787"

# PHP pages
wget -O RebelIrisai.php "$BASE/RebelIrisai.php"
wget -O index.php "$BASE/index.php"
wget -O about.php "$BASE/about.php"
wget -O features.php "$BASE/features.php"
wget -O how-to-install.php "$BASE/how-to-install.php"
wget -O guide.php "$BASE/guide.php"
wget -O download.php "$BASE/download.php"
wget -O apk.php "$BASE/apk.php"

# Includes
mkdir -p includes
wget -O includes/config.php "$BASE/includes/config.php"
wget -O includes/head.php "$BASE/includes/head.php"
wget -O includes/header.php "$BASE/includes/header.php"
wget -O includes/footer.php "$BASE/includes/footer.php"

# CSS / JS
mkdir -p assets/css assets/js assets/img
wget -O assets/css/iris-tailwind.css "$BASE/assets/css/iris-tailwind.css"
wget -O assets/css/custom.css "$BASE/assets/css/custom.css"
wget -O assets/js/ripple-grid.js "$BASE/assets/js/ripple-grid.js"
wget -O assets/js/site.js "$BASE/assets/js/site.js"

# Images
wget -O assets/img/Logo.png "$BASE/assets/img/Logo.png"
wget -O assets/img/screen.png "$BASE/assets/img/screen.png"
wget -O assets/img/cli.png "$BASE/assets/img/cli.png"
wget -O assets/img/graphic.webp "$BASE/assets/img/graphic.webp"
wget -O assets/img/iris-future.png "$BASE/assets/img/iris-future.png"
wget -O assets/img/tryiris.png "$BASE/assets/img/tryiris.png"
wget -O assets/img/bright-neon-bg.png "$BASE/assets/img/bright-neon-bg.png"

# Example config
wget -O rebel_apk_config.json.example "$BASE/rebel_apk_config.json.example"

chmod 755 . && chmod 644 *.php includes/*.php assets/css/* assets/js/*
chmod 775 .
```

## After upload

1. Open `https://yourdomain.com/iris/RebelIrisai.php`
2. Open `https://yourdomain.com/iris/apk.php` → login `rebel2026` → change password
3. Set `website_url`, `server_url`, `apk_download` in config panel
4. Test `apk.php?format=json`

## File list

| File | Purpose |
|------|---------|
| `RebelIrisai.php` | Home (matches irisaiw.vercel.app) |
| `index.php` | Redirect to home |
| `about.php` | About page |
| `features.php` | Features page |
| `how-to-install.php` | Install guide |
| `guide.php` | API keys guide |
| `download.php` | Download page |
| `apk.php` | Config panel + JSON API |
| `includes/*` | Shared PHP partials |
| `assets/css/iris-tailwind.css` | Production Tailwind bundle from IRIS site |
| `assets/css/custom.css` | IRIS animations (wave bars, scroll, etc.) |
| `assets/js/*` | Hero grid + UI scripts |
| `assets/img/*` | IRIS marketing images |

## Requirements

- PHP 8.0+
- Writable directory for `rebel_apk_config.json` and `.rebel_apk_admin_pass`
- HTTPS recommended
