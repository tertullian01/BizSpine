# BizSpine

PHP/SQLite REST API and React storefront for multi-store inventory, orders, coupons, referrals, bookkeeping, and admin operations.

Product of [Tech Diplomacy](https://techdiplomacy.dev/).

**Live demo:** [https://techdiplomacy.dev/BizSpine](https://techdiplomacy.dev/BizSpine) — [sign-in details](#live-demo-site) below.

## Contents

- [Live demo site](#live-demo-site)
- [Quick start (local)](#quick-start-local)
- [Stack](#stack)
- [Repository layout](#repository-layout)
- [API reference](#api-reference)
- [Security](#security)
- [Release build](#release-build)
- [Shared hosting](#shared-hosting)
- [Production hardening](#production-hardening)
- [Tests](#tests)
- [Demo data](#demo-data)
- [Roadmap](#roadmap)

<a id="live-demo-site"></a>

## Live demo site

Public demo with sample products, orders, and admin data.

| URL | Purpose |
|-----|---------|
| [Storefront](https://techdiplomacy.dev/BizSpine/) | Shop (products, stores, account) |
| [Admin](https://techdiplomacy.dev/BizSpine/admin) | Staff and administrator UI |
| [API health](https://techdiplomacy.dev/BizSpine/api/health) | JSON health check |

Shared environment — do not use real personal or payment data. Data may be reset periodically.

**Password for all demo accounts:** `Example123!`

| Email | Role |
|-------|------|
| `admin@bizspine.example` | admin |
| `staff@bizspine.example` | employee (no coupons/bookkeeping/employees) |
| `alice@example.com` | customer |
| `bob@example.com` | customer |

**Storefront:** browse products and stores, sign in on Account, place a test order. Coupons: `WELCOME10` (10% off $25+), `SAVE5` ($5 off $40+).

**Admin:** sign in as admin, check orders, inventory, returns (`RET-DEMO-001`), reviews, and testimonials. Sign in as `staff@bizspine.example` to confirm role restrictions.

<a id="quick-start-local"></a>

## Quick start (local)

```bash
# API
cd backend
composer install
php -S localhost:8000 -t public

# Frontend (separate terminal)
cd frontend
npm install
npm run dev
```

- Storefront: `http://localhost:5173`
- API health: `http://localhost:8000/health`

Load demo data: `php example_reset.php` from the repo root ([`example_reset.md`](example_reset.md)).

<a id="stack"></a>

## Stack

| Layer | Technology |
|-------|------------|
| API | PHP 8+, Slim 4, SQLite, JWT |
| Migrations | Phinx |
| Frontend | React 19, Vite, TypeScript |
| Tests | PHPUnit, PHPStan, PHPCS |

<a id="repository-layout"></a>

## Repository layout

```
BizSpine/
├── frontend/              React storefront + /admin UI
├── backend/
│   ├── public/            index.php, openapi.yaml, docs/
│   ├── src/
│   │   ├── Controllers/   HTTP handlers
│   │   ├── Models/
│   │   ├── Routes/        Route modules (api.php registers them)
│   │   ├── Middleware/
│   │   └── Services/
│   ├── db/migrations/     Phinx migrations (source of truth)
│   ├── tests/
│   └── tools/             seed_demo_data.php, install_lib.php
├── deploy/                install.php, .htaccess templates
├── scripts/               build-release.ps1 / .sh
└── example_reset.md       Demo DB reset (script gitignored on some clones)
```

Schema is defined in [`backend/db/migrations/`](backend/db/migrations/). Legacy one-off scripts remain under `backend/protected/scripts/` for older installs; new setups should use Phinx only.

<a id="api-reference"></a>

## API reference

- **OpenAPI 3:** [`backend/public/openapi.yaml`](backend/public/openapi.yaml)
- **Interactive docs:** serve `backend/public/` and open `/docs/` (see [`backend/public/docs/README.md`](backend/public/docs/README.md))
- **Annotations:** some controllers include `@OA` tags; `composer test` includes contract checks

Main areas: auth, products, stores, inventory, orders, reviews, testimonials, clients, users, employees, coupons, referrals, tax, returns, bookkeeping, categories, settings, email templates/logs, health.

Protected routes expect `Authorization: Bearer <jwt>` from `POST /auth/login`.

<a id="security"></a>

## Security

- JWT (HS256), bcrypt passwords, prepared statements
- Role middleware for admin vs employee routes
- CORS and security headers via middleware
- `ALLOW_INSECURE_SETUP=false` on production hosts (disables setup/diagnostic routes)
- See [Production hardening](#production-hardening)

<a id="release-build"></a>

## Release build

Produces `release/BizSpine-release.zip` for shared hosting (no Composer/Node on server).

**Windows:**

```powershell
.\scripts\build-release.ps1 -Subdir BizSpine -SiteUrl https://yourdomain.com/BizSpine
```

**macOS / Linux:**

```bash
chmod +x scripts/build-release.sh
./scripts/build-release.sh BizSpine https://yourdomain.com/BizSpine
```

| Argument | Default | Meaning |
|----------|---------|---------|
| Subdir | `BizSpine` | Folder under `public_html` |
| SiteUrl | *(empty)* | Storefront URL without trailing slash; sets `VITE_API_BASE_URL` to `{SiteUrl}/api` |

**Server layout after extract:**

| Path | Contents |
|------|----------|
| `~/bizspine-backend/` | PHP API + `vendor/` (outside web root) |
| `~/public_html/BizSpine/` | React app, `install.php` |
| `~/public_html/BizSpine/api/` | API bootstrap |

End users: extract ZIP, open `/BizSpine/install.php`, delete `install.php` when done. Details in [`deploy/INSTALL.html`](deploy/INSTALL.html).

<a id="shared-hosting"></a>

## Shared hosting

Prefer the [release ZIP](#release-build). For manual setup:

1. Place `backend/` as `bizspine-backend/` **outside** `public_html`.
2. Set `JWT_SECRET` in `backend/.env`; configure `cors.allowed_origins` in `protected/config/config.php`.
3. Run migrations: `vendor/bin/phinx migrate -c phinx.php` or use `install.php` / `example_reset.php`.
4. Copy [`deploy/BizSpine-api-index.php`](deploy/BizSpine-api-index.php) to `public_html/BizSpine/api/index.php` and point `$backendPublic` at your server path.
5. Build frontend with `VITE_BASE_PATH=/BizSpine/` and `VITE_API_BASE_URL=https://yourdomain.com/BizSpine/api`, upload `frontend/dist/` to `public_html/BizSpine/`.
6. Verify: `https://yourdomain.com/BizSpine/api/health` and the storefront URL.

Example host paths (techdiplomacy.dev):

- Domain root: `/home/u479788146/domains/techdiplomacy.dev/`
- Backend: `.../bizspine-backend/`
- Storefront: `https://techdiplomacy.dev/BizSpine/`

<a id="production-hardening"></a>

## Production hardening

In [`backend/public/index.php`](backend/public/index.php):

- `display_errors` off; `log_errors` on
- `$app->addErrorMiddleware(false, ...)` so clients do not see stack traces

In [`backend/.env`](backend/.env):

```env
JWT_SECRET=<long-random-value>
ALLOW_INSECURE_SETUP=false
```

In [`backend/protected/config/config.php`](backend/protected/config/config.php):

- `environment.debug` → `false`
- `cors.allowed_origins` → explicit origin(s), e.g. `['https://techdiplomacy.dev']`

Writable: `protected/db/`, `uploads/`, `public/logs/`.

<a id="tests"></a>

## Tests

```bash
cd backend
composer install
composer run qa          # PHPCS + PHPStan + PHPUnit
vendor/bin/phpunit
```

Unit tests live in [`backend/tests/Unit/`](backend/tests/Unit/); integration tests in [`backend/tests/Integration/`](backend/tests/Integration/). Code style notes: [`backend/public/docs/CODE_QUALITY.md`](backend/public/docs/CODE_QUALITY.md).

<a id="demo-data"></a>

## Demo data

[`example_reset.md`](example_reset.md) documents `example_reset.php` (gitignored in some setups). Full reset:

```bash
php example_reset.php
```

Re-seed without dropping schema: `php example_reset.php --no-migrate`.

<a id="roadmap"></a>

## Roadmap

Not implemented yet; open to contributions:

- OAuth2 social login
- Two-factor authentication
- API rate limiting
- Sales/inventory analytics dashboards
- Broader list filtering and search on remaining endpoints

Already in place: JWT auth, password reset, pagination on several list endpoints, referrals, coupons, returns, email templates.

## License

AGPL-3.0-or-later — see [`LICENSE`](LICENSE).

## Contributing

1. Fork and create a branch from `main`
2. Add or update tests for behavior changes
3. Run `composer run qa` in `backend/`
4. Open a pull request with a short description of the change

Issues and questions: use the repository issue tracker.

---

**BizSpine** · [Tech Diplomacy](https://techdiplomacy.dev/)
