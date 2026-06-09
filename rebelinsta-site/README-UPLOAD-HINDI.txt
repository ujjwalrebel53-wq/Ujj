╔══════════════════════════════════════════════════════════╗
║         REBELINSTA — UPLOAD KARO, SITE CHAL JAYEGI       ║
║              rebelinsta.alwaysdata.net                   ║
╚══════════════════════════════════════════════════════════╝

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
STEP 1: ZIP UPLOAD KARO (FTP / File Manager)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Poora "rebelinsta-site" folder upload karo yahan:

   /home/rebelinsta/rebelinsta-site/

(FTP se ya AlwaysData File Manager se)


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
STEP 2: ALWAYSDATA ADMIN — SITE SET KARO (sirf ek baar)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

1. https://admin.alwaysdata.com login
2. Web > Sites > Add a site (ya edit existing)

   Addresses:     rebelinsta.alwaysdata.net
   Type:          PHP
   Root directory: /home/rebelinsta/rebelinsta-site/public

3. Save


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
STEP 3: BROWSER KHOLO — INSTALL DABAO
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

   https://rebelinsta.alwaysdata.net/setup.php

   "Install Now" button dabao.

   Automatic ho jayega:
   ✓ Database banega
   ✓ Webshare proxy connect
   ✓ Python Instagram bridge install
   ✓ Secret keys generate

   Done! Dashboard khul jayega.


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
WGET SE DOWNLOAD (agar zip nahi hai)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

SSH se:
   cd ~
   wget -O rebelinsta.zip "https://github.com/ujjwalrebel53-wq/Ujj/archive/refs/heads/cursor/multi-instagram-handler-756b.zip"
   unzip rebelinsta.zip
   mv Ujj-cursor-multi-instagram-handler-756b/rebelinsta-site ~/rebelinsta-site


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
FOLDER STRUCTURE (upload ke baad)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

/home/rebelinsta/rebelinsta-site/
├── public/          ← AlwaysData root yahan point kare
│   ├── index.php
│   └── setup.php    ← pehli baar yahan jao
├── src/
├── python-bridge/
├── data/            ← auto banega
└── views/


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
PROBLEM?
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

500 Error → data/ folder permissions:
   chmod -R 755 ~/rebelinsta-site/data

Python fail → SSH se manually:
   cd ~/rebelinsta-site/python-bridge
   python -m venv venv
   venv/bin/pip install -r requirements.txt

Phir setup.php dubara kholo.
