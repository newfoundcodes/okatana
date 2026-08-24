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

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Project;
use App\Models\User;
use App\Services\ApiCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalApiTest extends TestCase {
  use RefreshDatabase;

  public function test_scoped_api_credential_can_create_ticket_and_revocation_blocks_it(): void {
    $owner = User::create([
      'name' => 'Owner',
      'email' => 'api-owner@example.test',
      'password' => 'Password!12345',
    ]);

    $org = Organization::create([
      'name' => 'API Org',
      'slug' => 'api-org',
      'settings' => []
    ]);

    OrganizationMember::create([
      'organization_id' => $org->id,
      'user_id' => $owner->id,
      'role' => 'owner',
    ]);

    $project = Project::create([
      'organization_id' => $org->id,
      'name' => 'API Project',
      'key' => 'API',
      'settings' => [],
    ]);

    $board = Board::create([
      'project_id' => $project->id,
      'name' => 'Open',
      'slug' => 'open',
      'position' => 1000,
    ]);

    [$credential, $token] = app(ApiCredentialService::class)->create($org, $owner, 'CI', [
      'tickets:write',
    ]);

    $this->withToken($token)
      ->postJson("/api/v1/projects/{$project->id}/tickets", [
        'board_id' => $board->id,
        'title' => 'Created by external API',
      ])
      ->assertCreated()
      ->assertJsonPath('data.title', 'Created by external API');

    $credential->update(['revoked_at' => now()]);
    $this->withToken($token)
      ->postJson("/api/v1/projects/{$project->id}/tickets", [
        'board_id' => $board->id,
        'title' => 'Must fail',
      ])
      ->assertUnauthorized();
  }
}
