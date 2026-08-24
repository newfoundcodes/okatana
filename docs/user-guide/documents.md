# Documents knowledge base

Documents turn durable team knowledge into a first-class Okatana domain. An article is organization-owned, optionally project-scoped, author-led, collaboratively editable, searchable, discussable, auditable, exportable, and downloadable as PDF.

## Browse articles

Select **Documents** in the main sidebar. The workspace has:

- **Articles** — every article currently visible to you;
- **Favorites** — the visible subset you saved;
- **Audit logs** — document events for documents you may currently see;
- **Integrations** — document API credentials and webhook endpoints for administrators.

The article directory is server-paginated with 10, 20, 50, or 100 rows per page. Search matches title, caption, status, author name/email, organization name, project name, and tag name.

Results sort published articles by most recent publication and then update; drafts without `published_at` follow, ordered by update.

## Understand scope and visibility

Every article has one organization. A project is optional but, when selected, must belong to that organization.

Visibility is the intersection of **scope access** and **publication access**:

1. The viewer must belong to the organization.
2. For a project article, the viewer must be an organization owner/admin or explicit project member.
3. Published, non-archived articles are visible to everyone who passes scope.
4. Drafts and archived articles are visible only to the author or a selected editor.

This rule also filters document audit results so a draft title cannot leak through the audit tab to a non-editor.

## Create an article

Select **Create document** and complete the fields in order:

1. **Title** — required, maximum 500 characters.
2. **Organization** — required; establishes the permanent tenancy boundary.
3. **Project** — optional; narrows readership and eligible collaborators.
4. **Caption** — optional summary, maximum 2,000 characters.
5. **Contents** — sanitized rich HTML, maximum 1,000,000 characters.
6. **Editors** — up to 100 users with access to the chosen scope.
7. **Tags** — up to 20 organization-scoped names, each up to 50 characters.
8. **Status** — draft or published.

The creation screen will not create an article until a valid organization is available. Editor search excludes the author and returns up to 12 matching eligible users.

### Draft first or publish immediately

Creating as draft leaves `published_at` empty and limits viewing to author/editors. Creating as published sets `published_at`, records both `document.created` and `document.published`, and exposes it to every in-scope reader.

Use draft for unfinished or sensitive content. Select editors before mentioning collaborators because draft mention delivery is constrained to people who can actually read the draft.

## Edit and publish

The author and selected editors can edit title, caption, content, tags, and status. The author alone can change project placement or editor membership.

Important constraints:

- An article cannot move to another organization after creation.
- Moving it to a project requires the author to have access to that project.
- Every selected editor must have access to the resulting scope.
- Publishing from draft sets `published_at` to the transition time.
- Returning to draft clears `published_at`.
- Content is sanitized and mentions are revalidated after relevant scope/content changes.

Every save records `document.updated` with before/after document, editor, and tag state. Status transitions also record `document.published` or `document.drafted`.

## Tags

Document tags are reusable within an organization. Display casing is preserved, while a normalized lowercase name prevents case-only duplicates. The editor uses a token input: Enter or comma commits a value, and Backspace removes the nearest token when the text input is empty.

Search includes tag names. Organization export includes organization-wide documents and their tags; project export includes project articles and their tags.

## Favorite an article

Use the favorite action on a viewable article. Favorites are per user and do not change publication, alert authors, or write an audit event. Removing a favorite simply detaches that user-document relationship.

If an article becomes inaccessible because project access or state changes, it also disappears from Favorites until access returns.

## Discuss an article

Any user who can view an article can add a comment. Comments:

- require non-empty content after sanitization;
- support the shared rich-text and mention behavior;
- record an author snapshot and edit time;
- can be edited/deleted by their author or an organization owner/admin;
- are soft-deleted;
- produce document comment audit/webhook events.

On a new comment, general notifications go to the document author and selected editors, excluding the commenter and direct-mention recipients. The `notify_document_comments` preference controls this general fan-out; direct mentions follow the mention email preference.

Unlike ticket comments, document comments do not currently expose file attachments or revision-history endpoints.

## Archive and delete

The author or editor can archive/unarchive. Archived articles become private to the author/editors even if their status remains `published`. Archive records `document.archived` or `document.unarchived`.

The author or an organization owner/admin can delete. Delete is soft and records `document.deleted`. The current UI has no undelete workflow.

## Download PDF

Any viewer can download an article as A4 PDF. Okatana uses Spatie Laravel PDF with DOMPDF by default. A dedicated Blade layout uses DOMPDF-compatible CSS, and authenticated local editor-image URLs are converted to data URIs before rendering.

Remote images remain dependent on DOMPDF/network configuration. For portable and predictable PDFs, upload important images to Okatana instead of hotlinking them.

## Documents and integrations

External clients use scopes:

- `documents:read` to list/read;
- `documents:write` to create, update, change status/archive/editors, or delete;
- `document_comments:write` to add comments.

Document webhooks cover creation, updates, publish/draft transitions, archive transitions, deletion, and comment creation/update/deletion. See [HTTP API guide](../integrations/http-api.md) and [Webhooks](../integrations/webhooks.md).

## Good knowledge-base practices

- Use organization-wide scope for policies relevant to every member.
- Use project scope for implementation-specific runbooks and decisions.
- Keep drafts to the smallest editor group required.
- Treat the caption as a useful search result summary.
- Use tags consistently across an organization.
- Prefer uploaded local images for transferable articles and reliable PDFs.
- Archive obsolete material; delete only when it should leave normal application access.
- Review the audit history when publication or editor access is disputed.
