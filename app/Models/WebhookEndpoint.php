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
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEndpoint extends Model
{
  use HasUlids;

  protected $fillable = [
    'organization_id',
    'project_id',
    'name',
    'url',
    'secret',
    'events',
    'is_active',
    'created_by',
    'last_rotated_at',
  ];

  protected $hidden = ['secret'];

  protected function casts(): array
  {
    return [
      'secret' => 'encrypted',
      'events' => 'array',
      'is_active' => 'boolean',
      'last_rotated_at' => 'datetime',
    ];
  }

  public function organization(): BelongsTo
  {
    return $this->belongsTo(Organization::class);
  }

  public function project(): BelongsTo
  {
    return $this->belongsTo(Project::class);
  }

  public function deliveries(): HasMany
  {
    return $this->hasMany(WebhookDelivery::class);
  }
}
