# Invitations and membership

Invitations establish organization membership and may grant one project in the same transaction. Owners and admins manage them from the organization **Members** tab.

## Send an invitation

Select **Invite member** and enter:

- destination email;
- organization role: owner, admin, or user;
- optional project access from the same organization.

The invitation service normalizes the email, generates a high-entropy plaintext token, stores only its SHA-256 hash, sets the configured expiry, and queues an email notification containing the join URL.

Default lifetime is `OKATANA_INVITATION_TTL_HOURS=168` (seven days). The full token is not recoverable from the database. If delivery is lost and no resend workflow is available, revoke and create a new invitation.

## Pending invitations

Owners/admins can see active pending invitations: accepted/revoked rows and expired rows are excluded. Ordinary users receive an empty pending list even though they may read current membership.

Revoke an invitation when:

- it was sent to the wrong email;
- the role or project selection is wrong;
- the recipient no longer needs access;
- you need to replace a lost or expired invitation.

Revocation sets `revoked_at`; it does not delete the row. The operation records `invitation.revoked`.

## Acceptance behavior

The recipient opens `/join/{token}`. Okatana returns `410 Gone` when the invitation is expired, accepted, or revoked, except while an accepted user is legitimately completing a pending security/TOTP challenge from that same invitation flow.

### New account

The user supplies a name and strong confirmed password. The invitation’s email becomes the account email and is marked verified. Organization membership is created with the invited role; optional project membership is created. Required TOTP setup follows.

### Existing local account

The user must supply the current password plus a new strong confirmed password. Existing display name is retained when already present. The account email must match the invitation email.

### Existing membership

If the account already belongs to the organization, invitation acceptance compares role rank:

```text
user < admin < owner
```

It upgrades only when the invitation role is higher and never demotes. Optional project access is still added. Acceptance timestamps the invitation and records `invitation.accepted` using the accepting user as actor.

## Change a member’s role

Owners/admins can choose another role from the Members table. The update records before/after role values in `organization.member_role_changed`.

Before demoting an owner, confirm another owner exists. Laravel counts current owners and rejects a change that would leave zero.

## Remove a member

Removal:

1. checks owner/admin authority;
2. refuses removal of the last owner;
3. detaches the user from every project in the organization;
4. removes the organization membership row;
5. records `organization.member_removed`.

The account itself is not deleted. Content and audit actor snapshots remain. Ticket/comment foreign keys may retain or null user IDs according to schema behavior; author snapshots preserve human context where implemented.

## Operational checklist

Before inviting a large team:

- [ ] Configure a production mail transport.
- [ ] Run the queue worker continuously.
- [ ] Set `APP_URL` to the public HTTPS origin so invitation links are correct.
- [ ] Decide whether TOTP is required before onboarding.
- [ ] Set a suitable invitation TTL.
- [ ] Create at least two owners.
- [ ] Define which people truly need admin.
- [ ] Create projects before invitations that should include project access.
- [ ] Test the complete join flow with a non-administrator address.

## Troubleshooting

| Result                                | Cause/action                                              |
| ------------------------------------- | --------------------------------------------------------- |
| Email never arrives                   | inspect queue and mail transport; invitation still exists |
| `410` on join                         | expired, revoked, or completed; create a replacement      |
| Existing user gets password error     | current account password is required                      |
| User joined but cannot see project    | invitation had no project or membership was later removed |
| Role remained admin after user invite | lower invitation correctly does not demote                |
| Cannot remove/demote owner            | add/promote another owner first                           |
| Join stops at authenticator           | deployment policy requires TOTP enrollment or challenge   |

See [Account and sign-in](../user-guide/account-and-access.md) for the recipient experience and [Mail and queues](../operations/mail-and-queues.md) for delivery operations.
