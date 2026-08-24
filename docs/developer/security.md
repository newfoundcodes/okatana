# Security model

Okatana treats the React client, imported/exported HTML, API clients, and webhook destinations as untrusted boundaries. Laravel and the database enforce access and immutability.

## Threat-boundary map

```text
Browser input/session
  → CSRF + auth + account-security middleware
  → authorization + validation + sanitizer + relationship checks
  → SQL constraints/transactions/audit triggers

Bearer client
  → token hash + active check + tenancy + scope + rate limit
  → same domain validation/sanitization

Stored audit event
  → endpoint selection + SSRF guard + queued HMAC request
  → untrusted receiver
```

## Authentication controls

### Local accounts

- Email normalized before lookup/creation.
- Strong password: minimum 14, mixed case, number, symbol.
- Laravel hashed password cast.
- Signup email confirmation with hashed expiring single active code.
- Authentication/security endpoints limited to 10/minute by identity plus IP.
- Session regeneration after authentication and security transitions.
- Session invalidation and CSRF-token regeneration on log-out.

The schema includes password reset storage but routes/UI do not currently implement a reset/recovery flow.

### TOTP

- RFC 6238 secret encrypted at rest.
- QR rendered locally with GD.
- Enrollment only after verified email.
- Six-digit validation.
- `verifyKeyNewer` plus stored last-used step prevents login code replay.
- Deployment policy reevaluated for protected requests.

No recovery-code/user-reset facility exists. This is an operational gap requiring a trusted recovery procedure if TOTP is mandatory.

### OAuth/SSO

Generic OAuth uses state validation and PKCE S256, outbound timeouts, verified-email by default, and email-based linking. SSO protocol validation is delegated entirely to the configured adapter. Both still participate in local TOTP policy.

## Browser request security

The React helper sends same-origin credentials and a CSRF token for non-read methods. Laravel sessions are database-backed and encrypted by default; production should use Secure, HttpOnly, SameSite cookies over HTTPS.

CORS is empty by default and credential-capable only for explicit origins. Same-origin deployment is safest. Frontend capability visibility never substitutes for controllers/services.

## Authorization controls

- Organization membership and role verified server-side.
- Last owner protected on demotion/removal.
- Ordinary project access explicit.
- Relationship IDs checked against the parent project/organization.
- Comment/attachment/document authorship/moderation checked separately.
- Document queries constrain publication/editor/scope.
- Document audit queries prevent draft metadata leaks.
- API credentials constrained by both organization ID and exact scope.

See [Permissions](../reference/permissions.md) for the complete mapping.

## Secret storage matrix

| Secret              | Storage                | Recovery/rotation                                           |
| ------------------- | ---------------------- | ----------------------------------------------------------- |
| Password            | one-way adaptive hash  | replace through supported account flow; no current reset UI |
| Invitation token    | SHA-256                | cannot recover; revoke/reissue                              |
| API secret          | SHA-256                | cannot recover; create replacement/revoke                   |
| Webhook secret      | Laravel encrypted cast | rotate; returned once after rotation                        |
| TOTP secret         | Laravel encrypted cast | no current user reset/recovery interface                    |
| Email code          | adaptive hash          | resend replaces                                             |
| OAuth client secret | environment            | rotate at provider/deployment                               |

Protect and back up `APP_KEY`. Use previous keys only through a tested Laravel rotation process.

## Rich HTML security

`HtmlSanitizer` uses DOM parsing and an explicit tag/attribute scheme list. Dangerous containers are removed with contents; unknown benign wrappers are unwrapped and descendants recursively cleaned. Links/images use safe schemes; mentions and table spans use strict value formats.

Sanitization occurs on browser creates/updates, external API, and imports. Output rendering still should not use unsafe bypasses outside known sanitized fields.

Inline editor images require authentication but their route is path-based rather than resource-membership-aware. Private ticket attachments enforce project membership and send no-store/nosniff headers.

## Webhook/SSRF security

Webhook URLs accept HTTP/HTTPS only. Unless private networks are explicitly allowed, DNS must resolve entirely to public non-reserved IPs and localhost is rejected. Validation repeats immediately before send.

This does not replace egress firewalling, DNS security, response size/time limits, and monitoring. Redirect behavior of the underlying HTTP client and remote DNS/network architecture should be reviewed for a high-assurance deployment.

Payload authenticity uses HMAC-SHA256 over timestamp plus raw body. Receivers must validate digest in constant time, timestamp freshness, and delivery deduplication.

## Audit/revision integrity

Audit model events reject update/delete and SQL triggers block direct changes for supported databases. Revision models also reject update/delete. Audit entries capture request IP/user agent and actor/subject snapshots; imports mark restored records.

Limitations:

- privileged DB administrators can alter triggers/data;
- backup operators can replace snapshots;
- revisions do not have database triggers;
- audit immutability does not encrypt content or provide cryptographic log chaining.

For stronger evidence, export/replicate logs to separately controlled immutable storage.

## File/upload controls

- Avatars/editor images restrict image formats and 5 MB.
- Attachments allow up to 10 files and 200 MB each.
- Original name is basename/capped; stored path uses generated identifier.
- Downloads recheck project access.
- Deletion requires uploader/admin.
- PHP/proxy limits must match application validation.

Consider malware scanning, content disarm, quotas, and storage encryption for your threat model; they are not built into the current application flow.

## Rate limits

- Auth register/login/invitation acceptance: 10/minute keyed by normalized input email + IP.
- Security code/challenge/setup confirmation: 10/minute keyed by user/pending challenge + IP.
- External API: configurable default 120/minute keyed by credential (or IP before credential context).

Rate limiting is application-level. Add edge abuse controls for distributed attacks.

## Security review checklist for changes

- [ ] Tenant and object authorization for reads and writes.
- [ ] Cross-parent IDs rejected.
- [ ] Request and post-sanitization validation.
- [ ] Secrets hidden and not added to exports/audit/logs.
- [ ] Soft-delete and historical visibility considered.
- [ ] Audit event actor/context correct.
- [ ] Notifications cannot leak draft/project data.
- [ ] Webhook payload contains no unexpected secret.
- [ ] Import path applies same trust rules as interactive/API path.
- [ ] Denial, cross-organization, replay, and unsafe-input tests added.
- [ ] Operator/user docs updated for recovery and configuration impact.

## Report and respond

For a suspected exposure, revoke/rotate affected API/webhook/OAuth/database credentials, preserve audit/delivery/log evidence, assess `APP_KEY` impact, and invalidate sessions as appropriate. Do not mutate audit history. The repository license/readme does not define a dedicated vulnerability reporting channel, so deployment owners should establish one.
