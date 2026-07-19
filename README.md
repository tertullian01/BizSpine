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
- [Troubleshooting](#troubleshooting)
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

- **OpenAPI 3:** [`backend/public/openapi.yaml`](backend/public/openapi.yaml) (canonical; served at `/openapi.yaml` and `/docs/`)
- **Response envelope:** `{ "success": true, "data": ... }` or `{ "success": false, "error": "..." }`
- **Current user:** `GET /auth/me` with `Authorization: Bearer <token>`
- **Interactive docs:** serve `backend/public/` and open `/docs/` (see [`backend/public/docs/README.md`](backend/public/docs/README.md))
- **Annotations:** some controllers include `@OA` tags; `composer test` includes contract checks

Main areas: auth, products, stores, inventory, orders, reviews, testimonials, clients, users, employees, coupons, referrals, tax, returns, bookkeeping, categories, settings, email templates/logs, health, system (export).

Protected routes expect `Authorization: Bearer <jwt>` from `POST /auth/login`.

**Testimonials (public storefront):**
- `GET /testimonials/published` — published reviews (includes `is_featured`, `rating`)
- `GET /testimonials/featured` — published + featured only (no auth)

**System (admin):**
- `GET /system/export` — download ZIP of all DB tables as CSV (admin JWT required)

<a id="security"></a>

## Security

- JWT (HS256) via [`backend/src/Routes/RouteSecurity.php`](backend/src/Routes/RouteSecurity.php), bcrypt passwords, prepared statements
- Role middleware (`PrivilegedRoleMiddleware`) for admin vs employee routes
- CORS and security headers via middleware — CORS must be the outermost layer in Slim (see [backend README](backend/README.md#middleware))
- `ALLOW_INSECURE_SETUP=false` on production hosts (disables setup/diagnostic routes such as `/system/import` and `/system/migrate`; admin `GET /system/export` remains available behind JWT)
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

1. Place `backend/` as `bizspine-backend/` **outside** `public_html` (or your equivalent layout).
2. Create `backend/.env` with a non-empty `JWT_SECRET` (copy from [`.env.example`](backend/.env.example)). The release ZIP **excludes** `.env` — the web installer creates it, or add it by hand.
3. Configure CORS: set `cors.allowed_origins` to your **storefront** URL(s) in `protected/config/config.php` or `protected/config/install_local.php` (installer writes the latter). List `www` and non-`www` if both are used. The API subdomain is not an allowed origin — only the site that runs the browser UI.
4. Run migrations: `vendor/bin/phinx migrate -c phinx.php` or use `install.php` / `example_reset.php`.
5. Copy [`deploy/BizSpine-api-index.php`](deploy/BizSpine-api-index.php) to `public_html/BizSpine/api/index.php` and point `$backendPublic` at your server path — **or** map your API vhost to `backend/public/` if using a subdomain (e.g. `api.yourdomain.com`).
6. Build frontend with `VITE_BASE_PATH=/BizSpine/` and `VITE_API_BASE_URL=https://yourdomain.com/BizSpine/api` (or `https://api.yourdomain.com` for a subdomain API), upload `frontend/dist/` to the storefront path.
7. Verify: API health endpoint and the storefront URL; test cross-origin login if frontend and API are on different hosts.

When updating an existing host, upload the **full** backend (`src/`, `vendor/`, `public/`, etc.), not only `public/index.php`. Partial uploads cause errors such as `Class "App\Routes\RouteSecurity" not found`.

Example host paths (techdiplomacy.dev):

- Domain root: `/home/u479788146/domains/techdiplomacy.dev/`
- Backend: `.../bizspine-backend/`
- Storefront: `https://techdiplomacy.dev/BizSpine/`

<a id="production-hardening"></a>

## Production hardening

In [`backend/public/index.php`](backend/public/index.php):

- Set `display_errors` off and `log_errors` on before going live (the repo default enables display for local debugging).
- Error detail is controlled by `environment.debug` in config — `addErrorMiddleware` reads that flag so clients do not see stack traces when debug is off.

In [`backend/.env`](backend/.env):

```env
JWT_SECRET=<long-random-value>
ALLOW_INSECURE_SETUP=false
```

Do not leave `JWT_SECRET` blank. Changing it invalidates existing login tokens.

In [`backend/protected/config/config.php`](backend/protected/config/config.php) (or `install_local.php`):

- `environment.debug` → `false`
- `cors.allowed_origins` → explicit storefront origin(s), e.g. `['https://yourdomain.com', 'https://www.yourdomain.com']`

Cross-origin API (storefront and API on different hosts): see [Troubleshooting](#troubleshooting) and [backend README — Configuration](backend/README.md#configuration).

Writable: `protected/db/`, `uploads/`, `public/logs/`.

<a id="troubleshooting"></a>

## Troubleshooting

| Symptom | Likely cause | Fix |
|---------|----------------|-----|
| `Class "App\Routes\RouteSecurity" not found` | Partial backend deploy | Upload full `backend/src/` (and run `composer install` if `vendor/` is missing). |
| `JWT_SECRET is not configured` | Missing or empty `.env` | Set non-empty `JWT_SECRET` in `backend/.env`. |
| Login `net::ERR_FAILED` in browser (cross-origin) | CORS preflight failing | Add storefront origin to `cors.allowed_origins`; ensure OPTIONS on `/auth/login` returns 200 with CORS headers; keep `CorsMiddleware` registered **last** in `index.php`. |
| `405 Method not allowed` on OPTIONS | Routing before CORS | See [backend README — Middleware](backend/README.md#middleware). |

**Test CORS preflight** (replace origins/URL as needed):

```bash
curl -i -X OPTIONS "https://api.yourdomain.com/auth/login" \
  -H "Origin: https://yourdomain.com" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Content-Type"
```

Expect `HTTP/1.1 200` and `Access-Control-Allow-Origin: https://yourdomain.com` — not a PHP fatal error.

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
