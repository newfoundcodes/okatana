# Glossary

**Account security gate**  
Middleware-enforced requirement that email ownership and deployment-required TOTP state be complete before protected workspace access.

**Actor**  
User, API credential, import identity, or other principal recorded as performing an audit event.

**Admin**  
Organization role able to manage members, projects, integrations, and settings, but not delete the organization solely by role.

**Archive**  
Reversible lifecycle state that excludes tickets/projects/documents from normal active behavior without soft-deleting them.

**Attachment**  
Private downloadable file attached to a ticket or normal ticket comment, up to 200 MB.

**Audit log**  
Append-only operational event with tenant, actor, subject, changes, metadata, request context, and occurrence time.

**Board / phase**  
One configurable workflow column inside a project. “Board” is the model/API term; “phase” is common UI wording.

**Caption**  
Optional short document summary, searchable and displayed with article metadata.

**Credential**  
Organization-scoped external API identity with bearer token, scopes, optional expiry, and revocation state.

**Deep link**  
Direct ticket path `/app/projects/{project-ulid}/{ticket-number}`.

**Document**  
Organization-owned knowledge article, optionally project-scoped, with author/editors, tags, state, comments, favorites, and PDF.

**Done phase**  
Board whose `is_done` flag makes current tickets count as completed and controls `completed_at`.

**Editor image**  
JPEG/PNG/GIF/WebP up to 5 MB uploaded through rich text and rendered through authenticated `/app-api/editor/images/...`.

**Explicit project membership**  
`project_members` relationship required for ordinary users; owners/admins instead have implicit project access.

**Favorite**  
Per-user saved document relationship, with no effect on visibility/publication.

**Integration actor**  
API credential identity stored in audit activity instead of impersonating a browser user.

**Label**  
Admin-managed project metadata with project-unique name and optional color, applied to tickets.

**Mention**  
Sanitized non-editable rich-text token containing a valid user ULID and capable of direct notification.

**Movement entry**  
Non-editable/non-deletable ticket timeline comment recording a phase transition.

**Organization**  
Top-level tenancy/administration boundary for members, projects, documents, integrations, transfers, and audit.

**Owner**  
Highest organization role and only role permitted to delete an organization. At least one must remain.

**Project key**  
Short uppercase organization-unique stable identifier used in human ticket references, such as `CORE`.

**Published**  
Document state readable by all users in organization/project scope when not archived.

**Revision**  
Immutable versioned snapshot of ticket or normal ticket-comment content/properties after edits.

**Scalar**  
Interactive OpenAPI client/reference at `/docs/api`, distinct from the MkDocs manual.

**Scope (API)**  
Named external API permission such as `tickets:write` attached to a credential.

**Scope (document)**  
Organization plus optional project that determines eligible readers/editors/mentions.

**Soft delete**  
Timestamp-based removal from normal queries while retaining the database row and historical relationships.

**Tag**  
Lightweight case-insensitively reused term. Ticket tags are project-scoped; document tags are organization-scoped.

**Ticket**  
Project work item with phase, local number, priority, rich description, dates, assignment, labels/tags, comments/files, revisions, and lifecycle state.

**Ticket reference**  
Human form `{project-key}-{number}`, e.g. `CORE-17`; not globally unique without project key.

**Timeline export**  
Portable `okatana.export.v1` payload containing audit history rather than the full work graph.

**TOTP**  
RFC 6238 time-based one-time password from an authenticator application, optionally required deployment-wide.

**Transfer**  
Recorded organization/project/timeline import/export attempt using the portable schema.

**ULID**  
Lexicographically sortable unique identifier used by principal Okatana domain models.

**User (role)**  
Ordinary organization role with read access to organization administration and work capability only inside explicitly accessible projects.

**Webhook delivery**  
Stored event payload and attempt/result history for one endpoint, sent asynchronously with HMAC headers.

**WIP limit**  
Hard maximum active-ticket count allowed in a workflow phase for creates/moves.
