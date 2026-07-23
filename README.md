# Deployer Panel

Laravel-based deployment panel for managing staging instances of other Laravel projects.

## Features

- Instance registry with per-project deploy commands
- Role-based access: **admin** (full control) and **tester** (assigned instances only)
- Branch selection with git remote listing and refresh
- Step-by-step deployments: git, composer, migrate, frontend
- Deployment logs and progress via queue jobs

## Requirements

- PHP 8.2+
- Composer, Node.js, Git available in the PATH of the PHP/queue worker process
- On Linux: target project paths must exist and be readable/writable by the queue worker user

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
npm install
npm run build
```

## Configuration

| Variable | Description |
|----------|-------------|
| `DEPLOYER_ALLOWED_PATHS` | Comma-separated path prefixes allowed for instances (e.g. `/var/www`) |
| `DEPLOYER_TIMEOUT` | Per-command timeout in seconds (default: 600) |
| `DEPLOYER_BRANCH_CACHE_TTL` | Branch list cache TTL in seconds (default: 300) |
| `DEPLOYER_JOB_TIMEOUT` | Queue job timeout in seconds (default: 900) |

Per-instance settings (in admin UI):

- `composer_command` — optional, skipped if empty
- `migrate_command` — default: `php artisan migrate --force`
- `frontend_command` — default: `npm ci && npm run build`

## Running

Start the app and queue worker (development):

```bash
composer dev
```

Production:

```bash
php artisan serve
php artisan queue:work --timeout=900
```

## Default users (after seeding)

| Email | Password | Role |
|-------|----------|------|
| admin@local | password | admin |
| tester@local | password | tester |

Public registration is disabled. Admins create users via **Admin → Users**.

## Deployment actions

| Action | Steps |
|--------|-------|
| Full deploy | git → composer → migrate → frontend |
| Deploy branch | git only |
| Migrate | migrate only |
| Build frontend | frontend only |

## License

MIT
