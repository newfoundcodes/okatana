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

use App\Models\Label;
use App\Models\Project;
use App\Services\ActivityRecorder;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;

class LabelController extends Controller
{
  public function store(
    Request $request,
    Project $project,
    AuthorizationService $auth,
    ActivityRecorder $activity,
  ) {
    $project->load('organization');
    $auth->orgAdmin($request->user(), $project->organization);

    $data = $request->validate([
      'name' => 'required|string|max:80',
      'color' => 'nullable|string|max:32',
    ]);
    $label = Label::create(['project_id' => $project->id] + $data);

    $activity->record(
      'label.created',
      $project->organization,
      $project,
      $label,
      [],
      [],
      $request->user(),
      $request,
    );

    return response()->json(['label' => $label], 201);
  }

  public function update(
    Request $request,
    Label $label,
    AuthorizationService $auth,
    ActivityRecorder $activity,
  ) {
    $label->load('project.organization');
    $auth->orgAdmin($request->user(), $label->project->organization);

    $data = $request->validate([
      'name' => 'sometimes|required|string|max:80',
      'color' => 'nullable|string|max:32',
    ]);

    $label->update($data);
    $activity->record(
      'label.updated',
      $label->project->organization,
      $label->project,
      $label,
      [],
      [],
      $request->user(),
      $request,
    );

    return ['label' => $label];
  }

  public function destroy(
    Request $request,
    Label $label,
    AuthorizationService $auth,
    ActivityRecorder $activity,
  ) {
    $label->load('project.organization');
    $auth->orgAdmin($request->user(), $label->project->organization);

    $activity->record(
      'label.deleted',
      $label->project->organization,
      $label->project,
      $label,
      [],
      [],
      $request->user(),
      $request,
    );

    $label->delete();
    return response()->noContent();
  }
}
