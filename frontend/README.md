# Drive by NasLabs frontend

The frontend uses the Laravel API as its source of truth. Copy `.env.example` to
`.env.local` and set `NEXT_PUBLIC_API_URL` to the backend origin.

Local development:

```sh
# Backend (PostgreSQL must be running)
cd ../backend
php artisan serve --host=localhost --port=8000
php artisan queue:work

# Frontend
cd ../frontend
npm install
npm run dev
```

Authentication is Laravel Sanctum SPA authentication. Requests use browser
cookies and `credentials: "include"`; the frontend does not store JWTs or
session tokens. Login initializes `/sanctum/csrf-cookie` before calling the
login endpoint. The backend must allow the frontend origin with credentials.

Production uses `https://drive.naslabs.my.id` for the frontend and
`https://api-drive.naslabs.my.id` for the API. Configure HTTPS, CORS,
`SANCTUM_STATEFUL_DOMAINS`, and the session cookie domain/secure/same-site
settings for those domains; do not copy local cookie settings unchanged.
