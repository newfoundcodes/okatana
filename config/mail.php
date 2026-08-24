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
  'default' => env('MAIL_MAILER', 'log'),
  'mailers' => [
    'smtp' => [
      'transport' => 'smtp',
      'scheme' => env('MAIL_SCHEME'),
      'url' => env('MAIL_URL'),
      'host' => env('MAIL_HOST', '127.0.0.1'),
      'port' => env('MAIL_PORT', 2525),
      'username' => env('MAIL_USERNAME'),
      'password' => env('MAIL_PASSWORD'),
      'timeout' => null,
      'local_domain' => env(
        'MAIL_EHLO_DOMAIN',
        parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST),
      ),
    ],
    'log' => ['transport' => 'log', 'channel' => env('MAIL_LOG_CHANNEL')],
    'array' => ['transport' => 'array'],
  ],
  'from' => [
    'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
    'name' => env('MAIL_FROM_NAME', 'Okatana'),
  ],
];
