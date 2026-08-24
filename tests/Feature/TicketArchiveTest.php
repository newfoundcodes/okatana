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
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketArchiveTest extends TestCase {
  use RefreshDatabase;

  public function test_project_member_can_archive_filter_and_unarchive_a_ticket(): void {
    $owner = User::create([
      'name' => 'Owner',
      'email' => 'archive-owner@example.test',
      'password' => 'Password!12345',
    ]);

    $org = Organization::create([
      'name' => 'Archive Org',
      'slug' => 'archive-org',
      'settings' => [],
    ]);

    OrganizationMember::create([
      'organization_id' => $org->id,
      'user_id' => $owner->id,
      'role' => 'owner',
    ]);

    $project = Project::create([
      'organization_id' => $org->id,
      'name' => 'Archive Project',
      'key' => 'ARC',
      'settings' => [],
    ]);

    $board = Board::create([
      'project_id' => $project->id,
      'name' => 'Open',
      'slug' => 'open',
      'position' => 1000,
    ]);

    $ticket = Ticket::create([
      'project_id' => $project->id,
      'board_id' => $board->id,
      'number' => 1,
      'title' => 'Archive me',
      'priority' => 'normal',
      'position' => 1000,
      'created_by' => $owner->id,
      'updated_by' => $owner->id,
    ]);

    $this->actingAs($owner)
      ->patchJson("/app-api/tickets/{$ticket->id}", ['archived' => true])
      ->assertOk()
      ->assertJsonPath('ticket.id', $ticket->id);

    self::assertNotNull($ticket->fresh()->archived_at);

    $this->actingAs($owner)
      ->getJson("/app-api/projects/{$project->id}/tickets?archived=1")
      ->assertOk()
      ->assertJsonPath('tickets.0.id', $ticket->id);

    $this->actingAs($owner)
      ->patchJson("/app-api/tickets/{$ticket->id}", ['archived' => false])
      ->assertOk();

    self::assertNull($ticket->fresh()->archived_at);
  }
}
