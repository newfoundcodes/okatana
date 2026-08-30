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

class Ticket extends Model
{
  use HasUlids;
  use SoftDeletes;

  protected $fillable = [
    'project_id',
    'board_id',
    'number',
    'title',
    'description_html',
    'priority',
    'position',
    'due_at',
    'started_at',
    'completed_at',
    'edited_at',
    'created_by',
    'updated_by',
    'api_credential_id',
    'archived_at',
  ];

  protected function casts(): array
  {
    return [
      'due_at' => 'datetime',
      'started_at' => 'datetime',
      'completed_at' => 'datetime',
      'edited_at' => 'datetime',
      'archived_at' => 'datetime',
    ];
  }

  public function project(): BelongsTo
  {
    return $this->belongsTo(Project::class);
  }

  public function board(): BelongsTo
  {
    return $this->belongsTo(Board::class);
  }

  public function creator(): BelongsTo
  {
    return $this->belongsTo(User::class, 'created_by');
  }

  public function apiCredential(): BelongsTo
  {
    return $this->belongsTo(ApiCredential::class);
  }

  public function assignees(): BelongsToMany
  {
    return $this->belongsToMany(User::class, 'ticket_assignees')->withTimestamps();
  }

  public function labels(): BelongsToMany
  {
    return $this->belongsToMany(Label::class, 'ticket_labels')->withTimestamps();
  }

  public function tags(): BelongsToMany
  {
    return $this->belongsToMany(Tag::class, 'ticket_tags')->withTimestamps();
  }

  public function mentions(): BelongsToMany
  {
    return $this->belongsToMany(User::class, 'ticket_mentions')->withTimestamps();
  }

  public function attachments(): HasMany
  {
    return $this->hasMany(Attachment::class)->orderBy('created_at');
  }

  public function comments(): HasMany
  {
    return $this->hasMany(TicketComment::class)->orderBy('created_at');
  }

  public function revisions(): HasMany
  {
    return $this->hasMany(TicketRevision::class)->orderBy('version');
  }
}
