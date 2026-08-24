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
use App\Models\Label;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsMemberFilterTest extends TestCase {
  use RefreshDatabase;

  public function test_project_analytics_can_be_filtered_to_one_assignee(): void {
    $owner = User::create([
      'name' => 'Owner',
      'email' => 'analytics-owner@example.test',
      'password' => 'Password!12345',
    ]);

    $alpha = User::create([
      'name' => 'Alpha',
      'email' => 'alpha@example.test',
      'password' => 'Password!12345',
    ]);

    $beta = User::create([
      'name' => 'Beta',
      'email' => 'beta@example.test',
      'password' => 'Password!12345',
    ]);

    $org = Organization::create([
      'name' => 'Analytics Org',
      'slug' => 'analytics-org',
      'settings' => [],
    ]);

    OrganizationMember::create([
      'organization_id' => $org->id,
      'user_id' => $owner->id,
      'role' => 'owner',
    ]);

    OrganizationMember::create([
      'organization_id' => $org->id,
      'user_id' => $alpha->id,
      'role' => 'user',
    ]);

    OrganizationMember::create([
      'organization_id' => $org->id,
      'user_id' => $beta->id,
      'role' => 'user',
    ]);

    $project = Project::create([
      'organization_id' => $org->id,
      'name' => 'Analytics Project',
      'key' => 'ANL',
      'settings' => [],
    ]);

    ProjectMember::create(['project_id' => $project->id, 'user_id' => $alpha->id]);
    ProjectMember::create(['project_id' => $project->id, 'user_id' => $beta->id]);

    $open = Board::create([
      'project_id' => $project->id,
      'name' => 'Open',
      'slug' => 'open',
      'position' => 1000,
    ]);

    $done = Board::create([
      'project_id' => $project->id,
      'name' => 'Done',
      'slug' => 'done',
      'position' => 2000,
      'is_done' => true,
    ]);

    $alphaTicket = Ticket::create([
      'project_id' => $project->id,
      'board_id' => $open->id,
      'number' => 1,
      'title' => 'Alpha ticket',
      'priority' => 'high',
      'position' => 1000,
      'created_by' => $owner->id,
      'updated_by' => $owner->id,
    ]);

    $alphaTicket->assignees()
      ->attach($alpha->id);
    $betaTicket = Ticket::create([
      'project_id' => $project->id,
      'board_id' => $done->id,
      'number' => 2,
      'title' => 'Beta ticket',
      'priority' => 'low',
      'position' => 1000,
      'completed_at' => now(),
      'created_by' => $owner->id,
      'updated_by' => $owner->id,
    ]);
    $betaTicket->assignees()
      ->attach($beta->id);

    $this->actingAs($owner)
      ->getJson("/app-api/projects/{$project->id}/analytics")
      ->assertOk()
      ->assertJsonPath('summary.total', 2);

    $this->actingAs($owner)
      ->getJson("/app-api/projects/{$project->id}/analytics?member_id={$alpha->id}")
      ->assertOk()
      ->assertJsonPath('member_id', $alpha->id)
      ->assertJsonPath('summary.total', 1)
      ->assertJsonPath('summary.open', 1)
      ->assertJsonPath('summary.done', 0)
      ->assertJsonPath('by_priority.high', 1);
  }

  public function test_project_analytics_supports_priority_phase_label_tag_overdue_and_archive_filters(): void {
    $owner = User::create([
      'name' => 'Owner',
      'email' => 'analytics-filters-owner@example.test',
      'password' => 'Password!12345',
    ]);

    $org = Organization::create([
      'name' => 'Filter Org',
      'slug' => 'analytics-filter-org',
      'settings' => [],
    ]);

    OrganizationMember::create([
      'organization_id' => $org->id,
      'user_id' => $owner->id,
      'role' => 'owner',
    ]);

    $project = Project::create([
      'organization_id' => $org->id,
      'name' => 'Filter Project',
      'key' => 'FLT',
      'settings' => [],
    ]);

    $open = Board::create([
      'project_id' => $project->id,
      'name' => 'Open',
      'slug' => 'open',
      'position' => 1000,
    ]);

    $done = Board::create([
      'project_id' => $project->id,
      'name' => 'Done',
      'slug' => 'done',
      'position' => 2000,
      'is_done' => true,
    ]);

    $label = Label::create([
      'project_id' => $project->id,
      'name' => 'Backend',
      'color' => '#595959',
    ]);

    $tag = Tag::create([
      'project_id' => $project->id,
      'name' => 'urgent',
      'normalized_name' => 'urgent',
    ]);

    $matching = Ticket::create([
      'project_id' => $project->id,
      'board_id' => $open->id,
      'number' => 1,
      'title' => 'Matching',
      'priority' => 'high',
      'position' => 1000,
      'due_at' => now()->subDay(),
      'created_by' => $owner->id,
      'updated_by' => $owner->id,
    ]);

    $matching->labels()
      ->attach($label->id);
    $matching->tags()
      ->attach($tag->id);

    Ticket::create([
      'project_id' => $project->id,
      'board_id' => $done->id,
      'number' => 2,
      'title' => 'Other',
      'priority' => 'low',
      'position' => 1000,
      'created_by' => $owner->id,
      'updated_by' => $owner->id,
    ]);

    Ticket::create([
      'project_id' => $project->id,
      'board_id' => $open->id,
      'number' => 3,
      'title' => 'Archived',
      'priority' => 'high',
      'position' => 2000,
      'archived_at' => now(),
      'created_by' => $owner->id,
      'updated_by' => $owner->id,
    ]);

    $this->actingAs($owner)
      ->getJson(
        "/app-api/projects/{$project->id}/analytics?priority=high&board_id={$open->id}&label_id={$label->id}&tag_ids={$tag->id}&overdue=1",
      )
      ->assertOk()
      ->assertJsonPath('summary.total', 1)
      ->assertJsonPath('summary.overdue', 1)
      ->assertJsonPath('filters.archived', false);

    $this->actingAs($owner)
      ->getJson("/app-api/projects/{$project->id}/analytics?archived=1&priority=high")
      ->assertOk()
      ->assertJsonPath('summary.total', 1)
      ->assertJsonPath('filters.archived', true);
  }
}
