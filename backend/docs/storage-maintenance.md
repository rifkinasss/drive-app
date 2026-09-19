# Storage maintenance

Trash items count toward quota until permanent deletion. `storage.trash_retention_days` is the canonical setting (currently defaults to 30 days, with values below 1 rejected). An item is eligible when `trashed_at` is at or before the current time minus the configured number of days. Lowering the value can make existing items immediately eligible at the next run; increasing it only benefits items not yet permanently removed. Disabled accounts are included. A missing/invalid runtime value falls back to 30 days; zero never means immediate deletion.

## Commands

All cleanup commands are non-destructive by default. Review dry-run results before explicitly using `--execute`:

```sh
php artisan cloud:storage-reconcile --dry-run
php artisan cloud:storage-audit
php artisan cloud:trash-cleanup --dry-run
php artisan cloud:cleanup-upload-staging --dry-run
php artisan cloud:cleanup-delete-staging --dry-run
```

`cloud:trash-cleanup --execute` processes at most 500 roots total per run by default; `--limit` accepts 1–10000 and `--user` accepts a user UUID or email. Each expired folder is handled as its trash batch, preserving independently trashed descendants for a later eligible run. Each root uses the existing permanent-delete path, so its physical bytes are staged before metadata, shares, and quota are updated transactionally. Automated expiration does not create per-file activity or user notifications. Failures are counted and reported; a later run can retry remaining roots.

Upload staging is `tmp/` (including replacement backups); newly committed deletion staging is `tmp-delete-committed/`; both are on the configured private Cloud storage disk. A transaction's pre-commit deletion staging is isolated under `tmp-delete-pending/`. Only after metadata/quota commit is a leftover promoted to `tmp-delete-committed/`; the age-based reaper never deletes pending objects, which may contain bytes needing manual recovery after a failed restore. The old `tmp-delete/` location is treated as legacy/ambiguous and is audit-only; its contents are not automatically removed. The audit reports pending and legacy objects separately. Cleanup defaults to 24 hours and will not accept a threshold under 24 hours. Use `--older-than=<hours>` to raise that threshold. Only the applicable staging contents are considered.

`cloud:storage-audit` checks database metadata against physical objects and scans only `users/{id}/files/{object}` for unreferenced objects. It reports staging counts separately. Missing objects remain in the database and continue to count toward quota; physical orphans are reported only and are never deleted or quarantined automatically. `cloud:storage-reconcile` remains a separate quota-accounting tool.

## Scheduler and recovery

Laravel schedules Trash cleanup daily at 02:00, upload staging every six hours, and committed-delete staging daily at 03:00, using `app.timezone` and overlap protection. This phase does not install or configure an operating-system scheduler. Production must later run Laravel's scheduler through an approved deployment mechanism. The overlap mutex relies on the configured Laravel cache store's atomic lock support; no Redis dependency is introduced here.

Coordinate destructive cleanup with future backup windows. Do not perform orphan remediation during backup restore or disaster recovery until storage/database consistency is verified. If an audit reports an orphan, preserve it and investigate the database, object-store history, and backups before any manual action. On Linux deployments, the future service account needs private read/write/list/delete access to the configured Cloud root, especially `tmp/` and `tmp-delete/`; no server permissions are changed here.
