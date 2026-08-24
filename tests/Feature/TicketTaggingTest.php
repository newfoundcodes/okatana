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
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketTaggingTest extends TestCase {
  use RefreshDatabase;

  public function test_project_members_can_tag_tickets_and_filter_by_multiple_used_tags(): void {
    $owner = User::create([
      'name' => 'Owner',
      'email' => 'tags-owner@example.test',
      'password' => 'Password!12345',
    ]);

    $organization = Organization::create([
      'name' => 'Tags Org',
      'slug' => 'tags-org',
      'settings' => [],
    ]);

    OrganizationMember::create([
      'organization_id' => $organization->id,
      'user_id' => $owner->id,
      'role' => 'owner',
    ]);

    $project = Project::create([
      'organization_id' => $organization->id,
      'name' => 'Tags Project',
      'key' => 'TAG',
      'settings' => [],
    ]);

    $board = Board::create([
      'project_id' => $project->id,
      'name' => 'Open',
      'slug' => 'open',
      'position' => 1000,
    ]);

    $first = $this->actingAs($owner)
      ->postJson("/app-api/projects/{$project->id}/tickets", [
        'board_id' => $board->id,
        'title' => 'Backend release',
        'priority' => 'high',
        'tag_names' => ['Backend', 'Urgent'],
      ])
      ->assertCreated();

    $this->actingAs($owner)
      ->postJson("/app-api/projects/{$project->id}/tickets", [
        'board_id' => $board->id,
        'title' => 'Backend cleanup',
        'priority' => 'normal',
        'tag_names' => ['backend'],
      ])
      ->assertCreated();

    self::assertSame(2, Tag::where('project_id', $project->id)->count());

    $backend = Tag::where('project_id', $project->id)
      ->where('normalized_name', 'backend')
      ->firstOrFail();
    $urgent = Tag::where('project_id', $project->id)
      ->where('normalized_name', 'urgent')
      ->firstOrFail();

    self::assertSame(2, $backend->tickets()->count());
    self::assertSame(1, $urgent->tickets()->count());

    $this->actingAs($owner)
      ->getJson("/app-api/projects/{$project->id}")
      ->assertOk()
      ->assertJsonCount(2, 'tags');

    $filtered = $this->actingAs($owner)
      ->getJson("/app-api/projects/{$project->id}/tickets?tag_ids={$backend->id},{$urgent->id}")
      ->assertOk()
      ->assertJsonCount(1, 'tickets');

    self::assertSame($first->json('ticket.id'), $filtered->json('tickets.0.id'));
    self::assertSame(
      ['Backend', 'Urgent'],
      collect($first->json('ticket.tags'))->pluck('name')->sort()->values()->all(),
    );
  }
}
