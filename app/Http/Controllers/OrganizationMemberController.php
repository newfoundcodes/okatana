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

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\ActivityRecorder;
use App\Services\AuthorizationService;
use App\Services\InvitationService;
use Illuminate\Http\Request;

class OrganizationMemberController extends Controller
{
  public function index(Request $request, Organization $organization, AuthorizationService $auth)
  {
    $auth->orgMember($request->user(), $organization);

    return [
      'members' => $organization->members()->orderBy('name')->get()->map(
        fn($u) => [
          'id' => $u->id,
          'name' => $u->name,
          'email' => $u->email,
          'avatar_url' => $u->avatar_url,
          'role' => $u->pivot->role,
        ],
      ),
      'pending_invitations' => $organization->canAdmin($request->user())
        ? Invitation::where('organization_id', $organization->id)
          ->whereNull('accepted_at')
          ->whereNull('revoked_at')
          ->where('expires_at', '>', now())
          ->get(['id', 'email', 'role', 'project_id', 'expires_at'])
        : [],
    ];
  }

  public function invite(
    Request $request,
    Organization $organization,
    AuthorizationService $auth,
    InvitationService $invites,
    ActivityRecorder $activity,
  ) {
    $auth->orgAdmin($request->user(), $organization);

    $data = $request->validate([
      'email' => 'required|email|max:255',
      'role' => 'required|in:owner,admin,user',
      'project_id' => 'nullable|exists:projects,id',
    ]);
    $project = null;

    if (!empty($data['project_id'])) {
      $project = $organization->projects()->findOrFail($data['project_id']);
    }

    $invite = $invites->invite(
      $organization,
      $project,
      $request->user(),
      $data['email'],
      $data['role'],
    );

    $activity->record(
      'invitation.created',
      $organization,
      $project,
      $invite,
      [],
      [
        'email' => $data['email'],
        'role' => $data['role'],
      ],
      $request->user(),
      $request,
    );

    return response()->json(
      [
        'invitation' => $invite->only(['id', 'email', 'role', 'project_id', 'expires_at']),
      ],
      201,
    );
  }

  public function update(
    Request $request,
    Organization $organization,
    User $user,
    AuthorizationService $auth,
    ActivityRecorder $activity,
  ) {
    $auth->orgAdmin($request->user(), $organization);

    $member = OrganizationMember::where('organization_id', $organization->id)
      ->where('user_id', $user->id)
      ->firstOrFail();

    $data = $request->validate(['role' => 'required|in:owner,admin,user']);
    if (
      $member->role === 'owner' &&
      $data['role'] !== 'owner' &&
      OrganizationMember::where('organization_id', $organization->id)
        ->where('role', 'owner')
        ->count() <= 1
    ) {
      abort(422, 'An organization must have at least one owner.');
    }

    $before = $member->role;
    $member->update(['role' => $data['role']]);

    $activity->record(
      'organization.member_role_changed',
      $organization,
      null,
      $user,
      [
        'role' => [
          'before' => $before,
          'after' => $data['role'],
        ],
      ],
      [],
      $request->user(),
      $request,
    );

    return [
      'member' => [
        'id' => $user->id,
        'role' => $data['role'],
      ],
    ];
  }

  public function destroy(
    Request $request,
    Organization $organization,
    User $user,
    AuthorizationService $auth,
    ActivityRecorder $activity,
  ) {
    $auth->orgAdmin($request->user(), $organization);

    $member = OrganizationMember::where('organization_id', $organization->id)
      ->where('user_id', $user->id)
      ->firstOrFail();

    if (
      $member->role === 'owner' &&
      OrganizationMember::where('organization_id', $organization->id)
        ->where('role', 'owner')
        ->count() <= 1
    ) {
      abort(422, 'The last owner cannot be removed.');
    }

    $organization->projects()->each(fn($p) => $p->members()->detach($user->id));
    $member->delete();

    $activity->record(
      'organization.member_removed',
      $organization,
      null,
      $user,
      [],
      [],
      $request->user(),
      $request,
    );

    return response()->noContent();
  }
}
