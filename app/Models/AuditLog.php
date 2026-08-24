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
use LogicException;

class AuditLog extends Model
{
  use HasUlids;

  public $timestamps = false;

  protected $fillable = [
    'organization_id',
    'project_id',
    'event',
    'actor_type',
    'actor_id',
    'actor_name',
    'actor_email',
    'subject_type',
    'subject_id',
    'subject_label',
    'changes',
    'metadata',
    'ip_address',
    'user_agent',
    'occurred_at',
  ];

  protected function casts(): array
  {
    return [
      'changes' => 'array',
      'metadata' => 'array',
      'occurred_at' => 'datetime',
    ];
  }

  protected static function booted(): void
  {
    static::updating(fn() => throw new LogicException('Audit logs are immutable.'));
    static::deleting(fn() => throw new LogicException('Audit logs cannot be deleted.'));
  }
}
