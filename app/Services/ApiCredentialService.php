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

use App\Models\ApiCredential;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Str;

class ApiCredentialService
{
  public function create(
    Organization $org,
    User $creator,
    string $name,
    array $scopes,
    $expiresAt = null,
  ): array {
    $public = 'oka_' . strtolower(Str::random(12));
    $secret = Str::random(48);

    $credential = ApiCredential::create([
      'organization_id' => $org->id,
      'name' => $name,
      'public_id' => $public,
      'secret_hash' => hash('sha256', $secret),
      'scopes' => array_values(array_unique($scopes)),
      'created_by' => $creator->id,
      'expires_at' => $expiresAt,
    ]);

    return [$credential, $public . '.' . $secret];
  }
}
