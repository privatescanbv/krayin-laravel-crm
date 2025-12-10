#!/bin/bash

TARGET_URL=$1

if [ -z "$TARGET_URL" ]; then
  echo "Usage: ./zap_scan.sh https://cms.local.privatescan.nl"
  exit 1
fi

docker run --rm \
  --add-host cms.local.privatescan.nl:host-gateway \
  -v $(pwd):/zap/wrk \
  zaproxy/zap-stable:latest zap-baseline.py \
  -t "$TARGET_URL" \
  -r zap_report.html

echo "ZAP baseline scan completed."
echo "Report saved to: zap_report.html"
