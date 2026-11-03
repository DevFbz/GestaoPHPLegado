# Sprint 8 — frontend/API bridge (sem e-mail)

## What is implemented

`public/assets/js/laravel-api-bridge.js` is an opt-in adapter for the existing server-rendered UI. Load it **after** `public/assets/js/api.js` on the pages that are ready to use Laravel:

```html
<script src="/assets/js/api.js"></script>
<script src="/assets/js/laravel-api-bridge.js"></script>
```

The adapter:

- keeps page URLs and legacy routes unchanged;
- translates the UI's existing `/api/...` calls to `/api/v1/...`;
- accepts Laravel's raw JSON responses and exposes the existing `{ ok, data, meta }` shape;
- maps Laravel validation/401 responses to the existing `ApiError` contract;
- stores the login token in `sessionStorage` and sends it as `Authorization: Bearer`;
- preserves idempotency keys, uploads, query parameters, formatters, and labels from the existing client.

The bridge is deliberately opt-in. It does not claim that every legacy endpoint is implemented by Laravel. Only screens backed by a matching Laravel route should load it; unsupported legacy screens remain on the legacy API until their backend contract exists.

## URL and CORS boundary

The bridge uses `<meta name="laravel-api-url" content="https://api.example.test">`. If omitted, it uses the current origin and still targets `/api/v1`.

For a separate frontend origin, set the Laravel backend environment variable:

```dotenv
CORS_ALLOWED_ORIGINS=https://brindes.example.com,https://homolog-brindes.example.com
```

Use exact origins, without a path or trailing slash. Do not use `*`: the API allows credentials and bearer-authenticated browser requests. `FRONTEND_URL` remains a backward-compatible fallback for existing installations.

This slice does **not** deploy Vercel, run migrations/seeds in a remote environment, validate production domains, implement email, or replace the legacy routes. Those require environment access and/or credentials not present in the repository.
