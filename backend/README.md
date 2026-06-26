# BizSpine API

Slim 4 REST API with SQLite for the BizSpine storefront and admin UI. Monorepo root: [`../README.md`](../README.md).

## Quick start

```bash
composer install
cp .env.example .env   # set JWT_SECRET to a long random string
php -S localhost:8000 -t public
```

Health check: `http://localhost:8000/health`

Demo database from repo root: `php ../example_reset.php` ([`../example_reset.md`](../example_reset.md)).

## Layout

```
backend/
├── public/           index.php, openapi.yaml, docs/, setup.html
├── src/
│   ├── Controllers/
│   ├── Models/
│   ├── Routes/       api.php + *Routes.php per area
│   ├── Middleware/
│   └── Services/
├── db/migrations/    Phinx (use this for schema changes)
├── protected/
│   ├── config/config.php
│   ├── db/           database.sqlite
│   └── scripts/      legacy one-off scripts (prefer Phinx)
├── tests/Unit/       tests/Integration/
└── tools/            seed_demo_data.php, install_lib.php
```

## API docs

- Spec: [`public/openapi.yaml`](public/openapi.yaml)
- Swagger UI: `http://localhost:8000/docs/` when serving `public/`
- Notes: [`public/docs/README.md`](public/docs/README.md)

Auth: `POST /auth/login` → `Authorization: Bearer <token>` on protected routes.

## Configuration

**`.env`** (not committed; see [`.env.example`](.env.example)):

```env
JWT_SECRET=<random-secret>
ALLOW_INSECURE_SETUP=false
```

An empty `JWT_SECRET=` line is treated as unset and the API will refuse to start. The release ZIP excludes `.env` — create it on the server or run the web installer.

**`protected/config/config.php`** — database path, CORS origins, `environment.debug`, upload limits. On installed hosts, prefer **`protected/config/install_local.php`** (written by the web installer) for storefront-specific CORS origins.

CORS example (list every **storefront** origin that calls the API — not the API host itself):

```php
'cors' => [
    // Path-based API: https://yourdomain.com/BizSpine/api
    'allowed_origins' => ['https://yourdomain.com'],
    // Subdomain API: storefront at https://yourdomain.com, API at https://api.yourdomain.com
    // 'allowed_origins' => ['https://yourdomain.com', 'https://www.yourdomain.com'],
],
```

Cross-origin login (`POST /auth/login` from a separate frontend host) sends an **OPTIONS preflight** first. If the browser shows `net::ERR_FAILED`, check:

1. `allowed_origins` includes the exact scheme + host of the page (including `www` if used).
2. OPTIONS on `/auth/login` returns **200** with `Access-Control-Allow-Origin` — test with curl (see [root README troubleshooting](../README.md#troubleshooting)).
3. [`public/index.php`](public/index.php) registers **`CorsMiddleware` last** so preflight is handled before routing (see [Middleware](#middleware) below).

JWT signing and role-gated routes use [`src/Routes/RouteSecurity.php`](src/Routes/RouteSecurity.php).

## Database

```bash
vendor/bin/phinx migrate -c phinx.php
vendor/bin/phinx create MyMigrationName
```

Web UI: `public/setup.html` (run pending migrations on existing DB).

Legacy `composer db:update` / `protected/scripts/` remain for old installs; new work should add Phinx migrations under `db/migrations/`.

## Tests and quality

```bash
composer run qa       # PHPCS + PHPStan + PHPUnit
composer test
composer test:coverage
```

Details: [`public/docs/CODE_QUALITY.md`](public/docs/CODE_QUALITY.md).

## Middleware

Slim 4 runs middleware in **reverse registration order** — the **last** `$app->add(...)` runs **first** on each request.

In [`public/index.php`](public/index.php), register layers innermost-first, with **CORS outermost (added last)**:

```
Request flow: CORS → Error → Routing → Security headers → Body parsing → File upload → Metrics → route
```

Do not register `RoutingMiddleware` after `CorsMiddleware` in a way that makes routing run before CORS. POST-only routes (e.g. `/auth/login`) reject OPTIONS with 405 and cross-origin login breaks in the browser.

## Production

Before a public host:

- `JWT_SECRET` in `.env` (non-empty); `ALLOW_INSECURE_SETUP=false`
- `environment.debug` → `false` in config (controls Slim `addErrorMiddleware` display of error details)
- In `public/index.php`: turn `display_errors` off; keep `log_errors` on
- Deploy the **full** `backend/` tree when updating — not only `public/index.php` (see [root README](../README.md#shared-hosting))
- Writable: `protected/db/`, `uploads/`, `public/logs/`

Shared-hosting layout, CORS, and troubleshooting: [root README](../README.md#shared-hosting).

## Modules

Auth, products, stores, inventory, orders, reviews, testimonials, clients, users, employees, coupons, referrals, tax, returns, bookkeeping, categories, settings, email templates, email logs, health, contact.

## License

AGPL-3.0-or-later — see [`../LICENSE`](../LICENSE).
