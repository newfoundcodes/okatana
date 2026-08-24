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
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketMovementHistoryTest extends TestCase {
  use RefreshDatabase;

  public function test_moving_a_ticket_creates_an_immutable_movement_comment(): void {
    $owner = User::create([
      'name' => 'Mover',
      'email' => 'mover@example.test',
      'password' => 'Password!12345',
    ]);

    $org = Organization::create([
      'name' => 'Move Org',
      'slug' => 'move-org',
      'settings' => []
    ]);

    OrganizationMember::create([
      'organization_id' => $org->id,
      'user_id' => $owner->id,
      'role' => 'owner',
    ]);

    $project = Project::create([
      'organization_id' => $org->id,
      'name' => 'Move Project',
      'key' => 'MOVE',
      'settings' => [],
    ]);

    $from = Board::create([
      'project_id' => $project->id,
      'name' => 'In-progress',
      'slug' => 'in-progress',
      'position' => 1000,
    ]);

    $to = Board::create([
      'project_id' => $project->id,
      'name' => 'Deployed',
      'slug' => 'deployed',
      'position' => 2000,
      'is_done' => true,
    ]);

    $ticket = Ticket::create([
      'project_id' => $project->id,
      'board_id' => $from->id,
      'number' => 1,
      'title' => 'Ship it',
      'priority' => 'normal',
      'position' => 1000,
      'created_by' => $owner->id,
      'updated_by' => $owner->id,
    ]);

    $this->actingAs($owner)
      ->postJson("/app-api/tickets/{$ticket->id}/move", ['board_id' => $to->id])
      ->assertOk()
      ->assertJsonPath('ticket.board.name', 'Deployed');

    $comment = TicketComment::where('ticket_id', $ticket->id)
      ->where('kind', 'movement')
      ->firstOrFail();

    self::assertSame($owner->id, $comment->user_id);
    self::assertStringContainsString('In-progress', $comment->body_html);
    self::assertStringContainsString('Deployed', $comment->body_html);

    $this->actingAs($owner)
      ->patchJson("/app-api/comments/{$comment->id}", ['body_html' => '<p>Changed</p>'])
      ->assertForbidden();

    $this->actingAs($owner)
      ->deleteJson("/app-api/comments/{$comment->id}")
      ->assertForbidden();
  }

  public function test_ticket_can_be_resolved_by_project_number_for_deep_links(): void {
    $owner = User::create([
      'name' => 'Owner',
      'email' => 'deep@example.test',
      'password' => 'Password!12345',
    ]);

    $org = Organization::create([
      'name' => 'Deep Org',
      'slug' => 'deep-org',
      'settings' => []
    ]);

    OrganizationMember::create([
      'organization_id' => $org->id,
      'user_id' => $owner->id,
      'role' => 'owner',
    ]);

    $project = Project::create([
      'organization_id' => $org->id,
      'name' => 'Deep Project',
      'key' => 'DEEP',
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
      'title' => 'Deep linked ticket',
      'priority' => 'normal',
      'position' => 1000,
      'created_by' => $owner->id,
      'updated_by' => $owner->id,
    ]);

    $this->actingAs($owner)
      ->getJson("/app-api/projects/{$project->id}/tickets/by-number/1")
      ->assertOk()
      ->assertJsonPath('ticket.id', $ticket->id)
      ->assertJsonPath('ticket.creator.name', 'Owner');
  }
}
