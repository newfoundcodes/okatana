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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
  use HasUlids;
  use SoftDeletes;

  protected $fillable = [
    'organization_id',
    'project_id',
    'author_id',
    'title',
    'caption',
    'content_html',
    'status',
    'published_at',
    'archived_at',
  ];

  protected function casts(): array
  {
    return [
      'published_at' => 'datetime',
      'archived_at' => 'datetime',
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

  public function author(): BelongsTo
  {
    return $this->belongsTo(User::class, 'author_id');
  }

  public function editors(): BelongsToMany
  {
    return $this->belongsToMany(User::class, 'document_editors')
      ->withPivot(['added_by'])
      ->withTimestamps();
  }

  public function favoritedBy(): BelongsToMany
  {
    return $this->belongsToMany(User::class, 'document_favorites')->withTimestamps();
  }

  public function comments(): HasMany
  {
    return $this->hasMany(DocumentComment::class);
  }

  public function mentions(): BelongsToMany
  {
    return $this->belongsToMany(User::class, 'document_mentions')->withTimestamps();
  }

  public function tags(): BelongsToMany
  {
    return $this->belongsToMany(DocumentTag::class, 'document_tag_assignments')
      ->withTimestamps()
      ->orderBy('document_tags.name');
  }

  public function isEditor(User $user): bool
  {
    return $this->author_id === $user->id || $this->editors()->whereKey($user->id)->exists();
  }
}
