#!/bin/sh
set -eu

export HOME="/Users/mmurphy"
export PATH="/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin"

mkdir -p "/Users/mmurphy/Desktop/CreditSoft/storage/logs"
exec >> "/Users/mmurphy/Desktop/CreditSoft/storage/logs/ngrok-creditsoft-runner.log" 2>&1

echo "[$(date -u '+%Y-%m-%dT%H:%M:%SZ')] starting CreditSoft ngrok runner"
echo "HOME=$HOME"
echo "PATH=$PATH"
id
pwd

exec /opt/homebrew/bin/ngrok http \
  --config "/Users/mmurphy/Library/Application Support/ngrok/ngrok.yml" \
  "http://100.80.51.78:8001" \
  --log=stdout
