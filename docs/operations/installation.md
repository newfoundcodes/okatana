# Install locally

This procedure creates a complete development installation with Laravel, React/Vite, SQLite, database-backed queues, Scalar, and the MkDocs manual.

## Requirements

- PHP 8.3 or later.
- Composer 2.
- Node.js 22 or another version compatible with Vite 8; Node 22 is the supported project baseline.
- npm.
- Python 3 with `venv` and pip for documentation.
- A PDO driver for SQLite, MySQL, or PostgreSQL.
- PHP extensions used by the project: DOM, GD, JSON, OpenSSL, PDO, and XMLWriter; common Laravel runtime extensions such as cURL and mbstring are also expected.

Check the main runtimes:

```bash
php --version
composer --version
node --version
npm --version
python3 --version
```

## Automated application setup

From the repository root:

```bash
composer run setup
```

The Composer setup script performs:

1. `composer install`;
2. copies `.env.example` when `.env` is absent;
3. generates `APP_KEY`;
4. creates `database/database.sqlite` when absent;
5. runs migrations with `--force`;
6. installs npm dependencies;
7. builds the React application.

It does not install Python documentation dependencies or build MkDocs.

## Manual setup

Use the manual sequence when you want to select a database before migration:

```bash
cp .env.example .env
composer install
php artisan key:generate
npm install
php artisan migrate
npm run build
```

For SQLite, create the database first when needed:

```bash
touch database/database.sqlite
```

Configure MySQL/PostgreSQL before `php artisan migrate`; see [Databases](databases.md).

## Start development services

The convenient command starts Laravel, a database queue worker, and Vite with labeled output:

```bash
composer run dev
```

Or start components separately:

```bash
php artisan serve
php artisan queue:work --tries=3
npm run dev
```

The default application is normally available at `http://127.0.0.1:8000`. Vite serves development assets through its own port and Laravel’s Vite integration points the page at them.

## Build and preview the manual

Create a Python virtual environment outside or inside your working tree, then install the docs requirements:

```bash
python3 -m venv .venv-docs
. .venv-docs/bin/activate
pip install -r requirements-docs.txt
mkdocs serve --dev-addr 127.0.0.1:8001
```

The documentation preview is at `http://127.0.0.1:8001`. Links to `/docs/api` expect a running Laravel application at the same origin when deployed; during standalone preview, open Scalar directly on Laravel at `http://127.0.0.1:8000/docs/api`.

Build the deployable static site with:

```bash
composer docs:build
```

Output goes to `public/docs` and is intentionally git-ignored. Once built, Apache/Laravel serves it at `/docs/`. The build creates neither an `api` directory nor an `openapi.yaml` file, so Laravel continues to own the Scalar and specification routes and can enforce their access middleware.

Run `composer serve` to rebuild the manual and optimized frontend assets once and then start Laravel. `composer dev` performs the same two pre-builds before starting Laravel, the queue worker, and Vite's hot-reloading development server. Direct `php artisan serve` usage skips this wrapper and therefore does not rebuild either output.

The repository's `server.php` development-router shim ensures PHP does not treat the generated `public/docs` directory as the base path for the dynamic `/docs/api` route. `php artisan serve` uses this shim automatically.

## Verify the installation

```bash
php artisan about
php artisan migrate:status
php artisan test
npm run build
npm run ui:audit
mkdocs build --strict
```

Then verify in a browser:

- `/up` returns application health.
- `/` loads sign-in.
- account registration delivers a code to the configured mail transport.
- `/docs/` loads this manual after a documentation build.
- `/docs/api` loads Scalar.
- `/docs/openapi.yaml` returns the OpenAPI document.

## Storage directories

Laravel needs write access to:

- `storage/framework/cache/data`;
- `storage/framework/sessions` when file sessions are used;
- `storage/framework/views`;
- `storage/logs`;
- `storage/app/private` for ticket attachments;
- `storage/app/public` for avatars/editor images;
- `bootstrap/cache`;
- the SQLite database directory/file when SQLite is selected.

Editor images are streamed through an authenticated application route and do not require `php artisan storage:link`. The Docker entrypoint still creates the standard public link for compatibility with Laravel public storage.

## Local mail choices

The default `MAIL_MAILER=log` writes email contents to `storage/logs/laravel.log`. Keep the queue worker running, request signup/invitation mail, then search the log for the code or link.

To avoid asynchronous behavior while debugging, `QUEUE_CONNECTION=sync` runs notifications/webhooks inline, but it also makes HTTP requests wait for them and does not represent production behavior.

## Resetting a disposable development database

For a development instance whose data can be destroyed:

```bash
php artisan migrate:fresh
```

This drops and recreates tables. It is destructive and must never be used against an environment whose data matters. Files in storage are not necessarily removed, so stale assets may remain after a database reset.

## Next steps

- Configure [mail and queues](mail-and-queues.md).
- Review [Configuration](configuration.md), especially TOTP policy and docs access.
- Complete the [five-minute quickstart](../getting-started/quickstart.md).
- Read [Testing and quality](../developer/testing.md) before changing code.
