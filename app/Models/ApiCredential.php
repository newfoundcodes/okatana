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

class ApiCredential extends Model
{
  use HasUlids;

  protected $fillable = [
    'organization_id',
    'name',
    'public_id',
    'secret_hash',
    'scopes',
    'created_by',
    'last_used_at',
    'expires_at',
    'revoked_at',
  ];

  protected $hidden = ['secret_hash'];

  protected function casts(): array
  {
    return [
      'scopes' => 'array',
      'last_used_at' => 'datetime',
      'expires_at' => 'datetime',
      'revoked_at' => 'datetime',
    ];
  }

  public function organization(): BelongsTo
  {
    return $this->belongsTo(Organization::class);
  }

  public function allows(string $scope): bool
  {
    $scopes = $this->scopes ?? [];
    return in_array('*', $scopes, true) || in_array($scope, $scopes, true);
  }

  public function active(): bool
  {
    return !$this->revoked_at && (!$this->expires_at || $this->expires_at->isFuture());
  }
}
