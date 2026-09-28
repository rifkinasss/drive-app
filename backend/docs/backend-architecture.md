# Backend Architecture

Drive by NasLabs uses a domain-oriented Laravel architecture while preserving the existing API and storage contracts.

## Request flow

```text
Route
  ↓
Controller
  ↓
FormRequest / Policy
  ↓
Service or focused use-case operation
  ↓
Model / Query / Storage capability
  ↓
Resource / ApiResponse
```

Not every read needs an Action. Simple reads may query a model or focused query service directly. Mutations that combine state changes, quota, storage, and side effects belong in a cohesive domain service.

## Boundaries

- `app/Http/Controllers` receives HTTP input and returns the existing response envelope.
- `app/Http/Requests` owns mutation and filter validation.
- `app/Policies` owns authorization decisions for protected resources.
- `app/Http/Resources` owns response transformation; controllers should not duplicate DTO mapping.
- `app/Services` owns cohesive domain capabilities and multi-step use cases.
- `app/Models` owns relationships, casts, scopes, and small invariant-adjacent helpers.
- `app/Support/ApiResponse.php` owns the shared JSON success/error envelope.
- `config` is the only runtime environment boundary. Application code uses `config()` rather than `env()`.

## Domain ownership

| Domain | Primary ownership |
| --- | --- |
| Auth and account lifecycle | `Auth` controllers plus invitation, verification, and password services |
| Files and folders | file/folder controllers, policies, and focused file/folder services |
| Uploads and private streams | `UploadFileService`, `PrivateStorageService`, `FileStreamService` |
| Trash and retention | `FileTrashService` with `TrashStorageService` |
| Quota and storage summary | `StorageQuotaService`, `StorageSummaryService` |
| Internal sharing | `InternalShareService` |
| Public links | `PublicShareService`, `PublicShareAccessService`, `ShareTokenService` |
| File requests | `FileRequestController` and file-request service behavior |
| Notifications | Laravel notifications, `StorageNotificationService`, `PushNotificationService` |
| Security sessions | `SecuritySessionService` |
| Admin | `Admin` controllers and admin services |

Internal shares, public links, and file requests are separate concepts even where they use related models or routes.

## Storage and database consistency

Private files are accessed through protected controllers and `FileStreamService`; they are not exposed as static public files. Upload and permanent-delete flows use staging and compensating cleanup because PostgreSQL transactions cannot atomically include the filesystem. This boundary is intentional and should remain explicit in changes to upload, trash, or restore behavior.

`PrivateStorageService` owns reusable private upload disk operations: disk resolution, upload staging, moves, and cleanup. `TrashStorageService` owns trash-specific staging, commit cleanup, and restoration of staged objects. `StorageQuotaService` owns usage locking, quota checks, and byte deltas. No controller should calculate or mutate quota directly.

## Authorization and security

Sanctum SPA cookie/session authentication remains unchanged. Resource authorization uses the existing policies and owner checks. Unauthenticated `/api/*` failures remain JSON responses. Session listings expose only masked device/IP information and hashed identifiers; raw session IDs, cookies, passwords, tokens, and private file contents must not be logged or serialized.

Public share access is anonymous and read-only according to the existing link rules. Public file requests are upload-only and must not expose folder browsing or owner content.

## Adding a new endpoint

1. Add a route to the existing domain group in `routes/api.php` unless a separate route file is materially clearer.
2. Add a FormRequest for non-trivial mutation validation.
3. Reuse or add a Policy decision for authorization.
4. Put multi-step behavior in the owning service or a focused Action if one use case needs a distinct seam.
5. Return an existing Resource and `ApiResponse` envelope.
6. Add feature/security tests before changing adjacent domains.

Do not introduce repositories, generic `AppService` classes, or a second response/error format without a concrete boundary problem.

## Commands and jobs

Legacy `cloud:*` command names remain for compatibility. Commands should invoke existing services and avoid reimplementing quota, storage, cleanup, or notification rules. Queue only work that benefits from asynchronous execution, such as email, push delivery, or heavy fan-out.

## Refactor rules

- Preserve endpoint URLs, response envelopes, authorization semantics, database semantics, and storage variable names.
- Keep controllers thin and avoid catch-all `Throwable` handling in HTTP code.
- Prefer Eloquent scopes, focused query services, and eager loading over duplicate raw query fragments.
- Split by responsibility, not arbitrary line count.
- Never rewrite historical migrations or deploy from a local refactor.
