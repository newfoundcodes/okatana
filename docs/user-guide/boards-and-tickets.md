# Boards and tickets

The Board tab is the primary execution surface. It combines configurable workflow phases, independently paginated ticket rows, drag-and-drop movement, filters, creation, and the ticket detail drawer.

## Configure workflow phases

Organization owners/admins manage phases in **Project settings** or use **Create phase** from the project header.

Each phase has:

| Property  | Behavior                                                                        |
| --------- | ------------------------------------------------------------------------------- |
| Name      | visible workflow label, maximum 120 characters                                  |
| Slug      | generated once from name, made unique within project, and stable after renaming |
| Color     | stored project metadata, maximum 32 characters                                  |
| Position  | integer spacing in 1,000-step increments                                        |
| WIP limit | optional positive integer up to 100,000                                         |
| Done      | classifies contained tickets as complete                                        |
| Hidden    | removes phase from normal board display without deleting it                     |

Use the drag handle in project settings to reorder phases. It also supports Up/Down arrow keys and has an accessible label. The application validates that every submitted phase belongs to the project and writes the complete new order as an audit event.

### Delete a phase

An empty phase can be soft-deleted directly. If it contains tickets, choose a different phase in the same project as the move target. Okatana moves the tickets, creates movement entries for changed phases, then deletes the source phase. You cannot choose the phase itself as its destination.

### Design useful WIP limits

WIP limits apply to active tickets entering a phase through browser/API creation or movement. A move into the same phase does not consume another slot. Archived tickets do not count against the limit.

A limit is a hard validation rule, not a warning. When reached, creation or movement returns `422`. Resolve it by completing, moving, archiving, or otherwise removing active work from the phase before retrying.

## Create a ticket

Anyone with project access can create a ticket. Select **Create ticket** and provide:

- phase;
- required title, up to 500 characters;
- optional rich description, up to 200,000 characters before sanitization;
- priority: `lowest`, `low`, `normal`, `high`, `highest`, or `critical`;
- optional due date;
- zero or more allowed assignees;
- zero or more project labels;
- up to 20 project tags, each up to 50 characters.

The server locks the project row while allocating the next number, preventing concurrent requests from receiving the same project-local ticket number. Initial position appends to the chosen phase in 1,000-step increments. A ticket created in a done phase receives `completed_at` immediately.

Descriptions are sanitized on Laravel even though the browser editor already constrains formatting. Mention identities and all assignee/label relationships are validated against project scope before persistence.

## Read the board

Each visible phase owns independent client-side pagination. It begins at 10 rows and offers 10, 20, 50, or 100. Phase pagination has no local search field because project-level ticket filters control the complete result set.

Ticket cards present the reference, title, priority, key dates, labels/tags, and assignees as available. Open a card with pointer input or keyboard Enter/Space. Numeric values use tabular numerals.

## Move and reorder work

Drag a ticket to another phase or reorder it within one phase. On movement, Laravel:

1. rechecks project access;
2. verifies the destination belongs to the project;
3. enforces the destination WIP limit;
4. assigns an explicit position or appends after the current maximum;
5. sets completion when entering a done phase;
6. clears completion when leaving a done phase;
7. sets `started_at` on the first move into a non-done phase when not already present;
8. writes a non-editable `From → To` movement entry if the phase changed;
9. records `ticket.moved` and notifies ticket participants.

Reordering a list normalizes positions to 1,000, 2,000, 3,000, and so on and records `ticket.reordered`.

## Edit ticket details

The ticket drawer lets collaborators change title, description, priority, due date, assignees, labels, tags, and archive state. Relationship arrays replace their corresponding current selections when submitted.

Every effective content/property edit maintains immutable revision history:

- the original snapshot is created if it does not yet exist;
- a new version captures the resulting ticket state and editor;
- `edited_at` is set without generating an extra audit write;
- `ticket.updated` stores before/after audit data.

Open **Revision history** from the ticket overflow actions to inspect versions. Revisions cannot be modified or deleted through the model.

## Comment on a ticket

A normal comment may contain sanitized rich text and can be file-only in the browser flow when attachments are added. A stored text body is limited to 200,000 characters.

- The author or an organization owner/admin can edit/delete a normal comment.
- An edit creates immutable comment revisions and updates the parent ticket timestamp.
- A deletion is a soft delete and records `comment.deleted`.
- A movement entry cannot be edited, deleted, or receive an attachment.
- Comments notify existing ticket participants, excluding the commenter and direct-mention recipients to avoid duplicates.

## Attach files

A ticket or normal comment accepts 1–10 files in one request, up to 200 MB per file. Files are stored on Laravel’s private `local` disk under project/ticket or project/comment paths.

Downloads require current project access and return `private, no-store` plus `X-Content-Type-Options: nosniff`. The uploader or an organization owner/admin can remove an attachment; file deletion and record deletion happen together after the audit event is recorded.

The file size limit must agree with PHP/web-server upload limits. The included container sets compatible values through `docker/php-upload.ini`.

## Archive, restore, and delete

**Archive** sets `archived_at` and hides the ticket from default results. Enable the archived filter to find and restore it. Archiving retains the ticket, relations, comments, attachments, revisions, and history.

**Delete** performs a soft delete after writing `ticket.deleted`. The current UI has no ticket undelete operation. Use deletion only when the work should leave ordinary application views; use archive for reversible lifecycle management.

## Completion semantics

Completion comes from the phase, not a separate checkbox:

- entering any `is_done=true` phase sets `completed_at` to the move time;
- moving between done phases refreshes `completed_at` under the current controller behavior;
- leaving a done phase clears `completed_at`;
- analytics counts tickets in done phases as done;
- overdue excludes tickets whose current phase is done.

## Recommended workflow practices

- Mark only phases that genuinely represent completed work as done.
- Keep project keys short, stable, and recognizable.
- Use WIP limits on constrained work stages, not every column by default.
- Use labels for governed reporting dimensions and tags for emergent vocabulary.
- Archive resolved historical work rather than deleting it.
- Use comments for decisions and attachments for private files; use Documents for durable long-form knowledge.
- Review revision history for content changes and audit history for operational accountability.

Continue with [Rich text and files](rich-text-and-files.md) or learn how [filters, analytics, and Gantt](filters-analytics-gantt.md) interpret these records.
