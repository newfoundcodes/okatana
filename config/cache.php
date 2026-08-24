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
  'default' => env('CACHE_STORE', 'database'),
  'stores' => [
    'array' => ['driver' => 'array', 'serialize' => false],
    'database' => [
      'driver' => 'database',
      'connection' => env('DB_CACHE_CONNECTION'),
      'table' => env('DB_CACHE_TABLE', 'cache'),
      'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
      'lock_table' => env('DB_CACHE_LOCK_TABLE'),
    ],
    'file' => [
      'driver' => 'file',
      'path' => storage_path('framework/cache/data'),
      'lock_path' => storage_path('framework/cache/data'),
    ],
  ],
  'prefix' => env('CACHE_PREFIX', 'okatana-cache-'),
];
