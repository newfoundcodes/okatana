<?php

/*
 * Okatana - A Newfoundcodes project.
 *
 * Copyright (C) 2026 Jonathan Eldy Baldivicio
 *
 * Author: Jonathan Eldy Baldivicio
 * Contact: jonathaneldy.baldivicio@newfoundcodes.com
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

return [
  'invitation_ttl_hours' => (int) env('OKATANA_INVITATION_TTL_HOURS', 168),
  'webhooks' => [
    'timeout_seconds' => (int) env('OKATANA_WEBHOOK_TIMEOUT_SECONDS', 8),
    'max_attempts' => (int) env('OKATANA_WEBHOOK_MAX_ATTEMPTS', 5),
    'allow_private_networks' => (bool) env('OKATANA_WEBHOOK_ALLOW_PRIVATE_NETWORKS', false),
  ],
  'api_rate_limit' => (int) env('OKATANA_API_RATE_LIMIT', 120),
  'security' => [
    'two_factor_required' => filter_var(
      env('OKATANA_REQUIRE_TWO_FACTOR', true),
      FILTER_VALIDATE_BOOL,
    ),
    'email_code_ttl_minutes' => (int) env('OKATANA_EMAIL_CODE_TTL_MINUTES', 12),
    'email_code_resend_seconds' => (int) env('OKATANA_EMAIL_CODE_RESEND_SECONDS', 60),
    'email_code_max_attempts' => (int) env('OKATANA_EMAIL_CODE_MAX_ATTEMPTS', 8),
  ],
  'api_docs' => [
    'public' => filter_var(env('OKATANA_API_DOCS_PUBLIC', true), FILTER_VALIDATE_BOOL),
    'spec_path' => env('OKATANA_OPENAPI_PATH', '/docs/openapi.yaml'),
  ],
  'oauth' => array_merge(
    [
      'google' => [
        'enabled' => (bool) env('OAUTH_GOOGLE_ENABLED', false),
        'client_id' => env('OAUTH_GOOGLE_CLIENT_ID'),
        'client_secret' => env('OAUTH_GOOGLE_CLIENT_SECRET'),
        'authorization_url' => env('OAUTH_GOOGLE_AUTHORIZATION_URL'),
        'token_url' => env('OAUTH_GOOGLE_TOKEN_URL'),
        'userinfo_url' => env('OAUTH_GOOGLE_USERINFO_URL'),
        'scopes' => env('OAUTH_GOOGLE_SCOPES', 'openid email profile'),
        'email_field' => 'email',
        'name_field' => 'name',
        'id_field' => 'sub',
        'email_verified_field' => 'email_verified',
        'require_verified_email' => true,
      ],
    ],
    json_decode(env('OKATANA_OAUTH_PROVIDERS_JSON', '[]'), true) ?: [],
  ),
  'sso_drivers' => array_filter([
    'saml' => env('OKATANA_SAML_DRIVER_CLASS'),
  ]),
];
