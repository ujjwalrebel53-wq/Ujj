#!/bin/bash
# cd www && bash update.sh
set -e
B="https://raw.githubusercontent.com/ujjwalrebel53-wq/Ujj/cursor/multi-instagram-handler-756b/www"
for f in lib.php api.php index.php setup.php worker.php ig.py ui.html a.js a.css ping.php ping.html req.txt .htaccess; do
  wget -q -O "$f" "$B/$f"
done
chmod +x ig.py worker.php update.sh 2>/dev/null || true
mkdir -p data/logs data/sessions data/uploads
echo "Done. Test: https://rebelinsta.alwaysdata.net/ping.html"
