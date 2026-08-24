# HTTP API guide

The external API is a versioned server-to-server surface under `/api/v1`. It uses organization-scoped bearer credentials, JSON requests/responses, Laravel validation errors, and a per-credential rate limit.

Use the [Endpoint reference](../API.md) for the route catalog or the [interactive Scalar console](/docs/api) to inspect the OpenAPI document and try requests.

## Do not confuse the two APIs

| Surface      | Authentication                  | Intended client           | Stability                               |
| ------------ | ------------------------------- | ------------------------- | --------------------------------------- |
| `/app-api/*` | Laravel session + CSRF          | bundled React SPA         | internal application contract           |
| `/api/v1/*`  | `Authorization: Bearer oka_...` | external services/scripts | explicit versioned integration contract |

External clients should never automate the session API or scrape React pages.

## Create a credential

As an organization owner/admin:

1. Open **Organizations → organization → Integrations**.
2. Under **API access**, enter a descriptive client/environment name.
3. Select minimum required scopes.
4. Optionally choose a future expiry.
5. Create and copy the token into a secret manager.

Token format:

```text
oka_<12-character-public-id>.<48-character-secret>
```

Okatana stores only the secret’s SHA-256 hash. It can list the public ID but cannot recover the complete token. A revoked or expired credential returns `401`.

## Make a request

Set local shell variables without committing them:

```bash
export OKATANA_URL="https://okatana.example.com"
export OKATANA_TOKEN="oka_public.secret"
export OKATANA_ORG="01..."
```

Read the organization:

```bash
curl --fail-with-body \
  --header "Authorization: Bearer ${OKATANA_TOKEN}" \
  --header "Accept: application/json" \
  "${OKATANA_URL}/api/v1/organizations/${OKATANA_ORG}"
```

Create a project:

```bash
curl --fail-with-body \
  --request POST \
  --header "Authorization: Bearer ${OKATANA_TOKEN}" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data '{"name":"Platform","key":"PLAT","description":"Platform work"}' \
  "${OKATANA_URL}/api/v1/organizations/${OKATANA_ORG}/projects"
```

Keep token variables out of recorded terminal output, CI logs, tickets, documents, and source control.

## Response conventions

Successful object/collection external responses generally use a top-level `data` key:

```json
{
  "data": {
    "id": "01...",
    "name": "Platform"
  }
}
```

Create operations normally return `201`; deletes and ticket reorder return `204`; reads/updates return `200`.

Laravel validation errors use:

```json
{
  "message": "The title field is required.",
  "errors": {
    "title": ["The title field is required."]
  }
}
```

Business-rule failures may return `422` with only `message`. Clients must handle both shapes.

## Authentication and authorization errors

| Status | Meaning                                                                                |
| -----: | -------------------------------------------------------------------------------------- |
|  `401` | bearer token absent/malformed, public ID unknown, secret mismatch, revoked, or expired |
|  `403` | credential belongs to another organization or lacks required scope                     |
|  `404` | route-bound object absent/soft-deleted or relationship-constrained ID not found        |
|  `422` | field validation, cross-project relationship, WIP, or lifecycle rule failed            |
|  `429` | configured per-minute limit reached                                                    |

Do not retry `401`, `403`, or `422` without changing credentials/request. Back off on `429`; add jitter for concurrent clients.

## Scopes

Scopes split by domain and read/write intent:

```text
organization:read
projects:read       projects:write
boards:read         boards:write
tickets:read        tickets:write
comments:write
analytics:read
documents:read      documents:write
document_comments:write
notifications:write
*
```

`*` permits all external operations but still cannot cross the credential’s organization. See [Events and scopes](../reference/events-and-scopes.md) for the operation map.

## Discover relationship identifiers

Use read endpoints before creating/updating relationships:

1. List projects in the organization.
2. List project phases to obtain IDs and stable slugs.
3. List project members to obtain assignable user IDs.
4. List labels and in-use tags.
5. Use returned ULIDs in ticket/document payloads.

Ticket creation accepts either `board_id` or `board_slug`; when both are absent it looks for slug `open`. Board slugs are stable after renaming, making them useful for automation, but they remain project-local.

## Pagination

Ticket and document list endpoints use Laravel pagination. The outer response has `data`, whose value is a paginator object containing its own `data` array plus fields such as current page, last page, per page, total, and navigation URLs.

Ticket `per_page` defaults to 50 and is clamped to 1–200. Document lists accept the implemented pagination/filter parameters described in the endpoint reference/OpenAPI. Do not assume every collection endpoint is paginated: projects, phases, members, labels, and tags return complete arrays.

Follow response fields, not a hard-coded last page calculation, and tolerate new response properties.

## Ticket automation rules

- Project ticket numbers allocate under a project row lock.
- Priority is one of six fixed values.
- Assignees must be explicit project members or organization owners/admins.
- Labels must belong to the project.
- Tags normalize and reuse within the project; up to 20 names.
- Rich HTML is sanitized; mention IDs are scope-validated.
- WIP limit applies to creation/movement.
- Board moves create immutable movement timeline entries and notify participants.
- Delete is soft; archive is reversible via update.
- API actor context appears in audit metadata/snapshots rather than impersonating browser activity.

When listing tickets, `tag_ids` is a comma-separated match-all filter. List ordering is ticket number descending; page size max 200.

## Document automation rules

An API credential is an organization-level trusted principal for the document scope. `documents:read` reads organization documents through the external contract rather than a browser user’s author/editor visibility. Protect this scope accordingly: it can expose drafts in that organization if the endpoint returns them.

For writes:

- project must belong to the credential organization;
- selected editors must belong to the organization/project access set;
- content and comments are sanitized;
- status is draft or published;
- changing status/archive creates corresponding audit/webhook events;
- moving project scope filters editors that no longer qualify when appropriate;
- deletion is soft.

## Idempotency and retries

The current API does not expose idempotency keys. A retry of a successful-but-timed-out POST can create a duplicate project, ticket, comment, document, or notification.

Client strategy:

- retry safe reads automatically with bounded exponential backoff;
- retry `429`/transient `5xx` carefully;
- before retrying a create after ambiguous timeout, search/read for a client-chosen unique marker where possible;
- make project keys unique and treat `422 key exists` as a reconciliation signal;
- store created ULIDs immediately;
- avoid concurrent reorder writes to the same phase/project;
- record request purpose and resulting audit/delivery IDs without recording the token.

## Rate limiting

Default `OKATANA_API_RATE_LIMIT=120` requests per minute per credential. Successful authentication updates `last_used_at`. Rate limiting happens after credential middleware in the route stack, so rotate or partition clients instead of sharing a single broad token across unrelated workloads.

## Versioning and compatibility

All current routes are under `v1`. Clients should:

- send `Accept: application/json`;
- ignore unknown response properties;
- treat documented enum additions carefully;
- validate required fields, not exact key order;
- pin integration tests against `docs/openapi.yaml`;
- monitor release notes/source changes because the OpenAPI file is maintained in the repository.

## Minimal integration test

Before production use, verify:

1. valid scoped read succeeds;
2. omitted token returns `401`;
3. missing scope returns `403`;
4. resource from another organization returns `403`;
5. invalid relationship returns `422`/`404` as documented;
6. revocation immediately makes the token return `401`;
7. intended writes appear with an integration actor in audit history;
8. selected webhook receives the resulting event when configured.
