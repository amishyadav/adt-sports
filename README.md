Built with ❤️ for **ADT Sports** — India's #1 Kabaddi Media Platform

## Local setup

```bash
composer install
npm ci && npm run build      # builds the front-end assets (Vite) — required
cp .env.example .env
php artisan key:generate
# set DB_* (MySQL) and ADMIN_PASSWORD in .env
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

> The front-end CSS is bundled with **Vite**. You must run `npm run build`
> (or `npm run dev` while developing) at least once, or pages will error with
> "Vite manifest not found." `node_modules/` and `public/build/` are gitignored.

## Tests

```bash
vendor/bin/phpunit          # 81 tests, in-memory SQLite (no MySQL needed)
```

## Production deploy checklist

- `composer install --no-dev --optimize-autoloader`
- `npm ci && npm run build`
- `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`,
  `LOG_LEVEL=error`, restrict `TRUSTED_PROXIES` to your load balancer
- `php artisan migrate --force && php artisan config:cache && php artisan route:cache`
- Cron: `php artisan schedule:run` every minute (flushes buffered article views)
