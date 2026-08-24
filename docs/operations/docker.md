# Deploy with Docker

The supplied image is multi-stage: Node builds React, Python builds the MkDocs Material site, Composer installs optimized production PHP dependencies, and the runtime uses Apache with PHP 8.4. The final image contains neither Node nor a Python documentation server.

## Image stages

| Stage           | Produces                                            |
| --------------- | --------------------------------------------------- |
| `frontend`      | `public/build` from Vite                            |
| `documentation` | `public/docs` from `mkdocs build --strict`          |
| `vendor`        | optimized, no-dev Composer dependencies             |
| `runtime`       | Apache/PHP application with all generated artifacts |

Runtime PHP enables cURL, DOM, GD, mbstring, OPcache, pcntl, PDO drivers for all supported databases, XML, and XMLWriter. Apache enables rewrite and headers modules and points its document root at `public`.

## Configure first

```bash
cp .env.example .env
docker compose build
docker compose run --rm -e OKATANA_AUTO_MIGRATE=false web php artisan key:generate --show
```

Copy the printed value into `APP_KEY` in `.env`. Do not leave it blank and do not regenerate it casually after data exists: TOTP and webhook secrets use Laravel encryption tied to this key.

For the default SQLite deployment set:

```dotenv
APP_URL=http://localhost:8000
DB_CONNECTION=sqlite
DB_DATABASE=/data/database.sqlite
```

Start:

```bash
docker compose up -d
```

The application is available on port 8000.

## Services and volumes

The base Compose definition runs:

| Service | Responsibility                                                |
| ------- | ------------------------------------------------------------- |
| `web`   | Apache, Laravel, compiled React, static MkDocs, Scalar routes |
| `queue` | `php artisan queue:work --sleep=2 --tries=5 --timeout=90`     |

Persistent volumes:

| Volume            | Container path          | Data                                                                    |
| ----------------- | ----------------------- | ----------------------------------------------------------------------- |
| `okatana_storage` | `/var/www/html/storage` | private attachments, public avatars/editor images, logs/framework files |
| `okatana_data`    | `/data`                 | default SQLite database                                                 |

Both services mount the same storage/database volumes. A queue worker using different database or `APP_KEY` configuration will fail or produce inconsistent behavior.

The entrypoint creates required directories, fixes ownership, conditionally creates the public storage link, creates/chowns the selected SQLite file, and runs migrations when `OKATANA_AUTO_MIGRATE=true`. It drops privileges to `www-data` when the command starts with `php`.

## MySQL overlay

Set strong passwords in environment or deployment secrets, then run:

```bash
docker compose -f docker-compose.yml -f docker-compose.mysql.yml up -d --build
```

The overlay adds MySQL 8.4 with a health check and named `okatana_mysql` volume. Both application services point at host `database` on 3306. Variables:

```dotenv
OKATANA_DB_PASSWORD=replace-with-strong-value
OKATANA_DB_ROOT_PASSWORD=replace-with-separate-strong-value
```

## PostgreSQL overlay

```bash
docker compose -f docker-compose.yml -f docker-compose.postgres.yml up -d --build
```

The overlay adds PostgreSQL 17, `pg_isready` health check, and `okatana_postgres` volume. Set `OKATANA_DB_PASSWORD` securely.

## Production adjustments

The Compose files are a functional baseline, not a complete Internet edge. In production:

- terminate HTTPS at a trusted proxy/load balancer;
- ensure Laravel receives correct forwarded host/scheme headers;
- set canonical `APP_URL=https://...`;
- set `APP_ENV=production`, `APP_DEBUG=false`, and `SESSION_SECURE_COOKIE=true`;
- move `.env` secrets to the platform’s secret store where possible;
- use a managed or independently backed-up SQL service for larger installations;
- forward logs to the platform (for containers, `LOG_CHANNEL=stderr` is often appropriate);
- set CPU/memory limits and health-based restart policy;
- monitor both web and queue services;
- control migration rollout instead of allowing every replica to migrate concurrently.

To disable entrypoint auto-migration and run it as an explicit deployment step:

```dotenv
OKATANA_AUTO_MIGRATE=false
```

Then execute once:

```bash
docker compose run --rm web php artisan migrate --force
```

## Upgrade runbook

1. Read application/database changes and back up database, storage, `.env`, and `APP_KEY`.
2. Build the new image; strict docs, frontend, and Composer stages must all pass.
3. Stop or quiesce writes when the migration requires it.
4. Run migrations once.
5. Replace web containers.
6. Restart queue workers so they load new code.
7. Verify `/up`, `/docs/`, `/docs/api`, sign-in, asset uploads/downloads, queue processing, and a representative API call.
8. Watch logs, failed jobs, and webhook failures.

## Inspect the deployment

```bash
docker compose ps
docker compose logs web
docker compose logs queue
docker compose exec web php artisan about
docker compose exec web php artisan migrate:status
docker compose exec web php artisan queue:failed
```

Avoid printing the complete environment or secret-bearing configuration into shared logs.

## Back up Docker data

Named-volume backup mechanics depend on the host/orchestrator. At minimum preserve:

- the SQL database using a consistent database-native snapshot/dump;
- `okatana_storage`, particularly `storage/app/private` and `storage/app/public`;
- `APP_KEY` and deployment environment secrets;
- the exact application image/version.

See [Backup and restore](backup-restore.md) for dependency order and verification.
