# Permissions matrix

This is the compact authorization reference. “Project collaborator” means organization owner/admin or ordinary user with explicit project membership.

## Organization and project

| Action                              | Owner | Admin | User |
| ----------------------------------- | :---: | :---: | :--: |
| Create organization                 |   ✓   |   ✓   |  ✓   |
| Read member organization            |   ✓   |   ✓   |  ✓   |
| Edit organization                   |   ✓   |   ✓   |  —   |
| Delete organization                 |   ✓   |   —   |  —   |
| Read members                        |   ✓   |   ✓   |  ✓   |
| View pending invitations            |   ✓   |   ✓   |  —   |
| Invite/revoke/change/remove members |   ✓   |   ✓   |  —   |
| Create/edit/archive/delete projects |   ✓   |   ✓   |  —   |
| Access all organization projects    |   ✓   |   ✓   |  —   |
| Access explicitly assigned project  |   ✓   |   ✓   |  ✓   |
| Synchronize project members         |   ✓   |   ✓   |  —   |
| Manage phases/labels                |   ✓   |   ✓   |  —   |
| Manage integrations                 |   ✓   |   ✓   |  —   |

Any activated user may create a new organization and becomes its owner. Last-owner demotion/removal is rejected.

## Tickets and files

| Action                                              | Project collaborator | Owner/admin special authority        |
| --------------------------------------------------- | :------------------: | ------------------------------------ |
| Read/create/edit/move/reorder/archive/delete ticket |          ✓           | same through implicit project access |
| Create ticket comment                               |          ✓           | same                                 |
| Edit/delete own normal comment                      |          ✓           | may moderate any normal comment      |
| Edit/delete movement entry                          |          —           | —                                    |
| Upload/download attachment                          |          ✓           | same                                 |
| Delete own uploaded attachment                      |          ✓           | may delete any project attachment    |
| Read revisions/analytics/project audit              |          ✓           | same                                 |
| Export project/project timeline                     |          ✓           | same                                 |

## Documents

| Action                   | Published active          | Draft/archived | Additional rule                                 |
| ------------------------ | ------------------------- | -------------- | ----------------------------------------------- |
| View                     | in-scope member           | author/editor  | project articles also require project access    |
| Edit content/status/tags | author/editor             | author/editor  | must retain scope access                        |
| Change project/editors   | author                    | author         | editors must access destination                 |
| Archive/unarchive        | author/editor             | author/editor  | archive makes article private to author/editors |
| Delete                   | author or org owner/admin | same           | soft delete                                     |
| Favorite/PDF/comment     | any viewer                | any viewer     | comment author/admin moderates comment          |

## Data transfer and audit

| Action                           | Required access                                           |
| -------------------------------- | --------------------------------------------------------- |
| Personal export                  | authenticated account, self                               |
| Import organization into new org | authenticated activated account                           |
| Export full organization         | organization owner/admin                                  |
| Import project into organization | organization owner/admin                                  |
| Import timeline                  | organization owner/admin                                  |
| Export organization timeline     | organization member (document events visibility-filtered) |
| Export project snapshot/timeline | project collaborator                                      |
| Read organization audit          | organization member, document events visibility-filtered  |
| Read project audit               | project collaborator, document events visibility-filtered |
| Update/delete audit              | nobody through application; database trigger rejects      |

## External API

Every call requires an active credential owned by the target resource’s organization plus the operation scope (or `*`). API credentials are not browser users and do not depend on project membership.

| Operation family             | Read scope                     | Write scope                           |
| ---------------------------- | ------------------------------ | ------------------------------------- |
| Organization                 | `organization:read`            | none                                  |
| Projects/members/labels/tags | `projects:read`                | `projects:write` for project mutation |
| Phases                       | `boards:read`                  | `boards:write`                        |
| Tickets                      | `tickets:read`                 | `tickets:write`                       |
| Ticket comments              | included when reading ticket   | `comments:write`                      |
| Analytics                    | `analytics:read`               | none                                  |
| Documents                    | `documents:read`               | `documents:write`                     |
| Document comments            | included when reading document | `document_comments:write`             |
| Send user notification       | none                           | `notifications:write`                 |

## HTTP outcomes

- `401` — API credential authentication failed.
- `403` — authenticated principal lacks tenant/scope/role/resource permission.
- `404` — resource not found in normal/bounded query.
- `422` — permitted operation violates validation or business invariant.

For rationale and administration guidance, see [Roles and permissions](../administration/roles-and-permissions.md).
