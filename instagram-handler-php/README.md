# IG Handler — PHP Version

Yeh **pure PHP** mein likha gaya multi Instagram account handler hai — same features jo Python version mein hain.

## Features

- Multi-account dashboard
- Bulk login / refresh
- Account groups & proxy support
- Encrypted password storage
- Photo posting
- **Auto Account Creator** (single + batch)
- Temp email verification (mail.tm)
- Activity log

## Requirements

- PHP 8.1+ (extensions: `pdo_sqlite`, `curl`, `openssl`, `json`)
- Python 3 + instagrapi (Instagram API bridge ke liye)

> Instagram private API ke liye `bridge/ig_bridge.py` Python instagrapi use karta hai. Baaki sab kuch PHP mein hai.

## Quick Start

```bash
# Python bridge setup (ek baar)
cd ../instagram-handler
python3 -m venv venv && source venv/bin/activate
pip install -r requirements.txt

# PHP server chalao
cd ../instagram-handler-php
php -S 0.0.0.0:8080 -t public
```

Browser: **http://localhost:8080**

## Project Structure

```
instagram-handler-php/
├── public/
│   ├── index.php          # Router + API
│   └── static/            # CSS, JS
├── src/
│   ├── Database.php       # SQLite
│   ├── Crypto.php         # AES encryption
│   ├── TempEmail.php      # mail.tm temp email
│   ├── AccountManager.php # Login, post, bulk
│   ├── AccountCreator.php # Auto account creator
│   └── InstagramBridge.php# PHP → Python bridge
├── bridge/
│   └── ig_bridge.py       # Instagram API (instagrapi)
├── cli/
│   └── worker.php         # Batch job worker
└── views/
    └── index.html         # Dashboard UI
```

## Apache / cPanel

Document root ko `public/` folder par point karo. `.htaccess`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [L]
```

## Environment

Optional `.env` file (load manually ya server config se):

```
SECRET_KEY=your-random-secret
ENCRYPTION_KEY=your-encryption-key
```

## Note

Instagram automation unke Terms of Service ke against ho sakta hai. Responsibly use karo.
