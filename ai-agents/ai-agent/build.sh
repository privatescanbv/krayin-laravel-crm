#!/usr/bin/env bash

set -e

APP_DIR="/home/apps/ai-agents/ai-agent"
VENV_DIR="$APP_DIR/venv"

echo "➡️  Ga naar project directory"
cd "$APP_DIR"

echo "➡️  Check Python"
python3 --version

echo "➡️  Maak venv (indien niet bestaat)"
if [ ! -d "$VENV_DIR" ]; then
  python3 -m venv venv
fi

echo "➡️  Activeer venv"
source venv/bin/activate

echo "➡️  Upgrade pip"
pip install --upgrade pip

echo "➡️  Installeer dependencies"
pip install -r requirements.txt

echo "✅ AI agent build is done"

#echo "➡️  Stop bestaande uvicorn (indien draait)"
#pkill -f "uvicorn main:app" || true
#
#echo "➡️  Start AI agent"
#nohup venv/bin/uvicorn main:app \
#  --host 0.0.0.0 \
#  --port 8001 \
#  > app.log 2>&1 &

#echo "✅ AI agent draait op poort 8001"

cd /home/apps
