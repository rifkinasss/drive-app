# Drive backup and restore runbook

This is an operations procedure, not an application backup engine. Backup sets contain personal data and credentials/hashes; store them outside the webroot and source repository with restrictive access. Production backup-at-rest encryption and an offsite copy are required before relying on this for production recovery. This runbook does not configure a scheduler or deployment host.

## Recovery scope

Recover these assets together:

- PostgreSQL schema and data: users/password hashes, folders/files metadata, Trash/batches, internal shares and public links, invitations and verification/reset tokens, activity, notifications, settings, sessions/cache, and database queue tables (`jobs`, `failed_jobs`, `job_batches`). Review queue rows before starting any worker; never replay them blindly after recovery.
- The private Drive storage root configured by `CLOUD_STORAGE_ROOT` (legacy alias `CLOUD_FILESYSTEM_ROOT`; default `storage/app/cloud`). Keep the relative object namespace unchanged. Do not copy through the public API.
- The exact `APP_KEY`, database/mail configuration, service URLs, storage-root configuration, compatible source revision and lockfiles. Keep secrets in a password/secret manager or encrypted operations vault, separately from the ordinary database/storage backup. The manifest may contain only an APP_KEY fingerprint, never the key.

Upload staging (`tmp/`) is transient and excluded. New committed-delete staging (`tmp-delete-committed/`) is excluded because it represents bytes whose database deletion committed. Pre-commit (`tmp-delete-pending/`) and legacy ambiguous `tmp-delete/` objects require manual recovery review and should be preserved separately if present. No quarantine path is currently configured. Do not run Trash/staging cleanup during a backup snapshot.

## Consistent backup procedure

For production, arrange a controlled write freeze: enable maintenance/read-only behavior, stop queue workers, wait for and inspect active upload/delete/cleanup operations, then take the database dump first and storage archive second. Resume workers and traffic only after both artifacts, checksums, and manifest validate. The order is safe because mutations remain paused across both operations. This repository phase did not enable maintenance or stop a server worker.

Use PostgreSQL-native tools compatible with the source server. Avoid passwords in command arguments/history; prefer a protected `PGPASSFILE`, peer auth, or a controlled process environment. A custom-format dump includes schema, data, sequences, constraints, and indexes:

```sh
umask 077
BACKUP_DIR=/absolute/private/backup/path
BACKUP_ID=cloud-YYYYMMDD-HHMMSS
DB_NAME=naslabs_cloud
STORAGE_ROOT=/absolute/configured/cloud/root

pg_dump --format=custom --no-owner --no-privileges \
  --file="$BACKUP_DIR/$BACKUP_ID-db.dump" "$DB_NAME"
tar -czf "$BACKUP_DIR/$BACKUP_ID-storage.tar.gz" \
  -C "$(dirname "$STORAGE_ROOT")" "$(basename "$STORAGE_ROOT")"
pg_restore --list "$BACKUP_DIR/$BACKUP_ID-db.dump" >/dev/null
tar -tzf "$BACKUP_DIR/$BACKUP_ID-storage.tar.gz" >/dev/null
```

Generate a JSON manifest with backup ID/time, app revision if available, source PostgreSQL/pg_dump versions, database name, storage root, migration and relevant table counts, file-row/logical-byte totals, physical file count/bytes, artifact names/sizes, and a short SHA-256 fingerprint of `APP_KEY`. Do not include filenames, tokens, database passwords, mail credentials, or `APP_KEY` itself. Generate SHA-256 sums for the dump, storage archive, and manifest (`sha256sum` or `shasum -a 256`) and verify them before any restore. Treat a missing artifact, command error, non-zero size failure, or checksum mismatch as failure of the entire set; never label a partial pair successful.

## Isolated restore and validation

Choose a new disposable database name and a new empty storage root explicitly. Verify the database does not already exist and the path is not the active Drive storage root. Never use the development/production database as the restore target.

1. Verify `SHA256SUMS`; stop on any mismatch.
2. Create an empty restore database owned by the intended local role, then run `pg_restore --exit-on-error --no-owner --no-privileges --dbname="$RESTORE_DB" "$DB_DUMP"`. Do not use `--clean` or point at the source DB.
3. Extract the storage archive into the separate restore root, preserving the internal tree. Configure the validation process with `DB_DATABASE=$RESTORE_DB`, `CLOUD_STORAGE_ROOT=$RESTORE_ROOT`, the original `APP_KEY`, `MAIL_MAILER=array` (or log), and production-like safe URLs. Do not start a queue worker. Clear restored cache and session state; invalidate old password-reset tokens according to incident policy.
4. Run `php artisan migrate:status`; do not run new migrations during the drill. Compare manifest row counts for users, folders, files, shares, public links, settings, activity, notifications, invitations, and queue tables. Confirm file row count and `SUM(size_bytes)` match. Review sequence behavior by inserting then removing a disposable test record.
5. Run `php artisan cloud:storage-reconcile --dry-run` and `php artisan cloud:storage-audit`. Investigate every mismatch, missing object, or physical orphan; do not repair/delete unknown orphans as part of this check.
6. Compare physical object count/bytes with the manifest. For representative files, stream/read from the restored application and compare SHA-256 with the file metadata checksum. For a small test dataset, verify all files. Test a known controlled account without resetting any real user's password; check role/status are preserved.
7. Verify settings/types and Trash/batch state, internal shares, public-link lookup and encrypted token copy, and invitation state without emailing or exposing tokens. Confirm the restored `APP_KEY` fingerprint matches; decrypt a real restored encrypted value when one exists. If none exists, report that limitation rather than claiming application-data decryption was tested.
8. In the disposable environment only, create a folder, upload a small file through the application service, stream it back, verify checksum/quota, and exercise Trash→restore. Do not mutate the source environment.
9. Only after all checks pass, stop the disposable app, drop the exact restore database, and remove only the exact temporary restore-storage path. Preserve the canonical backup set and its checksums for the planned retention period.

Any restore error, count mismatch, missing file, quota discrepancy, failed login for a known test account, or key/decryption problem means the drill failed. Keep the source and canonical backup unchanged; investigate with a fresh isolated target rather than retrying over existing data.

## Operations policy

- Initial planning targets: RPO 24 hours and RTO 2–4 hours. These are targets, not guarantees; workload-specific timing and production rehearsal are still needed.
- Proposed retention: 7 daily, 4 weekly, and 3 monthly sets, with a separate offsite copy on different infrastructure. This is a recommendation only; no schedule or offsite destination is configured here.
- Rehearse quarterly and after major schema/storage changes. During future restore, keep traffic and queue workers stopped until validation passes. Expect users to sign in again; review/invalidate restored sessions and old reset tokens.
- Backup archives are not encrypted by this application. Use encrypted-at-rest infrastructure/storage before production, restrict file and directory access, and keep the APP_KEY/config backup separate.
- A new host must configure a portable storage root; DB file paths are relative internal object keys, not machine-absolute paths. Recreate service ownership/permissions for the private root during deployment. Do not apply server permissions in this phase.

## Drill record

The 2026-09-19 local drill record and non-secret manifests are stored with dumps/archives/checksums in the private temporary backup directory reported in the phase handoff. The original source was `APP_ENV=local`, PostgreSQL 18.4, with four users and no file rows or physical Drive storage objects. The root fallback was corrected so empty legacy root variables resolve to the documented `storage/app/cloud` default. A second, explicitly synthetic fixture set was created only inside the isolated restored environment; it contained one small file and one public link. That fixture set was independently restored, table counts matched, the file checksum matched metadata, and the encrypted public token decrypted/resolved using the same APP_KEY. No production-like source was mutated. No maintenance/write freeze was simulated; source counts were unchanged across the backup window, and there were no file payloads or pending jobs. A production backup still requires the write freeze described above. The original source snapshot had no invitations, settings, or stored encrypted application values, so those source-state cases remain unrepresented. The recorded backup artifacts are not encrypted at rest.
