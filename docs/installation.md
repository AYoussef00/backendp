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
curl -fsSL https://panel.example.com/install/TOKEN.sh | sudo bash
```

3. The installer:
   - creates `zyrox` system user
   - writes `/etc/zyrox-agent/agent.env`
   - installs systemd unit
   - registers with the panel
   - starts heartbeat/discovery

## Uninstall

```bash
sudo bash /path/to/agent/uninstall.sh
```
