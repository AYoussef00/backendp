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

if ! command -v curl >/dev/null 2>&1; then
  echo "curl is required"
  exit 1
fi

id -u zyrox >/dev/null 2>&1 || useradd --system --home /var/lib/zyrox-agent --shell /usr/sbin/nologin zyrox

mkdir -p /etc/zyrox-agent /var/lib/zyrox-agent /var/log/zyrox-agent /usr/local/bin /opt/zyrox-agent
chown -R zyrox:zyrox /var/lib/zyrox-agent /var/log/zyrox-agent
chmod 750 /etc/zyrox-agent

BINARY_URL="${PANEL_URL}/agent/binaries/zyrox-agent-${OS}-${ARCH}"
TMP_BIN="$(mktemp)"
INSTALLED_BINARY=0

if curl -fsSL "${BINARY_URL}" -o "${TMP_BIN}"; then
  install -m 0755 "${TMP_BIN}" /usr/local/bin/zyrox-agent
  INSTALLED_BINARY=1
  echo "==> Downloaded compiled agent binary"
else
  echo "==> Compiled binary not found on panel. Installing built-in bash agent fallback."
  cat > /usr/local/bin/zyrox-agent <<'AGENTEOF'
#!/usr/bin/env bash
set -euo pipefail

cmd="${1:-run}"
PANEL_URL="${ZYROX_PANEL_URL:-}"
AGENT_ID="${ZYROX_AGENT_ID:-}"
AGENT_SECRET="${ZYROX_AGENT_SECRET:-}"
AGENT_VERSION="${ZYROX_AGENT_VERSION:-1.0.0-bash}"

api() {
  local method="$1"
  local path="$2"
  local data="${3:-}"
  if [[ -n "${data}" ]]; then
    curl -fsS -X "${method}" "${PANEL_URL}${path}" \
      -H "Content-Type: application/json" \
      -H "Accept: application/json" \
      -H "X-Agent-Id: ${AGENT_ID}" \
      -H "X-Agent-Secret: ${AGENT_SECRET}" \
      -d "${data}"
  else
    curl -fsS -X "${method}" "${PANEL_URL}${path}" \
      -H "Accept: application/json" \
      -H "X-Agent-Id: ${AGENT_ID}" \
      -H "X-Agent-Secret: ${AGENT_SECRET}"
  fi
}

hostname_val() { hostname -f 2>/dev/null || hostname; }

os_name() {
  if [[ -f /etc/os-release ]]; then
    . /etc/os-release
    echo "${NAME:-Linux}"
  else
    uname -s
  fi
}

os_version() {
  if [[ -f /etc/os-release ]]; then
    . /etc/os-release
    echo "${VERSION_ID:-}"
  fi
}

mem_total() {
  awk '/MemTotal:/ {print $2 * 1024}' /proc/meminfo 2>/dev/null || echo 0
}

cpu_cores() { nproc 2>/dev/null || echo 1; }

discover_payload() {
  cat <<JSON
{"hostname":"$(hostname_val)","os":{"name":"$(os_name)","version":"$(os_version)"},"cpu":{"cores":$(cpu_cores)},"memory":{"total":$(mem_total)},"services":[]}
JSON
}

discover_websites() {
  python3 - <<'PY' 2>/dev/null || echo '{"websites":[]}'
import json, os, re, glob
sites=[]
for path in glob.glob('/etc/nginx/sites-enabled/*') + glob.glob('/etc/apache2/sites-enabled/*'):
    try:
        text=open(path).read()
    except Exception:
        continue
    names=re.findall(r'(?:server_name|ServerName)\s+([^;]+)', text)
    roots=re.findall(r'(?:^\s*root\s+|DocumentRoot\s+)([^\s;]+)', text, re.M)
    if not names:
        continue
    parts=names[0].split()
    primary=parts[0]
    if primary == '_':
        continue
    sites.append({
        "domain": primary,
        "aliases": parts[1:],
        "root_path": roots[0] if roots else None,
        "config_path": path,
        "webserver": "nginx" if "nginx" in path else "apache",
        "ssl": "ssl_certificate" in text or "SSLEngine" in text,
        "status": "active",
    })
print(json.dumps({"websites": sites}))
PY
}

metrics_payload() {
  local mem_total mem_avail mem_pct load1
  mem_total=$(awk '/MemTotal:/ {print $2}' /proc/meminfo 2>/dev/null || echo 1)
  mem_avail=$(awk '/MemAvailable:/ {print $2}' /proc/meminfo 2>/dev/null || echo 0)
  mem_pct=$(awk -v t="$mem_total" -v a="$mem_avail" 'BEGIN{ if(t==0){print 0}else{printf "%.2f", ((t-a)/t)*100 }}')
  load1=$(awk '{print $1}' /proc/loadavg 2>/dev/null || echo 0)
  cat <<JSON
{"cpu_percent":0,"memory_percent":${mem_pct},"load_1":${load1},"uptime_seconds":$(awk '{print int($1)}' /proc/uptime 2>/dev/null || echo 0)}
JSON
}

case "${cmd}" in
  version) echo "${AGENT_VERSION}" ;;
  status) echo "panel=${PANEL_URL} agent_id=${AGENT_ID} version=${AGENT_VERSION}" ;;
  discovery)
    api POST /api/agent/v1/discovery "$(discover_payload)" >/dev/null
    api POST /api/agent/v1/websites "$(discover_websites)" >/dev/null
    echo "discovery sent"
    ;;
  test)
    api POST /api/agent/v1/heartbeat "{\"hostname\":\"$(hostname_val)\",\"agent_version\":\"${AGENT_VERSION}\"}" >/dev/null
    echo ok
    ;;
  run)
    api POST /api/agent/v1/heartbeat "{\"hostname\":\"$(hostname_val)\",\"agent_version\":\"${AGENT_VERSION}\"}" >/dev/null || true
    api POST /api/agent/v1/discovery "$(discover_payload)" >/dev/null || true
    api POST /api/agent/v1/websites "$(discover_websites)" >/dev/null || true
    while true; do
      api POST /api/agent/v1/heartbeat "{\"hostname\":\"$(hostname_val)\",\"agent_version\":\"${AGENT_VERSION}\"}" >/dev/null || true
      api POST /api/agent/v1/metrics "$(metrics_payload)" >/dev/null || true
      job_json="$(api GET /api/agent/v1/jobs || echo '{}')"
      job_id="$(printf '%s' "${job_json}" | sed -n 's/.*"id":\([0-9]*\).*/\1/p' | head -1)"
      job_type="$(printf '%s' "${job_json}" | sed -n 's/.*"type":"\([^"]*\)".*/\1/p' | head -1)"
      if [[ -n "${job_id}" ]]; then
        case "${job_type}" in
          discover_server)
            api POST /api/agent/v1/discovery "$(discover_payload)" >/dev/null || true
            api POST "/api/agent/v1/jobs/${job_id}/result" '{"success":true,"result":{"ok":true}}' >/dev/null || true
            ;;
          discover_websites)
            api POST /api/agent/v1/websites "$(discover_websites)" >/dev/null || true
            api POST "/api/agent/v1/jobs/${job_id}/result" '{"success":true,"result":{"ok":true}}' >/dev/null || true
            ;;
          get_metrics)
            api POST "/api/agent/v1/jobs/${job_id}/result" "{\"success\":true,\"result\":$(metrics_payload)}" >/dev/null || true
            ;;
          *)
            api POST "/api/agent/v1/jobs/${job_id}/result" '{"success":false,"error":{"code":"JOB_NOT_ALLOWED","message":"Bash fallback agent does not support this command yet"}}' >/dev/null || true
            ;;
        esac
      fi
      sleep 15
    done
    ;;
  *)
    echo "usage: zyrox-agent <run|status|discovery|test|version>"
    exit 1
    ;;
esac
AGENTEOF
  chmod 0755 /usr/local/bin/zyrox-agent
fi
rm -f "${TMP_BIN}"

HOSTNAME_VAL="$(hostname -f 2>/dev/null || hostname)"
REGISTER_PAYLOAD=$(cat <<EOF
{"installation_token":"${INSTALL_TOKEN}","hostname":"${HOSTNAME_VAL}","agent_version":"${AGENT_VERSION}"}
EOF
)

echo "==> Registering agent with panel..."
REGISTER_RESPONSE="$(curl -fsS -X POST "${PANEL_URL}/api/agent/v1/register" \
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
User=root
Group=root
EnvironmentFile=/etc/zyrox-agent/agent.env
ExecStart=/usr/local/bin/zyrox-agent run
Restart=always
RestartSec=5
PrivateTmp=true

[Install]
WantedBy=multi-user.target
EOF

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
echo "    Binary mode: $([ "${INSTALLED_BINARY}" = "1" ] && echo compiled || echo bash-fallback)"
echo "    Service: systemctl status zyrox-agent"
