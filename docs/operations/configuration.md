# Configuration

Okatana uses Laravel environment configuration. Begin with `.env.example`, keep environment files out of version control, and apply changes to both web and queue processes.

The complete lookup table is in [Environment variables](../reference/environment.md). This page explains the decisions behind the main groups.

## Application identity and runtime

```dotenv
APP_NAME=Okatana
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://okatana.example.com
APP_TIMEZONE=UTC
APP_LOCALE=en
```

- `APP_KEY` encrypts application data including webhook and TOTP secrets. Back it up separately from the database. Laravel supports `APP_PREVIOUS_KEYS` for controlled encryption-key transition, but test decryption paths before rotating.
- `APP_URL` builds invitation and identity-provider callback URLs and public-disk URLs.
- `APP_TIMEZONE` affects server-side date calculations and presentation inputs. Store a clear deployment policy; the browser also contributes local time in Gantt.
- Never enable `APP_DEBUG` on an Internet-facing deployment because exceptions may expose context.

## Database, session, cache, and queue

The default all-SQL profile is:

```dotenv
DB_CONNECTION=sqlite
SESSION_DRIVER=database
SESSION_ENCRYPT=true
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
```

This minimizes dependencies and keeps state inspectable in one SQL system. For higher scale, Laravel-compatible replacements can be configured, but domain code assumes neither Redis nor a broker-specific API.

The default queue uses `after_commit=true`, so jobs are available only after the database transaction commits. Keep `DB_QUEUE_RETRY_AFTER` longer than the worker timeout to reduce duplicate reservation during slow jobs.

## Session cookies

Production baseline:

```dotenv
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_PATH=/
SESSION_DOMAIN=null
```

The same-origin React application does not normally need broad CORS or cross-site cookies. If you introduce a separate trusted frontend origin, review CORS, CSRF, cookie domain, SameSite, secure transport, and credential handling as one system.

## Account security policy

```dotenv
OKATANA_REQUIRE_TWO_FACTOR=true
OKATANA_EMAIL_CODE_TTL_MINUTES=12
OKATANA_EMAIL_CODE_RESEND_SECONDS=60
OKATANA_EMAIL_CODE_MAX_ATTEMPTS=8
```

New local accounts confirm email regardless of TOTP policy. Enabling TOTP after users exist immediately forces unenrolled accounts through setup on protected requests. Disabling it preserves secrets and confirmation timestamps for future re-enable.

Changing the flag is an enforcement change, not a schema migration. Ensure server time is synchronized before requiring TOTP.

## Invitations

```dotenv
OKATANA_INVITATION_TTL_HOURS=168
```

Choose a duration that balances delivery/onboarding time against exposure of a forwarded link. Tokens are one-way hashed; replace a lost invitation instead of trying to recover it.

## External API

```dotenv
OKATANA_API_RATE_LIMIT=120
```

The limit is per authenticated credential per minute; unauthenticated failures key by client IP where applicable. Authentication and security setup routes have a fixed 10/minute limiter keyed by email/account plus IP.

## Webhooks

```dotenv
OKATANA_WEBHOOK_TIMEOUT_SECONDS=8
OKATANA_WEBHOOK_MAX_ATTEMPTS=5
OKATANA_WEBHOOK_ALLOW_PRIVATE_NETWORKS=false
```

Delivery job backoff is 10, 60, 300, and 900 seconds. Keep timeout below the worker’s 90-second timeout. Private-network allowance disables the application SSRF destination restriction and should remain false unless explicitly required.

## API documentation

```dotenv
OKATANA_API_DOCS_PUBLIC=true
OKATANA_API_DOCS_PATH=/docs/api
OKATANA_OPENAPI_PATH=/docs/openapi.yaml
```

These settings control Scalar and its OpenAPI specification. `OKATANA_API_DOCS_PUBLIC=false` requires an authenticated Laravel user for those dynamic routes. The generated MkDocs site under `public/docs` is static and remains publicly readable at the web-server layer unless your proxy adds access control.

Do not set `OKATANA_API_DOCS_PATH` to `/docs` because that path is reserved for this manual. Do not add a MkDocs page whose output directory is `/docs/api`, or it may shadow Scalar as a physical directory.

`OKATANA_SCALAR_CDN` may point to a mirrored/pinned browser bundle for private networks.

## PDF

```dotenv
LARAVEL_PDF_DRIVER=dompdf
LARAVEL_PDF_DOMPDF_REMOTE_ENABLED=false
LARAVEL_PDF_DOMPDF_CHROOT=
```

DOMPDF is the portable default. Local editor images are inlined by the application. Enable remote images only after assessing server-side network access risk.

## CORS

`CORS_ALLOWED_ORIGINS` is a comma-separated list. With an empty value, Okatana is same-origin only. Supported paths include `/api/*`, `/app-api/*`, and `/auth/*`, with credentials enabled.

Avoid wildcard origins with credentialed browser traffic. The bearer API does not require CORS for server-to-server clients.

## Apply changes safely

Laravel may cache configuration. After changing values:

```bash
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

If running containers, restart/recreate both web and queue services with the new environment. Confirm effective behavior through safe functional checks, not by dumping decrypted secrets.

## Configuration drift checklist

- Web and queue use the same `APP_KEY`, database, queue, mail, and webhook settings.
- `APP_URL` matches the externally visible scheme/host.
- proxy TLS and `SESSION_SECURE_COOKIE` agree.
- database driver is supported by the audit trigger migration.
- queue workers were restarted after deployment.
- documentation build preserves `/docs/api` for Scalar.
- storage disks are persistent and writable.
- TOTP server time is synchronized.
