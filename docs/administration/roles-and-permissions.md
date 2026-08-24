# Roles and permissions

Okatana authorization combines an organization role, optional explicit project membership, resource authorship, and integration scopes. Hiding a button is only presentation; Laravel repeats every meaningful check at the controller/service boundary.

## Organization roles

| Capability                                 |  Owner   |  Admin   |         User          |
| ------------------------------------------ | :------: | :------: | :-------------------: |
| Read organization                          |    ✓     |    ✓     |           ✓           |
| Change organization settings               |    ✓     |    ✓     |           —           |
| Delete organization                        |    ✓     |    —     |           —           |
| Read members                               |    ✓     |    ✓     |           ✓           |
| View pending invitations                   |    ✓     |    ✓     |           —           |
| Invite, revoke, change role, remove member |    ✓     |    ✓     |           —           |
| Create/change/archive/delete projects      |    ✓     |    ✓     |           —           |
| Access every organization project          | implicit | implicit |     explicit only     |
| Manage project membership                  |    ✓     |    ✓     |           —           |
| Manage phases and labels                   |    ✓     |    ✓     |           —           |
| Work on accessible tickets/comments        |    ✓     |    ✓     | explicit project only |
| Read accessible audit history              |    ✓     |    ✓     |           ✓           |
| Manage webhooks/API credentials            |    ✓     |    ✓     |           —           |
| Import project/timeline                    |    ✓     |    ✓     |           —           |
| Export organization snapshot               |    ✓     |    ✓     |           —           |
| Export accessible project/timeline         |    ✓     |    ✓     |           ✓           |

The application never permits demoting or removing the last remaining owner. An admin may promote another member to owner, so assign admin only to trusted organization operators.

## Project access

The access test is:

```text
organization owner/admin
  OR explicit project membership
```

An ordinary user who merely belongs to the organization does not see or operate a project. A project membership is meaningful only while organization membership also exists.

Project access grants broad collaboration capability: create/edit/move/reorder/archive/delete tickets, add comments, upload files, and read project analytics/history. Okatana does not currently define separate project roles such as viewer, reporter, or maintainer.

!!! warning "Project access is edit access"

    Do not grant project membership as read-only visibility. Anyone with project access can change and soft-delete tickets. Use organization/project boundaries—not an assumed project viewer role—to scope sensitive work.

## Resource ownership rules

Some actions narrow access beyond project collaboration:

| Resource/action                   | Authorized users                            |
| --------------------------------- | ------------------------------------------- |
| Edit/delete normal ticket comment | comment author or organization owner/admin  |
| Edit/delete movement entry        | nobody                                      |
| Delete attachment                 | uploader or organization owner/admin        |
| View published active document    | any member with organization/project scope  |
| View draft/archived document      | author or selected editor with scope access |
| Edit document content/status/tags | author or selected editor                   |
| Change document project/editors   | author only                                 |
| Delete document                   | author or organization owner/admin          |
| Edit/delete document comment      | comment author or organization owner/admin  |
| Favorite document                 | any viewer, for self only                   |

Changing scope can invalidate otherwise valid editors or readers. When the author moves an article to a project, all selected editors must have access to that destination.

## Integration authorization

Only organization owners/admins create or revoke API credentials and webhook endpoints. An API credential then acts as its own `api_credential` audit actor and never impersonates the creating user.

Two checks apply on every external API operation:

1. The requested organization/project/resource belongs to the credential’s organization.
2. The credential has the required scope or wildcard `*`.

Possessing `*` does not bypass tenancy. A credential for organization A cannot read a known ULID from organization B.

Webhook endpoints are also organization-scoped and may optionally be narrowed to one project. Endpoint configuration never grants access back into the browser application.

## Document visibility and audit privacy

Document authorization is query-level as well as object-level. Organization/project audit views filter `document.*` events to documents the requesting user can currently see. This prevents a draft title or comment label from leaking through a general organization audit timeline.

Published state alone is insufficient: a project-scoped article still requires project access. Conversely, project access alone is insufficient for a draft: the user must be its author or selected editor.

## Account activation gate

Authenticated browser users must complete deployment-required security setup before any protected `/app-api` operation. The middleware evaluates current policy on each request. React state cannot bypass it.

Public/unprotected paths are limited to configuration, register/login/challenge, invitation inspection/acceptance, OAuth/SSO callbacks, the SPA shell, health check, and documentation according to documentation configuration.

## How to design access

Use this sequence:

1. Create at least two owners for continuity.
2. Assign admin only to people who should manage membership, all projects, integrations, and imports.
3. Keep everyone else at user.
4. Grant ordinary users explicit project access for current responsibility.
5. Review project membership during role/team changes.
6. Use project-scoped documents for restricted knowledge.
7. Issue API credentials per integration with minimum scopes and expiry.
8. Narrow webhook endpoints to a project when organization-wide events are unnecessary.
9. Review audit logs after material access changes.

## Known granularity limits

The current model does not provide:

- deny rules for an owner/admin on a particular project;
- read-only project membership;
- per-phase permissions;
- per-ticket access control;
- separate document viewer lists beyond scope/publication state;
- per-credential user impersonation;
- field-level API scopes.

Model projects and organizations accordingly. If these constraints do not match your tenancy needs, change the authorization model and tests before importing real users/data.

For the exact compact matrix, see [Permissions reference](../reference/permissions.md). For implementation, see [Security model](../developer/security.md).
