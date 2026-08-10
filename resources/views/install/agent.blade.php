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
LOG_FILE="${SHD_LOG_FILE:-/var/log/syshealth/daemon.log}"

mkdir -p "$(dirname "${LOG_FILE}")" 2>/dev/null || true

log() {
  # Always print to stderr first so journalctl shows activity.
  printf '%s %s\n' "$(date -Is 2>/dev/null || date)" "$*" >&2 || true
  printf '%s %s\n' "$(date -Is 2>/dev/null || date)" "$*" >> "${LOG_FILE}" 2>/dev/null || true
}

echo "$(date -Is 2>/dev/null || date) syshealthd boot cmd=${cmd:-} id=${AGENT_ID:-none}" >&2 || true


api() {
  local method="$1"
  local path="$2"
  local data="${3:-}"
  local tmp response code
  tmp="$(mktemp)"
  if [[ -n "${data}" ]]; then
    code="$(curl -sS -o "${tmp}" -w '%{http_code}' -X "${method}" "${PANEL_URL}${path}" \
      -H "Content-Type: application/json" \
      -H "Accept: application/json" \
      -H "X-Agent-Id: ${AGENT_ID}" \
      -H "X-Agent-Secret: ${AGENT_SECRET}" \
      -d "${data}" 2>/dev/null || echo 000)"
  else
    code="$(curl -sS -o "${tmp}" -w '%{http_code}' -X "${method}" "${PANEL_URL}${path}" \
      -H "Accept: application/json" \
      -H "X-Agent-Id: ${AGENT_ID}" \
      -H "X-Agent-Secret: ${AGENT_SECRET}" 2>/dev/null || echo 000)"
  fi
  response="$(cat "${tmp}" 2>/dev/null || true)"
  rm -f "${tmp}"
  if [[ "${code}" != 2* ]]; then
    log "api ${method} ${path} failed http=${code} body=${response:0:300}"
    return 1
  fi
  printf '%s' "${response}"
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

parse_job() {
  JOB_JSON="${1:-{}}" python3 - <<'PY' 2>/dev/null || true
import json, os
raw = os.environ.get("JOB_JSON") or "{}"
try:
    data = json.loads(raw)
except Exception:
    print("")
    print("")
    print("{}")
    raise SystemExit
job = (data.get("data") or {}).get("job") or {}
if not job:
    print("")
    print("")
    print("{}")
else:
    print(job.get("id") or "")
    print(job.get("type") or "")
    print(json.dumps(job.get("payload") or {}))
PY
}

json_err() {
  python3 -c 'import json,sys; print(json.dumps({"success":False,"error":{"code":sys.argv[1],"message":sys.argv[2]}}))' "$1" "$2"
}

website_toggle() {
  local enable="$1"
  local webserver="${2:-nginx}"
  local config_path="$3"
  local base enabled available src out rc
  local nginx_bin="/usr/sbin/nginx"
  local systemctl_bin="/bin/systemctl"

  [[ -x "${nginx_bin}" ]] || nginx_bin="$(command -v nginx || true)"
  [[ -x "${systemctl_bin}" ]] || systemctl_bin="$(command -v systemctl || true)"

  if [[ -z "${config_path}" ]]; then
    json_err "FILE_NOT_FOUND" "missing config_path"
    return 0
  fi

  base="$(basename "${config_path}")"

  case "${webserver}" in
    nginx|"")
      if [[ -z "${nginx_bin}" ]]; then
        json_err "OPERATION_FAILED" "nginx binary not found"
        return 0
      fi
      enabled="/etc/nginx/sites-enabled/${base}"
      available="/etc/nginx/sites-available/${base}"
      if [[ "${enable}" == "1" ]]; then
        if [[ ! -e "${enabled}" ]]; then
          src="${available}"
          if [[ -e "${config_path}" && "${config_path}" != "${enabled}" ]]; then
            src="${config_path}"
          fi
          if [[ ! -e "${src}" ]]; then
            json_err "FILE_NOT_FOUND" "site config not found in sites-available"
            return 0
          fi
          if ! ln -sfn "${src}" "${enabled}"; then
            json_err "PERMISSION_DENIED" "failed to enable site"
            return 0
          fi
        fi
      else
        if [[ -f "${enabled}" && ! -L "${enabled}" && ! -e "${available}" ]]; then
          mkdir -p /etc/nginx/sites-available
          mv -f "${enabled}" "${available}" || rm -f "${enabled}"
        else
          rm -f "${enabled}"
        fi
      fi
      out="$(timeout 15 "${nginx_bin}" -t 2>&1)"
      rc=$?
      if [[ ${rc} -ne 0 ]]; then
        json_err "NGINX_CONFIG_INVALID" "${out}"
        return 0
      fi
      # Avoid systemctl deadlock when called from inside another unit.
      out="$(timeout 15 "${nginx_bin}" -s reload 2>&1)"
      rc=$?
      if [[ ${rc} -ne 0 ]]; then
        json_err "OPERATION_FAILED" "${out}"
        return 0
      fi
      ;;
    apache)
      if [[ "${enable}" == "1" ]]; then
        out="$(timeout 15 a2ensite "${base}" 2>&1)"
        rc=$?
      else
        out="$(timeout 15 a2dissite "${base}" 2>&1)"
        rc=$?
      fi
      if [[ ${rc} -ne 0 ]]; then
        json_err "OPERATION_FAILED" "${out}"
        return 0
      fi
      out="$(timeout 15 "${systemctl_bin:-systemctl}" reload apache2 --no-block 2>&1)"
      rc=$?
      if [[ ${rc} -ne 0 ]]; then
        json_err "OPERATION_FAILED" "${out}"
        return 0
      fi
      ;;
    *)
      json_err "JOB_NOT_ALLOWED" "unsupported webserver"
      return 0
      ;;
  esac

  if [[ "${enable}" == "1" ]]; then
    echo '{"success":true,"result":{"enabled":true,"status":"active"}}'
  else
    echo '{"success":true,"result":{"enabled":false,"status":"disabled"}}'
  fi
  return 0
}

handle_website_job() {
  local job_type="$1"
  local payload_json="$2"
  local webserver config_path result

  webserver="$(printf '%s' "${payload_json}" | python3 -c 'import json,sys; print(json.load(sys.stdin).get("webserver") or "nginx")' 2>/dev/null || echo nginx)"
  config_path="$(printf '%s' "${payload_json}" | python3 -c 'import json,sys; print(json.load(sys.stdin).get("config_path") or "")' 2>/dev/null || true)"

  case "${job_type}" in
    website_start|website_enable)
      website_toggle 1 "${webserver}" "${config_path}"
      ;;
    website_stop|website_disable)
      website_toggle 0 "${webserver}" "${config_path}"
      ;;
    website_restart)
      result="$(website_toggle 0 "${webserver}" "${config_path}")"
      if printf '%s' "${result}" | grep -q '"success":false'; then
        printf '%s\n' "${result}"
        return 0
      fi
      website_toggle 1 "${webserver}" "${config_path}"
      ;;
    *)
      json_err "JOB_NOT_ALLOWED" "unknown website command"
      ;;
  esac
  return 0
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
    log "syshealthd starting panel=${PANEL_URL} agent_id=${AGENT_ID}"
    api POST /api/agent/v1/heartbeat "{\"hostname\":\"$(hostname_val)\",\"agent_version\":\"${AGENT_VERSION}\"}" >/dev/null || true
    api POST /api/agent/v1/discovery "$(discover_payload)" >/dev/null || true
    api POST /api/agent/v1/websites "$(discover_websites)" >/dev/null || true
    loop=0
    while true; do
      set +e
      loop=$((loop + 1))
      if (( loop % 2 == 1 )); then
        api POST /api/agent/v1/heartbeat "{\"hostname\":\"$(hostname_val)\",\"agent_version\":\"${AGENT_VERSION}\"}" >/dev/null
      fi
      if (( loop % 6 == 0 )); then
        api POST /api/agent/v1/metrics "$(metrics_payload)" >/dev/null
      fi
      job_json="$(api GET /api/agent/v1/jobs)"
      api_rc=$?
      if [[ ${api_rc} -ne 0 || -z "${job_json}" ]]; then
        job_json='{}'
      fi
      job_meta="$(parse_job "${job_json}")"
      job_id="$(printf '%s' "${job_meta}" | sed -n '1p')"
      job_type="$(printf '%s' "${job_meta}" | sed -n '2p')"
      job_payload="$(printf '%s' "${job_meta}" | sed -n '3p')"
      if [[ -n "${job_id}" ]]; then
        log "claimed job id=${job_id} type=${job_type}"
        result_json='{"success":false,"error":{"code":"OPERATION_FAILED","message":"handler produced no result"}}'
        case "${job_type}" in
          discover_server)
            api POST /api/agent/v1/discovery "$(discover_payload)" >/dev/null
            result_json='{"success":true,"result":{"ok":true}}'
            ;;
          discover_websites)
            api POST /api/agent/v1/websites "$(discover_websites)" >/dev/null
            result_json='{"success":true,"result":{"ok":true}}'
            ;;
          get_metrics)
            result_json="{\"success\":true,\"result\":$(metrics_payload)}"
            ;;
          website_start|website_stop|website_restart|website_enable|website_disable)
            result_json="$(handle_website_job "${job_type}" "${job_payload:-{}}")"
            if [[ -z "${result_json}" ]]; then
              result_json='{"success":false,"error":{"code":"OPERATION_FAILED","message":"empty website handler result"}}'
            fi
            log "website job result=${result_json}"
            ;;
          *)
            log "unsupported job type=${job_type}"
            result_json='{"success":false,"error":{"code":"JOB_NOT_ALLOWED","message":"Bash fallback does not support this command yet"}}'
            ;;
        esac
        api POST "/api/agent/v1/jobs/${job_id}/result" "${result_json}" >/dev/null
        case "${job_type}" in
          website_*)
            api POST /api/agent/v1/websites "$(discover_websites)" >/dev/null
            ;;
        esac
      fi
      set -e
      sleep 3
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
ExecStart=/bin/bash ${BINARY_PATH} run
Restart=always
RestartSec=3
PrivateTmp=true
StandardOutput=journal
StandardError=journal
SyslogIdentifier=syshealthd

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
