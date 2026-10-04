# Drive Web Push

Web Push is optional and user initiated. Drive remains online-only and the service worker does not cache API responses, private previews, downloads, or user data.

Generate a VAPID key pair locally with the installed dependency:

```bash
php -r "require 'vendor/autoload.php'; var_export(\Minishlink\WebPush\VAPID::createVapidKeys());"
```

Set the resulting values in the backend environment:

```dotenv
VAPID_SUBJECT=mailto:hello@example.com
VAPID_PUBLIC_KEY=...
VAPID_PRIVATE_KEY=...
```

Expose only the public key to the frontend as `NEXT_PUBLIC_VAPID_PUBLIC_KEY`. Keep the private key server-side. Run the push migration before accepting subscriptions. A browser user must explicitly enable push from Settings; denied permissions are not requested again automatically.

Supported push events are share received/permission changes/revocations and quota warnings. Payloads contain only a short message, type, and safe internal destination. Expired subscriptions are removed after a failed delivery.

## Local test

After a local user has enabled Push Notifications and the backend has local VAPID values, send a test notification with:

```bash
php artisan cloud:push-test <user-id-or-email>
```

This command is guarded to run only when `APP_ENV` is `local` or `testing`; it is not available as a production push tool.

## Deployment checklist

When deploying a new environment, generate a VAPID key pair from the backend directory:

```bash
php -r "require 'vendor/autoload.php'; var_export(\Minishlink\WebPush\VAPID::createVapidKeys());"
```

Then:

1. Set `VAPID_SUBJECT`, `VAPID_PUBLIC_KEY`, and `VAPID_PRIVATE_KEY` in the backend environment.
2. Set the same public key as `NEXT_PUBLIC_VAPID_PUBLIC_KEY` in the frontend build environment.
3. Run `php artisan migrate --force` so the push subscription table exists.
4. Run `php artisan optimize:clear` and rebuild the frontend so the public key is embedded at build time.
5. Test enabling Push Notifications from Settings with a real supported browser.

Keep `VAPID_PRIVATE_KEY` server-side. Do not commit generated keys, copy them into `.env.example`, or expose them to the frontend.
