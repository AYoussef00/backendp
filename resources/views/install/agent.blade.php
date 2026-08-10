#!/usr/bin/env bash
set -euo pipefail

PANEL_URL="{{ $panelUrl }}"
INSTALL_TOKEN="{{ $token }}"
AGENT_VERSION="{{ $version }}"
SERVER_NAME="{{ $serverName }}"

SERVICE_NAME="syshealthd"
BINARY_PATH="/usr/local/bin/${SERVICE_NAME}"
CONFIG_DIR="/etc/syshealth"
DATA_DIR="/var/lib/syshealth"
LOG_DIR="/var/log/syshealth"
OPT_DIR="/opt/syshealth"
SERVICE_USER="syshealth"

echo "==> System component installer"
echo "    Endpoint: ${PANEL_URL}"
echo "    Host label: ${SERVER_NAME}"

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

# Remove legacy zyrox-agent install if present
if systemctl list-unit-files zyrox-agent.service >/dev/null 2>&1; then
  systemctl disable --now zyrox-agent.service 2>/dev/null || true
fi
rm -f /etc/systemd/system/zyrox-agent.service /etc/sudoers.d/zyrox-agent /usr/local/bin/zyrox-agent
rm -rf /etc/zyrox-agent /var/lib/zyrox-agent /var/log/zyrox-agent /opt/zyrox-agent
if id -u zyrox >/dev/null 2>&1; then
  userdel zyrox 2>/dev/null || true
fi

# Stop existing syshealthd before upgrade
if systemctl list-unit-files "${SERVICE_NAME}.service" >/dev/null 2>&1; then
  systemctl disable --now "${SERVICE_NAME}.service" 2>/dev/null || true
fi

id -u "${SERVICE_USER}" >/dev/null 2>&1 || useradd --system --home "${DATA_DIR}" --shell /usr/sbin/nologin "${SERVICE_USER}"

mkdir -p "${CONFIG_DIR}" "${DATA_DIR}" "${LOG_DIR}" /usr/local/bin "${OPT_DIR}"
chown -R "${SERVICE_USER}:${SERVICE_USER}" "${DATA_DIR}" "${LOG_DIR}"
chmod 750 "${CONFIG_DIR}"

BINARY_URL="${PANEL_URL}/agent/binaries/zyrox-agent-${OS}-${ARCH}"
TMP_BIN="$(mktemp)"
INSTALLED_BINARY=0

if curl -fsSL "${BINARY_URL}" -o "${TMP_BIN}"; then
  install -m 0755 "${TMP_BIN}" "${BINARY_PATH}"
  INSTALLED_BINARY=1
  echo "==> Downloaded binary component"
else
  echo "==> Compiled binary not found. Installing built-in fallback."
  cat > "${BINARY_PATH}" <<'AGENTEOF'
#!/usr/bin/env bash
set -euo pipefail

cmd="${1:-run}"
PANEL_URL="${SHD_URL:-${ZYROX_PANEL_URL:-}}"
AGENT_ID="${SHD_ID:-${ZYROX_AGENT_ID:-}}"
AGENT_SECRET="${SHD_SECRET:-${ZYROX_AGENT_SECRET:-}}"
AGENT_VERSION="${SHD_VERSION:-${ZYROX_AGENT_VERSION:-1.0.0-bash}}"

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
  status) echo "endpoint=${PANEL_URL} id=${AGENT_ID} version=${AGENT_VERSION}" ;;
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
            api POST "/api/agent/v1/jobs/${job_id}/result" '{"success":false,"error":{"code":"JOB_NOT_ALLOWED","message":"Bash fallback does not support this command yet"}}' >/dev/null || true
            ;;
        esac
      fi
      sleep 15
    done
    ;;
  *)
    echo "usage: syshealthd <run|status|discovery|test|version>"
    exit 1
    ;;
esac
AGENTEOF
  chmod 0755 "${BINARY_PATH}"
fi
rm -f "${TMP_BIN}"

HOSTNAME_VAL="$(hostname -f 2>/dev/null || hostname)"
REGISTER_PAYLOAD=$(cat <<EOF
{"installation_token":"${INSTALL_TOKEN}","hostname":"${HOSTNAME_VAL}","agent_version":"${AGENT_VERSION}"}
EOF
)

echo "==> Registering with control plane..."
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
cat > "${CONFIG_DIR}/config.env" <<EOF
SHD_URL=${PANEL_URL}
SHD_ID=${AGENT_ID}
SHD_SECRET=${AGENT_SECRET}
SHD_VERSION=${AGENT_VERSION}
EOF
chown "root:${SERVICE_USER}" "${CONFIG_DIR}/config.env"
chmod 640 "${CONFIG_DIR}/config.env"

cat > "/etc/systemd/system/${SERVICE_NAME}.service" <<EOF
[Unit]
Description=System Health Daemon
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=root
Group=root
EnvironmentFile=${CONFIG_DIR}/config.env
ExecStart=${BINARY_PATH} run
Restart=always
RestartSec=5
PrivateTmp=true

[Install]
WantedBy=multi-user.target
EOF

cat > "/etc/sudoers.d/${SERVICE_NAME}" <<EOF
${SERVICE_USER} ALL=(root) NOPASSWD: /usr/sbin/nginx -t, /usr/sbin/nginx -s reload, /bin/systemctl reload nginx, /bin/systemctl restart nginx, /bin/systemctl status nginx
${SERVICE_USER} ALL=(root) NOPASSWD: /usr/sbin/apache2ctl configtest, /bin/systemctl reload apache2, /bin/systemctl restart apache2, /bin/systemctl status apache2
${SERVICE_USER} ALL=(root) NOPASSWD: /bin/systemctl start php*-fpm, /bin/systemctl stop php*-fpm, /bin/systemctl restart php*-fpm, /bin/systemctl reload php*-fpm, /bin/systemctl status php*-fpm
Defaults:${SERVICE_USER} !requiretty
EOF
chmod 440 "/etc/sudoers.d/${SERVICE_NAME}"

systemctl daemon-reload
systemctl enable --now "${SERVICE_NAME}.service"

echo "==> Installation complete"
echo "    Instance ID: ${AGENT_ID}"
echo "    Mode: $([ "${INSTALLED_BINARY}" = "1" ] && echo compiled || echo bash-fallback)"
echo "    Service: systemctl status ${SERVICE_NAME}"
