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

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;

return [
  'default' => env('LOG_CHANNEL', 'stack'),
  'deprecations' => [
    'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
    'trace' => env('LOG_DEPRECATIONS_TRACE', false),
  ],
  'channels' => [
    'stack' => [
      'driver' => 'stack',
      'channels' => explode(',', (string) env('LOG_STACK', 'single')),
      'ignore_exceptions' => false,
    ],
    'single' => [
      'driver' => 'single',
      'path' => storage_path('logs/laravel.log'),
      'level' => env('LOG_LEVEL', 'debug'),
      'replace_placeholders' => true,
    ],
    'stderr' => [
      'driver' => 'monolog',
      'level' => env('LOG_LEVEL', 'debug'),
      'handler' => StreamHandler::class,
      'handler_with' => ['stream' => 'php://stderr'],
      'formatter' => env('LOG_STDERR_FORMATTER'),
    ],
    'null' => ['driver' => 'monolog', 'handler' => NullHandler::class],
  ],
];
