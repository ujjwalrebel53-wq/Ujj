# AlwaysData Deploy — rebelinsta.alwaysdata.net

Yeh guide **rebelinsta.alwaysdata.net** par IG Handler deploy karne ke liye hai.

## Step 1: SSH se connect karo

```bash
ssh rebelinsta@ssh-rebelinsta.alwaysdata.net
```

(Password AlwaysData admin panel se milega: **Advanced > SSH configuration**)

---

## Step 2: Code download karo (wget)

```bash
cd ~
wget -O ig-handler.zip "https://github.com/ujjwalrebel53-wq/Ujj/archive/refs/heads/cursor/multi-instagram-handler-756b.zip"
unzip ig-handler.zip
mkdir -p ig-handler
cp -r Ujj-cursor-multi-instagram-handler-756b/instagram-handler-php ~/ig-handler/
cp -r Ujj-cursor-multi-instagram-handler-756b/instagram-handler ~/ig-handler/
cd ~/ig-handler
```

**Ya auto install script:**
```bash
cd ~
wget -O install.sh "https://raw.githubusercontent.com/ujjwalrebel53-wq/Ujj/cursor/multi-instagram-handler-756b/instagram-handler-php/install-alwaysdata.sh"
bash install.sh
```

---

## Step 3: Python bridge setup (Instagram API ke liye)

```bash
cd ~/ig-handler/instagram-handler
python -m venv venv
source venv/bin/activate
pip install -r requirements.txt
deactivate
```

---

## Step 4: .env file banao

```bash
nano ~/ig-handler/instagram-handler-php/.env
```

Andar yeh daalo:

```env
SECRET_KEY=apna-random-secret-yahan
ENCRYPTION_KEY=apna-encryption-key-yahan
WEBSHARE_PROXY_URL=https://proxy.webshare.io/api/v2/proxy/list/download/atncfuvekqfugbctsmrndphgnkzsekraaomqcnfa/-/any/username/direct/-/?plan_id=13556677
PYTHON_BRIDGE_BIN=/home/rebelinsta/ig-handler/instagram-handler/venv/bin/python
```

Save: `Ctrl+O` → Enter → `Ctrl+X`

---

## Step 5: Data folder permissions

```bash
mkdir -p ~/ig-handler/instagram-handler-php/data/sessions
mkdir -p ~/ig-handler/instagram-handler-php/data/uploads
chmod -R 755 ~/ig-handler/instagram-handler-php/data
```

---

## Step 6: AlwaysData Admin — Site add karo

1. Login: https://admin.alwaysdata.com
2. **Web > Sites > Add a site**
3. Fill karo:

| Field | Value |
|-------|-------|
| **Name** | IG Handler |
| **Addresses** | `rebelinsta.alwaysdata.net` |
| **Type** | PHP |
| **Root directory** | `/home/rebelinsta/ig-handler/instagram-handler-php/public` |

4. **Save**

---

## Step 7: PHP version check

**Environment > PHP** → PHP **8.1+** select karo

Required extensions (default mein hote hain):
- `pdo_sqlite`
- `curl`
- `openssl`
- `json`

---

## Step 8: Test karo

Browser kholo: **https://rebelinsta.alwaysdata.net**

Dashboard dikhna chahiye.

---

## Batch Account Creator (Cron)

AlwaysData par background worker ke liye cron set karo:

**Advanced > Scheduled tasks (Cron)**

| Field | Value |
|-------|-------|
| Command | `/usr/bin/php /home/rebelinsta/ig-handler/instagram-handler-php/cli/worker.php 30` |
| Frequency | Every minute (ya jab chahiye) |

---

## Folder Structure (server par)

```
/home/rebelinsta/
└── ig-handler/
    ├── instagram-handler-php/
    │   ├── public/          ← Site root (AlwaysData points here)
    │   ├── src/
    │   ├── data/            ← SQLite DB + sessions (writable)
    │   ├── bridge/
    │   └── .env
    └── instagram-handler/
        └── venv/            ← Python Instagram bridge
```

---

## Problems?

**500 Error:**
```bash
tail -f ~/ig-handler/instagram-handler-php/data/error.log
# ya AlwaysData logs: Web > Logs
```

**Instagram login fail:**
```bash
cd ~/ig-handler/instagram-handler
source venv/bin/activate
python bridge/ig_bridge.py
# Test bridge manually
```

**Permission denied on data/:**
```bash
chmod -R 755 ~/ig-handler/instagram-handler-php/data
```

---

## Security

- `.env` file kabhi `public/` folder mein mat rakho
- `data/` folder web se accessible nahi hai (public/ ke bahar hai)
- Webshare API token secret rakho
