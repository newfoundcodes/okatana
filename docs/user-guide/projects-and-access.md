# Projects and access

A project is both a unit of work and an access boundary. It owns workflow phases, a ticket-number sequence, explicit members, labels, tags, tickets, analytics, project documents, and project history.

## Project directory

Select **Projects** in the main sidebar to see all projects you can currently access across organizations. This directory is server-paginated and searchable by project name, key, ULID, description, or organization name. It starts at 10 rows and supports 10, 20, 50, or 100 rows per page.

Visibility is calculated on every request:

- organization owners/admins see all projects in that organization;
- ordinary users see only projects with an explicit membership row;
- archived projects remain records but may be presented differently by the client;
- soft-deleted projects are excluded by normal Eloquent queries.

You can also enter a project from an organization’s **Projects** tab or a recent **Quick Link**.

## Create a project

An organization owner or admin can select **Create project** and supply:

| Field                 | Rule                                                                                                           |
| --------------------- | -------------------------------------------------------------------------------------------------------------- |
| Name                  | required, maximum 180 characters                                                                               |
| Key                   | required, maximum 12 characters in the browser API; starts with a letter and then letters, digits, `_`, or `-` |
| Description           | optional, maximum 5,000 characters                                                                             |
| Create default phases | optional boolean, defaults to true                                                                             |

The key is uppercased and must be unique inside the organization, including soft-deleted projects. It becomes part of every human ticket reference, so choose a durable abbreviation.

### Default workflow

When enabled, project creation inserts:

| Position | Phase        | Stored slug    | Done? |
| -------: | ------------ | -------------- | ----- |
|    1,000 | Open         | `open`         | no    |
|    2,000 | Hold         | `hold`         | no    |
|    3,000 | In-progress  | `in_progress`  | no    |
|    4,000 | Pull Request | `pull_request` | no    |
|    5,000 | Deployed     | `deployed`     | yes   |

These are starting points, not hard-coded state-machine values. Admins can replace the entire sequence.

## Project page

All project collaborators see:

- **Board** — workflow phases and tickets.
- **Analytics** — one server-calculated filtered view.
- **Gantt** — date ranges for the shared ticket filter set.
- **Audit log** — append-only activity in this project.
- project and timeline JSON export actions.

Organization owners/admins also see **Project settings** and a **Create phase** action.

Ticket deep links follow `/app/projects/{project-ulid}/{ticket-number}`. The application resolves the number within that project, then performs the usual project-access check.

## Grant project access

Open **Project settings → Members** as an organization owner/admin. The eligible list comes from organization membership. Synchronizing access replaces the explicit project member set with the selected user IDs.

Important behavior:

- Owners/admins do not require explicit selection; access is implicit.
- An ordinary user must be selected.
- Assignees must be explicit project members or organization owners/admins.
- Removing organization membership detaches all of that user’s project memberships in the organization.
- Project membership alone cannot survive loss of organization membership as a valid authorization path.

For a one-step onboarding flow, an invitation can assign an organization role and optional project access simultaneously.

## Manage project identity and state

Owners/admins can change name, description, internal settings, and archived state. The project key is intentionally not accepted by the update endpoint because it anchors human ticket references.

Archiving sets `archived_at`; unarchiving clears it. Deleting performs a soft delete. Both update/delete operations record audit events.

!!! note "Archive before delete"

    Prefer archiving for completed or paused work. Archive is reversible through normal project update behavior. The current UI does not expose a project soft-delete restore workflow.

## Labels

Labels are managed in **Project settings**. A label has a project-unique name and optional stored color value. The directory shows ticket usage count and follows the shared search/pagination pattern.

Anyone with project access can apply existing labels to a ticket. Only organization owners/admins can create, update, or delete label definitions. A label cannot be assigned across project boundaries; Laravel verifies every submitted label ID against the ticket’s project.

## Tags

Ticket tags are created and reused through the ticket editor rather than a separate administration page. Names are trimmed, case-normalized for uniqueness, and limited to 50 characters. The project page returns only tags that are in current use, including counts, so obsolete unused terms do not clutter filters.

Tags differ from labels:

- use **labels** for controlled categories and stable, admin-managed metadata;
- use **tags** for lightweight vocabulary that collaborators can introduce while working.

## Copyable identifiers

Organization and project tables expose copy actions for ULIDs. Use those values for external API calls, import/export diagnostics, and support work. Use the project key plus ticket number for human communication, but use ULIDs at API relationship boundaries unless an endpoint explicitly accepts a board slug.

## Access troubleshooting

1. Confirm the user still belongs to the organization.
2. Confirm the organization role. Owner/admin access is implicit.
3. For an ordinary user, confirm selection under Project settings.
4. If assigning a ticket fails, confirm the intended assignee has project access.
5. If a project document is absent, check both project access and document publication/editor state.
6. Search the organization audit log for `project.members_changed` or `organization.member_removed`.

Next, configure [Boards and tickets](boards-and-tickets.md), or read [Invitations and membership](../administration/invitations.md) for administrator procedures.
