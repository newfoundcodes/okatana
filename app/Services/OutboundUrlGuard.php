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

namespace App\Services;

class OutboundUrlGuard
{
  public function assertSafe(string $url): void
  {
    if (config('okatana.webhooks.allow_private_networks', false)) {
      return;
    }

    $parts = parse_url($url);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = (string) ($parts['host'] ?? '');

    abort_unless(
      in_array($scheme, ['http', 'https'], true) && $host !== '',
      422,
      'Webhook URL must use HTTP or HTTPS.',
    );

    abort_if(
      strtolower($host) === 'localhost' || str_ends_with(strtolower($host), '.localhost'),
      422,
      'Private webhook targets are disabled.',
    );

    $ips = [];
    if (filter_var($host, FILTER_VALIDATE_IP)) {
      $ips[] = $host;
    } else {
      foreach (dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
        if (isset($record['ip'])) {
          $ips[] = $record['ip'];
        }

        if (isset($record['ipv6'])) {
          $ips[] = $record['ipv6'];
        }
      }
    }

    abort_if(!$ips, 422, 'Webhook hostname did not resolve.');
    foreach ($ips as $ip) {
      abort_unless(
        filter_var(
          $ip,
          FILTER_VALIDATE_IP,
          FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false,
        422,
        'Webhook target resolves to a private or reserved address.',
      );
    }
  }
}
