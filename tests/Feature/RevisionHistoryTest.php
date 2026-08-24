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
use App\Models\TicketCommentRevision;
use App\Models\TicketRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevisionHistoryTest extends TestCase {
  use RefreshDatabase;

  public function test_ticket_and_comment_edits_create_immutable_revision_history(): void {
    $owner = User::create([
      'name' => 'Editor',
      'email' => 'editor@example.test',
      'password' => 'Password!12345',
    ]);

    $organization = Organization::create([
      'name' => 'Revision Org',
      'slug' => 'revision-org',
      'settings' => [],
    ]);

    OrganizationMember::create([
      'organization_id' => $organization->id,
      'user_id' => $owner->id,
      'role' => 'owner',
    ]);

    $project = Project::create([
      'organization_id' => $organization->id,
      'name' => 'Revision Project',
      'key' => 'REV',
      'settings' => [],
    ]);

    $board = Board::create([
      'project_id' => $project->id,
      'name' => 'Open',
      'slug' => 'open',
      'position' => 1000,
    ]);

    $created = $this->actingAs($owner)
      ->postJson("/app-api/projects/{$project->id}/tickets", [
        'board_id' => $board->id,
        'title' => 'Original title',
        'description_html' => '<p>Original description</p>',
        'priority' => 'normal',
      ])
      ->assertCreated();

    $ticketId = $created->json('ticket.id');
    self::assertSame(1, TicketRevision::where('ticket_id', $ticketId)->count());
    self::assertNull($created->json('ticket.edited_at'));

    $this->actingAs($owner)
      ->patchJson("/app-api/tickets/{$ticketId}", [
        'title' => 'Edited title',
        'description_html' => '<p>Edited description</p>',
        'priority' => 'high',
      ])
      ->assertOk()
      ->assertJsonPath('ticket.title', 'Edited title');

    self::assertSame(2, TicketRevision::where('ticket_id', $ticketId)->count());
    self::assertNotNull(Ticket::findOrFail($ticketId)->edited_at);

    $ticketHistory = $this->actingAs($owner)
      ->getJson("/app-api/tickets/{$ticketId}/revisions")
      ->assertOk()
      ->assertJsonCount(2, 'revisions');

    self::assertSame(2, $ticketHistory->json('revisions.0.version'));
    self::assertSame('Edited title', $ticketHistory->json('revisions.0.snapshot.title'));
    self::assertSame('Original title', $ticketHistory->json('revisions.1.snapshot.title'));

    $comment = $this->actingAs($owner)
      ->postJson("/app-api/tickets/{$ticketId}/comments", [
        'body_html' => '<p>Original comment</p>',
      ])
      ->assertCreated();
    $commentId = $comment->json('comment.id');

    self::assertSame(1, TicketCommentRevision::where('ticket_comment_id', $commentId)->count());

    $this->actingAs($owner)
      ->patchJson("/app-api/comments/{$commentId}", [
        'body_html' => '<p>Edited comment</p>',
      ])
      ->assertOk();

    $commentHistory = $this->actingAs($owner)
      ->getJson("/app-api/comments/{$commentId}/revisions")
      ->assertOk()
      ->assertJsonCount(2, 'revisions');

    self::assertSame(
      '<p>Edited comment</p>',
      $commentHistory->json('revisions.0.snapshot.body_html'),
    );

    self::assertSame(
      '<p>Original comment</p>',
      $commentHistory->json('revisions.1.snapshot.body_html'),
    );
  }
}
