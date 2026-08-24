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
  'default' => env('QUEUE_CONNECTION', 'database'),
  'connections' => [
    'sync' => ['driver' => 'sync'],
    'database' => [
      'driver' => 'database',
      'connection' => env('DB_QUEUE_CONNECTION'),
      'table' => env('DB_QUEUE_TABLE', 'jobs'),
      'queue' => env('DB_QUEUE', 'default'),
      'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
      'after_commit' => true,
    ],
  ],
  'failed' => [
    'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
    'database' => env('DB_CONNECTION', 'sqlite'),
    'table' => 'failed_jobs',
  ],
];
