# API documentation

OpenAPI spec: [`../openapi.yaml`](../openapi.yaml)

## View locally

```bash
cd backend
composer install
php -S localhost:8000 -t public
```

Open `http://localhost:8000/docs/` for Swagger UI.

## Authentication

1. `POST /auth/register` or `POST /auth/login`
2. Send `Authorization: Bearer <token>` on protected routes

## Modules

Auth, products, stores, inventory, orders, reviews, testimonials, clients, users, employees, coupons, referrals, tax, returns, bookkeeping, categories, settings, email templates, email logs, health.

## Development

```bash
composer run qa    # style, static analysis, tests
```

See [`CODE_QUALITY.md`](CODE_QUALITY.md).
