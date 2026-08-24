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

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationPasswordTest extends TestCase {
  use RefreshDatabase;

  public function test_join_requires_strong_confirmed_password(): void {
    $org = Organization::create([
      'name' => 'Invite Org',
      'slug' => 'invite-org',
      'settings' => [],
    ]);

    $inviter = User::create([
      'name' => 'Owner',
      'email' => 'invite-owner@example.test',
      'password' => 'Password!12345',
    ]);

    $plain = 'plain-invite-token';

    Invitation::create([
      'organization_id' => $org->id,
      'email' => 'new-user@example.test',
      'role' => 'user',
      'token_hash' => hash('sha256', $plain),
      'invited_by' => $inviter->id,
      'expires_at' => now()->addDay(),
    ]);

    $this->postJson("/app-api/invitations/{$plain}/accept", [
      'name' => 'New User',
      'password' => 'weak',
      'password_confirmation' => 'weak',
    ])
      ->assertUnprocessable()
      ->assertJsonValidationErrors('password');
  }

  public function test_accepting_lower_role_invitation_does_not_demote_existing_admin(): void {
    $org = Organization::create([
      'name' => 'Role Org',
      'slug' => 'role-org',
      'settings' => []
    ]);

    $inviter = User::create([
      'name' => 'Owner',
      'email' => 'role-owner@example.test',
      'password' => 'Password!12345',
    ]);

    $admin = User::create([
      'name' => 'Admin',
      'email' => 'admin@example.test',
      'password' => 'Password!12345',
    ]);

    OrganizationMember::create([
      'organization_id' => $org->id,
      'user_id' => $inviter->id,
      'role' => 'owner',
    ]);

    OrganizationMember::create([
      'organization_id' => $org->id,
      'user_id' => $admin->id,
      'role' => 'admin',
    ]);

    $plain = 'role-invite-token';
    Invitation::create([
      'organization_id' => $org->id,
      'email' => $admin->email,
      'role' => 'user',
      'token_hash' => hash('sha256', $plain),
      'invited_by' => $inviter->id,
      'expires_at' => now()->addDay(),
    ]);

    $this->postJson("/app-api/invitations/{$plain}/accept", [
      'name' => 'Admin',
      'current_password' => 'Password!12345',
      'password' => 'NewPassword!1234',
      'password_confirmation' => 'NewPassword!1234',
    ])->assertOk();

    $this->assertDatabaseHas('organization_members', [
      'organization_id' => $org->id,
      'user_id' => $admin->id,
      'role' => 'admin',
    ]);
  }
}
