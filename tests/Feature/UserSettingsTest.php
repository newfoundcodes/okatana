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

use App\Models\ApiCredential;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSettingsTest extends TestCase {
  use RefreshDatabase;

  public function test_authenticated_user_can_update_full_name(): void {
    $user = User::create([
      'name' => 'Old Name',
      'email' => 'settings@example.test',
      'password' => 'Password!12345',
    ]);

    $this->actingAs($user)
      ->patchJson('/app-api/user', [
        'name' => '  New Full Name  '
      ])
      ->assertOk()
      ->assertJsonPath('user.name', 'New Full Name');

    $this->assertDatabaseHas('users', [
      'id' => $user->id,
      'name' => 'New Full Name',
    ]);
  }

  public function test_authenticated_user_can_update_notification_email_preferences(): void {
    $user = User::create([
      'name' => 'Preference User',
      'email' => 'preference@example.test',
      'password' => 'Password!12345',
    ]);

    $this->actingAs($user)
      ->patchJson('/app-api/user', [
        'email_notify_assigned' => false,
        'email_notify_mentions' => false,
        'notify_document_comments' => false,
      ])
      ->assertOk()
      ->assertJsonPath('user.email_notify_assigned', false)
      ->assertJsonPath('user.email_notify_mentions', false)
      ->assertJsonPath('user.notify_document_comments', false);

    $this->assertDatabaseHas('users', [
      'id' => $user->id,
      'email_notify_assigned' => false,
      'email_notify_mentions' => false,
      'notify_document_comments' => false,
    ]);
  }

  public function test_user_data_export_contains_personal_data_but_not_integration_secrets(): void {
    $user = User::create([
      'name' => 'Export User',
      'email' => 'export@example.test',
      'password' => 'Password!12345',
    ]);

    $organization = Organization::create([
      'name' => 'Export Org',
      'slug' => 'export-org',
      'settings' => [],
    ]);

    OrganizationMember::create([
      'organization_id' => $organization->id,
      'user_id' => $user->id,
      'role' => 'owner',
    ]);

    WebhookEndpoint::create([
      'organization_id' => $organization->id,
      'name' => 'Private webhook',
      'url' => 'https://example.test/hook',
      'secret' => 'whsec_must_not_export',
      'events' => ['ticket.created'],
      'is_active' => true,
      'created_by' => $user->id,
    ]);

    ApiCredential::create([
      'organization_id' => $organization->id,
      'name' => 'Private API key',
      'public_id' => 'pub_export_test_000001',
      'secret_hash' => hash('sha256', 'api-secret-must-not-export'),
      'scopes' => ['tickets:write'],
      'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get('/app-api/user/export');

    $response
      ->assertOk()
      ->assertHeader('Content-Type', 'application/json')
      ->assertJsonPath('schema', 'okatana.user-export.v1')
      ->assertJsonPath('account.name', 'Export User')
      ->assertJsonPath('organization_memberships.0.role', 'owner')
      ->assertJsonPath('webhooks_created.0.name', 'Private webhook')
      ->assertJsonPath('api_credentials_created.0.public_id', 'pub_export_test_000001');

    $body = $response->getContent();
    self::assertStringNotContainsString('whsec_must_not_export', $body);
    self::assertStringNotContainsString(hash('sha256', 'api-secret-must-not-export'), $body);
    self::assertStringNotContainsString('Password!12345', $body);
  }
}
