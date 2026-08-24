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
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\InAppWorkspaceNotification;
use App\Notifications\WorkspaceNotificationMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class CollaborationNotificationsTest extends TestCase {
  use RefreshDatabase;

  public function test_assignment_and_mentions_create_notifications_and_respect_email_preferences(): void {
    Notification::fake();
    [$owner, $member, $project, $board] = $this->workspace();

    $member->update(['email_notify_assigned' => false, 'email_notify_mentions' => true]);
    $ticketResponse = $this->actingAs($owner)
      ->postJson("/app-api/projects/{$project->id}/tickets", [
        'board_id' => $board->id,
        'title' => 'Mention and assign',
        'description_html' =>
          '<p>Hello <span data-mention-user-id="' .
          $member->id .
          '">@' .
          $member->name .
          '</span></p>',
        'assignee_ids' => [$member->id],
      ])
      ->assertCreated();

    Notification::assertSentTo(
      $member,
      InAppWorkspaceNotification::class,
      fn($notification) => $notification->payload['kind'] === 'ticket.assigned',
    );

    Notification::assertSentTo(
      $member,
      InAppWorkspaceNotification::class,
      fn($notification) => $notification->payload['kind'] === 'ticket.mentioned',
    );

    Notification::assertNotSentTo(
      $member,
      WorkspaceNotificationMail::class,
      fn($notification) => $notification->payload['kind'] === 'ticket.assigned',
    );

    Notification::assertSentTo(
      $member,
      WorkspaceNotificationMail::class,
      fn($notification) => $notification->payload['kind'] === 'ticket.mentioned',
    );

    $ticket = Ticket::findOrFail($ticketResponse->json('ticket.id'));
    $this->assertDatabaseHas('ticket_mentions', [
      'ticket_id' => $ticket->id,
      'user_id' => $member->id,
    ]);

    $this->actingAs($owner)
      ->postJson("/app-api/tickets/{$ticket->id}/comments", [
        'body_html' =>
          '<p>Review this <span data-mention-user-id="' .
          $member->id .
          '">@' .
          $member->name .
          '</span></p>',
      ])
      ->assertCreated();

    Notification::assertSentTo(
      $member,
      InAppWorkspaceNotification::class,
      fn($notification) => $notification->payload['kind'] === 'comment.mentioned',
    );

    Notification::assertSentTo(
      $member,
      WorkspaceNotificationMail::class,
      fn($notification) => $notification->payload['kind'] === 'comment.mentioned',
    );

    $this->actingAs($owner)
      ->postJson("/app-api/tickets/{$ticket->id}/comments", [
        'body_html' => '<p>Status update without a mention.</p>',
      ])
      ->assertCreated();

    Notification::assertSentTo(
      $member,
      InAppWorkspaceNotification::class,
      fn($notification) => $notification->payload['kind'] === 'comment.created',
    );

    Notification::assertSentTo(
      $member,
      WorkspaceNotificationMail::class,
      fn($notification) => $notification->payload['kind'] === 'comment.created',
    );

    $deployed = Board::create([
      'project_id' => $project->id,
      'name' => 'Deployed',
      'slug' => 'deployed',
      'position' => 2000,
      'is_done' => true,
    ]);

    $this->actingAs($owner)
      ->postJson("/app-api/tickets/{$ticket->id}/move", ['board_id' => $deployed->id])
      ->assertOk();

    Notification::assertSentTo(
      $member,
      InAppWorkspaceNotification::class,
      fn($notification) => $notification->payload['kind'] === 'ticket.moved',
    );

    Notification::assertSentTo(
      $member,
      WorkspaceNotificationMail::class,
      fn($notification) => $notification->payload['kind'] === 'ticket.moved',
    );
  }

  public function test_self_mentions_do_not_notify_the_actor(): void {
    Notification::fake();
    [$owner, , $project, $board] = $this->workspace();

    $ticketResponse = $this->actingAs($owner)
      ->postJson("/app-api/projects/{$project->id}/tickets", [
        'board_id' => $board->id,
        'title' => 'Self mention',
        'description_html' =>
          '<p><span data-mention-user-id="' .
          $owner->id .
          '" contenteditable="false">@' .
          $owner->name .
          '</span></p>',
      ])
      ->assertCreated();

    Notification::assertNotSentTo(
      $owner,
      InAppWorkspaceNotification::class,
      fn($notification) => $notification->payload['kind'] === 'ticket.mentioned',
    );

    Notification::assertNotSentTo(
      $owner,
      WorkspaceNotificationMail::class,
      fn($notification) => $notification->payload['kind'] === 'ticket.mentioned',
    );

    $ticket = Ticket::findOrFail($ticketResponse->json('ticket.id'));
    $this->actingAs($owner)
      ->postJson("/app-api/tickets/{$ticket->id}/comments", [
        'body_html' =>
          '<p><span data-mention-user-id="' .
          $owner->id .
          '" contenteditable="false">@' .
          $owner->name .
          '</span></p>',
      ])
      ->assertCreated();

    Notification::assertNotSentTo(
      $owner,
      InAppWorkspaceNotification::class,
      fn($notification) => $notification->payload['kind'] === 'comment.mentioned',
    );

    Notification::assertNotSentTo(
      $owner,
      WorkspaceNotificationMail::class,
      fn($notification) => $notification->payload['kind'] === 'comment.mentioned',
    );
  }

  public function test_notification_feed_supports_search_and_pagination(): void {
    [$owner, $member] = $this->workspace();
    foreach (range(1, 11) as $index) {
      $member->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => InAppWorkspaceNotification::class,
        'data' => [
          'kind' => 'api.notification',
          'title' => 'Deploy notice ' . $index,
          'body' => 'Deployment notification ' . $index,
          'url' => '/app',
          'actor' => ['id' => $owner->id, 'name' => $owner->name],
        ],
      ]);
    }

    $member->notifications()->create([
      'id' => (string) Str::uuid(),
      'type' => InAppWorkspaceNotification::class,
      'data' => [
        'kind' => 'api.notification',
        'title' => 'Review notice',
        'body' => 'Review this ticket.',
        'url' => '/app',
        'actor' => ['id' => $owner->id, 'name' => $owner->name],
      ],
    ]);

    $response = $this->actingAs($member)
      ->getJson('/app-api/notifications?q=Deploy&page=2&per_page=10')
      ->assertOk()
      ->assertJsonPath('pagination.total', 11)
      ->assertJsonPath('pagination.current_page', 2)
      ->assertJsonPath('pagination.per_page', 10);

    self::assertCount(1, $response->json('notifications'));
    self::assertStringContainsString('Deploy', $response->json('notifications.0.data.title'));
  }

  public function test_notification_feed_refreshes_actor_profile_picture_from_current_user_data(): void {
    [$owner, $member] = $this->workspace();
    $oldUrl = $owner->avatar_url;

    $member->notifications()->create([
      'id' => (string) Str::uuid(),
      'type' => InAppWorkspaceNotification::class,
      'data' => [
        'kind' => 'ticket.assigned',
        'title' => 'Assigned',
        'body' => 'Owner assigned you.',
        'url' => '/app',
        'actor' => ['id' => $owner->id, 'name' => $owner->name, 'avatar_url' => $oldUrl],
      ],
    ]);

    $owner->update(['avatar_path' => 'avatars/new-profile.webp']);
    $freshUrl = $owner->fresh()->avatar_url;

    $this->actingAs($member)
      ->getJson('/app-api/notifications')
      ->assertOk()
      ->assertJsonPath('notifications.0.data.actor.avatar_url', $freshUrl);
  }

  private function workspace(): array {
    $owner = User::create([
      'name' => 'Owner',
      'email' => 'notify-owner@example.test',
      'password' => 'Password!12345',
    ]);

    $member = User::create([
      'name' => 'Member',
      'email' => 'notify-member@example.test',
      'password' => 'Password!12345',
    ]);

    $org = Organization::create([
      'name' => 'Notify Org',
      'slug' => 'notify-org',
      'settings' => [],
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
      'name' => 'Notify Project',
      'key' => 'NTF',
      'settings' => [],
    ]);

    ProjectMember::create(['project_id' => $project->id, 'user_id' => $member->id]);

    $board = Board::create([
      'project_id' => $project->id,
      'name' => 'Open',
      'slug' => 'open',
      'position' => 1000,
    ]);

    return [$owner, $member, $project, $board];
  }
}
