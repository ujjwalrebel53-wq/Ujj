#!/bin/bash
# RebelInsta — updated files download karo (sirf www folder)
# Usage: cd /home/rebelinsta/www/rebelinsta.alwaysdata.net && bash update.sh

set -e
BASE="https://raw.githubusercontent.com/ujjwalrebel53-wq/Ujj/cursor/multi-instagram-handler-756b/www"

echo "==> Updating RebelInsta files..."

wget -q -O python-bridge/ig_bridge.py        "$BASE/python-bridge/ig_bridge.py"
wget -q -O app/src/AccountCreator.php          "$BASE/app/src/AccountCreator.php"
wget -q -O app/src/routes.php                  "$BASE/app/src/routes.php"
wget -q -O app/src/InstagramBridge.php         "$BASE/app/src/InstagramBridge.php"
wget -q -O static/js/app.js                    "$BASE/static/js/app.js"

chmod +x python-bridge/ig_bridge.py 2>/dev/null || true

echo "==> Done! Ab Account Creator dubara try karo."
echo "    https://rebelinsta.alwaysdata.net"
