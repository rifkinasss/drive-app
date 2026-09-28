# Production transactional mail

## Provider and environments

The app uses Laravel's provider-neutral SMTP transport. Choose a provider that offers authenticated SMTP (for example Resend, Mailgun, Postmark, SES, or another SMTP-compatible service); provider credentials are environment/secret-manager values, never System Settings or database data.

- Local: `MAIL_MAILER=log`; messages go to protected application logs and are not delivered.
- Production: `MAIL_MAILER=smtp`, verified host/credentials, `MAIL_SCHEME=smtps` with port 465 for implicit TLS, or `MAIL_SCHEME=smtp` with the provider's STARTTLS port (normally 587). Laravel 13.31 reads `MAIL_SCHEME`; `MAIL_ENCRYPTION` is not consumed by this app's current mail config.
- `MAIL_TIMEOUT=10` bounds SMTP socket waits. Mail is queued, so a provider delay/failure does not hold the user-facing HTTP request open.
- Global sender comes from `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME="Drive by NasLabs"`. Choose an address under a verified domain you control. No per-notification sender overrides or personal Reply-To are configured.

Production SMTP example (placeholders only):

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=<provider-smtp-host>
MAIL_PORT=465
MAIL_USERNAME=<provider-smtp-username>
MAIL_PASSWORD=<secret-from-secret-manager>
MAIL_TIMEOUT=10
MAIL_FROM_ADDRESS=<verified-sender-address>
MAIL_FROM_NAME="Drive by NasLabs"
```

Never disable TLS certificate verification to work around provider setup. If a provider requires STARTTLS on 587, use its documented host/credentials and test the connection using the diagnostic command below. Changing mail environment values requires `php artisan config:cache` to be refreshed and existing queue workers to be restarted; this phase does not restart a server or install a worker service.

## Flows and link semantics

| Flow | Mail behavior | URL / expiry | State after send failure |
| --- | --- | --- | --- |
| Invitation | Queued, after commit, encrypted queue payload | `FRONTEND_URL/invite/{token}`; 72 hours by default | Account remains pending; administrator can resend or regenerate. Regeneration revokes the previous token before queuing the replacement link. |
| Email verification | Queued, after commit, encrypted queue payload | `FRONTEND_URL/verify-email/{token}`; 24 hours by default | Account remains unverified; administrator can resend. Verification is single-use. |
| Password reset | Laravel broker with a queued, after-commit, encrypted Laravel notification | `FRONTEND_URL/reset-password?token=…&email=…`; broker expiry is 60 minutes; token is invalidated after use | Forgot-password response remains generic; a failed job can be retried. |

Mail subjects and copy are transactional and in English. Invitation includes the recipient name when present and expiry; verification includes purpose and expiry; reset includes its configured expiry and no password. Reset query parameters are URL-encoded. User-supplied text is rendered through Laravel's escaped Markdown mail templates. Public share links and internal-share events do not generate email. No disabled-account or system-wide announcement email is added.

## Queue and failure operations

Database queue remains the v1 queue. The `jobs`, `failed_jobs`, and `job_batches` tables are included in the migrations. Queue connections are configured for after-commit dispatch, and the three transactional notifications explicitly request it as well. Their needed link tokens are carried only inside Laravel-encrypted queued notification payloads; queued and failed job tables remain sensitive operational data and require restricted database access and a future retention/cleanup policy. Queue payload encryption depends on the stable `APP_KEY`; rotating that key before queued/failed token jobs are drained can make those payloads unreadable.

Run a worker under the deployment's process supervisor (no service is installed in this phase):

```sh
php artisan queue:work database --tries=3 --timeout=60 --backoff=10
```

`retry_after` defaults to 90 seconds, above the recommended worker timeout. Inspect/recover failures with:

```sh
php artisan queue:failed
php artisan queue:retry <id>
php artisan queue:forget <id>
```

Also watch queue backlog, failed-job count, worker restarts, and retry volume in deployment monitoring. Do not put a synchronous SMTP probe on `/health`.

## Safe one-recipient delivery diagnostic

`php artisan cloud:mail-test <controlled-address>` sends a single generic test message through the configured mailer. It is not an HTTP endpoint. Production refuses to send without `--force` and valid authenticated SMTP/TLS/sender settings:

```sh
php artisan cloud:mail-test <controlled-test-address> --force
```

The command logs only exception class and recipient domain on failure, not address, message, token, or provider credentials. “Accepted” means the configured transport accepted the message; verify inbox and spam placement separately. Do not use a distribution list or bulk recipient.

## Rate limits and secret handling

- Login keeps its existing five-attempt-per-minute limiter.
- Forgot-password is limited to five requests/minute per source IP, remains generic for known/unknown addresses, and Laravel's password broker also throttles token creation for 60 seconds per account.
- Invitation resend/regenerate, email-verification resend, and administrator-triggered password reset have a five-request/minute per-admin-and-IP limiter, in addition to the API-wide limiter.
- Queue exceptions are logged by Laravel/worker. App-specific mail diagnostic logs exclude token/link/body and SMTP secrets. The default local `log` mailer does write message bodies and links to local logs; protect and clear those logs according to local data-handling policy.
- No SMTP password, API key, or provider credential is registered in `config/cloud_settings.php` or written through System Settings.

## DNS/provider delivery checklist (deployment-time only)

- [ ] Verify the sender domain with the chosen provider.
- [ ] Publish provider-authorized SPF and DKIM records.
- [ ] Publish a DMARC policy and review aggregate reports.
- [ ] Generate a dedicated SMTP credential with only mail-send permissions.
- [ ] Confirm the sender address is approved and aligned with the verified domain.
- [ ] Run one diagnostic to a controlled mailbox; verify inbox/spam and sender display.
- [ ] Open invitation, verification, and password-reset links; confirm each remains on `https://drive.naslabs.my.id` and honors its expiry/single-use behavior.
- [ ] Confirm the database queue worker is running and monitor failed jobs/backlog.

No DNS records, provider account, server, or real recipient were contacted or changed in this phase. Code and environment template are ready; provider configuration and real delivery remain unverified until credentials and a controlled mailbox are supplied.

## Current delivery state

| Flow | Code Ready | Config Ready | Delivery Verified |
| --- | --- | --- | --- |
| Invitation | Yes | No — production SMTP credentials/sender are not configured | No — no provider send performed |
| Verification | Yes | No — production SMTP credentials/sender are not configured | No — no provider send performed |
| Password Reset | Yes | No — production SMTP credentials/sender are not configured | No — no provider send performed |
