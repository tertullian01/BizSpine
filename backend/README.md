# BizSpine API

Slim 4 REST API with SQLite for the BizSpine storefront and admin UI. Monorepo root: [`../README.md`](../README.md).

## Quick start

```bash
composer install
cp .env.example .env   # if present; set JWT_SECRET
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

**`.env`** (not committed):

```env
JWT_SECRET=<random-secret>
ALLOW_INSECURE_SETUP=false
```

**`protected/config/config.php`** — database path, CORS origins, `environment.debug`, upload limits. Optional override: `protected/config/install_local.php`.

CORS example:

```php
'cors' => [
    'allowed_origins' => ['https://techdiplomacy.dev'],
],
```

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

## Production

Before a public host:

- `JWT_SECRET` in `.env`; `ALLOW_INSECURE_SETUP=false`
- `environment.debug` → `false` in config
- In `public/index.php`: `display_errors` off; `addErrorMiddleware(false, ...)`
- Writable: `protected/db/`, `uploads/`, `public/logs/`

Shared-hosting layout and release ZIP: see [root README](../README.md#shared-hosting).

## Modules

Auth, products, stores, inventory, orders, reviews, testimonials, clients, users, employees, coupons, referrals, tax, returns, bookkeeping, categories, settings, email templates, email logs, health, contact.

## License

AGPL-3.0-or-later — see [`../LICENSE`](../LICENSE).
