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

use App\Models\ApiCredential;
use App\Models\Organization;
use App\Services\ActivityRecorder;
use App\Services\ApiCredentialService;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;

class ApiCredentialController extends Controller
{
  public function index(Request $request, Organization $organization, AuthorizationService $auth)
  {
    $auth->orgAdmin($request->user(), $organization);

    return [
      'credentials' => $organization
        ->apiCredentials()
        ->latest()
        ->get([
          'id',
          'name',
          'public_id',
          'scopes',
          'last_used_at',
          'expires_at',
          'revoked_at',
          'created_at',
        ]),
      'available_scopes' => $this->scopes(),
    ];
  }

  public function store(
    Request $request,
    Organization $organization,
    AuthorizationService $auth,
    ApiCredentialService $service,
    ActivityRecorder $activity,
  ) {
    $auth->orgAdmin($request->user(), $organization);
    $data = $request->validate([
      'name' => 'required|string|max:120',
      'scopes' => 'required|array|min:1',
      'scopes.*' => 'string|max:80',
      'expires_at' => 'nullable|date|after:now',
    ]);

    foreach ($data['scopes'] as $scope) {
      abort_unless(
        in_array($scope, $this->scopes(), true),
        422,
        'Unsupported API scope: ' . $scope,
      );
    }
    [$cred, $token] = $service->create(
      $organization,
      $request->user(),
      $data['name'],
      $data['scopes'],
      $data['expires_at'] ?? null,
    );

    $activity->record(
      'api_credential.created',
      $organization,
      null,
      $cred,
      [],
      [
        'public_id' => $cred->public_id,
        'scopes' => $cred->scopes,
      ],
      $request->user(),
      $request,
    );

    return response()->json(
      [
        'credential' => $cred->only(['id', 'name', 'public_id', 'scopes', 'expires_at']),
        'token' => $token,
        'warning' => 'Store this token now. Okatana will not show the secret again.',
      ],
      201,
    );
  }

  public function destroy(
    Request $request,
    ApiCredential $credential,
    AuthorizationService $auth,
    ActivityRecorder $activity,
  ) {
    $credential->load('organization');
    $auth->orgAdmin($request->user(), $credential->organization);

    $credential->update(['revoked_at' => now()]);
    $activity->record(
      'api_credential.revoked',
      $credential->organization,
      null,
      $credential,
      [],
      [
        'public_id' => $credential->public_id,
      ],
      $request->user(),
      $request,
    );

    return response()->noContent();
  }

  private function scopes(): array
  {
    return [
      '*',
      'organization:read',
      'projects:read',
      'projects:write',
      'boards:read',
      'boards:write',
      'tickets:read',
      'tickets:write',
      'comments:write',
      'analytics:read',
      'documents:read',
      'documents:write',
      'document_comments:write',
      'notifications:write',
    ];
  }
}
