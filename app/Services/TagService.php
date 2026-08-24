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

namespace App\Services;

use App\Models\Project;
use App\Models\Tag;
use App\Models\Ticket;
use Illuminate\Support\Str;

class TagService
{
  public function normalizeNames(array $names): array
  {
    $normalized = [];

    foreach ($names as $raw) {
      if (!is_string($raw)) {
        continue;
      }

      $display = preg_replace('/\s+/u', ' ', trim($raw));
      $display = ltrim($display, "# \t\n\r\0\x0B");
      $display = trim($display);

      if ($display === '') {
        continue;
      }

      abort_if(Str::length($display) > 50, 422, 'Tags may not be longer than 50 characters.');

      $key = Str::lower($display);
      $normalized[$key] ??= $display;
    }

    abort_if(count($normalized) > 20, 422, 'A ticket may have at most 20 tags.');
    return array_values($normalized);
  }

  public function syncTicket(Ticket $ticket, array $names): void
  {
    $names = $this->normalizeNames($names);
    $ids = [];

    foreach ($names as $name) {
      $normalized = Str::lower($name);
      $tag = Tag::firstOrCreate(
        ['project_id' => $ticket->project_id, 'normalized_name' => $normalized],
        ['name' => $name],
      );

      $ids[] = $tag->id;
    }

    $ticket->tags()->sync($ids);
  }

  public function usedTags(Project $project)
  {
    return $project
      ->tags()
      ->whereHas('tickets')
      ->withCount('tickets')
      ->orderBy('name')
      ->get(['id', 'project_id', 'name']);
  }
}
