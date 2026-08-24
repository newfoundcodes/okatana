# Import and export

Okatana’s portable JSON format moves organization/project graphs between installations without reusing database identifiers. It is also useful for logical backups and review, but it does not replace database and file backups for complete disaster recovery.

## Formats

| Schema                   | Purpose                                                                        |
| ------------------------ | ------------------------------------------------------------------------------ |
| `okatana.export.v1`      | organization, project, or audit timeline transfer                              |
| `okatana.user-export.v1` | one user’s personal-data download; not accepted by organization/project import |

Every transferable payload declares its schema and scope. Import rejects an unsupported schema or mismatched scope with `422`.

## Export choices

### Organization snapshot

Owners/admins can download the organization graph from **Import/export**. It includes organization metadata, memberships, active projects, project members, phases, labels, tags, tickets, assignees, comments, revision history, attachments, referenced editor images, organization/project documents, document comments/tags/editors, and audit history as represented by the transfer service.

The filename is `okatana-org-{slug}-{timestamp}.json`.

### Project snapshot

Any project collaborator can download one accessible project from its page. It includes the project graph, explicit members, relevant identities, documents, assets, attachments, revisions, and project audit history.

The filename is `okatana-project-{lowercase-key}-{timestamp}.json`.

### Timeline only

Organization members can export a visible organization timeline; project collaborators can export a project timeline. Timeline payloads preserve audit rows without the complete work graph.

### Personal data

Each user can export their own `okatana.user-export.v1` from Settings. See [Account and sign-in](../user-guide/account-and-access.md). It is a subject-access artifact, not an import bundle.

## Import choices

### Import an organization

Any activated user can upload an organization payload from the organization directory. Okatana creates a **new** organization, makes the importer its owner, and regenerates a unique slug. It does not overwrite an existing organization.

### Import a project

An owner/admin imports a project payload into an existing organization. The destination receives a new project whose name is suffixed `(Imported)`. A safe unique key is derived, and all internal identifiers are regenerated/remapped.

### Import a timeline

An owner/admin can import timeline history at organization scope or into a selected project. This appends marked audit rows; it does not synthesize the missing domain objects represented by those events.

## Identity and relationship mapping

Import builds maps such as:

```text
source phase ID   → destination phase ULID
source label ID   → destination label ULID
source tag ID     → destination tag ULID
source ticket ID  → destination ticket ULID
source document ID→ destination document ULID
source user ID    → destination user found by email, when allowed
```

Ticket relationships, revision mention tokens, document editors, and audit subject references are rewritten where a map is available.

Existing users are linked by normalized email only when they belong to or can validly access the destination scope. Missing project members receive invitations rather than placeholder accounts. Imported creators/assignees/editors that cannot access the destination fall back to the importing actor or are omitted, and warnings explain material membership substitutions.

## Collision behavior

- Organization slug: normalized source plus `-2`, `-3`, etc.
- Project name: source name plus `(Imported)`.
- Project key: sanitized uppercase base up to eight characters plus numeric suffix if needed.
- Phase/label/tag/ticket relationships: rebuilt from generated IDs.
- Ticket numbers: source number is retained where the imported new project permits it.

Imports always create a new project graph, so they do not merge tickets into an existing project.

## Content and asset safety

Rich HTML is untrusted and re-sanitized. Mention IDs are rewritten only for mapped users; unmapped mention attributes are removed.

Assets are bounded:

- referenced editor images are base64 encoded/restored only up to 5 MB each;
- attachments are base64 encoded/restored only up to 200 MB each;
- unsafe paths containing `..` or outside `editor-images/` are ignored;
- missing or oversized content may be omitted even when metadata exists.

Because JSON plus base64 expands binary data, large project exports can consume substantial memory and disk. Test realistic payload sizes before using this mechanism as the only migration path.

## Transaction and transfer records

Graph creation runs in a database transaction. A `data_transfers` row records direction, scope, running/completed/failed status, timestamps, warnings/summary, or captured error message. Success records `import.completed` or `export.completed` in the local audit log.

File writes occur alongside database import work. Treat interrupted large imports carefully: validate database result and storage contents, and retain the original payload until verification is complete.

## Safe migration runbook

1. Back up destination database and both Laravel storage disks.
2. Confirm source/destination versions support `okatana.export.v1` semantics used by the payload.
3. Create destination users or verify mail/queue for invitations.
4. Export and securely checksum/store the JSON.
5. Inspect `schema`, `scope`, size, and high-level identity data; do not hand-edit IDs unless you own the mapping consequences.
6. Import into a staging destination first.
7. Review returned warnings.
8. Compare phase, ticket, document, comment, attachment, and audit counts.
9. Open rich text with images and download a sample attachment/PDF.
10. Verify invited users and scope access.
11. Run the production import and retain the before/after backups.

## What export does not replace

A portable export may omit runtime/session/cache/queue state, secrets, some unavailable/oversized binaries, soft-deleted graphs outside the service query, and infrastructure configuration. For disaster recovery, follow [Backup and restore](../operations/backup-restore.md).
