#!/usr/bin/env bash
# Allow the panel PHP user to manage nginx/apache site enablement without a password.
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
  echo "Run as root: sudo bash $0"
  exit 1
fi

PHP_USER="${1:-www-data}"
FILE="/etc/sudoers.d/zyrox-panel"

cat > "${FILE}" <<EOF
${PHP_USER} ALL=(root) NOPASSWD: /usr/sbin/nginx, /bin/ln, /usr/bin/ln, /bin/rm, /usr/bin/rm, /bin/mv, /usr/bin/mv, /bin/mkdir, /usr/bin/mkdir, /usr/sbin/a2ensite, /usr/sbin/a2dissite, /bin/systemctl reload apache2, /bin/systemctl reload apache2 --no-block
Defaults:${PHP_USER} !requiretty
EOF

chmod 440 "${FILE}"
visudo -cf "${FILE}"
echo "Installed ${FILE} for user ${PHP_USER}"
