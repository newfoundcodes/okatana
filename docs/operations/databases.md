# Databases

Okatana supports SQLite, MySQL, and PostgreSQL. The domain model and queue/cache/session defaults live in SQL, and the audit-protection migration contains explicit trigger implementations for exactly these three driver names.

## Choose a database

| Database      | Best fit                                                   | Operational tradeoff                                                     |
| ------------- | ---------------------------------------------------------- | ------------------------------------------------------------------------ |
| SQLite        | evaluation, single-node/small installations, simple backup | one file and serialized writes; web/queue must share the same filesystem |
| MySQL 8.4     | conventional multi-process production                      | separate service, credentials, dumps, upgrades, and network security     |
| PostgreSQL 17 | conventional production with strong SQL operations         | separate service and PostgreSQL-specific backup/operations knowledge     |

These versions match the supplied Compose overlays; compatible versions may work but must be tested with migrations and audit triggers.

## SQLite

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/okatana/database/database.sqlite
```

Create and migrate:

```bash
touch database/database.sqlite
php artisan migrate
```

Outside Docker, leaving `DB_DATABASE` unset uses `database/database.sqlite`. In Docker, use `/data/database.sqlite` so the file lives in the persistent `okatana_data` volume.

SQLite constraints are enabled by default. Web and queue processes must see the exact same database file. Do not put an active SQLite database on an unsafe network filesystem; locking behavior and durability may differ.

## MySQL

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=okatana
DB_USERNAME=okatana
DB_PASSWORD=replace-me
```

The connection uses `utf8mb4`, strict mode, and prefix indexes. Grant the migration principal enough permission to create tables, indexes, and triggers. If a restricted production principal cannot create triggers, deploy schema with a migration role and run the application with a narrower role afterward.

## PostgreSQL

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=okatana
DB_USERNAME=okatana
DB_PASSWORD=replace-me
```

The default search path is `public` and default SSL mode is `prefer`. Set a stricter SSL configuration appropriate to your database provider rather than assuming `prefer` is sufficient across an untrusted network.

The audit migration creates a `prevent_audit_log_mutation()` trigger function and update/delete triggers. The migration role needs the corresponding schema privileges.

## Migrations

Inspect and apply:

```bash
php artisan migrate:status
php artisan migrate --force
```

The initial schema includes framework tables for sessions, cache, jobs/batches/failed jobs, then application domain tables. Later migrations add audit protection, comment kinds, revisions, tags, collaboration notifications/attachments, account security, documents, document tags, and document mentions.

### Audit trigger check

The test suite verifies model and direct database mutation rejection on SQLite. For production MySQL/PostgreSQL, include a post-migration smoke test appropriate to your change-control process to confirm both triggers exist and reject an attempted update inside a rolled-back test transaction.

Never disable audit triggers to “clean up” rows. Repair the code/data workflow without mutating evidence.

## Concurrency rules

Ticket number allocation locks the project row before choosing the next project-local number. This works within a transactional database and prevents two concurrent creators from receiving the same number.

Phase/ticket reorder writes normalized integer positions inside a transaction. Imports create the project graph inside a transaction. The database queue is configured `after_commit=true` so jobs do not see uncommitted domain rows.

SQLite’s write concurrency is inherently narrower. If many simultaneous users, uploads, imports, queue jobs, and webhooks contend for writes, move to a client/server database after load testing.

## Switching database engines

Changing `DB_CONNECTION` does not move data. Use a deliberate migration:

1. Stop or quiesce writes and queue workers.
2. Create database/storage backups.
3. Provision the destination schema with `php artisan migrate`.
4. Transfer domain data using verified database tooling or Okatana organization/project exports as appropriate.
5. Verify ULIDs, pivots, soft deletes, JSON, timestamps, audit rows/triggers, and framework queue/session/cache tables.
6. Switch both web and queue configuration.
7. Clear/cache configuration and restart.
8. Test sign-in, ticket numbering, audit immutability, imports, API, and webhook queues.

Portable exports create new organizations/projects and regenerated identifiers; they are not a byte-identical replacement for database migration when URLs/IDs must remain unchanged.

## Database maintenance

- Back up using a consistent engine-native method.
- Monitor size and growth of audit logs, revisions, notifications, jobs, failed jobs, and webhook deliveries.
- Purge framework cache/session/failed-job data only according to an explicit policy; never include audit logs in generic cleanup.
- Analyze/vacuum/optimize using database-specific best practices.
- Keep time zones and clock synchronization consistent across web, queue, and database.
- Apply security updates and test version upgrades against migrations/triggers.

See [Backup and restore](backup-restore.md) and [Troubleshooting](troubleshooting.md).
