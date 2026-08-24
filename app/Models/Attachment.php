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

class Attachment extends Model
{
  use HasUlids;

  protected $fillable = [
    'project_id',
    'ticket_id',
    'ticket_comment_id',
    'uploaded_by',
    'original_name',
    'mime_type',
    'size_bytes',
    'disk',
    'path',
  ];

  protected $hidden = ['path', 'disk'];

  protected $appends = ['download_url'];

  protected function casts(): array
  {
    return ['size_bytes' => 'integer'];
  }

  public function project(): BelongsTo
  {
    return $this->belongsTo(Project::class);
  }

  public function ticket(): BelongsTo
  {
    return $this->belongsTo(Ticket::class);
  }

  public function comment(): BelongsTo
  {
    return $this->belongsTo(TicketComment::class, 'ticket_comment_id');
  }

  public function uploader(): BelongsTo
  {
    return $this->belongsTo(User::class, 'uploaded_by');
  }

  public function getDownloadUrlAttribute(): string
  {
    return '/app-api/attachments/' . $this->getKey() . '/download';
  }
}
