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
use App\Models\OrganizationMember;
use App\Models\ProjectMember;
use App\Models\User;
use App\Notifications\InvitationAcceptedNotification;
use App\Notifications\InvitationDeclinedNotification;
use App\Services\AccountSecurityService;
use App\Services\ActivityRecorder;
use App\Services\AuthorizationService;
use App\Services\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class InvitationController extends Controller
{
  public function show(
    Request $request,
    string $token,
    InvitationService $service,
    AccountSecurityService $security,
  ) {
    $invite = $service->findByPlainToken($token);
    $pendingLoginId = $request->session()->get('two_factor_login_user_id');
    $viewer = $request->user() ?: ($pendingLoginId ? User::find($pendingLoginId) : null);
    $sameViewer = $viewer && strtolower($viewer->email) === strtolower($invite->email);
    $pendingSecurity =
      $invite->accepted_at && $sameViewer && !$security->state($viewer)['complete'];

    $pendingChallenge =
      $security->twoFactorRequired() &&
      $invite->accepted_at &&
      $sameViewer &&
      $viewer->two_factor_confirmed_at &&
      (string) $pendingLoginId === (string) $viewer->id;

    abort_unless(
      $invite->active() || $pendingSecurity || $pendingChallenge,
      410,
      'Invitation is expired, accepted, or revoked.',
    );

    return [
      'invitation' => [
        'email' => $invite->email,
        'role' => $invite->role,
        'expires_at' => $invite->expires_at,
        'organization' => $invite->organization->only(['id', 'name']),
        'project' => $invite->project?->only(['id', 'name', 'key']),
        'account_exists' => ($existing = User::where('email', $invite->email)->first()) !== null,
        'requires_current_password' => !empty($existing?->password),
        'two_factor_required' => $security->twoFactorRequired(),
        'security_pending' => (bool) $pendingSecurity,
        'two_factor_challenge_pending' => (bool) $pendingChallenge,
        'security' => $pendingSecurity ? $security->state($viewer) : null,
      ],
    ];
  }

  public function accept(
    Request $request,
    string $token,
    InvitationService $service,
    ActivityRecorder $activity,
    AccountSecurityService $security,
  ) {
    $invite = $service->findByPlainToken($token);
    abort_unless($invite->active(), 410, 'Invitation is expired, accepted, or revoked.');

    $data = $request->validate([
      'name' => 'required|string|max:120',
      'current_password' => 'nullable|string|max:1000',
      'password' => ['required', 'confirmed', Password::min(14)->mixedCase()->numbers()->symbols()],
    ]);

    $user = DB::transaction(function () use ($invite, $data) {
      $user = User::where('email', $invite->email)->first();
      if (!$user) {
        $user = User::create([
          'name' => $data['name'],
          'email' => $invite->email,
          'password' => $data['password'],
          'email_verified_at' => now(),
          'security_setup_required_at' => now(),
        ]);
      } else {
        if (!empty($user->password)) {
          abort_unless(
            isset($data['current_password']) &&
              Hash::check($data['current_password'], $user->password),
            422,
            'The current password is required for an existing Okatana account.',
          );
        }

        $user
          ->forceFill([
            'name' => $user->name ?: $data['name'],
            'password' => $data['password'],
            'email_verified_at' => $user->email_verified_at ?: now(),
            'security_setup_required_at' => $user->two_factor_confirmed_at
              ? $user->security_setup_required_at
              : now(),
          ])
          ->save();
      }

      $membership = OrganizationMember::where('organization_id', $invite->organization_id)
        ->where('user_id', $user->id)
        ->first();

      if (!$membership) {
        OrganizationMember::create([
          'organization_id' => $invite->organization_id,
          'user_id' => $user->id,
          'role' => $invite->role,
        ]);
      } else {
        $rank = [
          'user' => 1,
          'admin' => 2,
          'owner' => 3,
        ];

        if (($rank[$invite->role] ?? 0) > ($rank[$membership->role] ?? 0)) {
          $membership->update(['role' => $invite->role]);
        }
      }
      if ($invite->project_id) {
        ProjectMember::firstOrCreate([
          'project_id' => $invite->project_id,
          'user_id' => $user->id,
        ]);
      }

      $invite->update(['accepted_at' => now()]);
      return $user;
    });

    $fresh = $user->fresh();
    $activity->record(
      'invitation.accepted',
      $invite->organization,
      $invite->project,
      $invite,
      [],
      ['email' => $invite->email],
      $user,
      $request,
    );

    $inviter = $invite->inviter;
    if ($inviter) {
      $inviter->notify(new InvitationAcceptedNotification($invite, $fresh));
    }

    if ($security->twoFactorRequired() && $fresh->two_factor_confirmed_at) {
      Auth::logout();

      $request->session()->regenerate();
      $request->session()->put('two_factor_login_user_id', $fresh->id);
      $request->session()->put('two_factor_login_remember', false);

      return [
        'status' => 'two_factor_challenge_required',
        'account' => [
          'email' => $fresh->email,
          'name' => $fresh->name,
        ],
        'organization_id' => $invite->organization_id,
        'project_id' => $invite->project_id,
      ];
    }

    Auth::login($fresh, false);

    $request->session()->regenerate();
    $state = $security->state($fresh);

    return [
      'status' => $state['complete'] ? 'authenticated' : 'two_factor_setup_required',
      'user' => $fresh,
      'security' => $state,
      'organization_id' => $invite->organization_id,
      'project_id' => $invite->project_id,
    ];
  }

  public function respond(
    Request $request,
    Invitation $invitation,
    ActivityRecorder $activity,
  ) {
    $invitation->load(['organization', 'project', 'inviter']);

    abort_unless(
      strtolower($request->user()->email) === strtolower($invitation->email),
      403,
      'This invitation is not addressed to your account.',
    );
    abort_unless($invitation->active(), 410, 'Invitation is expired, accepted, declined, or revoked.');

    $data = $request->validate([
      'action' => 'required|in:accept,decline',
    ]);

    $user = $request->user();

    if ($data['action'] === 'accept') {
      DB::transaction(function () use ($invitation, $user) {
        $membership = OrganizationMember::where('organization_id', $invitation->organization_id)
          ->where('user_id', $user->id)
          ->first();

        if (!$membership) {
          OrganizationMember::create([
            'organization_id' => $invitation->organization_id,
            'user_id' => $user->id,
            'role' => $invitation->role,
          ]);
        } else {
          $rank = ['user' => 1, 'admin' => 2, 'owner' => 3];
          if (($rank[$invitation->role] ?? 0) > ($rank[$membership->role] ?? 0)) {
            $membership->update(['role' => $invitation->role]);
          }
        }

        if ($invitation->project_id) {
          ProjectMember::firstOrCreate([
            'project_id' => $invitation->project_id,
            'user_id' => $user->id,
          ]);
        }

        $invitation->update(['accepted_at' => now()]);
      });

      $activity->record(
        'invitation.accepted',
        $invitation->organization,
        $invitation->project,
        $invitation,
        [],
        ['email' => $invitation->email],
        $user,
        $request,
      );

      if ($invitation->inviter) {
        $invitation->inviter->notify(new InvitationAcceptedNotification($invitation, $user));
      }

      return [
        'status' => 'accepted',
        'organization_id' => $invitation->organization_id,
        'project_id' => $invitation->project_id,
      ];
    }

    $invitation->update(['declined_at' => now()]);
    $activity->record(
      'invitation.declined',
      $invitation->organization,
      $invitation->project,
      $invitation,
      [],
      ['email' => $invitation->email],
      $user,
      $request,
    );

    if ($invitation->inviter) {
      $invitation->inviter->notify(new InvitationDeclinedNotification($invitation, $user));
    }

    return ['status' => 'declined'];
  }

  public function revoke(
    Request $request,
    Invitation $invitation,
    AuthorizationService $auth,
    ActivityRecorder $activity,
  ) {
    $invitation->load('organization', 'project');
    $auth->orgAdmin($request->user(), $invitation->organization);
    abort_if($invitation->accepted_at, 422, 'Accepted invitations cannot be revoked.');

    $invitation->update(['revoked_at' => now()]);
    $activity->record(
      'invitation.revoked',
      $invitation->organization,
      $invitation->project,
      $invitation,
      [],
      ['email' => $invitation->email],
      $request->user(),
      $request,
    );

    return response()->noContent();
  }
}
