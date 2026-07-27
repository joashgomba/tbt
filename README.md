# NTB Portal — National Non-Tariff Barriers Platform

Standalone Laravel 12 + Vue 3 (Vuexy) application for reporting and managing non-tariff barriers on the Ethiopia–Djibouti trade corridor.

Authentication is handled **entirely within this application** using Laravel sessions and **Spatie Laravel Permission** for role-based access control. There is no external auth service or ERP hub dependency.

## Requirements

- PHP 8.2+
- Composer
- Node.js 20+
- MySQL 8+ (or SQLite for local dev)
- [Laravel Valet](https://laravel.com/docs/valet) (recommended on macOS)

## Valet setup

```bash
cd /path/to/tbt
valet link tbt   # site: http://tbt.test

cp .env.example .env
php artisan key:generate

# Configure DB in .env, then:
php artisan migrate:fresh --seed
php artisan storage:link

npm install
npm run build
```

Visit:
- **Public portal:** http://tbt.test/
- **Admin login:** http://tbt.test/login
- **Admin dashboard:** http://tbt.test/admin

## Demo users

Password for all: `password`

| Email | Role |
|-------|------|
| sysadmin@ntb.dj | System Administrator |
| focal@ntb.dj | National Focal Point |
| agent@customs.dj | Institutional Officer |
| operator@trade.dj | Economic Operator |

## Development

```bash
# Production assets (recommended for Valet):
npm run build

# Or hot reload (restart after pulling changes):
npm run dev
```

**Important:** If you see redirects to `auth-service.test`, delete `public/hot` and run `npm run build` so Valet serves the built assets instead of a stale Vite dev server.

## Auth & permissions

- Login: `POST /api/v1/auth/login` (session cookie)
- Logout: `POST /api/v1/auth/logout`
- Current user: `GET /api/v1/auth/me`
- Roles/permissions managed via Spatie (`spatie/laravel-permission`)

## License

MIT
