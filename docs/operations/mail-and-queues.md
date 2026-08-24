# Mail and queues

Invitations, email verification, collaboration email, and webhooks depend on background processing. The default database queue avoids a broker dependency but still requires a running worker.

## Configure SMTP

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=okatana@example.com
MAIL_PASSWORD=replace-me
MAIL_FROM_ADDRESS=okatana@example.com
MAIL_FROM_NAME="Okatana"
```

Laravel also accepts `MAIL_URL` and `MAIL_EHLO_DOMAIN` through the project mail configuration. Use the provider’s exact scheme/port requirements; do not infer TLS behavior solely from the port.

For local development:

```dotenv
MAIL_MAILER=log
```

Messages appear in `storage/logs/laravel.log` after the worker processes their jobs. `MAIL_MAILER=array` is useful in automated tests because it captures mail in memory.

## Queue configuration

```dotenv
QUEUE_CONNECTION=database
DB_QUEUE_TABLE=jobs
DB_QUEUE=default
DB_QUEUE_RETRY_AFTER=90
QUEUE_FAILED_DRIVER=database-uuids
```

Run a worker:

```bash
php artisan queue:work --sleep=2 --tries=5 --timeout=90
```

The supplied Docker `queue` service uses that pattern. The Composer development script uses `--tries=3` while developing.

Current queued workloads include Laravel notifications/mail and webhook delivery. Database-queued jobs become available after the surrounding database transaction commits.

## Webhook retry behavior

`DeliverWebhookJob` receives the configured maximum attempts (five by default) and uses backoff intervals:

```text
10 seconds → 60 seconds → 300 seconds → 900 seconds
```

The delivery row changes from pending through retrying to delivered/failed (or disabled if its endpoint is inactive). It stores attempt count, response status, at most 2,000 response-body characters, last error, and delivery time.

HTTP status outside 2xx raises an error and retries. Receivers must be idempotent using `X-Okatana-Delivery`; a timeout can occur after the remote server committed work but before Okatana received its response.

## Operate workers

In production:

- use the container platform/process supervisor to restart workers;
- restart workers after code/config deployments;
- ensure web and workers share database, `APP_KEY`, storage, DNS, and outbound network policy;
- keep worker timeout consistent with `DB_QUEUE_RETRY_AFTER`;
- scale workers based on queue latency and remote webhook behavior;
- stop workers cleanly before schema changes that alter queued payload expectations.

Useful commands:

```bash
php artisan queue:work --tries=5 --timeout=90
php artisan queue:restart
php artisan queue:failed
php artisan queue:retry all
php artisan queue:flush
```

`queue:flush` destroys failed-job evidence; use it only under an approved retention procedure after capturing what is needed. `queue:retry all` can repeat external effects, so first confirm receiver idempotency and correct the original cause.

## Monitor

At minimum alert on:

- no live queue worker;
- oldest pending job age;
- jobs table growth;
- new `failed_jobs` rows;
- repeated webhook `retrying`/`failed` status;
- mail-provider authentication/rate-limit errors;
- queue job duration near the 90-second worker timeout;
- disk growth in logs.

Okatana does not bundle a queue dashboard. Use SQL metrics, platform process health, and centralized logs.

## Diagnose email

### Signup code or invitation not received

1. Confirm the database row/job exists.
2. Confirm a worker is connected to the same database.
3. Inspect failed jobs and Laravel logs.
4. Verify SMTP host/credentials/TLS and sender authorization.
5. Check provider suppression/spam/bounce handling.
6. Confirm `APP_URL` in links.
7. After correction, resend the code or create/retry the appropriate invitation/job.

Email verification code notifications encrypt the plaintext code before it enters the database queue payload and decrypt only while rendering mail. Do not log rendered messages in production.

### In-app notification exists, email absent

Check the user’s category preference, then mail/queue operation. The collaboration mutation does not wait for email success.

## Diagnose webhooks

1. Inspect endpoint active state and selected event.
2. Inspect delivery status, attempts, HTTP code/excerpt, and last error.
3. Confirm DNS resolves to a permitted public address.
4. Confirm outbound firewall/TLS trust.
5. Verify receiver signature logic uses the raw body.
6. Correct receiver/endpoint before retrying jobs.

See [Webhooks](../integrations/webhooks.md) for the receiver contract.
