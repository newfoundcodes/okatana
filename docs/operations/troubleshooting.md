# Troubleshooting

Start with the smallest failing boundary: browser assets, Laravel request, authorization, database, storage, queue/mail, or external network. Capture status codes and correlation context without copying secrets.

## First checks

```bash
php artisan about
php artisan migrate:status
php artisan queue:failed
npm run build
mkdocs build --strict
```

For Docker:

```bash
docker compose ps
docker compose logs web
docker compose logs queue
```

Check `storage/logs/laravel.log` for local/non-stderr logging. Confirm web and queue use the same release/environment.

## Application will not load

| Symptom                    | Check                                                                               |
| -------------------------- | ----------------------------------------------------------------------------------- |
| 500 before HTML            | `APP_KEY`, database connection/migrations, writable `storage` and `bootstrap/cache` |
| HTML loads but blank page  | browser console/network, missing `public/build/manifest.json`, run `npm run build`  |
| CSS/JS 404 behind proxy    | document root must be `public`; forwarded host/scheme and base URL                  |
| Vite errors in development | Node compatibility, `npm install`, running Vite, stale hot file                     |
| `/up` fails                | Laravel boot/database-independent exceptions; web logs                              |

## MkDocs or API docs fail

### `/docs/` is 404

Run `mkdocs build --strict` so `public/docs/index.html` exists, or use the Docker image whose documentation stage builds it. Confirm the web server allows directory indexes for the static directory while Apache’s application `.htaccess` still disables broad listing.

If `/docs` loads as unstyled HTML, redirect it to `/docs/` with the trailing slash so MkDocs' directory-relative CSS and JavaScript URLs resolve under `/docs/assets`. The repository's `server.php` provides this redirect for `php artisan serve`; configure the equivalent canonical redirect if a reverse proxy serves the directory directly.

### MkDocs build fails

- install `requirements-docs.txt` in a Python virtual environment;
- read the strict warning: missing nav files, broken relative links, invalid extension, or theme configuration;
- do not generate a page at `docs/api/index.md`, which would shadow Scalar;
- keep `docs/openapi.yaml` readable so Laravel can serve it; MkDocs deliberately excludes it from static output.

### `/docs/api` is 404/403

Confirm Scalar package discovery, `OKATANA_API_DOCS_PATH`, route cache, and `OKATANA_API_DOCS_PUBLIC`. With public=false, the request needs an authenticated Laravel session. Ensure no physical `public/docs/api` directory shadows the route.

### Scalar loads but requests fail

Use a valid `oka_...` bearer credential, correct organization ULIDs/scopes, and same-origin API base. `401` is credential authentication; `403` is tenancy/scope; `422` is input/business validation.

## Sign-in and activation

| Symptom                           | Check                                                                            |
| --------------------------------- | -------------------------------------------------------------------------------- |
| Confirmation code absent          | queue worker, mail transport, log mailer, spam/provider rejection                |
| Code invalid/expired              | TTL, attempts, newest resend, user input; request a replacement                  |
| TOTP never validates              | synchronized server/phone clocks, six digits, current enrollment secret          |
| Used code rejected                | replay protection correctly blocks an already-used time step; wait for next code |
| Setup appears after policy change | expected when TOTP was enabled for unenrolled users                              |
| OAuth state 419                   | session cookies/origin/proxy, callback replay or stale tab                       |
| Invitation 410                    | expired, revoked, or accepted                                                    |

Do not solve TOTP problems by deleting encrypted columns or changing `APP_KEY`; that can affect every enrolled account and webhook secret.

## Missing organization/project/document

1. Confirm current account email and organization membership.
2. Confirm organization role.
3. For ordinary users, confirm explicit project membership.
4. For project documents, confirm project access.
5. For draft/archived documents, confirm author/editor relationship.
6. Check soft-delete/archive state.
7. Search audit history for role/member/project/document transitions.

Laravel returns 403 for many known-but-forbidden objects and 404 where route binding/query privacy excludes them. Do not infer nonexistence from UI absence alone.

## Ticket movement fails with 422

- Destination WIP limit may be full; archived tickets do not count.
- Destination phase must belong to the same project.
- Reorder list must contain only tickets from the selected phase and no duplicates.
- Assignees/labels must belong to accessible project scope.
- The request may use an unsupported priority or invalid date.

Inspect the JSON `message` and validation `errors` rather than retrying blindly.

## Files or images fail

| Symptom                        | Check                                                                      |
| ------------------------------ | -------------------------------------------------------------------------- |
| Upload rejected before Laravel | proxy/PHP `upload_max_filesize`, `post_max_size`, request timeout          |
| Attachment 404                 | database path, private disk file, project access, restored storage mapping |
| Inline image 404               | authenticated session, public-disk file, valid owner/UUID filename         |
| Avatar 404 to teammate         | shared organization membership and stored file                             |
| PDF misses remote image        | DOMPDF remote access disabled by default; upload locally                   |
| Disk write failure             | volume mount, ownership, free space, Laravel disk root                     |

## Queue backlog or failed mail

```bash
php artisan queue:failed
```

Confirm worker liveness and database connectivity. Read the exception before retrying. Restart workers after deploy/config changes. A database queue worker should have a timeout shorter than retry-after.

With `MAIL_MAILER=log`, inspect the log rather than an inbox. In production, confirm SMTP credentials and sender policy.

## Webhook failure

- Private/reserved DNS target is rejected unless explicitly allowed.
- DNS may have changed between configuration and delivery; the guard rechecks.
- Outbound TLS/firewall may fail.
- Receiver may return non-2xx or take longer than timeout.
- Signature verifier may parse/re-encode JSON instead of using raw bytes.
- Endpoint may have rotated its secret or been deactivated.

Use delivery ID/status/response excerpt. Do not log the secret or Authorization data.

## Database and audit errors

`audit_logs are immutable` or `audit_logs cannot be deleted` is expected when code/tooling attempts mutation. Fix the process rather than disabling the trigger.

If migrations fail while creating triggers, verify the selected driver is `sqlite`, `mysql`, or `pgsql` and the migration role has trigger/function privileges. An unsupported driver will not receive an audit trigger even if generic Laravel queries happen to work.

For SQLite lock errors, reduce simultaneous write workload, ensure local reliable storage, inspect long transactions/imports, or migrate to MySQL/PostgreSQL.

## API errors

| Status | Meaning/action                                                               |
| -----: | ---------------------------------------------------------------------------- |
|    401 | missing/malformed/revoked/expired token or wrong secret                      |
|    403 | missing scope or cross-organization/resource authorization                   |
|    404 | resource absent/soft-deleted or not found in constrained relationship        |
|    409 | state conflicts such as a security step no longer required/already completed |
|    419 | expired session/challenge or OAuth state failure                             |
|    422 | validation, relationship, WIP limit, or business rule failure                |
|    429 | rate limit; back off and reduce request rate                                 |

## Escalation bundle

When handing off a problem, include:

- release identifier and deployment type;
- timestamp/timezone and affected URL pattern (without tokens);
- HTTP status and sanitized response message;
- actor type and organization/project ULIDs when safe;
- related audit event/delivery/job ID;
- relevant sanitized logs;
- whether web and queue reproduce it;
- recent config/deploy/migration changes;
- steps and expected versus actual behavior.

Never include `.env`, bearer tokens, session cookies, TOTP/manual keys, invitation URLs, webhook secrets, or raw exports in an untrusted support channel.
