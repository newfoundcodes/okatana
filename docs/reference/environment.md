# Environment variables

Values shown are repository defaults, not production recommendations. Laravel may expose additional framework variables; this reference focuses on values read by Okatana’s committed configuration and Docker setup.

## Application

| Variable                 | Default                 | Purpose                                                      |
| ------------------------ | ----------------------- | ------------------------------------------------------------ |
| `APP_NAME`               | `Okatana`               | display name, mail name, cookie prefix, TOTP issuer          |
| `APP_ENV`                | `local` in example      | runtime environment; use `production` in production          |
| `APP_KEY`                | empty                   | Laravel AES-256-CBC key; required for encryption             |
| `APP_PREVIOUS_KEYS`      | empty                   | comma-separated prior keys for Laravel decryption transition |
| `APP_DEBUG`              | `true` in example       | exception debug detail; false in production                  |
| `APP_URL`                | `http://localhost:8000` | canonical links, OAuth callbacks, public-disk URL            |
| `APP_TIMEZONE`           | `UTC`                   | server application timezone                                  |
| `APP_LOCALE`             | `en`                    | application locale                                           |
| `APP_FALLBACK_LOCALE`    | `en`                    | fallback translation locale                                  |
| `APP_FAKER_LOCALE`       | `en_US`                 | faker/test locale                                            |
| `APP_MAINTENANCE_DRIVER` | `file`                  | maintenance state driver                                     |
| `APP_MAINTENANCE_STORE`  | `database`              | store if driver uses cache-backed maintenance                |
| `BCRYPT_ROUNDS`          | `12`                    | password hashing cost                                        |
| `VITE_APP_NAME`          | `${APP_NAME}`           | frontend build-time application name                         |

## Logging

| Variable                   | Default  | Purpose                           |
| -------------------------- | -------- | --------------------------------- |
| `LOG_CHANNEL`              | `stack`  | active Laravel log channel        |
| `LOG_STACK`                | `single` | comma-separated channels in stack |
| `LOG_LEVEL`                | `debug`  | minimum log level                 |
| `LOG_DEPRECATIONS_CHANNEL` | `null`   | deprecation channel               |
| `LOG_DEPRECATIONS_TRACE`   | `false`  | include deprecation traces        |
| `LOG_STDERR_FORMATTER`     | empty    | optional Monolog stderr formatter |

For containers, `stderr` is often easier to centralize than `single`, but choose according to the platform.

## Database

| Variable          | SQLite/default             | MySQL/PostgreSQL purpose          |
| ----------------- | -------------------------- | --------------------------------- |
| `DB_CONNECTION`   | `sqlite`                   | `mysql` or `pgsql` selects driver |
| `DB_URL`          | empty                      | optional complete connection URL  |
| `DB_DATABASE`     | `database/database.sqlite` | database name/path                |
| `DB_HOST`         | `127.0.0.1`                | database server host              |
| `DB_PORT`         | driver default             | 3306 or 5432                      |
| `DB_USERNAME`     | driver default             | database user                     |
| `DB_PASSWORD`     | empty                      | database password                 |
| `DB_FOREIGN_KEYS` | `true`                     | SQLite foreign key enforcement    |
| `DB_SOCKET`       | empty                      | MySQL Unix socket                 |
| `DB_CHARSET`      | `utf8mb4`                  | MySQL charset                     |
| `DB_COLLATION`    | `utf8mb4_unicode_ci`       | MySQL collation                   |

The PostgreSQL configuration has committed `sslmode=prefer`; extend configuration if a provider requires a stricter mode/certificate parameters.

Compose overlays also use:

| Variable                   | Default in compose expression | Purpose                               |
| -------------------------- | ----------------------------- | ------------------------------------- |
| `OKATANA_DB_PASSWORD`      | `change-me`                   | MySQL/PostgreSQL application password |
| `OKATANA_DB_ROOT_PASSWORD` | `change-root-me`              | MySQL root/health password            |

Never keep these placeholder values in production.

## Session

| Variable                     | Default            | Purpose                                     |
| ---------------------------- | ------------------ | ------------------------------------------- |
| `SESSION_DRIVER`             | `database`         | session backend                             |
| `SESSION_LIFETIME`           | `120`              | idle lifetime in minutes                    |
| `SESSION_EXPIRE_ON_CLOSE`    | `false`            | expire on browser close                     |
| `SESSION_ENCRYPT`            | `true`             | encrypt session data                        |
| `SESSION_CONNECTION`         | empty              | database connection override                |
| `SESSION_TABLE`              | `sessions`         | database table                              |
| `SESSION_STORE`              | empty              | cache store when relevant                   |
| `SESSION_COOKIE`             | derived            | cookie name                                 |
| `SESSION_PATH`               | `/`                | cookie path                                 |
| `SESSION_DOMAIN`             | `null`             | cookie domain                               |
| `SESSION_SECURE_COOKIE`      | `false` in example | HTTPS-only cookie; true in production HTTPS |
| `SESSION_HTTP_ONLY`          | `true`             | deny JavaScript cookie access               |
| `SESSION_SAME_SITE`          | `lax`              | SameSite behavior                           |
| `SESSION_PARTITIONED_COOKIE` | `false`            | partitioned cookie flag                     |

## Cache and queue

| Variable                   | Default          | Purpose                         |
| -------------------------- | ---------------- | ------------------------------- |
| `CACHE_STORE`              | `database`       | cache backend                   |
| `CACHE_PREFIX`             | `okatana-cache-` | cache namespace                 |
| `DB_CACHE_CONNECTION`      | empty            | cache database override         |
| `DB_CACHE_TABLE`           | `cache`          | cache table                     |
| `DB_CACHE_LOCK_CONNECTION` | empty            | lock connection override        |
| `DB_CACHE_LOCK_TABLE`      | `cache_locks`    | lock table                      |
| `QUEUE_CONNECTION`         | `database`       | queue backend; `sync` available |
| `DB_QUEUE_CONNECTION`      | empty            | queue database override         |
| `DB_QUEUE_TABLE`           | `jobs`           | jobs table                      |
| `DB_QUEUE`                 | `default`        | queue name                      |
| `DB_QUEUE_RETRY_AFTER`     | `90`             | reservation retry seconds       |
| `QUEUE_FAILED_DRIVER`      | `database-uuids` | failed-job storage              |

## Files and PDF

| Variable                            | Default  | Purpose                                                         |
| ----------------------------------- | -------- | --------------------------------------------------------------- |
| `FILESYSTEM_DISK`                   | `local`  | application default disk; private root is `storage/app/private` |
| `LARAVEL_PDF_DRIVER`                | `dompdf` | document PDF driver                                             |
| `LARAVEL_PDF_DOMPDF_REMOTE_ENABLED` | `false`  | allow DOMPDF to fetch remote assets                             |
| `LARAVEL_PDF_DOMPDF_CHROOT`         | empty    | optional DOMPDF filesystem chroot                               |

Okatana explicitly uses the `public` disk for avatars/editor images and `local` for attachments regardless of default in those controller paths.

## Mail

| Variable            | Default                          | Purpose                                       |
| ------------------- | -------------------------------- | --------------------------------------------- |
| `MAIL_MAILER`       | `log`                            | `smtp`, `log`, or `array` in committed config |
| `MAIL_URL`          | empty                            | optional mailer URL                           |
| `MAIL_SCHEME`       | `null`                           | SMTP transport scheme                         |
| `MAIL_HOST`         | `127.0.0.1`                      | SMTP host                                     |
| `MAIL_PORT`         | `2525`                           | SMTP port                                     |
| `MAIL_USERNAME`     | `null`                           | SMTP user                                     |
| `MAIL_PASSWORD`     | `null`                           | SMTP password                                 |
| `MAIL_EHLO_DOMAIN`  | `APP_URL` host                   | SMTP EHLO domain                              |
| `MAIL_FROM_ADDRESS` | `okatana@example.com` in example | sender address                                |
| `MAIL_FROM_NAME`    | `${APP_NAME}`                    | sender display name                           |
| `MAIL_LOG_CHANNEL`  | empty                            | optional channel for log mailer               |

## Okatana policy

| Variable                                 | Default | Bounds/behavior                           |
| ---------------------------------------- | ------: | ----------------------------------------- |
| `OKATANA_INVITATION_TTL_HOURS`           |   `168` | invitation expiry hours                   |
| `OKATANA_REQUIRE_TWO_FACTOR`             |  `true` | deployment-wide TOTP enforcement          |
| `OKATANA_EMAIL_CODE_TTL_MINUTES`         |    `12` | service applies minimum 5                 |
| `OKATANA_EMAIL_CODE_RESEND_SECONDS`      |    `60` | service applies minimum 15                |
| `OKATANA_EMAIL_CODE_MAX_ATTEMPTS`        |     `8` | service applies minimum 3                 |
| `OKATANA_API_RATE_LIMIT`                 |   `120` | external calls per minute/credential      |
| `OKATANA_WEBHOOK_TIMEOUT_SECONDS`        |     `8` | outbound HTTP timeout                     |
| `OKATANA_WEBHOOK_MAX_ATTEMPTS`           |     `5` | job attempt count                         |
| `OKATANA_WEBHOOK_ALLOW_PRIVATE_NETWORKS` | `false` | bypass private/reserved destination block |

## Documentation

| Variable                  | Default                 | Purpose                                      |
| ------------------------- | ----------------------- | -------------------------------------------- |
| `OKATANA_API_DOCS_PUBLIC` | `true`                  | public Scalar/OpenAPI or authenticated-only  |
| `OKATANA_API_DOCS_PATH`   | `/docs/api`             | Scalar route; do not set to `/docs`          |
| `OKATANA_OPENAPI_PATH`    | `/docs/openapi.yaml`    | specification route                          |
| `OKATANA_SCALAR_CDN`      | jsDelivr Scalar package | override with mirrored/pinned browser bundle |

Static MkDocs output at `/docs/` is not controlled by the public flag. Use reverse-proxy authorization if the whole manual must be private.

## OAuth

| Variable                         | Default                | Purpose                                           |
| -------------------------------- | ---------------------- | ------------------------------------------------- |
| `OAUTH_GOOGLE_ENABLED`           | `false`                | show/enable Google provider                       |
| `OAUTH_GOOGLE_CLIENT_ID`         | empty                  | client ID                                         |
| `OAUTH_GOOGLE_CLIENT_SECRET`     | empty                  | client secret                                     |
| `OAUTH_GOOGLE_AUTHORIZATION_URL` | Google                 | authorization endpoint                            |
| `OAUTH_GOOGLE_TOKEN_URL`         | Google                 | token endpoint                                    |
| `OAUTH_GOOGLE_USERINFO_URL`      | Google                 | user-info endpoint                                |
| `OAUTH_GOOGLE_SCOPES`            | `openid email profile` | requested scope string                            |
| `OKATANA_OAUTH_PROVIDERS_JSON`   | `[]`                   | JSON object of additional provider definitions    |
| `OKATANA_SAML_DRIVER_CLASS`      | empty                  | Laravel-resolvable class implementing `SsoDriver` |

## CORS and container entrypoint

| Variable               | Default                         | Purpose                                       |
| ---------------------- | ------------------------------- | --------------------------------------------- |
| `CORS_ALLOWED_ORIGINS` | empty                           | comma-separated allowed origins               |
| `OKATANA_AUTO_MIGRATE` | `true` in base web Compose only | entrypoint runs `php artisan migrate --force` |

After changes, clear/cache config and restart web plus queue workers. See [Configuration](../operations/configuration.md).
