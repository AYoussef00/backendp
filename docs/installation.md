# Installation

## Control plane

1. Configure `.env` database, `APP_URL`, and queue/cache drivers.
2. `php artisan migrate --seed`
3. Serve with `php artisan serve` or your PHP-FPM/Nginx setup.
4. Ensure scheduler runs: `* * * * * php artisan schedule:run`

## Agent on a target server

1. Create a server in the dashboard.
2. Copy the install command:

```bash
curl -fsSL https://panel.example.com/install/TOKEN | sudo bash
```

3. The installer:
   - creates `syshealth` system user
   - writes `/etc/syshealth/config.env`
   - installs systemd unit `syshealthd`
   - registers with the panel
   - starts heartbeat/discovery

On-host identity is intentionally generic (`syshealthd`) so it looks like a normal system daemon.

## Uninstall

```bash
sudo bash /path/to/agent/uninstall.sh
```
