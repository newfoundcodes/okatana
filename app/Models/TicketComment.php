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

class TicketComment extends Model
{
  use HasUlids;
  use SoftDeletes;

  protected $fillable = [
    'ticket_id',
    'user_id',
    'kind',
    'body_html',
    'edited_at',
    'author_snapshot',
  ];

  protected function casts(): array
  {
    return [
      'edited_at' => 'datetime',
      'author_snapshot' => 'array',
    ];
  }

  public function ticket(): BelongsTo
  {
    return $this->belongsTo(Ticket::class);
  }

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  public function revisions(): HasMany
  {
    return $this->hasMany(TicketCommentRevision::class, 'ticket_comment_id')->orderBy('version');
  }

  public function mentions(): BelongsToMany
  {
    return $this->belongsToMany(
      User::class,
      'ticket_comment_mentions',
      'ticket_comment_id',
      'user_id',
    )->withTimestamps();
  }

  public function attachments(): HasMany
  {
    return $this->hasMany(Attachment::class, 'ticket_comment_id')->orderBy('created_at');
  }
}
