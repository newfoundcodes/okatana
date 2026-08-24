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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
  use HasUlids;
  use SoftDeletes;

  protected $fillable = ['name', 'slug', 'description', 'settings'];

  protected function casts(): array
  {
    return ['settings' => 'array'];
  }

  public function members(): BelongsToMany
  {
    return $this->belongsToMany(User::class, 'organization_members')
      ->withPivot(['role'])
      ->withTimestamps();
  }

  public function projects(): HasMany
  {
    return $this->hasMany(Project::class);
  }

  public function webhooks(): HasMany
  {
    return $this->hasMany(WebhookEndpoint::class);
  }

  public function apiCredentials(): HasMany
  {
    return $this->hasMany(ApiCredential::class);
  }

  public function documents(): HasMany
  {
    return $this->hasMany(Document::class);
  }

  public function documentTags(): HasMany
  {
    return $this->hasMany(DocumentTag::class);
  }

  public function roleFor(User $user): ?string
  {
    return $this->members()->whereKey($user->id)->value('organization_members.role');
  }

  public function canAdmin(User $user): bool
  {
    return in_array($this->roleFor($user), ['owner', 'admin'], true);
  }

  public function isOwner(User $user): bool
  {
    return $this->roleFor($user) === 'owner';
  }
}
