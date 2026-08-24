# Backup and restore

A recoverable Okatana deployment consists of more than SQL. Preserve the database, private/public file stores, encryption key and environment, and the application version that understands the schema.

## Backup inventory

| Component                  | Why it matters                                                                               |
| -------------------------- | -------------------------------------------------------------------------------------------- |
| SQL database               | users, memberships, tickets, documents, history, sessions/cache/queues, integration metadata |
| `storage/app/private`      | ticket and comment attachment contents                                                       |
| `storage/app/public`       | avatars and inline editor images                                                             |
| `APP_KEY`                  | decryption of TOTP and webhook secrets; session/application encryption                       |
| environment/secrets        | database/mail/OAuth configuration and operational policy                                     |
| release identifier/image   | code compatible with the migrated schema and queued jobs                                     |
| optional built docs/assets | reproducible from source, but helpful for exact release artifact continuity                  |

Logs and framework cache are operationally useful but not the primary domain backup. Database session/cache/job tables have different retention needs from domain data; include them in full snapshots but decide whether to restore stale runtime state.

## Consistency boundary

Database rows reference files. A backup taken while uploads/imports are active can capture one side without the other.

For the simplest consistent backup:

1. Enter a maintenance/quiesced period or stop write traffic.
2. Stop queue workers so they do not mutate delivery/job/notification state.
3. Take a consistent database snapshot/dump.
4. Snapshot both storage roots immediately in the same window.
5. Capture configuration/key metadata and release version.
6. Resume service.

At scale, use storage/database snapshot mechanisms with documented point-in-time coordination. Okatana does not implement a cross-database/filesystem snapshot transaction.

## Database-specific approach

### SQLite

Stop all writers or use SQLite’s supported online backup mechanism. Copying the database file while web/queue processes write can produce an inconsistent backup, especially when journal files are involved.

Record the absolute `DB_DATABASE` path and file permissions. In Docker the default file is `/data/database.sqlite` in `okatana_data`.

### MySQL

Use a transactionally consistent `mysqldump` or managed snapshot suitable for InnoDB, including triggers. Verify that the dump contains both audit-protection triggers. Store database/server version and character-set information.

### PostgreSQL

Use `pg_dump`/managed snapshot and preserve schema objects including the audit trigger function and triggers. For point-in-time recovery, retain the required WAL/base-backup chain under your PostgreSQL operations policy.

## Storage backup

Preserve file bytes, relative paths, permissions where relevant, and a manifest/checksum. The database stores attachment disk/path and avatar/editor-image paths. Restoring into a different root is fine only when Laravel disk configuration maps those same logical relative paths.

Do not rely only on `public/storage`: it is normally a symlink. Back up `storage/app/public` itself.

## Protect the backup

Backups contain personal data, rich content, attachment contents, audit IP/user-agent data, and integration metadata. Encrypt backups, restrict access, record retrieval, apply retention, and securely delete expired copies according to policy.

Store `APP_KEY` securely but ensure it can be restored during a disaster. A database/storage backup without the key cannot recover encrypted TOTP/webhook values.

## Restore runbook

Perform restore into an isolated network first:

1. Provision the same application release and compatible runtime/database version.
2. Restore `APP_KEY` and environment without exposing secrets in shell history/logs.
3. Restore the database, including triggers.
4. Restore `storage/app/private` and `storage/app/public` to configured disks.
5. Set writable ownership for Laravel.
6. Clear/rebuild configuration, route, and view caches.
7. Decide whether to retain restored `jobs`, `failed_jobs`, sessions, cache, and pending webhook deliveries. Replaying stale jobs can duplicate effects; make this an explicit recovery decision.
8. Run `php artisan migrate:status`; apply only migrations appropriate to the restored release/target release.
9. Start web without queue and perform read-only validation.
10. Start queue only after reviewing pending jobs/external effects.

## Restore validation

- [ ] `/up`, SPA assets, MkDocs, and Scalar load.
- [ ] A known account can sign in and decrypt/use TOTP state.
- [ ] Organization/project counts and membership are correct.
- [ ] A known private attachment downloads and inline image renders.
- [ ] A document PDF with a local image renders.
- [ ] Audit triggers reject update/delete.
- [ ] Ticket deep links and revision history resolve.
- [ ] API credential behavior matches planned secret handling.
- [ ] Webhook secret can sign a controlled test event.
- [ ] Queue/mail behavior is enabled only after review.
- [ ] A fresh export completes.

## Portable exports versus backups

`okatana.export.v1` is valuable for logical transfer, selective recovery, and cross-installation migration. It regenerates identifiers, may invite missing users, sanitizes content, omits some oversized/missing assets, and creates imported-history metadata. It is therefore not a transparent full-system restore.

Use both when appropriate:

- database/storage snapshots for exact disaster recovery;
- organization/project exports for portability and human-auditable logical recovery;
- timeline exports for preserving/reporting event history.

## Test recovery regularly

A backup is unproven until restored. Schedule restore drills, record recovery time/data point objectives, validate content and encrypted fields, and fix automation after every failure. Include both database and file loss scenarios.
