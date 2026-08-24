# Organizations

Organizations are the top-level boundary for people, projects, documents, audit history, data transfer, and integrations. A user may belong to multiple organizations, and every membership carries an organization-local role.

## Organization directory

Select **Organizations** in the workspace sidebar. The page lists memberships alphabetically and displays each organization’s role and project count. Search matches organization name, slug, description, identifier, or role. Client-side pagination starts at 10 rows and offers 10, 20, 50, or 100 rows.

The dashboard also shows up to five **Quick Links** based on your most recent project audit activity. Only projects you can currently access are eligible; stale activity does not leak a project after access is removed.

Each organization’s overflow menu links to Projects, Members, Audit log, Integrations, Import/export, and Settings. Administrative destinations remain disabled for ordinary users and explain the missing permission.

## Create an organization

Any fully activated account can create an organization.

1. Select **Create organization**.
2. Enter a name and optional description.
3. Optionally enter an `alpha_dash` slug: letters, digits, dashes, and underscores.
4. Submit.

Okatana derives a URL-safe slug from the provided slug or name. It checks active and soft-deleted organizations for collisions and adds `-2`, `-3`, and so on. The creator becomes the first owner in the same database transaction.

### Naming guidance

- Use the real team or business-unit name in **Name**.
- Treat the slug as a durable machine-readable handle even though the current SPA navigates by ULID.
- Put operational purpose—not secrets—in the description.
- Do not plan to reuse a deleted organization slug; soft-deleted rows retain it.

## Organization page

Every member can open three tabs:

- **Projects** — projects visible under the caller’s access rules.
- **Members** — current members; only admins see pending invitations.
- **Audit log** — organization history, subject to visibility filtering for document drafts.

Owners and admins additionally see:

- **Integrations** — webhook endpoints and API credentials.
- **Import/export** — organization/project snapshot and timeline transfer.
- **Settings** — organization name, description, and deletion controls.

The header exposes **Create project** for owners/admins. Project counts and member counts help orient the administrator without turning the page into an analytics dashboard.

## Search and pagination conventions

Organization, member, project, integration, and audit directories use one interaction grammar:

1. Type in the search field; a changed query resets to page 1.
2. Use Previous/Next only when results exceed the page size.
3. Choose 10, 20, 50, or 100 rows per page.
4. Read the range indicator to understand how much of the filtered set is shown.

Audit search and pagination run on Laravel because logs may be large. Most already-loaded organization management collections filter and paginate in React.

## Change organization settings

Owners and admins can update the display name, description, and internal settings object. The browser exposes name and description. Every change records `organization.updated` with before/after data.

The slug is not editable through the current update API. This avoids breaking external assumptions about an established organization handle.

## Delete an organization

Only an owner can use **Delete organization**. The interface requires an Okatana confirmation dialog. Deletion is a soft delete and records `organization.deleted` first with `soft_delete: true` metadata.

!!! danger "Deletion is not a recovery workflow"

    The current user interface has no organization restore command. Create a verified export and database backup before deletion. Soft deletion preserves records for database-level recovery but does not promise a supported self-service restore.

Audit rows remain immutable. Because child projects and related domain rows are retained unless a database relationship explicitly cascades during a hard delete, avoid manual hard deletion unless you have reviewed the full schema and retention obligations.

## Organization-wide versus project-scoped resources

| Resource       | Organization-wide behavior                              | Project-scoped behavior                                  |
| -------------- | ------------------------------------------------------- | -------------------------------------------------------- |
| Member         | role applies across organization administration         | explicit project row grants ordinary-user access         |
| Document       | readable by all organization members when published     | also requires project access                             |
| Webhook        | receives matching events across organization            | receives matching events only for selected project       |
| Audit view     | shows organization history caller may see               | narrows history to one project                           |
| API credential | always belongs to one organization                      | can call project resources only inside that organization |
| Export         | contains all active projects and organization-wide data | contains one project graph                               |

## Recommended setup sequence

For a new production organization:

1. Create the organization and add a clear description.
2. Invite at least one additional owner.
3. Invite administrators responsible for projects or integrations.
4. Create projects and configure their workflows.
5. Grant ordinary users only the projects they require.
6. Verify mail and queue operation before relying on invitations/notifications.
7. Create narrowly scoped API credentials and webhooks only after ownership is stable.
8. Schedule organization export and database/storage backups.

Continue with [Projects and access](projects-and-access.md) or review the exact [permissions matrix](../reference/permissions.md).
