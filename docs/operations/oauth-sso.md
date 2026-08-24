# OAuth and SSO

Okatana supports configurable generic OAuth 2.0/OIDC providers and application-provided SSO driver adapters. External identity establishes the user account; Okatana’s current deployment policy still controls local TOTP completion/challenge.

## Generic OAuth/OIDC

Google variables are included as the default provider template:

```dotenv
OAUTH_GOOGLE_ENABLED=true
OAUTH_GOOGLE_CLIENT_ID=...
OAUTH_GOOGLE_CLIENT_SECRET=...
OAUTH_GOOGLE_AUTHORIZATION_URL=https://accounts.google.com/o/oauth2/v2/auth
OAUTH_GOOGLE_TOKEN_URL=https://oauth2.googleapis.com/token
OAUTH_GOOGLE_USERINFO_URL=https://openidconnect.googleapis.com/v1/userinfo
OAUTH_GOOGLE_SCOPES="openid email profile"
```

Register this exact callback with the provider:

```text
{APP_URL}/auth/oauth/google/callback
```

The login page discovers enabled provider keys through `/app-api/auth/config`.

### OAuth flow security

Okatana:

1. generates a 48-character state value;
2. generates a 96-character PKCE verifier and S256 challenge;
3. stores state/verifier in the user’s session;
4. validates state with `hash_equals` and consumes the session entry;
5. exchanges the authorization code with a 10-second timeout;
6. fetches user-info using the access token with a 10-second timeout;
7. requires a syntactically valid normalized email;
8. by default requires the configured verified-email field to be truthy;
9. finds or creates a user by email.

New OAuth accounts receive the provider name and verified email, have no local password by default, and get `security_setup_required_at`. If TOTP is required, unenrolled users complete setup; enrolled users complete the normal challenge.

### Additional providers through JSON

`OKATANA_OAUTH_PROVIDERS_JSON` accepts an object keyed by provider name:

```json
{
  "company": {
    "enabled": true,
    "client_id": "...",
    "client_secret": "...",
    "authorization_url": "https://id.example.com/oauth/authorize",
    "token_url": "https://id.example.com/oauth/token",
    "userinfo_url": "https://id.example.com/oauth/userinfo",
    "scopes": "openid email profile",
    "email_field": "email",
    "name_field": "name",
    "id_field": "sub",
    "email_verified_field": "email_verified",
    "require_verified_email": true
  }
}
```

This object merges with built-in provider configuration. Provider names become route segments, so use safe stable keys.

Set `require_verified_email=false` only if a trusted provider/user-info endpoint already guarantees the asserted email. Account linking is email-based; a false verified-email assertion could grant an attacker an existing Okatana identity.

## SSO driver adapter

Create a service implementing `App\Contracts\SsoDriver`:

```php
<?php

namespace App\Sso;

use App\Contracts\SsoDriver;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CompanySamlDriver implements SsoDriver
{
  public function redirect(Request $request): RedirectResponse
  {
    // Delegate to a validated SAML library and preserve request state.
  }

  public function userFromCallback(Request $request): User
  {
    // Validate response, issuer, audience, destination, signature, and time.
    // Map a verified email/name and return an Okatana User.
  }
}
```

Configure:

```dotenv
OKATANA_SAML_DRIVER_CLASS=App\Sso\CompanySamlDriver
```

Routes are:

```text
GET       /auth/sso/saml
GET|POST  /auth/sso/saml/callback
```

`SsoManager` resolves the configured class through Laravel’s container and verifies it implements the contract. The adapter owns all protocol validation and account mapping; Okatana does not include a generic SAML parser.

## TOTP interaction

Identity provider authentication does not bypass `OKATANA_REQUIRE_TWO_FACTOR`:

- enrolled account → temporary Okatana TOTP login challenge;
- unenrolled account with required TOTP → authenticated setup session, then enrollment;
- TOTP enforcement disabled → Okatana session starts without local TOTP.

This is additional application-layer TOTP even if the upstream provider already performed MFA. If that duplication is undesirable, changing the trust/policy model requires code and tests; configuration alone does not mark upstream MFA as local TOTP confirmation.

## Production checklist

- [ ] Canonical HTTPS `APP_URL` matches registered callbacks exactly.
- [ ] Client secrets live in a secret store, not repository or tickets.
- [ ] Provider returns a verified stable email.
- [ ] OAuth state/session cookies survive the redirect through the proxy.
- [ ] PKCE is supported by the provider.
- [ ] SSO adapter validates every protocol security property.
- [ ] Account-linking collisions by email have been tested.
- [ ] TOTP policy interaction is intentional.
- [ ] Provider outages and deprovisioning behavior are documented.
- [ ] Organization membership is removed in Okatana when upstream access should end; authentication alone does not automatically reconcile organization/project memberships.

## Troubleshooting

| Error                                 | Check                                                                   |
| ------------------------------------- | ----------------------------------------------------------------------- |
| Provider not shown                    | enabled value/config cache/provider JSON validity                       |
| OAuth state 419                       | session cookie, domain, HTTPS, proxy, multiple origins, callback replay |
| No usable email                       | configured `email_field` and user-info scopes                           |
| Verified email rejected               | `email_verified_field` value/type; do not disable check casually        |
| Callback URL mismatch                 | `APP_URL` and provider registration                                     |
| SSO 404                               | configured class exists and key is `saml`                               |
| SSO 500                               | class does not implement `SsoDriver`                                    |
| External login followed by local TOTP | expected when deployment requires two-factor                            |
