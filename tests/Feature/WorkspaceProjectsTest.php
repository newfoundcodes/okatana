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

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceProjectsTest extends TestCase {
  use RefreshDatabase;

  public function test_workspace_projects_returns_only_currently_accessible_projects_with_pagination(): void {
    $user = User::create([
      'name' => 'Member',
      'email' => 'member@example.test',
      'password' => 'Password!12345',
    ]);

    $other = User::create([
      'name' => 'Other',
      'email' => 'other@example.test',
      'password' => 'Password!12345',
    ]);

    $memberOrg = Organization::create([
      'name' => 'Member Org',
      'slug' => 'member-org',
      'settings' => [],
    ]);

    OrganizationMember::create([
      'organization_id' => $memberOrg->id,
      'user_id' => $user->id,
      'role' => 'user',
    ]);

    OrganizationMember::create([
      'organization_id' => $memberOrg->id,
      'user_id' => $other->id,
      'role' => 'owner',
    ]);

    $accessible = collect(range(1, 11))->map(function ($index) use ($memberOrg, $user) {
      $project = Project::create([
        'organization_id' => $memberOrg->id,
        'name' => 'Accessible ' . $index,
        'key' => 'A' . $index,
        'settings' => [],
      ]);

      ProjectMember::create([
        'project_id' => $project->id,
        'user_id' => $user->id
      ]);

      return $project;
    });

    Project::create([
      'organization_id' => $memberOrg->id,
      'name' => 'Hidden project',
      'key' => 'HIDE',
      'settings' => [],
    ]);

    $response = $this->actingAs($user)->getJson('/app-api/projects?per_page=10&page=1')->assertOk();

    self::assertSame(11, $response->json('pagination.total'));
    self::assertCount(10, $response->json('projects'));
    self::assertNotContains('Hidden project', array_column($response->json('projects'), 'name'));
    self::assertSame('Member Org', $response->json('projects.0.organization.name'));

    $this->actingAs($user)
      ->getJson('/app-api/projects?q=' . urlencode($accessible->last()->name))
      ->assertOk()
      ->assertJsonPath('pagination.total', 1)
      ->assertJsonPath('projects.0.id', $accessible->last()->id);
  }

  public function test_organization_admin_sees_all_projects_in_their_organization(): void {
    $admin = User::create([
      'name' => 'Admin',
      'email' => 'admin@example.test',
      'password' => 'Password!12345',
    ]);

    $organization = Organization::create([
      'name' => 'Admin Org',
      'slug' => 'admin-org',
      'settings' => [],
    ]);

    OrganizationMember::create([
      'organization_id' => $organization->id,
      'user_id' => $admin->id,
      'role' => 'admin',
    ]);

    $project = Project::create([
      'organization_id' => $organization->id,
      'name' => 'Admin project',
      'key' => 'ADM',
      'settings' => [],
    ]);

    $this->actingAs($admin)
      ->getJson('/app-api/projects')
      ->assertOk()
      ->assertJsonPath('pagination.total', 1)
      ->assertJsonPath('projects.0.id', $project->id);
  }
}
