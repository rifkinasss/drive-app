# Frontend Architecture

Drive by NasLabs uses a domain-oriented frontend architecture with a single shared transport layer.

The primary goals are:

- keep API concerns explicit and discoverable
- reduce oversized files and mixed responsibilities
- make domain ownership clear
- keep refactors incremental and low risk
- avoid duplicate networking logic
- make the codebase easier to maintain as features grow

---

## 1. Architectural Boundaries

### `src/lib/api`

`src/lib/api` owns transport-level HTTP concerns only.

Responsibilities include:

- API base URL
- credentials
- Sanctum CSRF handling
- default headers
- response parsing
- normalized API errors
- blob responses
- downloads
- generic request helpers

Current structure:

```text
src/lib/api/
└── client.ts
```

`ApiError` currently lives in `client.ts`; it can be extracted into `api-error.ts` later if the error contract grows independently.

### Other application boundaries

- `src/features/<domain>/api` owns endpoint contracts and domain-facing API names.
- `src/services` is a compatibility implementation layer during the incremental migration. New UI code should import the matching feature API module instead of importing a service directly.
- `src/stores` owns cross-view client state and delegates network work to feature APIs.
- `src/components` renders state and user interactions. Presentational components do not construct endpoint paths.
- `src/config` is the single home for browser environment values and application metadata.

## 2. Domain API Modules

| Domain | Entry point |
| --- | --- |
| Auth | `features/auth/api/auth.api.ts` |
| Files and folders | `features/files/api/files.api.ts` |
| Folder mutations | `features/files/api/folders.api.ts` |
| Uploads | `features/files/api/uploads.api.ts` |
| Storage | `features/storage/api/storage.api.ts` |
| Activity | `features/activity/api/activity.api.ts` |
| Internal sharing | `features/sharing/api/internal-shares.api.ts` |
| Public links and public shares | `features/sharing/api/public-links.api.ts` |
| File requests | `features/file-requests/api/file-requests.api.ts` and `features/file-requests/api/public-file-requests.api.ts` |
| Notifications | `features/notifications/api/notifications.api.ts` |
| Security sessions | `features/security/api/sessions.api.ts` |
| Push subscriptions | `features/push/api/push-subscriptions.api.ts` |
| Admin settings | `features/admin/api/system-settings.api.ts` |

The feature APIs currently delegate to the existing service implementations where those implementations contain mapping or upload behavior. This keeps the refactor low-risk while giving callers a stable domain seam. Endpoint implementations can be moved behind these modules independently in a later cleanup.

## 3. Types and State

The existing shared types in `src/types` remain the compatibility model for the current UI. Domain-specific types should be added beside the relevant feature API when a DTO differs materially from the view model. There is no query library in this project; stores and local component state remain the query layer for now.

## 4. Migration Rules

1. Add or update the relevant feature API module.
2. Keep authorization and response mapping in the API/service boundary.
3. Keep loading, error, and empty states in the consuming store or component.
4. Do not introduce a new endpoint path or alter business behavior as part of a structural refactor.
5. Remove compatibility service imports only after all callers have moved.

## 5. Adding an Endpoint or Feature

For a new endpoint, add the explicit function to the owning feature API module. Use `src/lib/api/client.ts` only for transport; do not add domain methods there.

For a new feature:

1. Create the smallest owned folder under `src/features`.
2. Add `api`, `types`, `schemas`, or `constants` only when the feature needs them.
3. Let a store or domain hook orchestrate reusable state.
4. Keep components responsible for rendering and interaction.
5. Add public/anonymous API modules separately from authenticated modules.

Examples:

```text
features/file-requests/
├── api/file-requests.api.ts
├── api/public-file-requests.api.ts
└── types/
```

## 6. Anti-patterns

Avoid:

- endpoint URLs or `fetch()` calls in presentational components
- adding domain methods to the shared API client
- a second HTTP client, CSRF implementation, or error model
- a service that combines unrelated domains
- stores that map DTOs or become a second service layer
- global dumping-ground files such as `utils.ts`, `helpers.ts`, or `types/index.ts`
- generic repositories or universal CRUD abstractions without a concrete reuse case
- importing a feature's internal implementation from another feature

## 7. Current Technical Debt

The feature API modules are the public seam, but several mapping and multipart implementations still live in legacy services. Those services are intentionally retained as a temporary compatibility layer. They should shrink as each implementation is moved behind its domain module; new callers must not add to that legacy surface.
