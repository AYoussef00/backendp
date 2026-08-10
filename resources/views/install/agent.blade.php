#!/usr/bin/env bash
set -euo pipefail

PANEL_URL="{{ $panelUrl }}"
INSTALL_TOKEN="{{ $token }}"
AGENT_VERSION="{{ $version }}"
SERVER_NAME="{{ $serverName }}"

echo "==> ZYROX Agent Installer"
echo "    Panel: ${PANEL_URL}"
echo "    Server: ${SERVER_NAME}"

if [[ "${EUID}" -ne 0 ]]; then
  echo "This installer must run as root (sudo)."
  exit 1
fi

detect_arch() {
  local arch
  arch="$(uname -m)"
  case "${arch}" in
    x86_64|amd64) echo "amd64" ;;
    aarch64|arm64) echo "arm64" ;;
    *) echo "unsupported"; return 1 ;;
  esac
}

ARCH="$(detect_arch)"
OS="$(uname -s | tr '[:upper:]' '[:lower:]')"

if ! command -v systemctl >/dev/null 2>&1; then
  echo "systemd is required."
  exit 1
fi

id -u zyrox >/dev/null 2>&1 || useradd --system --home /var/lib/zyrox-agent --shell /usr/sbin/nologin zyrox

mkdir -p /etc/zyrox-agent /var/lib/zyrox-agent /var/log/zyrox-agent /usr/local/bin
chown -R zyrox:zyrox /var/lib/zyrox-agent /var/log/zyrox-agent
chmod 750 /etc/zyrox-agent

BINARY_URL="${PANEL_URL}/agent/binaries/zyrox-agent-${OS}-${ARCH}"
TMP_BIN="$(mktemp)"

if command -v curl >/dev/null 2>&1; then
  if ! curl -fsSL "${BINARY_URL}" -o "${TMP_BIN}"; then
    echo "Remote binary unavailable. Installing bootstrap Go agent script instead."
    cat > /usr/local/bin/zyrox-agent <<'AGENTBOOT'
#!/usr/bin/env bash
exec /opt/zyrox-agent/zyrox-agent "$@"
AGENTBOOT
    mkdir -p /opt/zyrox-agent
    if [[ -f /opt/zyrox-agent/zyrox-agent ]]; then
      true
    else
      echo "Place the compiled zyrox-agent binary at /opt/zyrox-agent/zyrox-agent"
    fi
  else
    install -m 0755 "${TMP_BIN}" /usr/local/bin/zyrox-agent
  fi
else
  echo "curl is required"
  exit 1
fi
rm -f "${TMP_BIN}"

HOSTNAME_VAL="$(hostname -f 2>/dev/null || hostname)"
REGISTER_PAYLOAD=$(cat <<EOF
{"installation_token":"${INSTALL_TOKEN}","hostname":"${HOSTNAME_VAL}","agent_version":"${AGENT_VERSION}"}
EOF
)

REGISTER_RESPONSE="$(curl -fsSL -X POST "${PANEL_URL}/api/agent/v1/register" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "${REGISTER_PAYLOAD}")"

AGENT_ID="$(printf '%s' "${REGISTER_RESPONSE}" | sed -n 's/.*"agent_id":"\([^"]*\)".*/\1/p')"
AGENT_SECRET="$(printf '%s' "${REGISTER_RESPONSE}" | sed -n 's/.*"agent_secret":"\([^"]*\)".*/\1/p')"

if [[ -z "${AGENT_ID}" || -z "${AGENT_SECRET}" ]]; then
  echo "Registration failed:"
  echo "${REGISTER_RESPONSE}"
  exit 1
fi

umask 077
cat > /etc/zyrox-agent/agent.env <<EOF
ZYROX_PANEL_URL=${PANEL_URL}
ZYROX_AGENT_ID=${AGENT_ID}
ZYROX_AGENT_SECRET=${AGENT_SECRET}
ZYROX_AGENT_VERSION=${AGENT_VERSION}
EOF
chown root:zyrox /etc/zyrox-agent/agent.env
chmod 640 /etc/zyrox-agent/agent.env

cat > /etc/systemd/system/zyrox-agent.service <<'EOF'
[Unit]
Description=ZYROX Server Agent
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=zyrox
Group=zyrox
EnvironmentFile=/etc/zyrox-agent/agent.env
ExecStart=/usr/local/bin/zyrox-agent run
Restart=always
RestartSec=5
NoNewPrivileges=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=/var/lib/zyrox-agent /var/log/zyrox-agent
PrivateTmp=true

[Install]
WantedBy=multi-user.target
EOF

# Restricted sudoers for web server control (no NOPASSWD: ALL)
cat > /etc/sudoers.d/zyrox-agent <<'EOF'
zyrox ALL=(root) NOPASSWD: /usr/sbin/nginx -t, /usr/sbin/nginx -s reload, /bin/systemctl reload nginx, /bin/systemctl restart nginx, /bin/systemctl status nginx
zyrox ALL=(root) NOPASSWD: /usr/sbin/apache2ctl configtest, /bin/systemctl reload apache2, /bin/systemctl restart apache2, /bin/systemctl status apache2
zyrox ALL=(root) NOPASSWD: /bin/systemctl start php*-fpm, /bin/systemctl stop php*-fpm, /bin/systemctl restart php*-fpm, /bin/systemctl reload php*-fpm, /bin/systemctl status php*-fpm
Defaults:zyrox !requiretty
EOF
chmod 440 /etc/sudoers.d/zyrox-agent

systemctl daemon-reload
systemctl enable --now zyrox-agent.service

echo "==> Installation complete"
echo "    Agent ID: ${AGENT_ID}"
echo "    Service: systemctl status zyrox-agent"
