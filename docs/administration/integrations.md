# Integrations

Organization owners/admins manage outgoing webhooks and external API credentials from **Integrations**. The Documents workspace also provides a focused integration directory for document-related scopes and events.

## Choose a mechanism

| Need                                     | Use                                                     |
| ---------------------------------------- | ------------------------------------------------------- |
| External system reads or changes Okatana | HTTP API credential                                     |
| Okatana announces an event               | Webhook endpoint                                        |
| External system alerts Okatana users     | HTTP API with `notifications:write`                     |
| Human explores/tries endpoints           | Scalar at `/docs/api`                                   |
| Browser application calls Laravel        | internal session `/app-api`, not an external credential |

## API credentials

Select **Create credential** and supply a name, one or more scopes, and optional future expiry. The response shows the complete bearer token exactly once.

Immediately copy it to a secret manager. Okatana stores the public lookup ID and SHA-256 secret hash; it cannot reveal the secret later. If lost, revoke and replace it.

### Scope design

- Prefer the smallest specific read/write scopes.
- Use separate credentials for separate external systems and environments.
- Add an expiry when the client can rotate before it.
- Avoid `*` unless a trusted integration genuinely requires every current and future operation.
- Remember scopes never cross the owning organization.

The credential directory shows name, public ID, scopes, creation, expiry, last use, and revocation. Successful authentication updates `last_used_at` quietly without creating audit noise. Creation and revocation are audited.

## Webhook endpoints

Create an endpoint with:

- descriptive name;
- HTTP(S) destination URL;
- optional project scope;
- one or more event selectors, including wildcard `*`.

The response reveals a `whsec_...` secret. Unlike API secrets, it is stored with Laravel’s encrypted cast because delivery jobs must recover it to sign requests. The value is shown on creation/rotation, then hidden from ordinary reads.

### Network safety

By default, Okatana resolves the destination and rejects localhost, private, or reserved IP addresses. The guard runs both during configuration and immediately before each queued delivery, reducing DNS rebinding exposure.

Only set `OKATANA_WEBHOOK_ALLOW_PRIVATE_NETWORKS=true` when the deployment intentionally needs internal destinations and network policy already constrains egress.

### Lifecycle

- **Update** changes name, URL, selected events, or active state.
- **Rotate** replaces the secret and returns the new value once.
- **Revoke/Delete** marks the endpoint inactive rather than deleting its history.
- **Deliveries** shows latest attempts, status, response status/excerpt, and errors.

There is no browser retry button in the current interface. Correct the endpoint and rely on queued retry for active jobs; design receivers to handle repeated delivery IDs safely.

## Audit relationship

Every integration configuration change is audited. Every domain audit event is evaluated against active matching endpoints. The delivery payload is stored once before dispatch, so later retries sign and send that stored representation rather than rebuilding current domain state.

## Secret rotation runbook

### API credential

1. Create a replacement with equal or narrower scopes.
2. Store and deploy the new token to the client.
3. Verify successful requests and updated last-use time.
4. Revoke the old credential.
5. Review audit records.

### Webhook secret

1. Prepare the receiver to accept both old/new secrets if it supports overlap.
2. Rotate in Okatana and immediately store the returned secret.
3. Update the receiver.
4. Verify a new delivery signature.
5. Remove the old receiver secret.

Okatana replaces the old webhook secret immediately, so receivers without dual-secret handling may reject deliveries during the change window. Schedule rotation accordingly.

## Monitor integration health

- Review API credential `last_used_at` and expiry.
- Revoke unused credentials.
- Review webhook delivery statuses and HTTP excerpts.
- Keep a queue worker running; webhook delivery is asynchronous.
- Alert on growth in `failed_jobs` and `webhook_deliveries.status=failed`.
- Investigate unexpected `api_credential` actors in audit history.
- Never place bearer tokens or webhook secrets in ticket/document rich text or export bundles.

Continue with the [HTTP API guide](../integrations/http-api.md), [Endpoint reference](../API.md), and [Webhooks](../integrations/webhooks.md).
