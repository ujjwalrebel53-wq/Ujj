#!/bin/bash
# cd www && bash update.sh
set -e
BASE="https://raw.githubusercontent.com/ujjwalrebel53-wq/Ujj/cursor/multi-instagram-handler-756b/www"

echo "==> Updating RebelInsta..."

wget -q -O python-bridge/ig_bridge.py     "$BASE/python-bridge/ig_bridge.py"
wget -q -O app/src/AccountCreator.php      "$BASE/app/src/AccountCreator.php"
wget -q -O app/src/ProxyManager.php         "$BASE/app/src/ProxyManager.php"
wget -q -O app/src/Database.php             "$BASE/app/src/Database.php"
wget -q -O app/src/InstagramBridge.php     "$BASE/app/src/InstagramBridge.php"
wget -q -O app/src/LiveLogger.php          "$BASE/app/src/LiveLogger.php"
wget -q -O app/src/routes.php               "$BASE/app/src/routes.php"
wget -q -O app/bootstrap.php                "$BASE/app/bootstrap.php"
wget -q -O cli/worker.php                   "$BASE/cli/worker.php"
wget -q -O index.php                        "$BASE/index.php"
wget -q -O health.php                         "$BASE/health.php"
wget -q -O static/js/app.js                 "$BASE/static/js/app.js"
wget -q -O static/css/style.css             "$BASE/static/css/style.css"
wget -q -O views/index.html                 "$BASE/views/index.html"

mkdir -p data/logs
chmod 755 data/logs 2>/dev/null || true
chmod +x python-bridge/ig_bridge.py 2>/dev/null || true

echo "==> Done! Live Logs page check karo + Account Creator try karo."
