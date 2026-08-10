#!/usr/bin/env bash
set -euo pipefail

systemctl disable --now zyrox-agent.service 2>/dev/null || true
rm -f /etc/systemd/system/zyrox-agent.service
rm -f /etc/sudoers.d/zyrox-agent
rm -f /usr/local/bin/zyrox-agent
rm -rf /etc/zyrox-agent /var/lib/zyrox-agent /var/log/zyrox-agent /opt/zyrox-agent
systemctl daemon-reload
echo "ZYROX agent uninstalled."
