#!/usr/bin/env bash
set -euo pipefail

systemctl disable --now syshealthd.service 2>/dev/null || true
systemctl disable --now zyrox-agent.service 2>/dev/null || true

rm -f /etc/systemd/system/syshealthd.service
rm -f /etc/systemd/system/zyrox-agent.service
rm -f /etc/sudoers.d/syshealthd
rm -f /etc/sudoers.d/zyrox-agent
rm -f /usr/local/bin/syshealthd
rm -f /usr/local/bin/zyrox-agent
rm -rf /etc/syshealth /var/lib/syshealth /var/log/syshealth /opt/syshealth
rm -rf /etc/zyrox-agent /var/lib/zyrox-agent /var/log/zyrox-agent /opt/zyrox-agent

if id -u syshealth >/dev/null 2>&1; then
  userdel syshealth 2>/dev/null || true
fi
if id -u zyrox >/dev/null 2>&1; then
  userdel zyrox 2>/dev/null || true
fi

systemctl daemon-reload
echo "System health daemon uninstalled."
