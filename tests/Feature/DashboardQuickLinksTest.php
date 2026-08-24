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

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardQuickLinksTest extends TestCase {
  use RefreshDatabase;

  public function test_organization_index_returns_five_most_recent_accessible_projects_by_user_activity(): void {
    $user = User::create([
      'name' => 'Owner',
      'email' => 'quick-owner@example.test',
      'password' => 'Password!12345',
    ]);

    $org = Organization::create([
      'name' => 'Quick Org',
      'slug' => 'quick-org',
      'settings' => [],
    ]);

    OrganizationMember::create([
      'organization_id' => $org->id,
      'user_id' => $user->id,
      'role' => 'owner',
    ]);

    $projects = collect(range(1, 6))->map(function ($index) use ($org, $user) {
      $project = Project::create([
        'organization_id' => $org->id,
        'name' => 'Project ' . $index,
        'key' => 'Q' . $index,
        'settings' => [],
      ]);

      AuditLog::create([
        'organization_id' => $org->id,
        'project_id' => $project->id,
        'event' => 'ticket.updated',
        'actor_type' => 'user',
        'actor_id' => $user->id,
        'actor_name' => $user->name,
        'subject_type' => Project::class,
        'subject_id' => $project->id,
        'subject_label' => $project->name,
        'occurred_at' => now()->subMinutes(7 - $index),
      ]);

      return $project;
    });

    $response = $this->actingAs($user)->getJson('/app-api/organizations')->assertOk();
    $recent = $response->json('recent_projects');

    self::assertCount(5, $recent);
    self::assertSame($projects->last()->id, $recent[0]['id']);
    self::assertNotContains($projects->first()->id, array_column($recent, 'id'));
  }
}
