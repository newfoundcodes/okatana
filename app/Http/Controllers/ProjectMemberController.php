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

use App\Models\Project;
use App\Services\ActivityRecorder;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;

class ProjectMemberController extends Controller
{
  public function sync(
    Request $request,
    Project $project,
    AuthorizationService $auth,
    ActivityRecorder $activity,
  ) {
    $project->load('organization');
    $auth->orgAdmin($request->user(), $project->organization);

    $data = $request->validate([
      'user_ids' => 'array',
      'user_ids.*' => 'string',
    ]);

    $allowed = $project->organization
      ->members()
      ->whereIn('users.id', $data['user_ids'] ?? [])
      ->pluck('users.id')
      ->all();

    if (count($allowed) !== count(array_unique($data['user_ids'] ?? []))) {
      abort(422, 'Every project member must first belong to the organization.');
    }

    $before = $project->members()->pluck('users.id')->all();
    $project->members()->sync($allowed);

    $activity->record(
      'project.members_changed',
      $project->organization,
      $project,
      $project,
      [
        'members' => [
          'before' => $before,
          'after' => $allowed,
        ],
      ],
      [],
      $request->user(),
      $request,
    );

    return [
      'members' => $project
        ->members()
        ->get(['users.id', 'users.name', 'users.email', 'users.avatar_path']),
    ];
  }
}
