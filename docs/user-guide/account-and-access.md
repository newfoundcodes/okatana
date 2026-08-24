# Account and sign-in

Your Okatana account is installation-wide. Organization membership and project access are added separately, so one account can participate in multiple organizations with different roles.

## Create a local account

From the sign-in screen, choose account creation and supply:

| Field                 | Rule                                                                |
| --------------------- | ------------------------------------------------------------------- |
| Full name             | required, maximum 120 characters                                    |
| Email                 | valid RFC email, maximum 255 characters, unique after normalization |
| Password              | at least 14 characters with mixed case, numbers, and symbols        |
| Password confirmation | must match                                                          |

Okatana lowercases and trims the email before validation. It hashes the password and immediately starts a signed-in session, but protected workspace endpoints remain unavailable until the required activation steps are complete.

### Confirm email ownership

New local accounts always confirm email ownership. Okatana sends an eight-character code formatted as `XXXX-XXXX` and stores only its password hash.

- Default lifetime: 12 minutes.
- Default resend cooldown: 60 seconds, with a hard minimum of 15 seconds.
- Default attempt limit: 8, with a hard minimum of 3.
- A resend replaces the previous active code.
- Verification ignores punctuation and letter casing.

If verification fails because a code expired or reached its attempt limit, request a new code. If no message arrives, ask an operator to check the [mail and queue](../operations/mail-and-queues.md) setup.

### Enroll an authenticator

When `OKATANA_REQUIRE_TWO_FACTOR=true`, email confirmation is followed by authenticator enrollment:

1. Scan the 320 px locally generated QR image with an RFC 6238 TOTP application.
2. Store the displayed manual key securely as a fallback for enrollment transfer.
3. Enter the current six-digit code.

The secret is generated and rendered locally; Okatana does not send it to a QR service. Codes are accepted for the current time step with no extra drift window during setup. After confirmation, the account is admitted to the workspace.

!!! danger "No built-in TOTP recovery flow"

    The current interface does not expose recovery codes or self-service TOTP reset. Protect the enrolled authenticator and manual key. An operator planning account-recovery procedures should account for this explicitly.

## Sign in

With two-factor enforcement enabled, password login creates a temporary challenge session rather than a full authenticated workspace session. Enter the current six-digit authenticator code. A successfully used time step is stored so the same TOTP cannot be replayed.

If enforcement is disabled, existing TOTP secrets remain stored but password login skips the TOTP challenge. Re-enabling enforcement immediately blocks protected workspace access for users who have never enrolled and resumes challenges for enrolled users.

## Accept an invitation

Invitation links use `/join/{token}` and expire after the configured invitation lifetime (seven days by default).

For a new email address:

1. Review the organization, optional project, role, and expiry.
2. Enter a name and a new password meeting the strong-password rule.
3. The invitation proves control of the destination email, so Okatana marks the address verified.
4. Complete authenticator setup when deployment policy requires it.

For an existing account with a local password, acceptance also requires the current password. This prevents possession of a forwarded invitation URL from modifying an existing account. The supplied new password replaces the prior one.

An accepted invitation never lowers an existing organization role. Project-specific invitations also create the explicit project membership.

## Navigate the workspace

The shared sidebar contains:

- **Organizations** — memberships, recent accessible projects, and organization administration.
- **Projects** — a searchable, server-paginated directory across all organizations you can access.
- **Documents** — visible articles, favorites, document audit events, and document integrations.
- **Profile** — avatar and account identity presentation.
- **Settings** — name, email notification preferences, and personal data export.
- **Log-out** — invalidates the session and regenerates its CSRF token.

The top bar contains the notification menu. On smaller screens, the same groups reflow above the main content rather than changing to a different navigation model.

## Manage your profile picture

Open **Profile** to upload, replace, or remove an avatar.

Accepted image formats are JPEG, PNG, GIF, and WebP, with a maximum file size of 5 MB. Replacement removes the old stored file after the new path is saved. Avatars are served through an authenticated endpoint; a viewer can fetch another user’s avatar only when both share an organization.

When no image is present, the interface uses initial-based avatar presentation.

## Change account settings

Open **Settings** to update your full name and email delivery preferences:

| Preference                     | Controls                                                                                    |
| ------------------------------ | ------------------------------------------------------------------------------------------- |
| Assignment emails              | email sent when you are newly assigned to a ticket                                          |
| Mention emails                 | email sent for direct mentions in tickets, ticket comments, documents, or document comments |
| Document-comment notifications | general article-comment fan-out to authors/editors; direct mentions remain separate         |

In-app notifications are still created by collaboration flows; these settings control the corresponding email categories.

## Export your personal data

**Download my data** produces `okatana.user-export.v1` JSON. It can include:

- account identity, verification/TOTP timestamps, preferences, and a base64 avatar up to 5 MB;
- organization and explicit project memberships;
- ticket assignments and tickets created by you;
- comments and ticket/comment revisions attributed to you;
- invitations received and sent;
- audit events where you are the actor;
- data transfers, notifications, and uploaded attachment metadata;
- integration metadata for credentials and webhooks you created.

It excludes password hashes, password-reset tokens, session payloads, email verification code hashes, invitation plaintext tokens, API secret hashes, webhook secrets, private attachment contents, and internal stored paths for uploaded files.

## Session behavior and privacy

Browser requests use Laravel’s session authentication under `/app-api/*`. Sessions use the database by default, are encrypted, last 120 minutes by default, use `HttpOnly`, and use `SameSite=Lax`. Production deployments must enable secure cookies and HTTPS.

The application API returns JSON errors. A protected request made before account activation completes returns `403`, code `account_security_setup_required`, and the current security state; React uses that response to keep the user in the setup flow.

## Common access problems

| Symptom                               | Likely explanation                                                          |
| ------------------------------------- | --------------------------------------------------------------------------- |
| Organization visible, project absent  | ordinary user lacks explicit project membership                             |
| Invitation says gone                  | link expired, was revoked, or was already accepted                          |
| Existing user cannot accept           | current password is required or invitation email differs from account email |
| Login returns to authenticator prompt | this deployment requires TOTP and the password step succeeded               |
| Workspace stays on setup              | email confirmation or required authenticator enrollment is incomplete       |
| Another user’s avatar is 404          | the accounts do not currently share an organization                         |

See [Invitations and membership](../administration/invitations.md) for administrator actions and [OAuth and SSO](../operations/oauth-sso.md) for external identity providers.
