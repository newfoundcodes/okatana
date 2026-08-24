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

use App\Models\Document;
use App\Models\DocumentTag;
use App\Models\Organization;
use Illuminate\Support\Str;

class DocumentTagService
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
      $normalized[Str::lower($display)] ??= $display;
    }

    abort_if(count($normalized) > 20, 422, 'A document may have at most 20 tags.');
    return array_values($normalized);
  }

  public function syncDocument(Document $document, array $names): void
  {
    $names = $this->normalizeNames($names);
    $ids = [];

    foreach ($names as $name) {
      $tag = DocumentTag::firstOrCreate(
        [
          'organization_id' => $document->organization_id,
          'normalized_name' => Str::lower($name),
        ],
        ['name' => $name],
      );
      $ids[] = $tag->id;
    }

    $document->tags()->sync($ids);
  }

  public function usedTags(Organization $organization)
  {
    return $organization
      ->documentTags()
      ->whereHas('documents')
      ->withCount('documents')
      ->orderBy('name')
      ->get(['id', 'organization_id', 'name']);
  }
}
