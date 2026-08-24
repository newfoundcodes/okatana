# Core concepts

Okatana’s concepts are intentionally few, but their boundaries matter. Understanding ownership, access, work state, and evidence before adding users prevents most administration mistakes.

## Identity and tenancy

### User

A user is an installation-wide account identified by a ULID and unique email address. The account holds a display name, password when locally managed, email verification state, optional retained TOTP state, avatar, and notification preferences.

A user is not automatically a member of any organization. One account can hold different roles in different organizations and explicit access to different projects.

### Organization

An organization is the tenancy and administration boundary. It owns projects, membership, invitations, documents, webhook endpoints, API credentials, transfers, and the organization-level audit timeline.

Organizations are soft-deleted. The application preserves their relational data and audit references rather than immediately destroying the graph.

### Organization membership

The membership pivot assigns exactly one role for a user in an organization:

- **Owner** — full administration and exclusive organization deletion authority.
- **Admin** — operational administration but cannot delete the organization solely by role.
- **User** — read-only organization administration surfaces and project work only where access exists.

Roles are organization-local. An owner in one organization can be an ordinary user in another.

## Work access

### Project

A project groups one workflow, member list, label directory, ticket number sequence, analytics view, and project-level history. Its short key forms human ticket references. Projects can be archived or soft-deleted.

### Project membership

An ordinary user needs a `project_members` relationship to access a project. Owners and admins inherit project access through their organization role and do not need explicit rows. Assignable people are the union of explicit project members and organization owners/admins.

Removing a user from an organization also removes that user’s project memberships in the organization.

### Board or phase

The persistence model calls each workflow column a **board**; the interface often calls it a **phase**. It has a project-local stable slug, visual color metadata, position, optional WIP limit, done marker, and hidden marker.

- **Position** defines left-to-right order.
- **WIP limit** caps active tickets entering that phase.
- **Done** controls ticket completion timestamps and analytics classification.
- **Hidden** removes a phase from normal board display without destroying it.

Phase names are configuration, not code-level status enums.

## Work records

### Ticket

A ticket is the unit of executable project work. Its database ULID is stable across operations. Its human reference combines project key and a project-local sequential number, for example `OPS-42`.

Six priority values are supported, from `lowest` through `critical`. Dates serve different purposes:

| Field          | Meaning                                                                        |
| -------------- | ------------------------------------------------------------------------------ |
| `created_at`   | record creation time                                                           |
| `started_at`   | first move into a non-done workflow phase after creation, when not already set |
| `due_at`       | optional user-selected deadline                                                |
| `completed_at` | most recent entry into a done phase; cleared after moving back out             |
| `updated_at`   | latest ticket or participant activity touch                                    |
| `archived_at`  | reversible exclusion from normal active results                                |
| `deleted_at`   | soft-delete marker                                                             |

### Label and tag

Labels are explicitly managed, project-scoped metadata with a name and stored color. Admins create, edit, and delete labels; ticket collaborators select them.

Tags are lightweight project-scoped terms created through ticket editing. Names are normalized case-insensitively for reuse, and unused tags do not appear in the project tag filter. Up to 20 tags can be submitted for one ticket. Selecting multiple tag filters uses **match-all** semantics.

### Comment and movement entry

User comments contain sanitized rich text, an author snapshot, optional attachments, edit time, mentions, and immutable revisions. Their author or an organization owner/admin can edit or delete them.

Movement entries are `ticket_comments` with kind `movement`. They record a phase transition in the ticket timeline and cannot be edited or deleted by any role.

### Revision

Ticket and ticket-comment edits create versioned snapshots. The model refuses updates and deletes to revision rows. Revision history complements—but does not replace—the event-oriented audit log.

## Knowledge records

### Document

A document belongs to one organization and may optionally belong to one project in that same organization. It has an author, selected editors, title, caption, rich HTML body, reusable organization tags, publication state, archive state, favorites, comments, and soft-delete state.

Visibility combines scope and state:

| State                | Who can view?                                   |
| -------------------- | ----------------------------------------------- |
| Published and active | any user with organization/project scope access |
| Draft                | author and selected editors only                |
| Archived             | author and selected editors only                |
| Soft-deleted         | not returned through normal application reads   |

The author and editors can edit content and publication state. Author-only operations control project placement and editor membership. The author or an organization admin can delete. Organization admins can moderate document comments.

## Evidence and automation

### Audit log

An audit log is an append-only event with organization/project context, actor identity or credential snapshot, subject identity and label, changes, metadata, request IP, user agent, and occurrence time. It is protected at both Eloquent and database-trigger layers.

### API credential

An API credential belongs to exactly one organization and carries explicit scopes. Its token is formatted as `oka_<public-id>.<secret>`. The public ID is stored for lookup; only a SHA-256 hash of the secret is retained. The complete token appears once at creation.

### Webhook endpoint and delivery

A webhook endpoint belongs to an organization and may be narrowed to one project. It selects event names, has an encrypted HMAC secret, and can be deactivated. Each matching audit event creates a delivery record that a queue worker sends independently of the originating request.

### Transfer

A transfer records an import or export attempt, scope, status, summary, errors, and timing. Portable payloads use schema `okatana.export.v1`; account exports use `okatana.user-export.v1`. Imports regenerate destination IDs and remap relationships.

## Similar terms that are not interchangeable

| Term A            | Term B         | Difference                                                                                             |
| ----------------- | -------------- | ------------------------------------------------------------------------------------------------------ |
| organization role | project access | role grants administration capability; access grants entry to a particular project                     |
| board             | project        | a board is one workflow phase inside a project                                                         |
| label             | tag            | labels are admin-managed and colored; tags are lightweight and created through ticket/document editing |
| archive           | delete         | archive is a reversible work-state flag; delete is a soft-deleted record hidden from ordinary queries  |
| revision          | audit event    | revision stores a content snapshot; audit stores an operational event and actor/context                |
| notification      | webhook        | notification targets an Okatana user; webhook targets an external HTTP endpoint                        |
| session API       | external API   | `/app-api` uses browser session/CSRF; `/api/v1` uses scoped bearer credentials                         |

Next, follow the [quickstart](quickstart.md) or use the [permissions matrix](../reference/permissions.md) to design access before inviting users.
