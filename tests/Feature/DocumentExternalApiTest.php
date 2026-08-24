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

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Project;
use App\Models\User;
use App\Services\ApiCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentExternalApiTest extends TestCase {
  use RefreshDatabase;

  public function test_scoped_external_api_can_create_read_update_and_comment_on_documents(): void {
    $owner = User::create([
      'name' => 'Owner',
      'email' => 'docs-api-owner@example.test',
      'password' => 'Password!12345',
    ]);

    $organization = Organization::create([
      'name' => 'Docs API Org',
      'slug' => 'docs-api-org',
      'settings' => [],
    ]);

    OrganizationMember::create([
      'organization_id' => $organization->id,
      'user_id' => $owner->id,
      'role' => 'owner',
    ]);

    $project = Project::create([
      'organization_id' => $organization->id,
      'name' => 'Docs API Project',
      'key' => 'DAP',
      'settings' => [],
    ]);

    [, $token] = app(ApiCredentialService::class)->create(
      $organization,
      $owner,
      'Docs automation',
      ['documents:read', 'documents:write', 'document_comments:write'],
    );

    $created = $this->withToken($token)
      ->postJson("/api/v1/organizations/{$organization->id}/documents", [
        'project_id' => $project->id,
        'title' => 'API article',
        'content_html' => '<p>Created by API.</p>',
        'status' => 'draft',
        'tag_names' => ['Automation', 'Runbook'],
      ])
      ->assertCreated()
      ->assertJsonPath('data.title', 'API article')
      ->assertJsonFragment(['name' => 'Automation'])
      ->assertJsonFragment(['name' => 'Runbook']);
    $id = $created->json('data.id');

    $this->withToken($token)
      ->getJson("/api/v1/documents/{$id}")
      ->assertOk()
      ->assertJsonPath('data.status', 'draft');

    $this->withToken($token)
      ->patchJson("/api/v1/documents/{$id}", ['status' => 'published', 'archived' => false])
      ->assertOk()
      ->assertJsonPath('data.status', 'published');

    $this->withToken($token)
      ->postJson("/api/v1/documents/{$id}/comments", [
        'body_html' => '<p>Automated note.</p>',
      ])
      ->assertCreated();

    $this->withToken($token)
      ->getJson("/api/v1/organizations/{$organization->id}/documents?q=API&tags=Automation,Runbook")
      ->assertOk()
      ->assertJsonPath('data.total', 1);
  }
}
