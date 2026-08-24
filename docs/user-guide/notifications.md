# Notifications and activity

Okatana separates user notifications from immutable audit history and external webhooks. A notification tells a person something needs attention; an audit row preserves an event; a webhook delivers an event to another system.

## Open the notification feed

Use the notification control in the workspace top bar. The feed is ordered newest first and reports an unread count.

You can:

- search notification type and JSON data text;
- paginate at 10, 20, 50, or 100 rows;
- mark one item read;
- mark all unread items read;
- follow an internal application URL carried by the notification.

Actor name and avatar are refreshed from the current user record when possible, so a profile picture update can appear in existing notifications without rewriting stored notification data.

## Collaboration notifications

Okatana creates notifications for several collaboration paths:

| Trigger                   | Typical recipient set                                                  |
| ------------------------- | ---------------------------------------------------------------------- |
| New ticket assignment     | newly added assignees, excluding actor                                 |
| Ticket comment            | current ticket participants, excluding commenter and explicit mentions |
| Ticket movement           | current ticket participants, excluding mover                           |
| Ticket/ comment mention   | valid mentioned project users, excluding actor                         |
| Document mention          | valid in-scope readers, further restricted for drafts, excluding actor |
| Document comment mention  | valid in-scope readers, excluding actor                                |
| Document comment          | author and selected editors, excluding commenter and explicit mentions |
| External notification API | selected users in credential organization                              |

Ticket participants are derived from the ticket’s collaboration relationships. Document participants are the author and selected editors. Recipient collections are deduplicated before sending.

## Email preferences

The in-app record and email delivery are related but distinct. Open **Settings** to control:

- **Assignment emails** — newly assigned ticket alerts.
- **Mention emails** — direct mentions across tickets/documents and their comments.
- **Document comment notifications** — general author/editor article-comment alerts.

The document-comment setting is a general opt-out; it does not suppress a direct mention, which follows mention preferences. Self-notifications are removed before fan-out.

Mail delivery is queued. A delayed or failed email does not roll back the underlying ticket/comment/document operation. In-app notification creation remains part of application collaboration behavior, while queue status is an operator concern.

## External organization notifications

An API credential with `notifications:write` can send a title, body, and optional internal `/app` URL to selected users in its own organization. Every recipient ID must be a member of that organization. The credential is the recorded integration actor; the call does not impersonate a browser user.

Use this for operational automation such as release-window changes, deployment results, or a link to an affected project. Do not use it as an unrestricted mail relay.

## Activity, revisions, and audit

These histories answer different questions:

| Surface          | Question                                                                          | Mutable?                                                          |
| ---------------- | --------------------------------------------------------------------------------- | ----------------------------------------------------------------- |
| Notification     | “What should this user notice?”                                                   | read state changes; content record otherwise retained             |
| Ticket timeline  | “What discussion and phase movement happened on this ticket?”                     | user comments may be edited/soft-deleted; movement entries cannot |
| Revision history | “What did this ticket/comment contain at each edit?”                              | model-immutable                                                   |
| Audit log        | “Which actor performed which domain operation, when and in what request context?” | model- and database-immutable                                     |
| Webhook delivery | “Was a selected event sent to this endpoint and what happened?”                   | delivery status changes during attempts                           |

Use the audit log for accountability, revisions for content reconstruction, and notifications for attention management.

## Notification delivery failures

If in-app notifications appear but email does not:

1. Confirm the user’s relevant email preference.
2. Confirm `MAIL_MAILER` is not `log` in production.
3. Confirm the queue worker is running against the same database/configuration as web.
4. Inspect `jobs`, `failed_jobs`, and `storage/logs/laravel.log`.
5. Confirm the mail provider accepts the configured sender.
6. Retry failed jobs only after correcting the underlying cause.

If neither in-app nor email appears, verify the actor was not the only recipient, mention selection encoded an actual user ID, and the target still has scope access.

See [Mail and queues](../operations/mail-and-queues.md) for operator procedures and [Audit history](../administration/audit-history.md) for evidence semantics.
