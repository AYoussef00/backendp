# ZYROX Server Manager

Production-oriented multi-tenant SaaS control plane for managing Linux servers through a lightweight outbound Go agent.

## Stack

- Laravel 13 + PHP 8.3+
- Inertia.js + Vue 3 + TypeScript + Tailwind CSS 4
- Fortify (auth) + Sanctum + Spatie Permission (teams/orgs)
- MySQL/PostgreSQL + Redis-ready queue/cache
- Go agent (`agent/`)

## Quick start

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run dev
php artisan serve
```

Demo login after seed:

- Email: `admin@zyrox.test`
- Password: `password`

## Agent

```bash
cd agent
chmod +x scripts/build.sh
./scripts/build.sh
```

From the dashboard: **Servers → Add Server** → run the generated install command on the target host.

## Docs

- [Architecture](docs/architecture.md)
- [Installation](docs/installation.md)
- [Agent](docs/agent.md)
- [API](docs/api.md)
- [Security](docs/security.md)
- [Development](docs/development.md)
- [Deployment](docs/deployment.md)
- [Troubleshooting](docs/troubleshooting.md)
