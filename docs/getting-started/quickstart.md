# Five-minute quickstart

This walkthrough creates one organization, one project, one teammate invitation, one ticket, and one document. It assumes an operator has already deployed Okatana and mail delivery is working. For a new local installation, complete [Install locally](../operations/installation.md) first.

## 1. Create and activate your account

1. Open the Okatana base URL and choose **Create account**.
2. Enter your full name, email address, and a password of at least 14 characters containing uppercase, lowercase, a number, and a symbol.
3. Find the eight-character confirmation code in email. The displayed format is `XXXX-XXXX`; punctuation and casing are normalized during verification.
4. If the deployment requires two-factor authentication, scan the locally generated QR code with an RFC 6238-compatible authenticator and enter the current six-digit code.
5. Store the manual authenticator key in an appropriate secret manager before leaving setup.

!!! warning "Mail must be real outside development"

    The default `MAIL_MAILER=log` writes messages to `storage/logs/laravel.log`. That is useful locally but will not deliver confirmation codes or invitations to users.

## 2. Create an organization

On **Organizations**, select **Create organization** and provide:

- **Name** — the human-facing workspace name, up to 180 characters.
- **Slug** — optional URL-safe text. If omitted, Okatana derives it from the name. Collisions receive a numeric suffix.
- **Description** — optional context, up to 5,000 characters.

You become the first owner. Keep at least two owners in a production organization so ownership is not operationally tied to one account.

## 3. Create a project

Open the organization and select **Create project**. Use:

```text
Name         Platform rollout
Project key  PLAT
Description  Work required to deliver the new platform
```

The key starts with a letter, may contain letters, digits, `_`, or `-`, is stored uppercase, and is unique within the organization—even relative to soft-deleted projects. The default workflow is created automatically.

## 4. Give a teammate access

Open the organization’s **Members** tab and select **Invite member**.

1. Enter the teammate’s email.
2. Choose the **User** role.
3. Choose **Platform rollout** as optional project access.
4. Send the invitation.

The recipient joins the organization and receives explicit membership in the selected project. If the email already belongs to an Okatana user, that person must prove knowledge of the current password while accepting. A lower-role invitation never demotes an existing higher-role membership.

## 5. Create and move a ticket

Open the project and select **Create ticket**.

1. Choose **Open** as the phase.
2. Add a concise title.
3. Use the rich editor for a description, including `@` mentions if useful.
4. Choose a priority and due date.
5. Add assignees, managed labels, and lightweight tags.
6. Save the ticket.

The board shows a reference such as `PLAT-1`. Open it to add comments or files. Drag it to **In-progress**; Okatana records the transition as a system timeline entry. Move it to **Deployed** to set `completed_at`. Moving it back to a non-done phase clears completion.

!!! tip "Stable deep link"

    A ticket can be opened directly at `/app/projects/{project-ulid}/{ticket-number}`. The number is local to the project, so always retain the project in the URL.

## 6. Inspect progress

Use the project tabs:

- **Board** for execution and per-phase pagination.
- **Analytics** for totals, completion, overdue work, phase/priority distribution, and 30-day throughput.
- **Gantt** for a date-based view of the same filtered ticket set.
- **Audit log** for append-only project history.

The **Filter tickets** action is shared by Board and Gantt. Apply an assignee or priority filter, then compare Analytics if you need a server-calculated view based on the same dimensions.

## 7. Publish a document

Open **Documents** in the main sidebar and select **Create document**.

1. Set the organization and optionally the Platform rollout project.
2. Enter a title, caption, and article content.
3. Add tags and, if appropriate, one or more editors.
4. Save as a draft, review, then publish.
5. Open the article to favorite, comment, or download a PDF.

Project-scoped articles are visible only to people with access to that project. Drafts and archived articles are visible only to their author and named editors; published, non-archived articles are readable by everyone in scope.

## 8. Verify the evidence trail

Return to the organization’s **Audit log** and search for the project or ticket title. You should see creation, membership, ticket, move, and document events. Owners and admins can also open **Import/export** to download a portable organization snapshot.

## You now have

- a secured account;
- an organization with an explicit ownership boundary;
- a project with configurable workflow phases;
- a teammate scoped to that project;
- a ticket with durable movement and audit history;
- a shared article connected to the organization or project.

Continue with [Boards and tickets](../user-guide/boards-and-tickets.md) for daily workflow details or [Roles and permissions](../administration/roles-and-permissions.md) before onboarding a larger team.
