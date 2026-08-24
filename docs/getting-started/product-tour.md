# Product tour

Okatana is a self-hosted workspace for teams that need controlled project access, flexible Kanban workflows, a shared knowledge base, and durable operational history without assembling several infrastructure services.

This page explains the product as a whole. If an installation is already available and you want to do real work immediately, continue with the [five-minute quickstart](quickstart.md).

## What Okatana manages

The workspace has four connected layers:

1. **Accounts** identify people and carry sign-in, security, avatar, and notification preferences.
2. **Organizations** are the membership and administration boundary. Roles, invitations, API credentials, webhook endpoints, audit history, and organization-wide documents belong here.
3. **Projects** are the work-access boundary. They contain members, workflow phases, labels, tags, tickets, analytics, project documents, and a project audit view.
4. **Tickets and documents** are the collaboration layer. Both support rich text and mentions; tickets also carry workflow state, assignment, files, revisions, and movement history.

The central access rule is simple: an ordinary organization user sees only projects to which they have been explicitly assigned. Organization owners and admins implicitly see every project in that organization.

## A typical lifecycle

<div class="okatana-flow">
  <div><strong>1 · Establish</strong><span>Create an organization and invite owners, admins, and users.</span></div>
  <div><strong>2 · Shape</strong><span>Create a project, grant access, and configure phases and labels.</span></div>
  <div><strong>3 · Execute</strong><span>Create, assign, discuss, move, analyze, and complete tickets.</span></div>
  <div><strong>4 · Preserve</strong><span>Publish documents, review audit history, integrate, and export.</span></div>
</div>

### Establish the organization

Any authenticated user can create an organization and becomes its first owner. Owners and admins manage membership and most organization settings; only an owner can delete the organization. The application prevents removal or demotion of the last owner.

Invitations can carry both an organization role and optional access to one project. A recipient follows a single-use link, confirms the expected email identity, sets a strong password, and completes any required authenticator setup.

### Shape project work

An admin creates a project with a short key such as `CORE`. Unless disabled at creation, the project starts with five editable phases: Open, Hold, In-progress, Pull Request, and Deployed. These names have no hidden business meaning. Admins can rename, reorder, hide, add, or delete phases, identify which phases count as done, and set work-in-progress limits.

Project access is explicit for ordinary users. This keeps a large organization from becoming one undifferentiated workspace and lets sensitive projects remain scoped to the people doing the work.

### Execute with tickets

Every ticket receives a project-local number. Ticket 17 in the `CORE` project is shown as `CORE-17`, while its internal identity remains a ULID. A ticket can carry:

- a title and sanitized rich description;
- one of six priorities;
- a due date and derived start/completion timestamps;
- multiple assignees, labels, and free-form reusable tags;
- private attachments;
- comments, mentions, and immutable revision history;
- archived and soft-deleted state.

Moving a ticket updates its phase and timestamps, enforces the destination WIP limit, writes an immutable movement entry into the comment timeline, records an audit event, and may notify participants.

### Preserve knowledge and evidence

The Documents workspace contains organization-wide or project-scoped articles. Articles can be drafts or published, can name collaborative editors, support reusable tags, comments, mentions, favorites, PDF export, and archiving, and participate in the audit/webhook pipeline.

The audit log is deliberately stronger than a normal activity feed. Application code refuses changes to an audit row, and database triggers reject direct SQL updates and deletes on SQLite, MySQL, and PostgreSQL. Exports carry relevant history so data can move between Okatana installations without assuming identifiers are reusable.

## What users see

The authenticated shell has three primary destinations:

| Destination       | Purpose                                                                                                                      |
| ----------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| **Organizations** | Organization directory, recent project shortcuts, members, settings, integrations, transfers, and organization audit history |
| **Projects**      | Searchable directory of all currently accessible projects across organizations                                               |
| **Documents**     | Articles, favorites, document audit history, and document-specific integrations                                              |

The account block at the bottom of the sidebar exposes Profile, Settings, and Log-out. The quiet top bar contains notifications. Project pages add Board, Analytics, Gantt, Audit log, and—for owners/admins—Project settings tabs.

## Deployment shape

Okatana is one Laravel application, not a distributed platform:

```text
Browser
  └─ HTTPS → Apache + Laravel
                 ├─ compiled React application
                 ├─ session API under /app-api
                 ├─ bearer API under /api/v1
                 ├─ MkDocs manual under /docs
                 ├─ Scalar API console under /docs/api
                 ├─ mail and notification jobs
                 └─ signed webhook delivery jobs
                              ↓
                 SQLite | MySQL | PostgreSQL
```

Node.js is a build dependency, not a runtime server. The default session, cache, and queue drivers use SQL tables. Docker Compose runs one web container and one queue worker against the same persistent database and storage volumes.

## What is deliberately absent

- No required Redis, message broker, or search cluster.
- No separate frontend runtime service.
- No fixed workflow vocabulary beyond configurable phase properties.
- No global ordinary-user access to every organization project.
- No recoverable API credential secret after initial creation.
- No browser-only authorization decision; Laravel checks every protected operation.
- No editable audit record.

## Choose an evaluation path

| Goal                                   | Next page                                                           |
| -------------------------------------- | ------------------------------------------------------------------- |
| Try a complete workflow                | [Five-minute quickstart](quickstart.md)                             |
| Learn the domain before configuring it | [Core concepts](concepts.md)                                        |
| Install a developer environment        | [Install locally](../operations/installation.md)                    |
| Deploy containers                      | [Deploy with Docker](../operations/docker.md)                       |
| Review authorization                   | [Roles and permissions](../administration/roles-and-permissions.md) |
| Assess the implementation              | [Architecture](../ARCHITECTURE.md)                                  |
