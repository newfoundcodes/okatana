# Okatana Architecture

## System boundary

Okatana is one Laravel application. Laravel owns HTTP routing, authentication, authorization, validation, persistence, mail, queues, integration calls, audit recording, and data transfer. React is compiled into static browser assets and calls Laravel over same-origin JSON endpoints.

```text
Browser
  |
  | HTTPS
  v
Laravel / Apache
  |-- React static assets
  |-- /docs/* generated MkDocs manual
  |-- /docs/api Scalar API reference
  |-- /app-api/* session + CSRF API
  |-- /api/v1/* bearer integration API
  |-- queued mail
  |-- queued webhook HTTP delivery
  |
  v
SQLite | MySQL | PostgreSQL
```

There is no required application server beside Laravel and no required data system beside the selected SQL database.

## Domain hierarchy

```text
User
  |
  +-- OrganizationMember(role: owner | admin | user)
        |
        v
    Organization
        |
        +-- Project
        |     |
        |     +-- ProjectMember
        |     +-- Board
        |     +-- Label
        |     +-- Ticket
        |           +-- assignees
        |           +-- labels
        |           +-- TicketComment
        |
        +-- Invitation
        +-- Document
        |     +-- editors
        |     +-- DocumentComment
        |     +-- favorites
        +-- AuditLog
        +-- WebhookEndpoint
        |     +-- WebhookDelivery
        +-- ApiCredential
        +-- DataTransfer
```

## Authorization invariants

1. An organization always has at least one owner through application operations.
2. `owner` and `admin` can modify organization state.
3. Only `owner` can delete an organization.
4. A normal `user` cannot modify organization state.
5. An ordinary user sees only projects in `project_members`.
6. An owner/admin has implicit access to all projects in the organization.
7. Only owner/admin can create or change project boards and project access.
8. Anyone with project access can create, edit, move, reorder, archive, or delete tickets.
9. Anyone with project access can post a comment.
10. Comment edit/delete is limited to the comment owner or an organization owner/admin.
11. API and webhook credentials can be managed only by organization owners/admins.
12. Audit rows cannot be updated or deleted.

These checks live in `AuthorizationService` and are repeated in the relevant controller entry points. Frontend visibility is only a convenience. Backend authorization is authoritative.

## Persistence rules

All principal domain identifiers use ULIDs. Human ticket identifiers use project key + project-local integer number.

Soft-deleted models:

- organizations
- projects
- boards
- tickets
- ticket comments

Append-only model:

- audit logs

One-way secrets:

- invitation token -> SHA-256
- external API credential secret -> SHA-256

Reversible encrypted secret:

- outgoing webhook HMAC secret -> Laravel encrypted cast

## Ticket consistency

Ticket creation locks the project row before selecting the next project-local ticket number. This serializes concurrent number allocation for a project.

Ticket movement validates that the destination board belongs to the ticket's project. If a WIP limit is set, moves into the board are rejected when the active count reaches the limit.

When a ticket enters a done board, `completed_at` is set. Moving from a done board to a non-done board clears `completed_at`.

## Rich text trust boundary

The browser WYSIWYG editor is not trusted. Both ticket descriptions and comment bodies pass through `HtmlSanitizer` before persistence.

Imports are also untrusted input. Imported ticket descriptions and comment bodies pass through the same sanitizer.

## Audit event flow

```text
Controller mutates business state
  -> ActivityRecorder::record(...)
       -> INSERT audit_logs
       -> find active matching webhook endpoints
       -> INSERT webhook_deliveries
       -> dispatch DeliverWebhookJob
```

The database trigger blocks direct update/delete SQL against `audit_logs`, including SQL issued outside Eloquent.

## Webhook delivery flow

1. The controller validates the URL.
2. `OutboundUrlGuard` rejects disallowed destinations.
3. The HMAC secret is encrypted at rest through the model cast.
4. `ActivityRecorder` creates a delivery row for subscribed events.
5. `DeliverWebhookJob` re-checks the URL before the network request.
6. The job JSON-encodes the stored payload once.
7. It signs `timestamp + "." + rawBody` with HMAC-SHA256.
8. It sends event, delivery ID, timestamp, and signature headers.
9. It stores response status and a bounded response excerpt.
10. Failed jobs use queue retry/backoff and retain error state.

## External API authentication

The token has a public lookup part and a high-entropy secret part.

```text
oka_<public-id>.<secret>
```

Middleware:

1. Splits the bearer token.
2. Loads the credential by public ID.
3. Rejects revoked/expired credentials.
4. Hashes the presented secret.
5. Uses `hash_equals` against the stored SHA-256 hash.
6. Updates `last_used_at` without audit noise.
7. Attaches the credential to the request.

Each controller method then checks organization ownership and the exact scope.

## Import/export mapping

The JSON transfer format never assumes database IDs can be reused.

During import:

```text
source board ID -> destination board ULID
source label ID -> destination label ULID
source ticket ID -> destination ticket ULID
```

Ticket relations and audit subject references are rewritten through these maps where possible.

## Account activation and configurable TOTP security

Account activation and authenticator enforcement are separate policies. New local accounts always carry an email-confirmation requirement, while TOTP enforcement is read from `config('okatana.security.two_factor_required')`, which is backed by `OKATANA_REQUIRE_TWO_FACTOR`.

With TOTP enforcement enabled:

```text
registered
  -> email confirmation required
  -> authenticator enrollment required
  -> workspace enabled
```

With TOTP enforcement disabled:

```text
registered
  -> email confirmation required
  -> workspace enabled
```

`security_setup_required_at` remains in the schema and marks accounts whose email ownership must be confirmed. The TOTP schema also remains permanently present. The deployment flag changes **enforcement only**; it never drops columns, clears secrets, or rewrites users. This lets an installation move from optional/no TOTP to required TOTP and back again without a migration rollback.

`AccountSecurityService::state()` combines persistent user state with the current deployment policy. When TOTP becomes required, any authenticated user without `two_factor_confirmed_at` becomes incomplete immediately and `EnsureAccountSecurity` blocks protected workspace endpoints until enrollment finishes. When the flag is disabled, TOTP-related incompleteness is ignored while stored TOTP data remains available for a future re-enable.

Email verification codes live in `email_verification_codes` with one active row per user. Only a password hash of the code is stored there. The queued email notification carries an encrypted form of the plaintext code and decrypts it only when rendering the mail, avoiding a usable code in the database queue payload. Codes expire, track failed attempts, and are replaced on resend.

TOTP state is stored on the user regardless of the current enforcement setting:

```text
two_factor_secret            encrypted text
two_factor_confirmed_at      activation timestamp
two_factor_last_used_step    replay guard for later logins
```

`AccountSecurityService` owns code issuance/verification, policy evaluation, TOTP secret creation, QR generation, enrollment confirmation, and later login-code verification. QR generation is local PNG output through BaconQrCode's GD renderer, so the TOTP secret is never submitted to a remote QR service. The container installs PHP GD and renders the QR at its native raster size to avoid browser SVG scaling artifacts.

The SPA may render activation steps during signup, invitation acceptance, after a policy change, or after an OAuth/SSO callback, but API authorization does not depend on React state: `EnsureAccountSecurity` evaluates the current configuration for every protected workspace request.

## React application

The SPA uses pathname routing without an additional router dependency.

Main pages:

- Authentication
- Invitation acceptance
- Organization dashboard
- Organization detail/administration
- Project Kanban/detail
- Documents knowledge base/editor/article view

The project page owns:

- filters
- board visibility
- native drag-and-drop
- ticket create/edit drawer
- WYSIWYG ticket body
- comments
- analytics
- timeline
- member access configuration
- label configuration
- board configuration
- import/export links

## Scaling notes

The default SQL-backed queue/cache/session configuration minimizes dependencies. For larger installations, standard Laravel configuration can move queue/cache/session workloads to infrastructure selected by the operator. That is an operational substitution; Okatana domain code does not depend on a Redis API.

The default queue connection is `database`. Docker Compose runs a dedicated queue worker alongside the web process, keeping mail, notification, webhook, and other queued work outside the HTTP request lifecycle while still using the selected SQL database. The queue worker can be scaled independently from the web service when needed.

The current ticket analytics endpoint is computed from transactional tables. If data volume becomes very large, introduce materialized counters or a reporting projection behind the same response contract instead of changing the UI contract.

## Failure modes

### Mail provider failure

Invitation remains in the database. The queued notification fails/retries through Laravel queue handling. Operators can inspect failed jobs and resend through a future administrative action.

### Webhook endpoint failure

Business writes do not wait for remote webhook delivery. The delivery row is queued after the audit event is inserted. HTTP errors are retried and visible in delivery history.

### Import failure

Organization/project graph creation runs in a database transaction. `DataTransfer` records success/failure around the operation.

### API secret loss

The secret cannot be recovered. Revoke the credential and create a replacement.

### Webhook secret loss

Rotate it. The new value is returned once by the rotate response and replaces the encrypted old value.

## Documents domain

Documents are owned by an organization and may optionally reference one project in that organization. `documents` stores the article body, publication state, archive state, and soft-delete timestamp. `document_editors` grants collaborative edit access, `document_favorites` records per-user saved articles, and `document_comments` stores article discussion with soft deletion. `document_tags` stores reusable organization-scoped article tags and `document_tag_assignments` provides the many-to-many document relation. Tag uniqueness uses `(organization_id, normalized_name)`, so display casing is preserved while reuse is case-insensitive.

`DocumentAccessService` is the authorization boundary. Organization membership is always required. Project-scoped documents additionally require project access. Published, non-archived documents are readable by users in scope; drafts and archived documents are readable only by their author or selected editors. The author and selected editors can edit article content/status, while author-only operations control project placement and editor membership. Organization admins can also soft-delete documents and moderate document comments.

Document activity is recorded through the same immutable `audit_logs` system and emits the same queued HMAC webhook pipeline. Document audit queries are restricted to documents the requesting user can currently view so a draft title is not exposed through the Documents audit tab. Integration scopes are `documents:read`, `documents:write`, and `document_comments:write`.

PDF output uses `spatie/laravel-pdf` with DOMPDF. A dedicated Blade template intentionally uses DOMPDF-compatible CSS rather than the React/Tailwind application layout. `DocumentPdfService` converts authenticated local editor-image URLs to data URIs before rendering.

Organization and project transfer payloads include documents. Organization-wide documents are included at organization scope; project documents are included inside project data. Editor identities are exported by email and re-resolved against users who have access to the destination scope.

### Project label management

Project labels remain project-scoped managed metadata. Project settings receives the full label collection because ticket forms and filters also require it, then presents the management directory through the shared client-side search and pagination primitive. `tickets_count` is loaded with the labels so administrators can see current usage before editing or deleting a label.

## Document mention and comment-notification architecture

Document rich text uses the same sanitized `data-mention-user-id` token model as ticket rich text. `MentionService` validates IDs against the document scope and synchronizes dedicated many-to-many pivots (`document_mentions`, `document_comment_mentions`). Organization-wide articles allow organization members; project-scoped articles allow project members plus organization owners/admins. Draft article mentions are synchronized only for users who can actually read the draft (author and selected editors). Self-mentions are excluded from notification fan-out for both ticket and document content.

Article comment fan-out is handled by `NotificationService::documentParticipants()`: the author and selected editors are deduplicated, the commenter is removed, and explicit mention recipients are removed from the generic recipient set to prevent duplicate alerts. `users.notify_document_comments` is a general article-comment notification opt-out; direct mentions remain separate and use the existing mention-email preference.

The document editor and the shared WYSIWYG are deliberately separated in form ownership. The page owns the document save form; WYSIWYG link/image/table insertion surfaces are contextual `role="dialog"` regions with non-submit buttons. This prevents rich-text insertion actions from submitting the outer article form.
