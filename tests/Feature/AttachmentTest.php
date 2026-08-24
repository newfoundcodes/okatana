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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentTest extends TestCase {
  use RefreshDatabase;

  public function test_project_member_can_attach_and_download_ticket_file(): void {
    Storage::fake('local');

    $owner = User::create([
      'name' => 'Owner',
      'email' => 'attach-owner@example.test',
      'password' => 'Password!12345',
    ]);

    $org = Organization::create([
      'name' => 'Attach Org',
      'slug' => 'attach-org',
      'settings' => [],
    ]);

    OrganizationMember::create([
      'organization_id' => $org->id,
      'user_id' => $owner->id,
      'role' => 'owner',
    ]);

    $project = Project::create([
      'organization_id' => $org->id,
      'name' => 'Attach Project',
      'key' => 'ATT',
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
      'title' => 'Files',
      'priority' => 'normal',
      'position' => 1000,
      'created_by' => $owner->id,
      'updated_by' => $owner->id,
    ]);

    $response = $this->actingAs($owner)
      ->post("/app-api/tickets/{$ticket->id}/attachments", [
        'files' => [UploadedFile::fake()->create('requirements.pdf', 1024, 'application/pdf')],
      ])
      ->assertOk();

    $attachmentId = $response->json('attachments.0.id');
    $this->assertDatabaseHas('attachments', [
      'id' => $attachmentId,
      'ticket_id' => $ticket->id,
      'original_name' => 'requirements.pdf',
    ]);

    $this->actingAs($owner)
      ->get("/app-api/attachments/{$attachmentId}/download")
      ->assertOk();
  }

  public function test_project_member_can_attach_file_to_comment(): void {
    Storage::fake('local');

    $owner = User::create([
      'name' => 'Owner',
      'email' => 'comment-attach-owner@example.test',
      'password' => 'Password!12345',
    ]);

    $org = Organization::create([
      'name' => 'Comment Attach Org',
      'slug' => 'comment-attach-org',
      'settings' => [],
    ]);

    OrganizationMember::create([
      'organization_id' => $org->id,
      'user_id' => $owner->id,
      'role' => 'owner',
    ]);

    $project = Project::create([
      'organization_id' => $org->id,
      'name' => 'Comment Attach Project',
      'key' => 'CAT',
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
      'title' => 'Comment files',
      'priority' => 'normal',
      'position' => 1000,
      'created_by' => $owner->id,
      'updated_by' => $owner->id,
    ]);

    $comment = TicketComment::create([
      'ticket_id' => $ticket->id,
      'user_id' => $owner->id,
      'kind' => 'comment',
      'body_html' => '<p>See attached.</p>',
      'author_snapshot' => [
        'name' => $owner->name,
        'email' => $owner->email
      ],
    ]);

    $response = $this->actingAs($owner)
      ->post("/app-api/comments/{$comment->id}/attachments", [
        'files' => [UploadedFile::fake()->create('trace.log', 128, 'text/plain')],
      ])
      ->assertOk();

    $this->assertDatabaseHas('attachments', [
      'id' => $response->json('attachments.0.id'),
      'ticket_comment_id' => $comment->id,
      'original_name' => 'trace.log',
    ]);
  }
}
