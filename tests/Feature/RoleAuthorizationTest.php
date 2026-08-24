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
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase {
  use RefreshDatabase;

  public function test_ordinary_member_cannot_modify_organization_but_can_work_on_explicit_project(): void {
    $owner = User::create([
      'name' => 'Owner',
      'email' => 'owner@example.test',
      'password' => 'Password!12345',
    ]);

    $member = User::create([
      'name' => 'Member',
      'email' => 'member@example.test',
      'password' => 'Password!12345',
    ]);

    $org = Organization::create([
      'name' => 'Acme',
      'slug' => 'acme',
      'settings' => []
    ]);

    OrganizationMember::create([
      'organization_id' => $org->id,
      'user_id' => $owner->id,
      'role' => 'owner',
    ]);

    OrganizationMember::create([
      'organization_id' => $org->id,
      'user_id' => $member->id,
      'role' => 'user',
    ]);

    $project = Project::create([
      'organization_id' => $org->id,
      'name' => 'Core',
      'key' => 'CORE',
      'settings' => [],
    ]);

    ProjectMember::create([
      'project_id' => $project->id,
      'user_id' => $member->id
    ]);

    $board = Board::create([
      'project_id' => $project->id,
      'name' => 'Open',
      'slug' => 'open',
      'position' => 1000,
    ]);

    $this->actingAs($member)
      ->patchJson("/app-api/organizations/{$org->id}", ['name' => 'Changed'])
      ->assertForbidden();

    $this->actingAs($member)
      ->postJson("/app-api/projects/{$project->id}/tickets", [
        'board_id' => $board->id,
        'title' => 'Allowed project work',
        'priority' => 'normal',
      ])
      ->assertCreated()
      ->assertJsonPath('ticket.title', 'Allowed project work');
  }

  public function test_board_management_is_restricted_to_org_admins_and_owners(): void {
    $member = User::create([
      'name' => 'Member',
      'email' => 'member2@example.test',
      'password' => 'Password!12345',
    ]);

    $org = Organization::create([
      'name' => 'Acme',
      'slug' => 'acme-2',
      'settings' => []
    ]);

    OrganizationMember::create([
      'organization_id' => $org->id,
      'user_id' => $member->id,
      'role' => 'user',
    ]);

    $project = Project::create([
      'organization_id' => $org->id,
      'name' => 'Core',
      'key' => 'CORE',
      'settings' => [],
    ]);

    ProjectMember::create([
      'project_id' => $project->id,
      'user_id' => $member->id
    ]);

    $this->actingAs($member)
      ->postJson("/app-api/projects/{$project->id}/boards", ['name' => 'QA'])
      ->assertForbidden();
  }
}
