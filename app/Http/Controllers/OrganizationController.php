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

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Project;
use App\Services\ActivityRecorder;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationController extends Controller
{
  public function index(Request $request)
  {
    $user = $request->user();
    $organizations = $user
      ->organizations()
      ->withCount('projects')
      ->orderBy('name')
      ->get()
      ->map(fn($o) => array_merge($o->toArray(), ['role' => $o->pivot->role]));

    $activity = DB::table('audit_logs')
      ->select('project_id')
      ->selectRaw('MAX(occurred_at) AS last_active_at')
      ->where('actor_type', 'user')
      ->where('actor_id', $user->id)
      ->whereNotNull('project_id')
      ->groupBy('project_id');

    $recentProjects = Project::query()
      ->select('projects.*')
      ->joinSub(
        $activity,
        'recent_activity',
        fn($join) => $join->on('projects.id', '=', 'recent_activity.project_id'),
      )
      ->withCount('tickets')
      ->where(function ($query) use ($user): void {
        $query
          ->whereHas(
            'organization.members',
            fn($members) => $members
              ->where('users.id', $user->id)
              ->whereIn('organization_members.role', ['owner', 'admin']),
          )
          ->orWhereHas('members', fn($members) => $members->where('users.id', $user->id));
      })
      ->orderByDesc('recent_activity.last_active_at')
      ->limit(5)
      ->get();

    return [
      'organizations' => $organizations,
      'recent_projects' => $recentProjects,
    ];
  }

  public function store(Request $request, ActivityRecorder $activity)
  {
    $data = $request->validate([
      'name' => 'required|string|max:180',
      'slug' => 'nullable|string|max:80|alpha_dash',
      'description' => 'nullable|string|max:5000',
    ]);

    $org = DB::transaction(function () use ($data, $request) {
      $base = Str::slug($data['slug'] ?? $data['name']);
      $slug = $base;

      $i = 2;
      while (Organization::withTrashed()->where('slug', $slug)->exists()) {
        $slug = $base . '-' . $i++;
      }

      $org = Organization::create([
        'name' => $data['name'],
        'slug' => $slug,
        'description' => $data['description'] ?? null,
        'settings' => [],
      ]);

      OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $request->user()->id,
        'role' => 'owner',
      ]);

      return $org;
    });

    $activity->record('organization.created', $org, null, $org, [], [], $request->user(), $request);

    return response()->json(
      [
        'organization' => $org,
        'role' => 'owner',
      ],
      201,
    );
  }

  public function show(Request $request, Organization $organization, AuthorizationService $auth)
  {
    $auth->orgMember($request->user(), $organization);

    return [
      'organization' => $organization,
      'role' => $organization->roleFor($request->user()),
      'projects' => $organization->projects()->withCount('tickets')->orderBy('name')->get(),
      'member_count' => $organization->members()->count(),
    ];
  }

  public function update(
    Request $request,
    Organization $organization,
    AuthorizationService $auth,
    ActivityRecorder $activity,
  ) {
    $auth->orgAdmin($request->user(), $organization);

    $data = $request->validate([
      'name' => 'sometimes|required|string|max:180',
      'description' => 'nullable|string|max:5000',
      'settings' => 'nullable|array',
    ]);
    $before = $organization->only(array_keys($data));

    $organization->update($data);
    $activity->record(
      'organization.updated',
      $organization,
      null,
      $organization,
      [
        'before' => $before,
        'after' => $organization->only(array_keys($data)),
      ],
      [],
      $request->user(),
      $request,
    );

    return ['organization' => $organization];
  }

  public function destroy(
    Request $request,
    Organization $organization,
    AuthorizationService $auth,
    ActivityRecorder $activity,
  ) {
    $auth->orgOwner($request->user(), $organization);
    $activity->record(
      'organization.deleted',
      $organization,
      null,
      $organization,
      [],
      ['soft_delete' => true],
      $request->user(),
      $request,
    );

    $organization->delete();
    return response()->noContent();
  }
}
