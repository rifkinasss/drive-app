# Drive by NasLabs API

Laravel API for Drive by NasLabs. The current foundation includes session-based Sanctum authentication, account setup, private folders/files, storage accounting, secure streaming, Trash lifecycle, personal activity, internal sharing, public viewer links, and database notifications.

## Requirements

- PHP 8.3+
- Composer
- PostgreSQL

## Local setup

```sh
composer install
cp .env.example .env
php artisan key:generate
```

Set the PostgreSQL `DB_*` values in `.env`, then run:

```sh
php artisan migrate
php artisan serve
```

The API health check is available at `GET http://127.0.0.1:8000/api/health`.

## Authentication

The browser flow uses Sanctum's first-party session cookie. The frontend should send credentials and follow this sequence:

```text
GET  /sanctum/csrf-cookie
POST /api/auth/login
GET  /api/auth/user
POST /api/auth/logout
```

The authentication routes are:

- `POST /api/auth/login`
- `POST /api/auth/logout`
- `GET /api/auth/user`
- `POST /api/auth/forgot-password`
- `POST /api/auth/reset-password`

Set `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, and `SESSION_SECURE_COOKIE` per environment. Local development uses `http://localhost:3000`; production uses `https://drive.naslabs.my.id`. `SESSION_DOMAIN=.naslabs.my.id` and `SESSION_SECURE_COOKIE=true` are production examples, not hardcoded defaults.

Password reset mail uses Laravel's configured mailer. With `MAIL_MAILER=log`, the reset URL is written to the application log and points to `${FRONTEND_URL}/reset-password` with `token` and `email` query parameters.

For production, use `APP_DEBUG=false`, set `APP_URL` to `https://api-drive.naslabs.my.id`, and set `FRONTEND_URL`/`CORS_ALLOWED_ORIGINS` to `https://drive.naslabs.my.id`. Keep credentials and `APP_KEY` in the environment only.

The default queue is database-backed. The private `cloud` filesystem disk is local by default and can be moved to a deployment path with `CLOUD_STORAGE_ROOT`. Redis remains optional.

When deployed behind Cloudflare Tunnel or another reverse proxy, configure the proxy trust according to the Laravel deployment topology; do not trust arbitrary forwarded headers.

## Checks

```sh
php artisan about
php artisan route:list
php artisan migrate:status
php artisan test
vendor/bin/pint --test
```

User management beyond setup and notifications are explicitly deferred to later phases. The test factory's password is for testing only; do not use it as a production credential.

## User access rules

- Roles are `admin` and `user`.
- Account statuses are `pending`, `active`, and `disabled`.
- Only active users can log in or use protected API routes.
- `admin` authorization is available through the reusable `admin` middleware and `admin` gate.
- The last active admin cannot be demoted or disabled through future management actions.
- Status and email verification remain independent; Phase 2 does not implement email verification.

## Dual account setup

Phase 3 supports two separate setup paths:

1. Invitation: an admin creates a pending user. The recipient accepts the invitation and sets a password; acceptance sets the account to `active` and verifies the email atomically.
2. Manual password: an admin creates an active user with an initial password. The user can log in immediately, while a separate verification email completes `email_verified_at`.

Invitation routes are `POST /api/admin/users/invitations`, `GET /api/invitations/{token}`, `POST /api/invitations/{token}/accept`, and the admin resend/regenerate routes shown by `php artisan route:list`. Manual users are created with `POST /api/admin/users`; verification uses `GET /api/email-verification/{token}` and `POST /api/email-verification/{token}/verify`.

Invitation tokens expire after `USER_INVITATION_TTL_HOURS` (72 hours by default). Verification tokens expire after `EMAIL_VERIFICATION_TTL_HOURS` (24 hours by default). Tokens are cryptographically random, looked up by SHA-256 hash, and stored for resend only as Laravel-encrypted ciphertext; plaintext token values are never stored. Accepted, verified, revoked, and expired tokens are single-use/unavailable.

Both flows use Laravel mail notifications. Local development can use `MAIL_MAILER=log`; production mail delivery remains provider-configurable. No in-app notification records are created.

## Folder domain

Folder routes are available to authenticated active users:

- `GET /api/folders` lists direct children; omit `parentId` for the virtual root.
- `POST /api/folders` creates a folder.
- `GET /api/folders/{folder}` returns one folder.
- `PATCH /api/folders/{folder}` renames a folder.
- `POST /api/folders/{folder}/move` moves a folder, including to the virtual root.
- `GET /api/folders/{folder}/breadcrumb` returns the root-first breadcrumb.

The virtual root is represented by `parent_id = NULL`; it is not a database row and is never returned as a fake “My Files” folder. Public folder IDs are UUIDs. Folder ownership is private: an administrator does not bypass another user's folder access. Names are trimmed, preserve their original casing, and are unique case-insensitively among siblings for the same owner. Empty names, `.`, `..`, path separators, and null bytes are rejected.

Moves reject cross-owner or trashed parents, self-parenting, descendant cycles, and duplicate sibling names. The database stores no physical directories. Trash/restore uses `trash_batch_id` to preserve independent child-trash state.

## File domain

The metadata-only file routes are available to authenticated active users:

- `GET /api/files` lists active files in the virtual root; use `folderId` for a logical folder and `starred=true` for the owner’s starred files.
- `GET /api/files/{file}` returns metadata by public UUID.
- `PATCH /api/files/{file}` renames the display name.
- `POST /api/files/{file}/move` changes the logical folder; `folderId: null` moves to root.
- `POST /api/files/{file}/star` and `DELETE /api/files/{file}/star` update the owner’s personal star state.

File metadata separates `original_name` (user-facing) from `stored_name` (UUID-based physical identity). Rename changes the display name and derived lowercase extension only; it never changes `stored_name` or MIME type. Move changes `folder_id` only; it never moves a physical object. Public responses expose UUIDs and factual metadata, but never stored names, disk paths, or numeric IDs.

Files are private to their owner unless an explicit internal share grants access. Administrators do not bypass ownership or sharing. File names are case-insensitively unique among active files owned by the same user in the same logical folder. File/folder name collisions are allowed because uniqueness is enforced per table.

## Upload and physical storage

`POST /api/files/upload` accepts `multipart/form-data` with `file`, optional `folderId`, and optional `conflictStrategy` (`ask`, `keep_both`, or `replace`). The authenticated active user is always the owner. The target folder must belong to that user and must not be trashed.

The private `cloud` disk defaults to `storage/app/cloud`. Set `CLOUD_STORAGE_ROOT` to use another root, such as `/srv/naslabs/cloud`, and keep the disk outside `public/`. Physical paths use only a safe internal owner namespace and generated file UUID: `users/{owner_id}/files/{file_uuid}`. Logical folder names and original filenames never become physical path segments. `stored_name` and `path` remain internal and are not returned by the API.

The upload service detects MIME server-side, derives a lowercase extension from the display name, calculates a streaming SHA-256 checksum, and persists actual byte size. It stages uploads in `tmp/`, compensates filesystem/database failures, and logs cleanup failures. `replace` preserves the existing file UUID and logical identity while safely swapping bytes; `keep_both` generates names such as `report (1).pdf`. `ask` (the default) returns HTTP 409 with `FILE_NAME_CONFLICT`.

`CLOUD_MAX_UPLOAD_SIZE_BYTES` defaults to 500 MiB. Effective limits also depend on PHP `upload_max_filesize`, `post_max_size`, reverse proxies, and Cloudflare or other upstream limits. Quota validation is enforced through `StorageQuotaService`; antivirus remains deferred.

## Quota and storage accounting

Users have integer byte counters: `quota_bytes` and `used_bytes`. New accounts receive `CLOUD_DEFAULT_USER_QUOTA_BYTES` (25 GiB by default) and start with zero usage. `used_bytes` is logical metadata accounting: active and trashed file rows count, while folders and orphan physical files do not.

`GET /api/storage` returns the authenticated active user’s quota, used bytes, available bytes, trash bytes, and usage percentage. It never exposes another user’s storage. Uploads add their actual size, `keep_both` adds a full new size, and `replace` applies the signed size delta. Rename, move, star, unstar, trash, and restore have zero delta; permanent deletion subtracts the freed file bytes transactionally.

Quota-mutating uploads lock the user row inside the same database transaction as file metadata and usage updates. Positive deltas are rejected with HTTP 422 and `STORAGE_QUOTA_EXCEEDED`; smaller replacements remain allowed even when an account is already over quota. Filesystem writes are compensated if the quota/database transaction fails.

Usage reconciliation is available through `php artisan cloud:storage-reconcile`, optionally with `--user=<id-or-email>` and `--dry-run`. It recalculates from `SUM(files.size_bytes)`, including trashed files. The migration backfills existing users from existing file metadata.

## Trash lifecycle

Normal deletion moves an owned file or folder to Trash rather than deleting it. The available routes are:

- `GET /api/trash` lists top-level trashed files and folders.
- `DELETE /api/trash` permanently empties the current user’s Trash.
- `POST /api/files/{file}/trash`, `/restore`, and `DELETE /permanent` manage files.
- `POST /api/folders/{folder}/trash`, `/restore`, and `DELETE /permanent` manage folders recursively.

Trash preserves the logical hierarchy and physical bytes, so quota usage does not change. Recursive folder trash uses a shared `trash_batch_id`; descendants already trashed independently retain their own batch and are not restored accidentally. Restore uses the original active parent when available, otherwise root, and returns `409 RESTORE_CONFLICT` unless `conflictStrategy=keep_both` is supplied. Permanent deletion stages physical bytes, commits metadata/quota deletion under a user row lock, then removes the staged object; cleanup failures are logged for maintenance.

## Secure download and preview

Private bytes are served only through authenticated owner-only routes:

- `GET /api/files/{file}/download` streams an attachment.
- `GET /api/files/{file}/preview` streams inline content only for the configured safe MIME allowlist.

Both routes resolve the database `disk` and relative `path`, check authorization and `trashed_at` before touching storage, and never return `Storage::url()` or a filesystem path. Missing physical bytes return a controlled `404` and create an internal warning log. Download uses the current logical filename; rename and logical move do not change physical storage.

Responses use server-stored MIME, `private, no-store`, and `X-Content-Type-Options: nosniff`. Single byte ranges are supported with `206`, `Content-Range`, and `Accept-Ranges`. Invalid ranges return `416`. Preview allows safe raster images, PDF, selected text/code, JSON, audio, and video MIME types; HTML, XHTML, and SVG are intentionally not rendered inline in v1. Orphan physical files are not scanned or deleted in this phase.

## Browser, Recent, and Starred

The combined browser endpoint is `GET /api/browser`. Without `folderId` it returns the authenticated user’s active root folders and files. With `folderId` it validates the owned active folder and returns only direct child folders/files plus the actual-folder breadcrumb. Trashed content and other users’ content are excluded.

Browser supports `sort=name|modified|created|size`, `direction=asc|desc`, and a maximum-200-character `search` that filters only the current folder. Folder size sorting intentionally falls back to case-insensitive folder-name sorting because folder aggregate size is not stored.

`GET /api/recent?limit=20` returns active, non-trashed files ordered by metadata `updated_at` descending. This is deterministic metadata recency, not activity/open history. `limit` is clamped to 1–100. Home can reuse this endpoint rather than maintaining duplicate recent logic.

Folders have an owner-only star state:

- `POST /api/folders/{folder}/star`
- `DELETE /api/folders/{folder}/star`
- `GET /api/starred`

Starred returns normalized combined file/folder items with `type`, UUID `id`, name, star state, `folderId`, optional parent location, timestamps, and file metadata where applicable. Trashed starred items are hidden; restoring them preserves their star state. These endpoints remain private to the authenticated owner, including for administrators.

## Activity timeline

`GET /api/activity` returns the authenticated user's append-only personal timeline, newest first. It supports cursor pagination (`limit`, default 20, maximum 100), action filters (`action[]`), `type=file|folder|system`, ISO date filters (`from` and `to`), and case-insensitive subject-name search (`search`). Admin users still receive only their own feed.

Recorded actions cover successful file/folder upload, create, rename, move, star/unstar, download, Trash, restore, permanent delete, and `trash.emptied`. Idempotent no-op stars/moves do not create events; recursive Trash/restore/delete records only the user-selected root action. Preview and ranged download requests do not create noisy events; a normal download records `file.downloaded` when the response is initiated.

Each event stores a UUID, action enum, actor, subject type/UUID/name snapshot, safe event metadata, and a UTC timestamp. Subject snapshots remain understandable after rename or permanent deletion. Physical paths, stored names, disk roots, credentials, tokens, and other private storage internals are excluded. DB-mutating actions record within the same transaction as their business mutation, so a rollback removes the activity too. There is no activity mutation route, global audit API, WebSocket, or retention job. Attention-oriented notifications are stored separately.

## Internal sharing

Internal sharing is account-based and limited to existing active Drive users. Public links, anonymous access, external email invitations, and collaborative editing are not included. Ownership and quota remain with the owner; sharing does not copy files or consume recipient quota.

Share management routes are `POST/GET /api/files/{file}/shares`, `POST/GET /api/folders/{folder}/shares`, `PATCH/DELETE /api/shares/{share}`. `GET /api/users/search?q=` returns at most 10 active users and excludes the current user. Duplicate shares return `409`; pending, disabled, self, or trashed-item recipients/items are rejected.

Viewer access is limited to metadata, preview, and download. Editor adds file rename only in v1; move, star, Trash, permanent delete, folder mutation, and re-sharing remain owner-only. Folder shares grant inherited read access to the shared folder subtree, with `GET /api/shared/folders/{folder}/browser` returning a breadcrumb beginning at the shared root. Recipients cannot navigate to the owner's parent or sibling hierarchy.

`GET /api/shared/with-me` returns directly shared active items for the current recipient. `GET /api/shared/by-me` aggregates the owner's items and recipient previews. Trashed items are inaccessible while their share rows remain; restoring resumes access. Permanent deletion removes related share rows. Disabled owners or recipients cannot use shared access. Share creation, receipt, permission changes, and revocation are recorded in the existing personal Activity system. Recipient attention events are stored separately as database notifications.

## Public share links

Public links are viewer-only and do not require a Drive account. Owners manage file/folder links with `POST/GET/DELETE /api/{files|folders}/{id}/public-link` and `POST /api/{files|folders}/{id}/public-link/regenerate`. `GET /api/shared/links` lists the current owner's links. Public access uses `GET /api/public/shares/{token}`, plus token-scoped preview/download and folder browser routes.

Tokens contain 32 random bytes, are stored as SHA-256 hashes for lookup, and are encrypted with Laravel `Crypt` for owner copy-link retrieval. Raw tokens are never stored plaintext. Re-enable and regenerate rotate the token; disable invalidates it immediately. The public response exposes only viewer-safe metadata and owner display name—never owner email/ID, folder hierarchy, checksum, disk, stored name, or physical path.

Folder links expose only the shared subtree. Child UUIDs require the original token context and cannot access parent/sibling items. Public preview uses the same MIME allowlist and streaming/range implementation as private preview. Public endpoints use conservative `private, no-store` streaming headers and a reasonable IP rate limiter; anonymous access is not recorded as Activity.

Trash suspends access while retaining link metadata; restore resumes the still-enabled link. Permanent deletion removes the link record. Disabled or pending owners make links unavailable. Internal shares remain independent from public links. Password protection, expiry controls, download limits, public editing/upload, analytics, and external invitations remain deferred.

## Admin user management

Administrators can manage users through `GET/PATCH/DELETE /api/admin/users/{user}`, with list and summary routes at `GET /api/admin/users` and `GET /api/admin/users/summary`. List filters cover name or email search, role, status, verification, whitelisted sorting, and standard pagination up to 100 records per page.

User responses include identity, role, account status, email verification timestamp, allocated quota, accounted usage, available bytes, and usage percentage. Passwords, reset tokens, invitation token material, physical storage paths, and private filenames are never returned. The detail response may include safe file, folder, and received-share counts plus invitation state without token data.

Admins may change name, role, and quota. Email changes are intentionally deferred. Quota updates are row-locked and cannot go below current accounted usage. Disable and enable preserve email verification state; pending users must complete the invitation flow and cannot be enabled or sent password reset instructions. Password reset uses the existing Laravel reset-link flow and never accepts or returns a plaintext password.

Self-disable and self-delete are rejected, and the last active admin cannot be demoted, disabled, or deleted. A user who still owns files, folders, or accounted storage cannot be deleted. A zero-data user can be removed after recipient shares, invitations, verification tokens, reset tokens, sessions, and the user’s own activity feed are cleaned up. Activity entries belonging to other users retain their actor snapshot and set the deleted actor reference to null.

The summary reports total, active, pending, disabled, admin, regular-user, verified, and unverified counts, plus allocated quota and accounted usage. These storage values describe Drive account allocation and metadata accounting, not physical server capacity.

## System settings

System settings are stored in `system_settings` as typed JSON values and are administered only through `GET /api/admin/settings`, `GET /api/admin/settings/{group}`, and `PATCH /api/admin/settings/{group}`. The registry in `config/cloud_settings.php` defines the supported groups, camelCase API names, defaults, validation rules, and read-only settings. Unknown keys, wrong-group keys, invalid values, and read-only values are rejected before any group row is written.

Supported groups are `general`, `access`, `storage`, `security`, `sharing`, `maintenance`, and `advanced`. Database values override centralized defaults, and missing rows fall back safely to the registry or existing environment-backed Drive defaults. Values are cached through Laravel cache and invalidated immediately after a successful update. No setting endpoint writes `.env` or stores credentials and secrets.

The current settings integrate with new-user default quota, upload size, blocked extensions, password rules, internal sharing, public links, and application maintenance mode. Maintenance blocks normal application and anonymous public-share APIs with HTTP 503 while active admins, login, health, and admin settings remain available. Existing public links remain stored when the global public-link switch is disabled and resume when it is enabled again.

## Notifications and mail delivery

Notifications use Laravel's database channel in PostgreSQL. Activity remains the historical timeline; notifications are limited to attention-oriented events such as `share.received`, `share.permission_changed`, `share.revoked`, and `storage.quota_warning`. Structured JSONB payloads contain safe snapshots and semantic targets, never passwords, tokens, physical paths, stored filenames, or rendered HTML.

The Bell API is `GET /api/notifications`, `GET /api/notifications/unread-count`, `POST /api/notifications/{notification}/read`, and `POST /api/notifications/read-all`. Every query is scoped through the authenticated user's notifiable relation. List pagination defaults to 20 and supports `status=all|unread|read` with a maximum of 100 per page. Notification records are retained for active, disabled, and deleted-user lifecycle rules without exposing an admin-wide notification feed.

Share notifications are written inside the successful share transaction, so a rollback leaves no notification. Permission changes and revocations store item snapshots before access disappears. Public-link actions do not notify the owner. Quota warnings use `storage.quota_warning_thresholds`, defaulting to 80, 90, and 100 percent, with persistent per-user threshold state and reset when usage falls below a threshold. Warning failures are logged and never cancel the storage operation.

Invitation, email-verification, and password-reset notifications use Laravel's database queue, after-commit dispatch, and encrypted queued payloads. User-facing links use `FRONTEND_URL`; local mail defaults to `MAIL_MAILER=log`, while production uses environment-configured authenticated SMTP with a bounded timeout. Mail resend and forgot-password endpoints are rate-limited. The `jobs` and `failed_jobs` tables are migrated; use `php artisan queue:work database`, `php artisan queue:failed`, and `php artisan queue:retry` for worker and recovery operations. Run `php artisan cloud:mail-test <controlled-address>` for one local diagnostic; production requires `--force`. See [production mail setup](docs/production-mail.md) for SMTP, DNS, queue, and safe-delivery guidance.
## API Documentation

The backend exposes a branded documentation landing page at `/docs`, the
Scramble API reference at `/docs/api`, and the machine-readable OpenAPI
document at `/docs/api.json`.

Set `API_DOCS_ENABLED=false` to disable all documentation surfaces. The
documentation describes the existing Laravel Sanctum cookie/session flow;
browser clients must include credentials and initialize `/sanctum/csrf-cookie`
before login. Scramble Try It includes credentials for supported requests, but
interactive authentication still requires obtaining the Sanctum CSRF cookie
first in the same browser session, then sending `POST /api/auth/login` before
testing `GET /api/auth/user`.
