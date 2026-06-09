#!/bin/bash
# IG Handler — AlwaysData install script
# Run on server: bash install-alwaysdata.sh

set -e

ACCOUNT="${ALWAYSDATA_ACCOUNT:-rebelinsta}"
HOME_DIR="/home/${ACCOUNT}"
APP_DIR="${HOME_DIR}/ig-handler"
DOMAIN="rebelinsta.alwaysdata.net"
ZIP_URL="https://github.com/ujjwalrebel53-wq/Ujj/archive/refs/heads/cursor/multi-instagram-handler-756b.zip"

echo "==> IG Handler install for AlwaysData (${DOMAIN})"

mkdir -p "${APP_DIR}"
cd "${APP_DIR}"

if [ ! -d "Ujj-cursor-multi-instagram-handler-756b" ]; then
  echo "==> Downloading code..."
  wget -q -O ig-handler.zip "${ZIP_URL}"
  unzip -qo ig-handler.zip
  rm ig-handler.zip
fi

SRC="Ujj-cursor-multi-instagram-handler-756b"
cp -r "${SRC}/instagram-handler-php" ./
cp -r "${SRC}/instagram-handler" ./

# Writable data dirs
mkdir -p instagram-handler-php/data/sessions
mkdir -p instagram-handler-php/data/uploads
chmod -R 755 instagram-handler-php/data

# .env for PHP
if [ ! -f instagram-handler-php/.env ]; then
  cp instagram-handler-php/.env.example instagram-handler-php/.env
  echo "" >> instagram-handler-php/.env
  echo "PYTHON_BRIDGE_BIN=${APP_DIR}/instagram-handler/venv/bin/python" >> instagram-handler-php/.env
fi

# Python venv for Instagram bridge
echo "==> Setting up Python bridge..."
cd "${APP_DIR}/instagram-handler"
if [ ! -d venv ]; then
  python -m venv venv
fi
source venv/bin/activate
pip install -q -r requirements.txt
deactivate

echo ""
echo "=============================================="
echo "  INSTALL DONE!"
echo "=============================================="
echo ""
echo "AlwaysData Admin panel mein jao:"
echo "  Web > Sites > Add a site"
echo ""
echo "  Name:        IG Handler"
echo "  Addresses:   ${DOMAIN}"
echo "  Type:        PHP"
echo "  Root dir:    ${APP_DIR}/instagram-handler-php/public"
echo ""
echo "Phir browser kholo: https://${DOMAIN}"
echo ""
echo "Optional: .env edit karo"
echo "  nano ${APP_DIR}/instagram-handler-php/.env"
echo "=============================================="
