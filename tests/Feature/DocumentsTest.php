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
use App\Models\DocumentComment;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentsTest extends TestCase {
  use RefreshDatabase;

  private function workspace(): array {
    $author = User::create([
      'name' => 'Author',
      'email' => 'author@example.test',
      'password' => 'Password!12345',
    ]);

    $editor = User::create([
      'name' => 'Editor',
      'email' => 'editor@example.test',
      'password' => 'Password!12345',
    ]);

    $member = User::create([
      'name' => 'Member',
      'email' => 'member@example.test',
      'password' => 'Password!12345',
    ]);

    $organization = Organization::create([
      'name' => 'Knowledge Org',
      'slug' => 'knowledge-org',
      'settings' => [],
    ]);

    foreach ([$author, $editor, $member] as $user) {
      OrganizationMember::create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => $user->is($author) ? 'owner' : 'user',
      ]);
    }

    $project = Project::create([
      'organization_id' => $organization->id,
      'name' => 'Knowledge Project',
      'key' => 'KNW',
      'settings' => [],
    ]);

    foreach ([$editor, $member] as $user) {
      ProjectMember::create(['project_id' => $project->id, 'user_id' => $user->id]);
    }

    return compact('author', 'editor', 'member', 'organization', 'project');
  }

  public function test_draft_is_visible_to_author_and_selected_editor_but_not_other_members(): void {
    extract($this->workspace());

    $created = $this->actingAs($author)
      ->postJson('/app-api/documents', [
        'organization_id' => $organization->id,
        'project_id' => $project->id,
        'title' => 'Release runbook',
        'caption' => 'Deployment procedure',
        'content_html' => '<h2>Deploy</h2><p>Run the checklist.</p>',
        'status' => 'draft',
        'editor_ids' => [$editor->id],
      ])
      ->assertCreated();
    $id = $created->json('document.id');
    $created->assertJsonPath('document.can_moderate_comments', true);

    $this->actingAs($editor)
      ->getJson("/app-api/documents/{$id}")
      ->assertOk()
      ->assertJsonPath('document.can_edit', true);

    $this->actingAs($member)
      ->getJson("/app-api/documents/{$id}")
      ->assertForbidden();

    $this->actingAs($editor)
      ->patchJson("/app-api/documents/{$id}", [
        'title' => 'Release runbook v2',
        'content_html' => '<p>Updated by the selected editor.</p>',
        'status' => 'published',
      ])
      ->assertOk()
      ->assertJsonPath('document.status', 'published');

    $this->actingAs($member)
      ->getJson("/app-api/documents/{$id}")
      ->assertOk()
      ->assertJsonPath('document.title', 'Release runbook v2');
  }

  public function test_project_scoped_draft_and_publish_responses_keep_organization_context(): void {
    extract($this->workspace());

    $draft = $this->actingAs($author)
      ->postJson('/app-api/documents', [
        'organization_id' => $organization->id,
        'project_id' => $project->id,
        'title' => 'Permission regression article',
        'caption' => 'Project-scoped document',
        'content_html' => '<p>Draft body.</p>',
        'status' => 'draft',
        'editor_ids' => [$editor->id],
      ])
      ->assertCreated();

    $draft
      ->assertJsonPath('document.project.organization_id', $organization->id)
      ->assertJsonPath('document.can_edit', true)
      ->assertJsonPath('document.can_delete', true)
      ->assertJsonPath('document.can_moderate_comments', true);
    $documentId = $draft->json('document.id');

    $this->actingAs($author)
      ->patchJson("/app-api/documents/{$documentId}", [
        'status' => 'published',
      ])
      ->assertOk()
      ->assertJsonPath('document.status', 'published')
      ->assertJsonPath('document.project.organization_id', $organization->id)
      ->assertJsonPath('document.can_delete', true);
  }

  public function test_articles_support_search_pagination_favorites_comments_archive_and_soft_delete(): void {
    extract($this->workspace());

    $documents = collect(range(1, 11))->map(
      fn($index) => Document::create([
        'organization_id' => $organization->id,
        'project_id' => $project->id,
        'author_id' => $author->id,
        'title' => 'Article ' . $index,
        'caption' => $index === 11 ? 'Distinct deployment handbook' : 'Knowledge article',
        'content_html' => '<p>Body ' . $index . '</p>',
        'status' => 'published',
        'published_at' => now()->subMinutes(12 - $index),
      ]),
    );

    $target = $documents->last();
    $this->actingAs($member)
      ->getJson('/app-api/documents?per_page=10&page=1')
      ->assertOk()
      ->assertJsonPath('pagination.total', 11)
      ->assertJsonCount(10, 'documents');

    $this->actingAs($member)
      ->getJson('/app-api/documents?q=' . urlencode('deployment handbook'))
      ->assertOk()
      ->assertJsonPath('pagination.total', 1)
      ->assertJsonPath('documents.0.id', $target->id);

    $this->actingAs($member)
      ->postJson("/app-api/documents/{$target->id}/favorite", ['saved' => true])
      ->assertOk()
      ->assertJsonPath('saved', true);

    $this->actingAs($member)
      ->getJson('/app-api/documents?scope=favorites')
      ->assertOk()
      ->assertJsonPath('pagination.total', 1)
      ->assertJsonPath('documents.0.id', $target->id);

    $comment = $this->actingAs($member)
      ->postJson("/app-api/documents/{$target->id}/comments", [
        'body_html' => '<p>Useful article.</p>',
      ])
      ->assertCreated();

    $commentId = $comment->json('comment.id');
    $this->actingAs($member)
      ->patchJson("/app-api/document-comments/{$commentId}", [
        'body_html' => '<p>Very useful article.</p>',
      ])
      ->assertOk();
    self::assertNotNull(DocumentComment::findOrFail($commentId)->edited_at);

    $this->actingAs($member)
      ->deleteJson("/app-api/document-comments/{$commentId}")
      ->assertNoContent();

    $this->assertSoftDeleted('document_comments', ['id' => $commentId]);

    $this->actingAs($author)
      ->postJson("/app-api/documents/{$target->id}/archive", ['archived' => true])
      ->assertOk();

    self::assertNotNull($target->fresh()->archived_at);
    $this->actingAs($author)
      ->deleteJson("/app-api/documents/{$target->id}")
      ->assertNoContent();

    $this->assertSoftDeleted('documents', ['id' => $target->id]);
  }
}
