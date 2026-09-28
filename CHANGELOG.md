# Changelog

## Drive by NasLabs v2.0.0

### Changed

- Rebranded the user-facing product from Cloud by NasLabs to Drive by NasLabs.
- Bumped the frontend package and API documentation release version to 2.0.0.
- Updated frontend metadata, page title architecture, navigation identity, settings/about copy, auth states, errors, and public share branding.
- Updated backend application identity, API gateway, documentation portal, Scramble metadata, system-setting defaults, and transactional email copy.
- Updated current production domain examples to `drive.naslabs.my.id` and `api-drive.naslabs.my.id`.
- Preserved existing `/api` routes and the existing product interaction patterns.
- Added native online-only PWA metadata, manifest, maskable app icons, Apple touch icon support, and a working `/favicon.ico`.
- Added a visible network-loss state and normalized common API/server error messages without introducing offline data caching.
- Added an online-only Install Drive control with browser prompt and iOS Add to Home Screen guidance.
- Added authenticated notification preferences for shares, quota alerts, and optional account/security alerts.
- Added a safe Recent Devices endpoint and Settings view using masked IP addresses and opaque session identifiers.
- Added storage warning states at 80%, 90%, and 100% based on the real storage summary API.
- Added Copy link feedback and an Open in Drive CTA to public share pages.

### Security

- Notification preference enforcement now suppresses optional internal-share and quota notifications when disabled; required authentication emails remain unaffected.
- Recent session activity never exposes raw Laravel session IDs, payloads, or cookies.

### Compatibility

- Database names, migrations, internal `CLOUD_*` environment variables, private storage disk names, storage paths, Laravel `cloud:*` commands, and technical service/type names remain unchanged.
- The production environment file was not edited. Deployment, DNS, Cloudflare, systemd, and production database/storage were not changed.

### Added

- Added a minimal service worker for install, activation, push display, and notification-click routing only. It does not cache private data or API responses.
- Added online recovery feedback and safe GET refetching after connectivity returns.
- Added user-scoped encrypted Web Push subscription storage with VAPID delivery configuration.
- Added user-initiated Push Notifications controls in Settings and preference-aware delivery for share and quota events.

### Deferred

- Service worker, offline caching, background sync, offline mutations, and offline private file access.
- Web Push, WebDAV, resumable uploads, and native client synchronization.
- Offline file browsing, private previews/download caching, background sync, and offline mutation queues.
- Renaming internal technical identifiers or maintenance command namespaces.
