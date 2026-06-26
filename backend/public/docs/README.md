# API documentation

OpenAPI spec: [`../openapi.yaml`](../openapi.yaml) (served at `/openapi.yaml` when running `public/`).

## View locally

```bash
cd backend
composer install
php -S localhost:8000 -t public
```

Open `http://localhost:8000/docs/` for Swagger UI.

## Response format

Successful JSON responses:

```json
{ "success": true, "data": { ... } }
```

Errors:

```json
{ "success": false, "error": "message" }
```

Client code should read `data` on success and `error` on failure (the React storefront does this via `apiRequest()` in `frontend/src/api/client.ts`).

## Authentication

1. `POST /auth/register` or `POST /auth/login` with JSON `{ "email", "password" }` (no auth header).
2. On login, use `data.access_token` and `data.role` from the response.
3. Send `Authorization: Bearer <access_token>` on protected routes.
4. `GET /auth/me` returns the current user profile in `data` when the token is valid.

Example login response:

```json
{
  "success": true,
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "role": "admin"
  }
}
```

**Cross-origin storefronts** (e.g. `https://yourdomain.com` calling `https://api.yourdomain.com`) require the storefront origin in backend CORS config. Browsers send an OPTIONS preflight before login. See [deployment troubleshooting](../../../README.md#troubleshooting) in the root README.

## Modules

Auth, products, stores, inventory, orders, reviews, testimonials, clients, users, employees, coupons, referrals, tax, returns, bookkeeping, categories, settings, email templates, email logs, health.

## Development

```bash
composer run qa    # style, static analysis, tests
```

See [`CODE_QUALITY.md`](CODE_QUALITY.md).
