# Data model

Okatana stores domain, framework, queue, and integration state in the selected SQL database. Principal domain identifiers are ULIDs; most pivot tables use auto-incrementing numeric keys plus composite uniqueness.

## Domain topology

```text
User
 ├─ OrganizationMember(role) ─ Organization
 │                              ├─ Project
 │                              │   ├─ ProjectMember ─ User
 │                              │   ├─ Board
 │                              │   ├─ Label
 │                              │   ├─ Tag
 │                              │   ├─ Ticket
 │                              │   │   ├─ assignees / labels / tags / mentions
 │                              │   │   ├─ Attachment
 │                              │   │   ├─ TicketRevision
 │                              │   │   └─ TicketComment
 │                              │   │       ├─ mentions / attachments
 │                              │   │       └─ TicketCommentRevision
 │                              │   └─ project Documents
 │                              ├─ organization Documents
 │                              ├─ Invitation
 │                              ├─ AuditLog
 │                              ├─ WebhookEndpoint ─ WebhookDelivery
 │                              ├─ ApiCredential
 │                              └─ DataTransfer
 └─ notifications / email verification security state
```

## Framework and identity tables

| Table                                | Purpose/key constraints                                                                           |
| ------------------------------------ | ------------------------------------------------------------------------------------------------- |
| `users`                              | ULID identity; unique email; password, avatar, preferences, email/TOTP state; encrypted TOTP cast |
| `password_reset_tokens`              | standard email-keyed reset storage; no reset routes currently exposed                             |
| `email_verification_codes`           | one row per user; hashed code, expiry, send time, attempt count                                   |
| `sessions`                           | database session payloads keyed by string session ID                                              |
| `notifications`                      | Laravel UUID notifications, read time, indexed per user/read/created                              |
| `cache`, `cache_locks`               | default database cache and locks                                                                  |
| `jobs`, `job_batches`, `failed_jobs` | default queue lifecycle                                                                           |

## Organization and project tables

| Table                  | Important fields/invariants                                                     |
| ---------------------- | ------------------------------------------------------------------------------- |
| `organizations`        | ULID; globally unique slug; JSON settings; soft delete                          |
| `organization_members` | unique organization/user; role string; role enforcement in application          |
| `projects`             | ULID; organization; key unique within organization; archive and soft delete     |
| `project_members`      | unique project/user explicit access                                             |
| `boards`               | ULID; project-local unique slug; position, color, WIP, done/hidden; soft delete |
| `labels`               | ULID; project-local unique name; color                                          |
| `tags`                 | ULID; project-local unique normalized name; display name                        |

Foreign keys generally cascade pivots/children when a parent is physically deleted. Application soft deletion avoids those cascades during normal user operations.

## Ticket collaboration tables

| Table                      | Purpose                                                                                                      |
| -------------------------- | ------------------------------------------------------------------------------------------------------------ |
| `tickets`                  | project/board, local number, rich description, priority/position/dates, creator/updater, archive/soft delete |
| `ticket_assignees`         | unique ticket/user                                                                                           |
| `ticket_labels`            | unique ticket/label                                                                                          |
| `ticket_tags`              | unique ticket/tag with reverse lookup index                                                                  |
| `ticket_mentions`          | unique ticket/user direct mention                                                                            |
| `ticket_comments`          | ULID, kind `comment`/`movement`, rich body, user/snapshot/edit time, soft delete                             |
| `ticket_comment_mentions`  | unique comment/user                                                                                          |
| `attachments`              | project plus ticket or comment relationship, uploader metadata, private disk/path/size                       |
| `ticket_revisions`         | immutable ticket version/snapshot/editor, unique ticket/version                                              |
| `ticket_comment_revisions` | immutable comment version/snapshot/editor, unique comment/version                                            |

`tickets` has unique `(project_id, number)` and indexes for board/position and priority/due queries. Board deletion is restricted by the ticket foreign key, so controllers move tickets before soft-deleting a populated board.

Attachments encode either `ticket_id` or `ticket_comment_id` by application convention. The database does not declare a check constraint requiring exactly one, so service/controller construction must preserve the invariant.

## Document tables

| Table                       | Purpose                                                                                                     |
| --------------------------- | ----------------------------------------------------------------------------------------------------------- |
| `documents`                 | organization, optional project, author, title/caption/body, status/published/archive/soft-delete timestamps |
| `document_editors`          | unique document/user plus adding user                                                                       |
| `document_favorites`        | unique document/user                                                                                        |
| `document_comments`         | ULID rich comments with snapshot/edit/soft-delete                                                           |
| `document_tags`             | organization-scoped display and normalized names                                                            |
| `document_tag_assignments`  | unique document/tag                                                                                         |
| `document_mentions`         | unique document/user                                                                                        |
| `document_comment_mentions` | unique document-comment/user                                                                                |

The database permits a project ID from another organization at the foreign-key level; `DocumentController`/external API constrain the project through the selected organization. Preserve that validation in all new write paths and import code.

## Audit and integration tables

| Table                | Purpose                                                                                                 |
| -------------------- | ------------------------------------------------------------------------------------------------------- |
| `audit_logs`         | append-only organization/project event, actor/subject snapshots, JSON changes/metadata, request context |
| `webhook_endpoints`  | organization/optional project, encrypted secret, selected events, active/rotation state                 |
| `webhook_deliveries` | stored payload, status/attempts/response/error/times per endpoint                                       |
| `api_credentials`    | organization, public ID, SHA-256 secret hash, JSON scopes, creator/use/expiry/revoke times              |
| `invitations`        | organization/optional project, email/role, one-way token hash, lifecycle times                          |
| `data_transfers`     | import/export direction/scope/status/summary/errors/timing                                              |

Audit identifiers for organization/project are intentionally plain indexed ULIDs rather than foreign keys. This preserves history across domain deletion. Update/delete triggers provide database-level immutability.

## Identifier and secret classes

| Value                   | Storage/behavior                                                              |
| ----------------------- | ----------------------------------------------------------------------------- |
| Domain primary keys     | generated ULIDs                                                               |
| Ticket human ID         | project key + project-local integer                                           |
| Invitation token        | plaintext delivered once; SHA-256 hash stored                                 |
| API token               | public ID stored + secret SHA-256 stored; full token once                     |
| Webhook secret          | encrypted at rest because signing requires recovery                           |
| TOTP secret             | encrypted cast                                                                |
| Email verification code | Laravel password hash; plaintext encrypted inside queued notification payload |
| Password                | Laravel hashed cast                                                           |

## Delete semantics

Soft-deleted: organizations, projects, boards, tickets, ticket comments, documents, document comments.

Immutable: audit logs, ticket revisions, ticket comment revisions (model-level for revisions; model plus database for audit).

Hard-deleted during normal actions: many pivots/memberships, attachments after file removal, verification code after success. Integration revoke normally timestamps/deactivates rather than removing records.

## Indexing and query patterns

Indexes support:

- project workflow ordering;
- project priority/due filtering;
- ticket comment chronology;
- organization/project audit chronology and subject lookup;
- endpoint active/delivery status history;
- organization credential revocation lookup;
- notification unread chronology;
- mention recipient chronology;
- normalized tag reuse and usage lookup;
- document organization/status/publish, project/status, author/update.

When adding filters, examine query plans across SQLite, MySQL, and PostgreSQL. Case-insensitive search currently uses `LOWER(...) LIKE` in several server directories, which may need functional/search indexing at larger scale.

## Migration guidance

- Write explicit forward/backward schema behavior for all supported drivers.
- Do not mutate existing audit rows during migration.
- Consider active and soft-deleted rows for new unique constraints.
- Backfill before making a column required.
- Preserve ULID column types across foreign keys.
- Add composite unique constraints to new pivots.
- Test SQLite in the standard suite and MySQL/PostgreSQL in CI/acceptance when changing driver-specific SQL.
- Update this model reference, exports/imports, personal export, and OpenAPI when persistence changes affect contracts.
