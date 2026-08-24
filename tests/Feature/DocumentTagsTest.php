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

use App\Models\DocumentTag;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentTagsTest extends TestCase {
  use RefreshDatabase;

  public function test_documents_support_reusable_organization_scoped_tags_and_tag_search(): void {
    $owner = User::create([
      'name' => 'Owner',
      'email' => 'document-tags@example.test',
      'password' => 'Password!12345',
    ]);

    $organization = Organization::create([
      'name' => 'Docs Tags',
      'slug' => 'docs-tags',
      'settings' => [],
    ]);

    OrganizationMember::create([
      'organization_id' => $organization->id,
      'user_id' => $owner->id,
      'role' => 'owner',
    ]);

    $first = $this->actingAs($owner)
      ->postJson('/app-api/documents', [
        'organization_id' => $organization->id,
        'title' => 'Production handbook',
        'content_html' => '<p>Deploy safely.</p>',
        'status' => 'published',
        'tag_names' => ['Runbook', '#Production'],
      ])
      ->assertCreated()
      ->assertJsonCount(2, 'document.tags');

    $this->actingAs($owner)
      ->postJson('/app-api/documents', [
        'organization_id' => $organization->id,
        'title' => 'Second handbook',
        'status' => 'draft',
        'tag_names' => ['runbook'],
      ])
      ->assertCreated();

    $this->assertDatabaseCount('document_tags', 2);
    self::assertSame(
      2,
      DocumentTag::where('normalized_name', 'runbook')->firstOrFail()->documents()->count(),
    );

    $this->actingAs($owner)
      ->getJson("/app-api/organizations/{$organization->id}/document-tags")
      ->assertOk()
      ->assertJsonFragment(['name' => 'Runbook', 'documents_count' => 2]);

    $this->actingAs($owner)
      ->getJson('/app-api/documents?q=production')
      ->assertOk()
      ->assertJsonPath('pagination.total', 1)
      ->assertJsonPath('documents.0.id', $first->json('document.id'));

    $id = $first->json('document.id');
    $this->actingAs($owner)
      ->patchJson("/app-api/documents/{$id}", [
        'tag_names' => ['Architecture'],
      ])
      ->assertOk()
      ->assertJsonPath('document.tags.0.name', 'Architecture');
  }
}
