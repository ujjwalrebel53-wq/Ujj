# IG Handler — Multi Instagram Account Manager

Ek powerful web-based tool jo multiple Instagram accounts ko ek jagah se manage karta hai.

## Features

- **Multi-Account Dashboard** — Saare accounts ek screen par dekho
- **Bulk Login & Refresh** — Ek saath multiple accounts login/refresh karo
- **Account Groups** — Accounts ko groups mein organize karo (e.g. personal, business, clients)
- **Proxy Support** — Har account ke liye alag proxy set karo
- **Session Management** — Login sessions save hote hain, dubara password ki zaroorat nahi
- **2FA Support** — Two-factor authentication wale accounts bhi kaam karte hain
- **Post Photos** — Kisi bhi logged-in account se photo post karo
- **Activity Log** — Saari actions ka record
- **Encrypted Storage** — Passwords aur sessions encrypted store hote hain
- **Export Data** — Account data JSON mein export karo
- **Auto Account Creator** — Temp email se automatically naye Instagram accounts banao
- **Batch Creation** — Ek saath 20 tak accounts queue mein create karo
- **Profile Generator** — Username, password, full name auto-generate

## Quick Start

```bash
cd instagram-handler

# Virtual environment
python3 -m venv venv
source venv/bin/activate

# Install dependencies
pip install -r requirements.txt

# Configure (optional)
cp .env.example .env

# Run
python app.py
```

Browser mein kholo: **http://localhost:5050**

## Usage

### Account Add Karna

1. **+ Add Account** button dabao
2. Username aur password daalo
3. Optional: Group name, proxy, notes set karo
4. Account automatically login hoga (agar 2FA hai to code maangega)

### Bulk Operations

1. Accounts page par jao
2. Checkboxes se accounts select karo
3. **Bulk Login** ya **Bulk Refresh** use karo

### Webshare Proxy (Auto)

`.env` mein apna Webshare proxy list URL set karo:

```
WEBSHARE_PROXY_URL=https://proxy.webshare.io/api/v2/proxy/list/download/YOUR_TOKEN/-/any/username/direct/-/?plan_id=YOUR_PLAN
```

Jab account mein proxy khali ho, tool automatically Webshare pool se proxy assign karega. Batch create mein har account ko alag proxy milta hai (rotation).

### Manual Proxy Format

```
http://username:password@proxy-host:port
socks5://username:password@proxy-host:port
```

### 2FA Accounts

Login par agar 2FA error aaye, verification code enter karo jab prompt aaye.

### Auto Account Creator

1. Sidebar se **Account Creator** page kholo
2. **Single Account** — ek account turant create karo (temp email automatic)
3. **Batch Create** — kitne accounts chahiye (1-20) aur delay set karo
4. Verification code chahiye ho to **Enter Code** button se code daalo
5. Success par credentials dikhenge — save kar lena!

**Tips:**
- Har account ke liye alag proxy use karo (ban se bachne ke liye)
- Batch mein kam se kam 30 second delay rakho
- Temp email (1secmail) se automatic verification hoti hai

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/stats` | Dashboard statistics |
| GET | `/api/accounts` | List all accounts |
| POST | `/api/accounts` | Add new account |
| PUT | `/api/accounts/:id` | Update account |
| DELETE | `/api/accounts/:id` | Delete account |
| POST | `/api/accounts/:id/login` | Login account |
| POST | `/api/accounts/:id/refresh` | Refresh profile stats |
| POST | `/api/accounts/bulk/login` | Bulk login |
| POST | `/api/accounts/bulk/refresh` | Bulk refresh |
| POST | `/api/accounts/:id/post` | Post photo |
| GET | `/api/activity` | Activity log |
| GET | `/api/export` | Export account data |
| GET | `/api/creator/preview` | Preview generated profiles |
| POST | `/api/creator/create` | Create single account |
| POST | `/api/creator/batch` | Batch create accounts |
| GET | `/api/creator/jobs` | List creation jobs |
| POST | `/api/creator/jobs/:id/verify` | Submit email verification code |
| POST | `/api/creator/jobs/:id/retry` | Retry failed job |

## Security Notes

- Passwords Fernet encryption se store hote hain
- `.env` mein `SECRET_KEY` aur `ENCRYPTION_KEY` set karo production ke liye
- `data/` folder gitignore mein hai — sensitive data commit nahi hoga
- Instagram rate limits lag sakte hain — zyada requests se bacho

## Disclaimer

Yeh tool educational aur personal account management ke liye hai. Instagram ke Terms of Service ka dhyaan rakho. Automation ya spam se bacho — account ban ho sakta hai.

## Tech Stack

- **Backend:** Python Flask
- **Instagram API:** instagrapi
- **Database:** SQLite
- **Frontend:** Vanilla HTML/CSS/JS
