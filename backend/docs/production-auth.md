# Production authentication contract

This is a configuration handoff, not a deployment recipe. Production values belong in the server's environment; do not copy them into `.env` in this checkout.

## Environment matrix

| Setting | Local development | Production |
| --- | --- | --- |
| Frontend | `http://localhost:3000` | `https://drive.naslabs.my.id` |
| API / `APP_URL` | `http://localhost:8000` | `https://api-drive.naslabs.my.id` |
| `FRONTEND_URL` | `http://localhost:3000` | `https://drive.naslabs.my.id` |
| `NEXT_PUBLIC_API_URL` | `http://localhost:8000` | `https://api-drive.naslabs.my.id` |
| `NEXT_PUBLIC_APP_URL` | `http://localhost:3000` | `https://drive.naslabs.my.id` |
| `SESSION_DOMAIN` | unset (`null`) | `.naslabs.my.id` |
| `SESSION_SECURE_COOKIE` | `false` for plain HTTP | `true` |
| `SESSION_SAME_SITE` | `lax` | `lax` |
| `SESSION_DRIVER` | `database` | `database` |
| `QUEUE_CONNECTION` | `database` | `database` |
| `CACHE_STORE` | `database` | `database` |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost:3000,127.0.0.1:3000` | `drive.naslabs.my.id` |
| `CORS_ALLOWED_ORIGINS` | `http://localhost:3000,http://127.0.0.1:3000` | `https://drive.naslabs.my.id` |
| `TRUSTED_PROXIES` | unset | exact private proxy peer(s), determined from deployment topology |

Keep `NEXT_PUBLIC_API_URL` as the API origin, without `/api`; application paths already include `/api`. `APP_URL` is the backend URL. `FRONTEND_URL` is used for user-facing invitation, verification, reset, and share links.

## Production environment template

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api-drive.naslabs.my.id
FRONTEND_URL=https://drive.naslabs.my.id

# Generate once on the server and preserve it. Rotating it invalidates encrypted data and sessions.
APP_KEY=<generate-on-server-and-preserve>

DB_CONNECTION=pgsql
DB_HOST=<private-postgresql-host>
DB_PORT=5432
DB_DATABASE=<database-name>
DB_USERNAME=<database-user>
DB_PASSWORD=<secret>

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_DOMAIN=.naslabs.my.id
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SESSION_HTTP_ONLY=true
SANCTUM_STATEFUL_DOMAINS=drive.naslabs.my.id
CORS_ALLOWED_ORIGINS=https://drive.naslabs.my.id
QUEUE_CONNECTION=database
CACHE_STORE=database

# Provider-neutral SMTP example (choose values from the verified provider).
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=<smtp-host>
MAIL_PORT=465
MAIL_USERNAME=<smtp-username>
MAIL_PASSWORD=<secret>
MAIL_TIMEOUT=10
MAIL_FROM_ADDRESS=<verified-sender-address>
MAIL_FROM_NAME="Drive by NasLabs"

# Set only to the actual private peer/CIDR that connects to PHP/Laravel.
# Never use * or trust arbitrary forwarded headers.
TRUSTED_PROXIES=<private-proxy-ip-or-cidr>
```

Frontend build-time values:

```dotenv
NEXT_PUBLIC_API_URL=https://api-drive.naslabs.my.id
NEXT_PUBLIC_APP_URL=https://drive.naslabs.my.id
```

Do not commit a real `.env`, database credential, or production key. Do not regenerate `APP_KEY` during deployment if encrypted data already exists.

## Auth and cookie behavior

- Laravel 13.31.0, Sanctum 4.x and Next.js 16.3.3 are the declared dependency versions; verify installed versions from the lock/install on the build host. The current local Next build reports 16.2.12, so the frontend dependency installation is not aligned with `package.json` and should be reconciled before release.
- Keep Sanctum SPA auth: `GET /sanctum/csrf-cookie`, `POST /api/auth/login`, cookie-authenticated `GET /api/auth/user`, and `POST /api/auth/logout`. The frontend uses `credentials: 'include'`; it does not persist bearer tokens.
- The frontend reads only the JavaScript-readable `XSRF-TOKEN` cookie to send `X-XSRF-TOKEN`. The session cookie remains HttpOnly. Laravel 13's CSRF middleware encrypts the XSRF cookie and validates the corresponding header; no CSRF exemptions were added.
- Session cookie name currently derives from `APP_NAME` as `drive-by-naslabs-session`, avoiding Laravel's generic default. The XSRF cookie retains Laravel's standard `XSRF-TOKEN` name. Keep other apps on host-only cookies unless they need shared subdomain scope; multiple apps using a parent-domain XSRF cookie can overwrite one another.
- The database session table is present in the migrations. A 120-minute lifetime is the current idle-session lifetime. The login `remember` option is implemented by Laravel's guard and remember token; it is not a change to the session lifetime.
- The API client obtains a CSRF cookie before unsafe requests and refreshes/retries a 419 only for login. It does not replay upload, share, delete, or other mutations, because their first response may be ambiguous.
- Login is rate-limited. The password broker also throttles reset-token creation for 60 seconds per account; that is not a substitute for an IP-level request limiter if abuse monitoring later shows it is needed.
- Disabled users are checked by `active.account` on authenticated application routes; login and current-session access do not provide an admin bypass. Logout invalidates the session and regenerates its CSRF token.

## Proxy and URL assumptions

Laravel trusts forwarded `for`, `host`, `port`, and `proto` headers only from addresses in `TRUSTED_PROXIES`. Determine the actual immediate peer address/CIDR for the reverse-proxy/Cloudflare Tunnel topology; do not set a global wildcard. With a correctly trusted proxy that sends `X-Forwarded-Proto: https`, Laravel should recognize HTTPS for generated URLs and secure-cookie behavior. Verify this on the deployed topology; no production host or tunnel was contacted during this phase.

## Verification

The feature tests assert exact-origin credentialed CORS preflight, the production-origin CSRF-cookie endpoint, required XSRF request header allowance, exposed `Content-Disposition`, session defaults, and Sanctum domain formatting. The corresponding production-like probes, to run after deployment from a controlled client, are:

```sh
curl -i -X OPTIONS 'https://api-drive.naslabs.my.id/api/auth/login' \
  -H 'Origin: https://drive.naslabs.my.id' \
  -H 'Access-Control-Request-Method: POST' \
  -H 'Access-Control-Request-Headers: content-type,x-xsrf-token'

curl -i 'https://api-drive.naslabs.my.id/sanctum/csrf-cookie' \
  -H 'Origin: https://drive.naslabs.my.id' \
  -H 'Accept: application/json' \
  --cookie-jar /tmp/cloud-cookies.txt
```

Expected CORS headers include the exact frontend origin and `Access-Control-Allow-Credentials: true`. Confirm cookies have the parent domain, `Secure`, `SameSite=Lax`; the session cookie also has `HttpOnly`. A real browser login across the deployed subdomains remains a deployment verification, not something these local tests claim to prove.
