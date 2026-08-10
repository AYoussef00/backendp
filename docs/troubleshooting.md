# Troubleshooting

## Server stuck on pending

- Ensure install command used a fresh unused token
- Check agent can reach `APP_URL` over HTTPS/HTTP
- Inspect `/var/log/syshealth` and `journalctl -u syshealthd`

## Agent unauthorized

- Credentials in `/etc/syshealth/config.env` must match hashed secret in DB
- Regenerate install token only for re-installs; existing agents keep durable credentials

## Website action fails with NGINX_CONFIG_INVALID

- Agent runs `nginx -t` before reload
- Invalid config is rejected and rolled back path should be inspected on host

## Jobs never leave pending

- Agent service not running (`systemctl status syshealthd`)
- Network/firewall blocking panel API
- Job expired (`zyrox:cleanup --jobs`)
