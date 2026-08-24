# Laravel backend

The backend is a Laravel 13 application on PHP 8.3+. It owns every trust decision: routing, authentication, account security, authorization, validation, persistence, sanitization, auditing, queues, mail, integration calls, import/export, and PDF rendering.

## Request surfaces

| Surface                        | Route file          | Middleware/auth                            | Purpose                               |
| ------------------------------ | ------------------- | ------------------------------------------ | ------------------------------------- |
| `/`, `/app/*`, `/join/*`       | `routes/web.php`    | web session shell                          | serve the React entry view            |
| `/app-api/*`                   | `routes/web.php`    | web, then `auth`/`account.secure` by group | same-origin browser JSON API          |
| `/auth/oauth/*`, `/auth/sso/*` | `routes/web.php`    | web session/state                          | external identity redirects/callbacks |
| `/api/v1/*`                    | `routes/api.php`    | `api.credential`, `throttle:api`           | versioned external bearer API         |
| `/docs/api`, OpenAPI           | Scalar/routes       | configurable docs access                   | interactive API reference/spec        |
| `/up`                          | `bootstrap/app.php` | framework health                           | basic application health              |

Laravel is configured to render JSON exceptions for `api/*` and `app-api/*`. The SPA shell uses one Blade template with CSRF meta and Vite entrypoints.

The generated manual is a static `/docs/` directory, while `/docs/api` and `/docs/openapi.yaml` remain Laravel routes. The root `server.php` file is used only by `php artisan serve`; it resets PHP's inferred script base before handing a non-static request to Laravel. Apache handles the same separation through its normal front-controller rewrite.

## Middleware

### `AuthenticateApiCredential`

Splits the bearer token at the first dot, looks up public ID, rejects revoked/expired credentials, hashes the presented secret with SHA-256, compares with `hash_equals`, quietly updates `last_used_at`, and attaches the credential to request attributes.

### `EnsureAccountSecurity`

Evaluates persistent email/TOTP state against current deployment policy. Incomplete setup returns a structured `403` before any workspace endpoint executes.

### `EnsureApiDocumentationAccess`

Allows Scalar/spec when docs are public or a Laravel user is authenticated. Static MkDocs is served by the web server and is outside this middleware.

## Controllers

Browser controllers are domain-focused:

- authentication/security, invitations, OAuth, SSO, and user profile;
- organizations/members, projects/members, boards, labels;
- tickets, comments, attachments, revisions, analytics;
- documents, document comments, editor assets, PDF;
- audit, transfers, notifications, webhooks, API credentials;
- API documentation.

`ExternalApiController` consolidates the external v1 contract so credential/scope actor handling remains consistent. It intentionally repeats domain validation rather than delegating to browser controller methods that assume a user/session request.

## Domain services

| Service                             | Responsibility                                                    |
| ----------------------------------- | ----------------------------------------------------------------- |
| `AccountSecurityService`            | email code issuance/verification, TOTP policy/setup/challenge/QR  |
| `AuthorizationService`              | organization/project/comment invariants for browser operations    |
| `DocumentAccessService`             | document query/object visibility and eligible editors             |
| `ActivityRecorder`                  | insert audit event and enqueue matching webhook deliveries        |
| `ApiCredentialService`              | generate public/secret token and persist hash/scopes              |
| `DataTransferService`               | versioned graph export/import, ID/identity/asset mapping          |
| `HtmlSanitizer`                     | DOM-based allowed HTML/attribute/URL boundary                     |
| `MentionService`                    | extract/validate/sync mention identities and send direct alerts   |
| `NotificationService`               | recipient derivation, in-app notification, preference-aware email |
| `RevisionService`                   | canonical ticket/comment snapshots and immutable versions         |
| `TagService` / `DocumentTagService` | normalize/reuse/synchronize lightweight tags                      |
| `InvitationService`                 | one-way invitation token generation/lookup and notification       |
| `OutboundUrlGuard`                  | webhook scheme/DNS/private-range checks                           |
| `DocumentPdfService`                | inline authenticated local images before DOMPDF                   |
| `UserDataExportService`             | privacy-oriented per-account export without secrets               |
| `SsoManager`                        | resolve and validate configured driver adapters                   |

Keep cross-controller invariants in services. Keep presentation shaping in controllers/resources as appropriate. A new mutation that should be auditable must call `ActivityRecorder` with the correct organization/project/subject and actor type.

## Mutation pattern

A typical protected browser mutation follows:

```text
route model binding
  → load parent organization/project
  → AuthorizationService check
  → Request validation
  → relationship/business invariant checks
  → database transaction when multiple writes must agree
  → domain writes + sanitized content
  → revision/mention/notification synchronization
  → ActivityRecorder
  → response with required relations
```

External API mutation follows the same domain steps but uses `authorize(request, scope, org)` and records an API actor/`source: api` metadata.

## Eloquent conventions

- Principal domain models use `HasUlids`.
- Membership/assignment pivots generally use numeric surrogate IDs plus composite unique constraints.
- Soft deletes preserve organizations, projects, boards, tickets, ticket comments, documents, and document comments.
- Casts handle JSON arrays, datetimes, booleans, hashed passwords, and encrypted TOTP/webhook secrets.
- Sensitive model properties are hidden from serialization.
- Relationships constrain access queries; never trust a client-supplied parent/child pair without verifying it.

## Transactions and locking

Use a database transaction when a state transition spans related rows:

- organization creation plus first owner;
- project creation plus default phases;
- ticket number allocation and relationship setup;
- movement plus movement timeline entry;
- board deletion plus ticket migration;
- graph imports.

Ticket creation locks the project row before calculating `max(number)+1`. Reorder operations normalize positions transactionally. The database queue’s `after_commit` prevents jobs from observing uncommitted rows.

## Audit and side effects

`ActivityRecorder::record()` inserts the audit row, derives a subject label, finds active endpoints in scope, stores one immutable-ish payload snapshot in each delivery, and dispatches a job.

Because webhook work is queued after the audit write, domain HTTP requests do not wait for remote services. Email notifications are also queued through Laravel Notifications. Failures should not roll back completed domain work unless the domain write itself failed.

When adding an event:

1. choose a stable dot-separated name;
2. record enough before/after/metadata for consumers;
3. add it to the webhook selector catalog when users should select it explicitly;
4. add docs/reference/OpenAPI where applicable;
5. test actor, scope, persistence, and signature flow.

## Rich text boundary

Never store client HTML without `HtmlSanitizer`, including imports and external API. Validate mention IDs after sanitization and before syncing notification relationships. A sanitizer change must include unit tests for dangerous wrappers, URL schemes, attributes, mentions, and table spans.

## Error conventions

- `401` for external credential authentication.
- `403` for authenticated but unauthorized operations.
- `404` for missing route-bound/resource-constrained relationships.
- `409` for incompatible account-security state.
- `410` for unusable invitations.
- `419` for expired challenge/session/state.
- `422` for validation and business invariants such as WIP/last owner.
- `429` for throttle.

Return actionable messages but avoid disclosing cross-tenant existence or secret validation detail.

## Adding a backend feature

1. Define tenant/authorization invariants and soft-delete/audit requirements.
2. Add a forward migration with indexes and cross-driver behavior.
3. Add/update model casts, hidden fields, and relationships.
4. Create focused service logic where reused or security-sensitive.
5. Add browser and/or external route/controller validation.
6. Record event and side effects after consistent state exists.
7. Add feature/unit tests, including denial and tenant-isolation cases.
8. Update OpenAPI, route/reference docs, and user/admin/operator docs.
9. Run Pint/Prettier/tests/UI audit/MkDocs strict build.
