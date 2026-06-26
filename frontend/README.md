# BizSpine Example Frontend

A small React storefront that talks to the [BizSpine API](../backend/README.md). Use it as a starting point for your own site, or deploy the static build anywhere (Netlify, Vercel, S3, etc.).

## Features

- **Health check** — `GET /health`
- **Product catalog** — `GET /products` with search
- **Stores** — `GET /stores`
- **Authentication** — register, login, profile (`/auth/*`), JWT stored in `localStorage`

## Prerequisites

- Node.js 18+
- PHP backend running locally (see [backend README](../backend/README.md))

## Quick start

1. **Start the API** (from the repo `backend` folder):

   ```bash
   composer install
   php -S localhost:8000 -t public
   ```

2. **Install and run the frontend**:

   ```bash
   cd frontend
   npm install
   npm run dev
   ```

3. Open [http://localhost:5173](http://localhost:5173).

In development, API calls use the Vite proxy (`/backend` → `http://localhost:8000`), so you do not need to change backend CORS settings on localhost.

## Configuration

Copy `.env.example` to `.env` when deploying or pointing at a remote API:

```bash
VITE_API_BASE_URL=https://your-api.example.com
```

For production, add your **storefront** origin(s) to the backend CORS allow list in `backend/protected/config/config.php` or `install_local.php`:

```php
'cors' => [
    'allowed_origins' => ['https://your-storefront.example.com'],
    // Include www if you serve both: 'https://www.your-storefront.example.com'
],
```

If the API is on a subdomain (e.g. `https://api.example.com`), list the **site the user visits in the browser**, not the API host. Cross-origin login requires a successful OPTIONS preflight — see [root README troubleshooting](../README.md#troubleshooting).

## Build for production

```bash
npm run build
```

Output is in `dist/`. Serve with any static host. Set `VITE_API_BASE_URL` at build time to your deployed API URL.

```bash
npm run preview   # local preview of production build
```

## Project layout

```
frontend/
├── src/
│   ├── api/          # HTTP client and types
│   ├── context/      # Auth state
│   ├── components/   # Layout, health widget
│   └── pages/        # Routes
├── vite.config.ts    # Dev server + API proxy
└── .env.example
```

## Extending

- Add routes in `src/App.tsx` and new pages under `src/pages/`.
- Reuse `apiRequest()` from `src/api/client.ts` for other endpoints (orders, inventory, etc.).
- Replace styles in `src/index.css` or add a UI library of your choice.
