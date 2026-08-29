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

namespace App\Services;

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Notifications\InAppWorkspaceNotification;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class InvitationService
{
  public function invite(
    Organization $org,
    ?Project $project,
    User $inviter,
    string $email,
    string $role = 'user',
  ): Invitation {
    $email = strtolower(trim($email));
    $plain = Str::random(64);
    $pending = Invitation::where('organization_id', $org->id)
      ->where('email', $email)
      ->whereNull('accepted_at')
      ->whereNull('revoked_at');

    $project ? $pending->where('project_id', $project->id) : $pending->whereNull('project_id');
    $pending->update(['revoked_at' => now()]);

    $invite = Invitation::create([
      'organization_id' => $org->id,
      'project_id' => $project?->id,
      'email' => $email,
      'role' => $role,
      'token_hash' => hash('sha256', $plain),
      'invited_by' => $inviter->id,
      'expires_at' => now()->addHours((int) config('okatana.invitation_ttl_hours', 168)),
    ]);

    $invite->load(['organization', 'project', 'inviter']);
    $existingUser = User::where('email', $email)->first();

    if ($existingUser) {
      Notification::route('mail', $email)->notify(
        new InvitationNotification($invite, $plain, accountExists: true),
      );

      $scope = $project ? 'project ' . $project->name : 'organization ' . $org->name;
      $existingUser->notifyNow(new InAppWorkspaceNotification([
        'kind' => 'invitation.received',
        'title' => 'Invitation to join ' . $scope,
        'body' => $inviter->name . ' invited you to join ' . $scope . ' as ' . $role . '.',
        'url' => null,
        'invitation_id' => $invite->id,
        'invitation_organization' => $org->name,
        'invitation_project' => $project?->name,
        'invitation_role' => $role,
        'invitation_inviter' => $inviter->name,
        'invitation_expires_at' => $invite->expires_at->toIso8601String(),
        'actor' => [
          'id' => $inviter->id,
          'name' => $inviter->name,
          'avatar_url' => $inviter->avatar_url,
        ],
      ]));
    } else {
      Notification::route('mail', $email)->notify(
        new InvitationNotification($invite, $plain, accountExists: false),
      );
    }

    return $invite;
  }

  public function findByPlainToken(string $token): Invitation
  {
    return Invitation::with(['organization', 'project', 'inviter'])
      ->where('token_hash', hash('sha256', $token))
      ->firstOrFail();
  }
}
