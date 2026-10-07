# Browser smoke tests (Playwright)

These tests open key pages and **fail** on any console error, page error, CSP header missing on documents, failed network request (except favicon), or blank body.

## Local

```bash
# One-time browsers
npx playwright install chromium

# App DB + assets
cp .env.example .env   # if needed
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=DemoSeeder
npm run build
npm run test:unit

# Terminal A (optional if Playwright webServer is used)
php artisan serve --host=127.0.0.1 --port=8000

# Terminal B
npm run test:browser
```

`playwright.config.js` starts `php artisan serve` automatically unless `PLAYWRIGHT_SKIP_WEBSERVER=1`.

Demo accounts (from `DemoSeeder`):

| Role  | Email             | Password  |
|-------|-------------------|-----------|
| Admin | admin@example.com | password  |
| Owner | owner@example.com | password  |
| User  | user@example.com  | password  |

## CI

GitHub Actions runs `npm run build` (includes CSP bundle grep) then seeds demo data and `npx playwright test`.

## CSP

Production assets must not contain `new Function(` or `eval(`. Enforced by `scripts/check-csp-bundle.mjs` after every `npm run build`.
