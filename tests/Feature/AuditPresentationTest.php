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
use App\Models\Board;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\ActivityRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditPresentationTest extends TestCase {
  use RefreshDatabase;

  public function test_organization_audit_rows_include_project_context_and_historical_comment_labels(): void {
    $owner = User::create([
      'name' => 'Owner',
      'email' => 'audit-present@example.test',
      'password' => 'Password!12345',
    ]);

    $org = Organization::create([
      'name' => 'Audit Org',
      'slug' => 'audit-present',
      'settings' => [],
    ]);

    OrganizationMember::create([
      'organization_id' => $org->id,
      'user_id' => $owner->id,
      'role' => 'owner',
    ]);

    $project = Project::create([
      'organization_id' => $org->id,
      'name' => 'Audit Project',
      'key' => 'AUD',
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
      'number' => 7,
      'title' => 'Audit ticket',
      'priority' => 'normal',
      'position' => 1000,
      'created_by' => $owner->id,
      'updated_by' => $owner->id,
    ]);

    $comment = TicketComment::create([
      'ticket_id' => $ticket->id,
      'user_id' => $owner->id,
      'kind' => 'comment',
      'body_html' => '<p>Hello</p>',
    ]);

    AuditLog::create([
      'organization_id' => $org->id,
      'project_id' => $project->id,
      'event' => 'comment.created',
      'actor_type' => 'user',
      'actor_id' => $owner->id,
      'actor_name' => $owner->name,
      'actor_email' => $owner->email,
      'subject_type' => TicketComment::class,
      'subject_id' => $comment->id,
      'subject_label' => null,
      'occurred_at' => now(),
    ]);

    $this->actingAs($owner)
      ->getJson("/app-api/organizations/{$org->id}/audit-logs")
      ->assertOk()
      ->assertJsonPath('audit_logs.data.0.project_key', 'AUD')
      ->assertJsonPath('audit_logs.data.0.project_name', 'Audit Project')
      ->assertJsonPath('audit_logs.data.0.subject_label', 'Comment on AUD-7');
  }

  public function test_new_comment_activity_uses_a_human_readable_subject_label(): void {
    $owner = User::create([
      'name' => 'Owner',
      'email' => 'audit-new@example.test',
      'password' => 'Password!12345',
    ]);

    $org = Organization::create([
      'name' => 'Audit New Org',
      'slug' => 'audit-new',
      'settings' => [],
    ]);

    OrganizationMember::create([
      'organization_id' => $org->id,
      'user_id' => $owner->id,
      'role' => 'owner',
    ]);

    $project = Project::create([
      'organization_id' => $org->id,
      'name' => 'Audit Project',
      'key' => 'AUD',
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
      'number' => 8,
      'title' => 'Audit ticket',
      'priority' => 'normal',
      'position' => 1000,
      'created_by' => $owner->id,
      'updated_by' => $owner->id,
    ]);

    $comment = TicketComment::create([
      'ticket_id' => $ticket->id,
      'user_id' => $owner->id,
      'kind' => 'comment',
      'body_html' => '<p>Hello</p>',
    ]);

    app(ActivityRecorder::class)->record(
      'comment.created',
      $org,
      $project,
      $comment,
      [],
      ['ticket_id' => $ticket->id],
      $owner,
    );

    self::assertSame('Comment on AUD-8', AuditLog::latest('occurred_at')->first()->subject_label);
  }

  public function test_audit_log_supports_ten_row_pagination_and_search(): void {
    $owner = User::create([
      'name' => 'Owner',
      'email' => 'audit-page@example.test',
      'password' => 'Password!12345',
    ]);

    $org = Organization::create([
      'name' => 'Pagination Org',
      'slug' => 'pagination-org',
      'settings' => [],
    ]);

    OrganizationMember::create([
      'organization_id' => $org->id,
      'user_id' => $owner->id,
      'role' => 'owner',
    ]);

    $project = Project::create([
      'organization_id' => $org->id,
      'name' => 'Pagination Project',
      'key' => 'PAGE',
      'settings' => [],
    ]);

    foreach (range(1, 11) as $index) {
      AuditLog::create([
        'organization_id' => $org->id,
        'project_id' => $project->id,
        'event' => $index === 11 ? 'ticket.unique_search_event' : 'ticket.updated',
        'actor_type' => 'user',
        'actor_id' => $owner->id,
        'actor_name' => $owner->name,
        'actor_email' => $owner->email,
        'subject_type' => Ticket::class,
        'subject_id' => null,
        'subject_label' => 'Ticket ' . $index,
        'occurred_at' => now()->subSeconds(12 - $index),
      ]);
    }

    $this->actingAs($owner)
      ->getJson("/app-api/organizations/{$org->id}/audit-logs?per_page=10")
      ->assertOk()
      ->assertJsonCount(10, 'audit_logs.data')
      ->assertJsonPath('audit_logs.total', 11)
      ->assertJsonPath('audit_logs.current_page', 1)
      ->assertJsonPath('audit_logs.last_page', 2);

    $this->actingAs($owner)
      ->getJson("/app-api/organizations/{$org->id}/audit-logs?per_page=10&page=2")
      ->assertOk()
      ->assertJsonCount(1, 'audit_logs.data');

    $this->actingAs($owner)
      ->getJson("/app-api/organizations/{$org->id}/audit-logs?q=unique_search_event")
      ->assertOk()
      ->assertJsonPath('audit_logs.total', 1)
      ->assertJsonPath('audit_logs.data.0.event', 'ticket.unique_search_event');
  }
}
