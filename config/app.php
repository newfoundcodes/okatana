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
  'name' => env('APP_NAME', 'Okatana'),
  'env' => env('APP_ENV', 'production'),
  'debug' => (bool) env('APP_DEBUG', false),
  'url' => env('APP_URL', 'http://localhost'),
  'timezone' => env('APP_TIMEZONE', 'UTC'),
  'locale' => env('APP_LOCALE', 'en'),
  'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
  'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),
  'cipher' => 'AES-256-CBC',
  'key' => env('APP_KEY'),
  'previous_keys' => array_filter(explode(',', (string) env('APP_PREVIOUS_KEYS', ''))),
  'maintenance' => [
    'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
    'store' => env('APP_MAINTENANCE_STORE', 'database'),
  ],
];
