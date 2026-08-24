# Audit history

Okatana’s audit history is an append-only evidence layer shared by browser actions, imports, external API calls, and webhook generation. It is not the same as the editable collaboration timeline.

## Read an audit row

Each row can carry:

| Field                            | Meaning                                                 |
| -------------------------------- | ------------------------------------------------------- |
| `id`                             | ULID for the audit record                               |
| `organization_id` / `project_id` | tenant and optional project context                     |
| `event`                          | stable selector such as `ticket.moved`                  |
| actor fields                     | actor type, identifier, name, and email snapshot        |
| subject fields                   | model class, identifier, and human label snapshot       |
| `changes`                        | event-specific before/after structured data             |
| `metadata`                       | additional context such as move names or import markers |
| request fields                   | IP address and user agent, when a request exists        |
| `occurred_at`                    | event time                                              |

The subject label is captured during the event. A ticket comment becomes a label such as `Comment on CORE-17`, improving readability even after soft deletion. Organization audit results also attach project name/key from active or soft-deleted projects for display.

## Browse history

Organization members can open the organization **Audit log**. Project collaborators can open the project **Audit log**. Results are newest first and server-paginated at 10, 20, 50, or 100 rows.

Search can match event, actor name/email, subject label/type, and—at organization scope—project name. API query parameters can also narrow an event prefix and actor.

Document events are visibility-filtered. The caller sees only document/comment events attached to documents currently visible under document authorization rules.

## Immutability layers

Audit rows are protected twice:

1. `AuditLog` throws on Eloquent update or delete.
2. A migration installs database-specific `BEFORE UPDATE` and `BEFORE DELETE` protection for SQLite, MySQL, and PostgreSQL.

No application route updates or deletes audit logs. The database trigger also blocks SQL issued outside Eloquent.

!!! warning "Append-only does not mean tamper-proof infrastructure"

    A sufficiently privileged database administrator can disable triggers, alter tables, or replace backups. Protect database credentials, log administrative access, and use external backup/retention controls when evidentiary requirements are strict.

## Actor types

- `user` — an authenticated browser user, normally with live ID plus name/email snapshot.
- `api_credential` — an external credential, identified by public ID/name rather than a user impersonation.
- `import` or imported actor metadata — restored history from another payload may preserve original actor snapshots and carries import metadata.

Background webhook delivery does not create a second domain audit event for every attempt; delivery state is kept in `webhook_deliveries`.

## Audit-to-webhook flow

```text
authorized controller operation
  → commit domain mutation
  → ActivityRecorder creates audit row
  → matching active webhook endpoints selected
  → one delivery row per endpoint
  → delivery job queued
```

An endpoint with `*` matches every supported recorded event. A project-scoped endpoint matches events for that project; an organization-wide endpoint can match organization and all project events. This means the audit event is the canonical payload source for outgoing webhook delivery.

## Export history

Users with appropriate scope can download project or organization timelines in `okatana.export.v1`. A full organization/project export also includes relevant audit rows.

On import:

- destination organization/project IDs are used;
- remappable subject identifiers are rewritten;
- original occurrence time and actor snapshots can be retained;
- metadata adds `imported: true` and `source_audit_id`;
- the import completion itself becomes a new local audit row.

The result distinguishes historical imported evidence from the local import operation.

## Investigation workflow

When investigating a change:

1. Start with project audit search using ticket reference or actor.
2. Open the ticket timeline for phase movement and discussion context.
3. Open revision history for exact ticket/comment content versions.
4. Compare event changes/metadata with current state.
5. For external actors, locate the API credential public ID.
6. For external delivery concerns, inspect the endpoint’s delivery history.
7. Export the relevant timeline before broader remediation when preservation matters.

## Retention and privacy

Audit rows may contain names, email addresses, IP addresses, user agents, content changes, and identifiers. They survive ordinary domain soft deletion and cannot be erased through application code. Set retention, access, and data-protection policy with this deliberate persistence in mind.

Personal data export includes events attributed to that user but omits secret material. Organization/project export can include a wider history; restrict those files as sensitive operational records.
