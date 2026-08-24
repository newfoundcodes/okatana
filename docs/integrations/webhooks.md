# Webhooks

Webhooks deliver selected Okatana audit events to external HTTP(S) endpoints. Delivery is asynchronous, HMAC-signed, retried, and recorded separately from the business operation.

## Configure an endpoint

An organization owner/admin creates an endpoint with a name, URL, optional project, and event list. The returned secret begins with `whsec_` and must be copied immediately.

Scope behavior:

- no project → matching organization-level and project events across the organization;
- selected project → matching events carrying that project;
- event `*` → every event that reaches `ActivityRecorder`, including new events not yet listed as individual selector options;
- explicit events → exact string matches only.

The UI event catalog is in [Events and scopes](../reference/events-and-scopes.md).

## Request contract

Each delivery is an HTTP POST with a JSON body and headers:

```http
Content-Type: application/json
User-Agent: Okatana-Webhook/1.0
X-Okatana-Event: ticket.moved
X-Okatana-Delivery: 01...
X-Okatana-Timestamp: 178...
X-Okatana-Signature: sha256=<hex-digest>
```

Payload shape:

```json
{
  "id": "01AUDIT...",
  "event": "ticket.moved",
  "occurred_at": "2026-08-23T12:34:56+00:00",
  "organization": {
    "id": "01ORG...",
    "name": "Platform"
  },
  "project": {
    "id": "01PROJECT...",
    "name": "Core",
    "key": "CORE"
  },
  "actor": {
    "type": "user",
    "id": "01USER...",
    "name": "Avery Example",
    "email": "avery@example.com"
  },
  "subject": {
    "type": "App\\Models\\Ticket",
    "id": "01TICKET...",
    "label": "Deploy the release"
  },
  "changes": {
    "board": {
      "before": "01BOARD1...",
      "after": "01BOARD2..."
    }
  },
  "metadata": {
    "from": "In-progress",
    "to": "Deployed"
  }
}
```

`project`, individual actor fields, subject fields, changes, or metadata can be null depending on event context. Consumers must not require every example field.

## Verify the signature

Okatana signs:

```text
HMAC-SHA256(secret, timestamp + "." + raw_request_body)
```

The header prefixes the lowercase hex digest with `sha256=`.

PHP example:

```php
<?php

$secret = getenv('OKATANA_WEBHOOK_SECRET');
$timestamp = $_SERVER['HTTP_X_OKATANA_TIMESTAMP'] ?? '';
$provided = $_SERVER['HTTP_X_OKATANA_SIGNATURE'] ?? '';
$body = file_get_contents('php://input');

$expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);

if (!hash_equals($expected, $provided)) {
  http_response_code(401);
  exit();
}
```

Node example:

```javascript
import { createHmac, timingSafeEqual } from 'node:crypto';

export function verify(secret, timestamp, rawBody, signature) {
  const expected = `sha256=${createHmac('sha256', secret)
    .update(`${timestamp}.`)
    .update(rawBody)
    .digest('hex')}`;

  const left = Buffer.from(expected);
  const right = Buffer.from(signature || '');
  return left.length === right.length && timingSafeEqual(left, right);
}
```

Use the exact raw bytes received. Parsing and re-serializing JSON changes whitespace/key encoding and invalidates the signature.

## Receiver security

After signature verification:

1. Parse timestamp as an integer and reject values outside your chosen tolerance, commonly about five minutes.
2. Store/check `X-Okatana-Delivery` and process a delivery ID once.
3. Optionally confirm body `event` equals `X-Okatana-Event` and body `id` is present.
4. Return a 2xx quickly after durable enqueue; perform slow work asynchronously.
5. Limit body size and JSON depth under receiver policy.
6. Log delivery/event IDs, not the shared secret or sensitive body fields.

The signature includes a timestamp but Okatana does not enforce receiver-side freshness; that is the receiver’s responsibility.

## Delivery behavior

For each matching endpoint, Okatana stores the payload and creates a `pending` delivery, then dispatches `DeliverWebhookJob`. The job:

1. loads delivery/endpoint and marks disabled if endpoint inactive;
2. revalidates destination network safety;
3. serializes stored payload once with unescaped slashes/Unicode;
4. creates timestamp/signature;
5. sends with the configured timeout;
6. stores response code and first 2,000 response characters;
7. marks delivered on 2xx;
8. marks retrying and throws on error;
9. marks failed when attempts are exhausted.

Default timeout is 8 seconds; default maximum attempts is 5; backoff is 10, 60, 300, and 900 seconds.

## SSRF protection

With private networks disallowed, configuration/delivery rejects:

- schemes other than HTTP/HTTPS;
- empty host;
- `localhost` and `*.localhost`;
- hostnames that do not resolve;
- any resolved IPv4/IPv6 address in private or reserved ranges.

DNS is checked again at delivery time. Also enforce egress restrictions at the network layer, because application URL validation is only one defense.

## Rotation and failure handling

Rotating immediately replaces the encrypted secret and returns the new plaintext. There is no built-in overlap window. Coordinate receivers to accept both secrets temporarily when possible, or schedule a brief controlled rejection window.

Non-2xx responses and network errors retry. Okatana may retry after the receiver already accepted work but the connection failed; deduplication is mandatory for non-idempotent side effects.

Endpoint revoke marks it inactive. Pending jobs encountering an inactive endpoint become `disabled`.

## Testing a receiver

1. Use a staging endpoint with a narrow event such as `ticket.created`.
2. Store the secret outside code.
3. Capture raw request bytes before body parsing.
4. Verify signature and timestamp.
5. Create one ticket and return 2xx.
6. Confirm delivery is `delivered` with one attempt.
7. Replay the same delivery ID and confirm the receiver deduplicates.
8. Intentionally return 500 and observe retry/backoff.
9. Rotate secret and confirm old signatures fail/new succeed.
10. Review the associated audit event.

## Common failures

| Failure                                 | Remedy                                                                                     |
| --------------------------------------- | ------------------------------------------------------------------------------------------ |
| Signature mismatch                      | use raw body, exact timestamp, correct current secret, include `sha256=` comparison        |
| Endpoint rejected at save               | public DNS/HTTP(S), or explicitly allow private networks when justified                    |
| Delivery always retrying                | return 2xx quickly; inspect status/excerpt/error                                           |
| Duplicate external action               | deduplicate by delivery ID                                                                 |
| Events missing                          | endpoint active, correct exact event, correct optional project scope, queue worker running |
| Events after adding new feature missing | explicit list lacks new selector; update endpoint or use wildcard knowingly                |
