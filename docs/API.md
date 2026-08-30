# Okatana External API v1

This page is the route-by-route endpoint reference. Start with the [HTTP API guide](integrations/http-api.md) for credential handling, pagination, error strategy, and client examples. A live, executable Scalar reference is available at [`/docs/api`](/docs/api) when it is enabled by the deployment.

Base path:

```text
/api/v1
```

Authentication:

```http
Authorization: Bearer oka_<public-id>.<secret>
```

All credentials belong to one organization. A credential cannot access a resource from another organization even when it has `*` scope.

## Scopes

| Scope                     | Operations                                                       |
| ------------------------- | ---------------------------------------------------------------- |
| `*`                       | Every external API operation                                     |
| `organization:read`       | Read organization                                                |
| `projects:read`           | List/read projects                                               |
| `projects:write`          | Create, update, archive, and delete projects                     |
| `boards:read`             | List boards                                                      |
| `boards:write`            | Create, update, reorder, and delete boards                       |
| `tickets:read`            | List/read tickets                                                |
| `tickets:write`           | Create/update/move/delete tickets                                |
| `comments:write`          | Add ticket comments                                              |
| `analytics:read`          | Read project analytics                                           |
| `documents:read`          | List/read documents                                              |
| `documents:write`         | Create/update/publish/archive/delete documents and editor access |
| `document_comments:write` | Add document comments                                            |
| `notifications:write`     | Send organization notifications                                  |

## Errors

Common statuses:

- `401` missing, invalid, expired, or revoked API credential
- `403` credential belongs to another organization or lacks scope
- `404` resource does not exist
- `422` validation, relationship, or WIP-limit failure
- `429` rate limit reached

Laravel JSON validation responses include a message and field errors.

## Read organization

```http
GET /api/v1/organizations/{organization}
```

Scope: `organization:read`

## List projects

```http
GET /api/v1/organizations/{organization}/projects
```

Scope: `projects:read`

## Create project

```http
POST /api/v1/organizations/{organization}/projects
Content-Type: application/json
```

Scope: `projects:write`

```json
{
  "name": "Platform",
  "key": "PLAT",
  "description": "Platform engineering work"
}
```

The API creates the default Open, Hold, In-progress, Pull Request, and Deployed boards.

## Read project

```http
GET /api/v1/projects/{project}
```

Scope: `projects:read`

## Project members

```http
GET /api/v1/projects/{project}/members
```

Scope: `projects:read`

Returns explicit project users plus organization owners/admins that have implicit project access.

## Project labels

```http
GET /api/v1/projects/{project}/labels
```

Scope: `projects:read`

Use these identifiers when assigning labels to tickets.

## Project tags

```http
GET /api/v1/projects/{project}/tags
```

Scope: `projects:read`

Returns project tags that are currently used by at least one ticket, including ticket counts.

## Update project

```http
PATCH /api/v1/projects/{project}
```

Scope: `projects:write`

Supported fields: `name`, `description`, and `archived`.

## Delete project

```http
DELETE /api/v1/projects/{project}
```

Scope: `projects:write`. The operation is a soft delete.

## List boards

```http
GET /api/v1/projects/{project}/boards
```

Scope: `boards:read`

## Create board

```http
POST /api/v1/projects/{project}/boards
```

Scope: `boards:write`. Supports `name`, `color`, `wip_limit`, and `is_done`.

## Update board

```http
PATCH /api/v1/boards/{board}
```

Scope: `boards:write`. Supports `name`, `color`, `wip_limit`, `is_done`, and `is_hidden`.

## Reorder boards

```http
PUT /api/v1/projects/{project}/boards/reorder
```

Scope: `boards:write`. Send `board_ids` in the required order.

## Delete board

```http
DELETE /api/v1/boards/{board}
```

Scope: `boards:write`. If the board contains tickets, pass `move_to_board_id`.

## List tickets

```http
GET /api/v1/projects/{project}/tickets?board_id=...&q=deployment&per_page=50
```

Scope: `tickets:read`

Maximum `per_page` is 200.

## Create ticket

```http
POST /api/v1/projects/{project}/tickets
Content-Type: application/json
```

Scope: `tickets:write`

Select a board using `board_id` or `board_slug`. The API credential is recorded as the ticket creator and its name will appear in the UI instead of the user who provisioned it.

```json
{
  "board_slug": "open",
  "title": "Design deployment runbook",
  "description_html": "<p>Write the first version.</p>",
  "priority": "high",
  "due_at": "2026-09-01T09:00:00+08:00",
  "assignee_ids": [],
  "label_ids": []
}
```

## Reorder tickets in a board

```http
PUT /api/v1/projects/{project}/tickets/reorder
```

Scope: `tickets:write`. Send `board_id` and the ordered `ticket_ids` array.

## Read ticket

```http
GET /api/v1/tickets/{ticket}
```

Scope: `tickets:read`

## Update ticket

```http
PATCH /api/v1/tickets/{ticket}
Content-Type: application/json
```

Scope: `tickets:write`

```json
{
  "title": "Design and review deployment runbook",
  "priority": "highest",
  "archived": false
}
```

## Move ticket

```http
POST /api/v1/tickets/{ticket}/move
Content-Type: application/json
```

Scope: `tickets:write`

```json
{
  "board_id": "01...",
  "position": 2000
}
```

`position` is optional. Without it, Okatana appends the ticket to the destination board.

## Delete ticket

```http
DELETE /api/v1/tickets/{ticket}
```

Scope: `tickets:write`

This is a soft delete.

## Add comment

```http
POST /api/v1/tickets/{ticket}/comments
Content-Type: application/json
```

Scope: `comments:write`

```json
{
  "body_html": "<p>Deployment validation is complete.</p>"
}
```

The API credential is recorded as the audit actor. The comment keeps an API-author snapshot because it does not impersonate a browser user.

## Project analytics

```http
GET /api/v1/projects/{project}/analytics
```

Scope: `analytics:read`

Response includes total, done, open, overdue, completion percentage, and counts by board.

## Send organization notification

```http
POST /api/v1/organizations/{organization}/notifications
Content-Type: application/json
```

Scope: `notifications:write`

```json
{
  "user_ids": ["01..."],
  "title": "Deployment window changed",
  "body": "Production deployment starts at 18:00.",
  "url": "/app/projects/01..."
}
```

Recipients must belong to the credential organization. `url`, when supplied, must be an internal `/app` path.

## Documents

Document API credentials are organization-scoped. A document may be organization-wide or linked to a project in the same organization.

### List documents

```http
GET /api/v1/organizations/{organization}/documents?q=runbook&project_id=01...&status=published&tags=runbook,production&per_page=50
```

Scope: `documents:read`

### Create document

```http
POST /api/v1/organizations/{organization}/documents
Content-Type: application/json
```

Scope: `documents:write`

```json
{
  "project_id": "01...",
  "title": "Production runbook",
  "caption": "How to deploy the production service",
  "content_html": "<h2>Deployment</h2><p>...</p>",
  "status": "draft",
  "editor_ids": ["01..."],
  "tag_names": ["runbook", "production"]
}
```

`project_id` is optional. `status` is `draft` or `published`. Every editor must have access to the selected organization/project scope. `tag_names` accepts up to 20 organization-scoped article tags; tag names are matched case-insensitively and reused across documents in the same organization. The API credential is recorded as the document author and its name will appear in the UI instead of the user who provisioned it.

### Read document

```http
GET /api/v1/documents/{document}
```

Scope: `documents:read`

### Update document

```http
PATCH /api/v1/documents/{document}
Content-Type: application/json
```

Scope: `documents:write`

The update can change `project_id`, `title`, `caption`, `content_html`, `status`, `archived`, `editor_ids`, and `tag_names`. Moving a document to another organization is not supported.

### Delete document

```http
DELETE /api/v1/documents/{document}
```

Scope: `documents:write`. This is a soft delete.

### Add document comment

```http
POST /api/v1/documents/{document}/comments
Content-Type: application/json
```

Scope: `document_comments:write`

```json
{
  "body_html": "<p>Reviewed and approved.</p>"
}
```

## Document webhook events

Document integrations can subscribe to these existing organization webhook events:

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
