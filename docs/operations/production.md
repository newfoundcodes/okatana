# Production checklist

Use this checklist before an Internet-facing launch and after material platform changes. It complements—not replaces—your organization’s deployment, security, privacy, and recovery standards.

## Runtime and edge

- [ ] `APP_ENV=production`.
- [ ] `APP_DEBUG=false`.
- [ ] Canonical `APP_URL` uses HTTPS and matches external routing.
- [ ] TLS terminates at a maintained proxy/load balancer with correct forwarded headers.
- [ ] `/public` is the only exposed application document root.
- [ ] Directory listing is disabled.
- [ ] `/up` is used for a basic health probe; deeper dependency checks are monitored separately.
- [ ] Web and queue processes run as non-root application users.
- [ ] PHP OPcache is enabled and resource limits are explicit.
- [ ] Frontend and MkDocs were built from the same release as Laravel code.

## Secrets and encryption

- [ ] Generate a unique `APP_KEY`; never reuse a development key.
- [ ] Back up `APP_KEY` in a separate protected secret store.
- [ ] Keep database, SMTP, OAuth, and other secrets outside version control/image layers where possible.
- [ ] Restrict `.env` file permissions.
- [ ] Set a credential/secret rotation procedure.
- [ ] Understand that losing `APP_KEY` loses decryption access to TOTP and webhook secrets.
- [ ] Use `APP_PREVIOUS_KEYS` only as part of a tested Laravel key-rotation process.

## Sessions and browser security

- [ ] `SESSION_ENCRYPT=true`.
- [ ] `SESSION_SECURE_COOKIE=true` behind HTTPS.
- [ ] `SESSION_HTTP_ONLY=true`.
- [ ] `SESSION_SAME_SITE=lax` remains appropriate for sign-in/OAuth callbacks.
- [ ] `SESSION_DOMAIN` is no broader than required.
- [ ] CORS origins are empty for normal same-origin SPA operation or explicitly restricted.
- [ ] Reverse proxy preserves Authorization and CSRF headers.
- [ ] Authentication/security rate limit behavior was tested from the real edge IP topology.

## Account policy

- [ ] Decide and document `OKATANA_REQUIRE_TWO_FACTOR` before onboarding.
- [ ] Synchronize server/container clocks through reliable NTP.
- [ ] Test signup email verification and TOTP from outside the internal network.
- [ ] Document recovery for lost authenticators because there is no user recovery-code interface.
- [ ] Keep at least two owners per production organization.
- [ ] Test invited new and existing-account flows.
- [ ] Review OAuth/SSO verified-email and account-linking behavior if enabled.

## Database and storage

- [ ] Use SQLite only when its single-node/write-concurrency profile is intentional.
- [ ] Restrict database network/firewall access.
- [ ] Use TLS for remote database links as appropriate.
- [ ] Migration role can create audit triggers; runtime role is appropriately restricted.
- [ ] Confirm audit update/delete triggers exist after migration.
- [ ] Persist `storage/app/private`, `storage/app/public`, and the selected database.
- [ ] Web and queue share the same storage where jobs need assets.
- [ ] Upload limits support 200 MB attachments and expected request overhead.
- [ ] Backups include database, both asset disks, `APP_KEY`, configuration, and release identifier.
- [ ] A restore drill has succeeded in an isolated environment.

## Queues and mail

- [ ] Configure a real mail provider; do not use `MAIL_MAILER=log`.
- [ ] Authenticate/verify the from-domain according to provider policy.
- [ ] Run at least one supervised queue worker for asynchronous connections.
- [ ] Worker and web environment/configuration match.
- [ ] Restart workers during deployments.
- [ ] Monitor queue depth/age and `failed_jobs`.
- [ ] Test verification, invitation, assignment, mention, and document-comment email categories.

## API and webhooks

- [ ] Set an appropriate API per-credential rate limit.
- [ ] Issue one minimally scoped credential per integration/environment.
- [ ] Store one-time tokens in a secret manager and test rotation.
- [ ] Set expirations where operationally supported.
- [ ] Keep `OKATANA_WEBHOOK_ALLOW_PRIVATE_NETWORKS=false` unless internal delivery is intentional.
- [ ] Restrict application egress at the network layer in addition to the URL guard.
- [ ] Receivers verify HMAC over the raw body, validate timestamp freshness, and deduplicate delivery IDs.
- [ ] Monitor failed webhook deliveries and queue jobs.
- [ ] Decide whether Scalar/OpenAPI should be public.

## Documentation

- [ ] `mkdocs build --strict` passes in release CI.
- [ ] `/docs/` loads static assets under the deployment subpath.
- [ ] `/docs/api` still resolves to Scalar, not a generated directory.
- [ ] `/docs/openapi.yaml` matches the deployed API.
- [ ] Public static MkDocs content contains no internal secrets/hostnames.
- [ ] If documentation must be private, enforce `/docs/` at the proxy; `OKATANA_API_DOCS_PUBLIC` protects Scalar/spec only.

## Observability

Monitor at least:

- HTTP error and latency rates for `/app-api` and `/api/v1` separately;
- database connectivity, locks, storage, and query latency;
- queue process health, depth, oldest job, and failures;
- mail provider rejection/bounce/rate-limit signals;
- webhook delivery status/latency;
- disk usage for private/public assets and logs;
- backup completion and restore verification;
- API `401`, `403`, `422`, and `429` rates;
- authentication/security throttle and TOTP clock-related errors.

Avoid logging request Authorization headers, session cookies, TOTP secrets/codes, invitation tokens, webhook secrets, or exported data bodies.

## Release verification

Run in CI or the release workspace:

```bash
composer run test
npm run build
npm run ui:audit
npm run lint
mkdocs build --strict
```

Then smoke-test the deployed release:

1. Health and static assets.
2. Sign-in/security flow.
3. Organization/project read.
4. Ticket create/edit/move/comment and private file download.
5. Document create/publish/PDF.
6. Notification and queued email.
7. Scoped API request.
8. Signed webhook delivery.
9. Audit event visibility and immutability.
10. Export download.

## Rollback preparation

- Keep the prior application image/release artifact.
- Know whether each migration is backward compatible with the prior code.
- Back up before irreversible schema/data change.
- Do not assume `migrate:rollback` is safe: the audit-protection migration intentionally controls triggers, and down migrations may drop data-bearing tables/columns.
- Restart workers with the same version as web during rollback.
- Validate encrypted secrets with the restored `APP_KEY`.
