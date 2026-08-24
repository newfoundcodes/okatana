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

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
  use HasUlids;

  protected $fillable = [
    'webhook_endpoint_id',
    'event',
    'payload',
    'status',
    'attempts',
    'response_status',
    'response_body_excerpt',
    'last_error',
    'delivered_at',
    'next_attempt_at',
  ];

  protected function casts(): array
  {
    return [
      'payload' => 'array',
      'delivered_at' => 'datetime',
      'next_attempt_at' => 'datetime',
    ];
  }

  public function endpoint(): BelongsTo
  {
    return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
  }
}
