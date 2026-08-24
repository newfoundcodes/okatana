# Events and scopes

This reference lists selectable webhook event names, additional emitted audit events, and external API scopes.

## Webhook selectors

An endpoint accepts one or more of these 46 selectors. `*` matches every event emitted through `ActivityRecorder`, including events not present as explicit selector options.

### Organization and access

```text
*
organization.created
organization.updated
organization.deleted
organization.member_role_changed
organization.member_removed
invitation.created
invitation.accepted
invitation.revoked
```

### Project workflow

```text
project.created
project.updated
project.deleted
project.members_changed
board.created
board.updated
board.reordered
board.deleted
label.created
label.updated
label.deleted
```

### Tickets and comments

```text
ticket.created
ticket.updated
ticket.moved
ticket.reordered
ticket.deleted
comment.created
comment.updated
comment.deleted
```

### Documents

```text
document.created
document.updated
document.published
document.drafted
document.archived
document.unarchived
document.deleted
document.comment.created
document.comment.updated
document.comment.deleted
```

### Integrations and transfer

```text
webhook.created
webhook.updated
webhook.secret_rotated
webhook.revoked
api_credential.created
api_credential.revoked
import.completed
export.completed
```

### Wildcard-only current events

The application also records events that are not currently in the explicit endpoint selector list:

```text
attachment.created
attachment.deleted
notification.sent
```

An endpoint subscribed to `*` receives these. An administrator cannot select them individually through the current validated catalog. When extending the system, keep emitted and selectable catalogs synchronized deliberately.

## Event actor and payload

Browser events normally use actor type `user`. External API controller calls currently record actor type string `api_key`, ID equal to credential model ULID, and name equal to credential name; some prose/UI may describe this generally as an API credential actor.

Webhook payload always includes event/audit ID, occurrence time, organization, optional project, actor object, subject object, changes, and metadata. Fields inside those structures may be null.

## External API scopes

| Scope                     | Grants                                                                 |
| ------------------------- | ---------------------------------------------------------------------- |
| `*`                       | every external v1 operation in credential organization                 |
| `organization:read`       | read organization metadata                                             |
| `projects:read`           | list/read projects and project members/labels/tags                     |
| `projects:write`          | create/update/archive/delete projects                                  |
| `boards:read`             | list project phases                                                    |
| `boards:write`            | create/update/reorder/delete phases                                    |
| `tickets:read`            | list/read tickets including comments/attachments metadata in detail    |
| `tickets:write`           | create/update/move/reorder/archive/delete tickets                      |
| `comments:write`          | add ticket comments                                                    |
| `analytics:read`          | read project analytics                                                 |
| `documents:read`          | list/read organization documents                                       |
| `documents:write`         | create/update/publish/draft/archive/delete documents and editor access |
| `document_comments:write` | add document comments                                                  |
| `notifications:write`     | send in-app organization notifications to members                      |

Credential organization isolation is always enforced in addition to scope.

## Event change semantics

Consumers should treat `changes` and `metadata` as event-specific, additive structures:

- updates often carry before/after snapshots;
- moves carry board IDs in changes and human phase names in metadata;
- deletes carry `soft_delete: true` when applicable;
- external API mutations often carry `source: api`;
- imported audit rows carry `imported: true` and source audit ID;
- comment events include parent ticket/document context in metadata.

Do not require exact key sets or use event payload as a substitute for fetching current state when the API provides a read endpoint.
