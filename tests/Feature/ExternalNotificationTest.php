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
use App\Models\User;
use App\Notifications\InAppWorkspaceNotification;
use App\Notifications\WorkspaceNotificationMail;
use App\Services\ApiCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ExternalNotificationTest extends TestCase {
  use RefreshDatabase;

  public function test_api_credential_can_trigger_user_notification(): void {
    Notification::fake();

    $owner = User::create([
      'name' => 'Owner',
      'email' => 'external-owner@example.test',
      'password' => 'Password!12345',
    ]);

    $member = User::create([
      'name' => 'Member',
      'email' => 'external-member@example.test',
      'password' => 'Password!12345',
    ]);

    $org = Organization::create([
      'name' => 'External Org',
      'slug' => 'external-org',
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

    [, $token] = app(ApiCredentialService::class)->create($org, $owner, 'Automation', [
      'notifications:write',
    ]);

    $this->withToken($token)
      ->postJson("/api/v1/organizations/{$org->id}/notifications", [
        'user_ids' => [$member->id],
        'title' => 'Build finished',
        'body' => 'The deployment build completed.',
        'url' => '/app',
      ])
      ->assertCreated()
      ->assertJsonPath('data.recipient_count', 1);

    Notification::assertSentTo($member, InAppWorkspaceNotification::class);
    Notification::assertSentTo($member, WorkspaceNotificationMail::class);

    $this->assertDatabaseHas('audit_logs', [
      'organization_id' => $org->id,
      'event' => 'notification.sent',
      'actor_type' => 'api_key',
    ]);
  }
}
