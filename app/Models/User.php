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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
  use HasUlids;
  use Notifiable;

  protected $fillable = [
    'name',
    'email',
    'password',
    'email_verified_at',
    'avatar_path',
    'email_notify_assigned',
    'email_notify_mentions',
    'notify_document_comments',
    'security_setup_required_at',
    'two_factor_secret',
    'two_factor_confirmed_at',
    'two_factor_last_used_step',
  ];

  protected $hidden = [
    'password',
    'remember_token',
    'avatar_path',
    'security_setup_required_at',
    'two_factor_secret',
    'two_factor_confirmed_at',
    'two_factor_last_used_step',
  ];

  protected $appends = ['avatar_url'];

  protected function casts(): array
  {
    return [
      'email_verified_at' => 'datetime',
      'password' => 'hashed',
      'email_notify_assigned' => 'boolean',
      'email_notify_mentions' => 'boolean',
      'notify_document_comments' => 'boolean',
      'security_setup_required_at' => 'datetime',
      'two_factor_secret' => 'encrypted',
      'two_factor_confirmed_at' => 'datetime',
      'two_factor_last_used_step' => 'integer',
    ];
  }

  public function organizations(): BelongsToMany
  {
    return $this->belongsToMany(Organization::class, 'organization_members')
      ->withPivot(['role'])
      ->withTimestamps();
  }

  public function projects(): BelongsToMany
  {
    return $this->belongsToMany(Project::class, 'project_members')->withTimestamps();
  }

  public function authoredDocuments(): HasMany
  {
    return $this->hasMany(Document::class, 'author_id');
  }

  public function editableDocuments(): BelongsToMany
  {
    return $this->belongsToMany(Document::class, 'document_editors')->withTimestamps();
  }

  public function favoriteDocuments(): BelongsToMany
  {
    return $this->belongsToMany(Document::class, 'document_favorites')->withTimestamps();
  }

  public function getAvatarUrlAttribute(): ?string
  {
    return $this->avatar_path
      ? '/app-api/users/' . $this->getKey() . '/avatar?v=' . substr(sha1($this->avatar_path), 0, 12)
      : null;
  }
}
