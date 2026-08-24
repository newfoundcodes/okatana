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

use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Notifications\InAppWorkspaceNotification;
use App\Notifications\WorkspaceNotificationMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DocumentMentionsAndNotificationsTest extends TestCase {
  use RefreshDatabase;

  public function test_article_mentions_notify_other_users_but_never_the_actor(): void {
    Notification::fake();
    [$author, $editor, $member, $organization, $project] = $this->workspace();

    $document = Document::create([
      'organization_id' => $organization->id,
      'project_id' => $project->id,
      'author_id' => $author->id,
      'title' => 'Mention article',
      'content_html' => '<p>Initial content.</p>',
      'status' => 'published',
      'published_at' => now(),
    ]);

    $document->editors()
      ->attach($editor->id, ['added_by' => $author->id]);

    $this->actingAs($author)
      ->patchJson('/app-api/documents/' . $document->id, [
        'content_html' =>
          '<p><span data-mention-user-id="' .
          $author->id .
          '" contenteditable="false">@' .
          $author->name .
          '</span> <span data-mention-user-id="' .
          $member->id .
          '" contenteditable="false">@' .
          $member->name .
          '</span></p>',
        'status' => 'published',
      ])
      ->assertOk();

    Notification::assertSentTo(
      $member,
      InAppWorkspaceNotification::class,
      fn($notification) => $notification->payload['kind'] === 'document.mentioned',
    );

    Notification::assertSentTo(
      $member,
      WorkspaceNotificationMail::class,
      fn($notification) => $notification->payload['kind'] === 'document.mentioned',
    );

    Notification::assertNotSentTo(
      $author,
      InAppWorkspaceNotification::class,
      fn($notification) => $notification->payload['kind'] === 'document.mentioned',
    );

    Notification::assertNotSentTo(
      $author,
      WorkspaceNotificationMail::class,
      fn($notification) => $notification->payload['kind'] === 'document.mentioned',
    );

    $this->assertDatabaseHas('document_mentions', [
      'document_id' => $document->id,
      'user_id' => $author->id,
    ]);

    $this->assertDatabaseHas('document_mentions', [
      'document_id' => $document->id,
      'user_id' => $member->id,
    ]);
  }

  public function test_article_comments_notify_author_and_editors_and_respect_opt_out(): void {
    Notification::fake();
    [$author, $editor, $member, $organization, $project] = $this->workspace();

    $document = Document::create([
      'organization_id' => $organization->id,
      'project_id' => $project->id,
      'author_id' => $author->id,
      'title' => 'Comment article',
      'content_html' => '<p>Body.</p>',
      'status' => 'published',
      'published_at' => now(),
    ]);
    $document->editors()
      ->attach($editor->id, ['added_by' => $author->id]);

    $this->actingAs($member)
      ->postJson('/app-api/documents/' . $document->id . '/comments', [
        'body_html' => '<p>First comment.</p>',
      ])
      ->assertCreated();

    Notification::assertSentTo(
      $author,
      InAppWorkspaceNotification::class,
      fn($notification) => $notification->payload['kind'] === 'document.comment.created',
    );

    Notification::assertSentTo(
      $editor,
      InAppWorkspaceNotification::class,
      fn($notification) => $notification->payload['kind'] === 'document.comment.created',
    );

    Notification::assertSentTo(
      $author,
      WorkspaceNotificationMail::class,
      fn($notification) => $notification->payload['kind'] === 'document.comment.created',
    );

    Notification::assertSentTo(
      $editor,
      WorkspaceNotificationMail::class,
      fn($notification) => $notification->payload['kind'] === 'document.comment.created',
    );

    $editor->update(['notify_document_comments' => false]);
    Notification::fake();

    $this->actingAs($member)
      ->postJson('/app-api/documents/' . $document->id . '/comments', [
        'body_html' =>
          '<p>Second comment with <span data-mention-user-id="' .
          $member->id .
          '" contenteditable="false">@' .
          $member->name .
          '</span>.</p>',
      ])
      ->assertCreated();

    Notification::assertSentTo(
      $author,
      InAppWorkspaceNotification::class,
      fn($notification) => $notification->payload['kind'] === 'document.comment.created',
    );

    Notification::assertNotSentTo(
      $editor,
      InAppWorkspaceNotification::class,
      fn($notification) => $notification->payload['kind'] === 'document.comment.created',
    );

    Notification::assertNotSentTo(
      $editor,
      WorkspaceNotificationMail::class,
      fn($notification) => $notification->payload['kind'] === 'document.comment.created',
    );

    Notification::assertNotSentTo(
      $member,
      InAppWorkspaceNotification::class,
      fn($notification) => $notification->payload['kind'] === 'document.comment.mentioned',
    );

    Notification::assertNotSentTo(
      $member,
      WorkspaceNotificationMail::class,
      fn($notification) => $notification->payload['kind'] === 'document.comment.mentioned',
    );
  }

  public function test_document_mention_candidates_follow_organization_and_project_access(): void {
    [$author, $editor, $member, $organization, $project] = $this->workspace();
    $outside = User::create([
      'name' => 'Outside',
      'email' => 'outside-doc@example.test',
      'password' => 'Password!12345',
    ]);

    OrganizationMember::create([
      'organization_id' => $organization->id,
      'user_id' => $outside->id,
      'role' => 'user',
    ]);

    $response = $this->actingAs($author)
      ->getJson(
        '/app-api/documents/mention-candidates?organization_id=' .
          $organization->id .
          '&project_id=' .
          $project->id,
      )
      ->assertOk();
    $ids = collect($response->json('users'))->pluck('id');

    self::assertTrue($ids->contains($author->id));
    self::assertTrue($ids->contains($editor->id));
    self::assertTrue($ids->contains($member->id));
    self::assertFalse($ids->contains($outside->id));
  }

  private function workspace(): array {
    $author = User::create([
      'name' => 'Author',
      'email' => 'doc-author@example.test',
      'password' => 'Password!12345',
    ]);

    $editor = User::create([
      'name' => 'Editor',
      'email' => 'doc-editor@example.test',
      'password' => 'Password!12345',
    ]);

    $member = User::create([
      'name' => 'Member',
      'email' => 'doc-member@example.test',
      'password' => 'Password!12345',
    ]);

    $organization = Organization::create([
      'name' => 'Docs Org',
      'slug' => 'docs-org',
      'settings' => [],
    ]);

    OrganizationMember::create([
      'organization_id' => $organization->id,
      'user_id' => $author->id,
      'role' => 'owner',
    ]);

    OrganizationMember::create([
      'organization_id' => $organization->id,
      'user_id' => $editor->id,
      'role' => 'user',
    ]);

    OrganizationMember::create([
      'organization_id' => $organization->id,
      'user_id' => $member->id,
      'role' => 'user',
    ]);

    $project = Project::create([
      'organization_id' => $organization->id,
      'name' => 'Docs Project',
      'key' => 'DOC',
      'settings' => [],
    ]);

    ProjectMember::create([
      'project_id' => $project->id,
      'user_id' => $editor->id
    ]);

    ProjectMember::create([
      'project_id' => $project->id,
      'user_id' => $member->id
    ]);

    return [$author, $editor, $member, $organization, $project];
  }
}
