# Route map

This is a routing inventory, not an alternative API contract. External clients should use [Endpoint reference](../API.md); browser endpoints are internal to the bundled React application.

## Browser pages and documentation

| Method | Path                 | Purpose                            |
| ------ | -------------------- | ---------------------------------- |
| GET    | `/`                  | React application shell            |
| GET    | `/app/{any?}`        | React application shell/deep links |
| GET    | `/join/{token}`      | invitation SPA shell               |
| GET    | `/docs/`             | generated static MkDocs manual     |
| GET    | `/docs/api`          | Scalar interactive API reference   |
| GET    | `/docs/openapi.yaml` | OpenAPI specification              |
| GET    | `/up`                | Laravel health endpoint            |

The Scalar/spec paths are configurable. `/docs/` is a physical generated directory and should remain reserved.

## Public/session-establishment browser API

Prefix: `/app-api` except OAuth/SSO.

| Method    | Path                              | Purpose                           |
| --------- | --------------------------------- | --------------------------------- |
| GET       | `/auth/config`                    | enabled OAuth/SSO and TOTP policy |
| POST      | `/auth/register`                  | local signup                      |
| POST      | `/auth/login`                     | password step                     |
| GET       | `/auth/two-factor/pending`        | pending challenge metadata        |
| POST      | `/auth/two-factor/challenge`      | complete login TOTP               |
| GET       | `/invitations/{token}`            | inspect invitation                |
| POST      | `/invitations/{token}/accept`     | accept invitation                 |
| GET       | `/auth/oauth/{provider}`          | OAuth redirect                    |
| GET       | `/auth/oauth/{provider}/callback` | OAuth callback                    |
| GET       | `/auth/sso/{driver}`              | SSO redirect                      |
| GET, POST | `/auth/sso/{driver}/callback`     | SSO callback                      |

Register/login/invitation acceptance use the auth throttle; TOTP uses the security throttle.

## Authenticated security setup

Prefix: `/app-api`, requires Laravel `auth` but not completed account security.

| Method | Path                       |
| ------ | -------------------------- |
| GET    | `/auth/me`                 |
| POST   | `/auth/logout`             |
| POST   | `/auth/email/verify-code`  |
| POST   | `/auth/email/resend-code`  |
| GET    | `/auth/two-factor/setup`   |
| POST   | `/auth/two-factor/confirm` |

## Protected workspace API

All routes below use `/app-api`, session `auth`, and `account.secure`.

### User, notifications, editor assets

```text
GET    /user
PATCH  /user
POST   /user/avatar
DELETE /user/avatar
GET    /users/{user}/avatar
GET    /user/export
GET    /notifications
POST   /notifications/read-all
POST   /notifications/{notification}/read
POST   /editor/images
GET    /editor/images/{owner}/{filename}
```

### Organizations, members, invitations

```text
GET    /organizations
POST   /organizations
GET    /organizations/{organization}
PATCH  /organizations/{organization}
DELETE /organizations/{organization}
GET    /organizations/{organization}/members
POST   /organizations/{organization}/invitations
PATCH  /organizations/{organization}/members/{user}
DELETE /organizations/{organization}/members/{user}
DELETE /invitations/{invitation}
```

### Projects, phases, labels

```text
GET    /projects
GET    /organizations/{organization}/projects
POST   /organizations/{organization}/projects
GET    /projects/{project}
PATCH  /projects/{project}
DELETE /projects/{project}
PUT    /projects/{project}/members
POST   /projects/{project}/boards
PUT    /projects/{project}/boards/reorder
PATCH  /boards/{board}
DELETE /boards/{board}
POST   /projects/{project}/labels
PATCH  /labels/{label}
DELETE /labels/{label}
GET    /projects/{project}/analytics
```

### Tickets, comments, revisions, attachments

```text
GET    /projects/{project}/tickets
POST   /projects/{project}/tickets
PUT    /projects/{project}/tickets/reorder
GET    /projects/{project}/tickets/by-number/{number}
GET    /tickets/{ticket}
PATCH  /tickets/{ticket}
DELETE /tickets/{ticket}
POST   /tickets/{ticket}/move
GET    /tickets/{ticket}/revisions
POST   /tickets/{ticket}/comments
PATCH  /comments/{comment}
DELETE /comments/{comment}
GET    /comments/{comment}/revisions
POST   /tickets/{ticket}/attachments
POST   /comments/{comment}/attachments
GET    /attachments/{attachment}/download
DELETE /attachments/{attachment}
```

### Documents

```text
GET    /documents
POST   /documents
GET    /documents/editor-options
GET    /documents/editor-candidates
GET    /documents/mention-candidates
GET    /organizations/{organization}/document-tags
GET    /documents/audit-logs
GET    /documents/{document}
PATCH  /documents/{document}
DELETE /documents/{document}
POST   /documents/{document}/archive
POST   /documents/{document}/favorite
GET    /documents/{document}/pdf
POST   /documents/{document}/comments
PATCH  /document-comments/{comment}
DELETE /document-comments/{comment}
```

### Audit, integrations, and transfer

```text
GET    /organizations/{organization}/audit-logs
GET    /projects/{project}/audit-logs
GET    /organizations/{organization}/webhooks
POST   /organizations/{organization}/webhooks
PATCH  /webhooks/{webhook}
POST   /webhooks/{webhook}/rotate
DELETE /webhooks/{webhook}
GET    /webhooks/{webhook}/deliveries
GET    /organizations/{organization}/api-credentials
POST   /organizations/{organization}/api-credentials
DELETE /api-credentials/{credential}
GET    /organizations/{organization}/export
GET    /projects/{project}/export
GET    /projects/{project}/timeline/export
GET    /organizations/{organization}/timeline/export
POST   /imports/organization
POST   /organizations/{organization}/imports/project
POST   /organizations/{organization}/imports/timeline
```

## External API v1

All routes use `/api/v1`, bearer credential middleware, and API throttle.

```text
GET    /organizations/{organization}
GET    /organizations/{organization}/projects
POST   /organizations/{organization}/projects
POST   /organizations/{organization}/notifications
GET    /organizations/{organization}/documents
POST   /organizations/{organization}/documents

GET    /projects/{project}
PATCH  /projects/{project}
DELETE /projects/{project}
GET    /projects/{project}/members
GET    /projects/{project}/labels
GET    /projects/{project}/tags
GET    /projects/{project}/boards
POST   /projects/{project}/boards
PUT    /projects/{project}/boards/reorder
GET    /projects/{project}/tickets
POST   /projects/{project}/tickets
PUT    /projects/{project}/tickets/reorder
GET    /projects/{project}/analytics

PATCH  /boards/{board}
DELETE /boards/{board}
GET    /tickets/{ticket}
PATCH  /tickets/{ticket}
DELETE /tickets/{ticket}
POST   /tickets/{ticket}/move
POST   /tickets/{ticket}/comments

GET    /documents/{document}
PATCH  /documents/{document}
DELETE /documents/{document}
POST   /documents/{document}/comments
```

Use `php artisan route:list` as the runtime authority after configuration and route caching.
