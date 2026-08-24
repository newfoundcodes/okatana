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

use App\Models\Board;
use App\Models\Organization;
use App\Models\Project;
use App\Services\ActivityRecorder;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
  public function workspaceIndex(Request $request): array
  {
    $data = $request->validate([
      'q' => 'nullable|string|max:200',
      'page' => 'nullable|integer|min:1',
      'per_page' => 'nullable|integer|in:10,20,50,100',
    ]);

    $user = $request->user();
    $search = trim((string) ($data['q'] ?? ''));
    $perPage = (int) ($data['per_page'] ?? 10);

    $query = Project::query()
      ->with(['organization:id,name'])
      ->withCount('tickets')
      ->where(function ($access) use ($user): void {
        $access
          ->whereHas('organization.members', function ($members) use ($user): void {
            $members
              ->where('users.id', $user->id)
              ->whereIn('organization_members.role', ['owner', 'admin']);
          })
          ->orWhereHas('members', fn($members) => $members->where('users.id', $user->id));
      });

    if ($search !== '') {
      $needle = '%' . strtolower($search) . '%';
      $query->where(function ($matches) use ($needle): void {
        $matches
          ->whereRaw('LOWER(projects.name) LIKE ?', [$needle])
          ->orWhereRaw('LOWER(projects.key) LIKE ?', [$needle])
          ->orWhereRaw('LOWER(projects.id) LIKE ?', [$needle])
          ->orWhereRaw('LOWER(projects.description) LIKE ?', [$needle])
          ->orWhereHas(
            'organization',
            fn($organization) => $organization->whereRaw('LOWER(name) LIKE ?', [$needle]),
          );
      });
    }

    $projects = $query->orderBy('name')->paginate($perPage);
    return [
      'projects' => $projects->items(),
      'pagination' => [
        'current_page' => $projects->currentPage(),
        'per_page' => $projects->perPage(),
        'total' => $projects->total(),
        'last_page' => $projects->lastPage(),
        'from' => $projects->firstItem(),
        'to' => $projects->lastItem(),
      ],
    ];
  }

  public function index(
    Request $request,
    Organization $organization,
    AuthorizationService $auth,
  ): array {
    $auth->orgMember($request->user(), $organization);

    $query = $organization->projects()->withCount('tickets');
    if (!$organization->canAdmin($request->user())) {
      $query->whereHas(
        'members',
        fn($members) => $members->where('users.id', $request->user()->id),
      );
    }

    return [
      'projects' => $query->orderBy('name')->get(),
    ];
  }

  public function store(
    Request $request,
    Organization $organization,
    AuthorizationService $auth,
    ActivityRecorder $activity,
  ) {
    $auth->orgAdmin($request->user(), $organization);

    $data = $request->validate([
      'name' => 'required|string|max:180',
      'key' => 'required|string|max:12|regex:/^[A-Za-z][A-Za-z0-9_-]*$/',
      'description' => 'nullable|string|max:5000',
      'create_default_boards' => 'nullable|boolean',
    ]);
    $data['key'] = strtoupper($data['key']);

    abort_if(
      $organization->projects()->withTrashed()->where('key', $data['key'])->exists(),
      422,
      'Project key already exists in this organization.',
    );

    $project = DB::transaction(function () use ($organization, $data) {
      $project = Project::create([
        'organization_id' => $organization->id,
        'name' => $data['name'],
        'key' => $data['key'],
        'description' => $data['description'] ?? null,
        'settings' => [],
      ]);

      if ($data['create_default_boards'] ?? true) {
        foreach (
          [
            ['Open', '#64748b', false],
            ['Hold', '#a16207', false],
            ['In-progress', '#2563eb', false],
            ['Pull Request', '#7c3aed', false],
            ['Deployed', '#15803d', true],
          ]
          as $index => $board
        ) {
          Board::create([
            'project_id' => $project->id,
            'name' => $board[0],
            'slug' => strtolower(str_replace([' ', '-'], ['_', '_'], $board[0])),
            'position' => ($index + 1) * 1000,
            'color' => $board[1],
            'is_done' => $board[2],
          ]);
        }
      }

      return $project;
    });

    $activity->record(
      'project.created',
      $organization,
      $project,
      $project,
      [],
      [],
      $request->user(),
      $request,
    );

    return response()->json(
      [
        'project' => $project->load('boards'),
      ],
      201,
    );
  }

  public function show(Request $request, Project $project, AuthorizationService $auth): array
  {
    $project->load('organization');
    $auth->projectMember($request->user(), $project);

    $members = $project
      ->members()
      ->get(['users.id', 'users.name', 'users.email', 'users.avatar_path']);

    $admins = $project->organization
      ->members()
      ->wherePivotIn('role', ['owner', 'admin'])
      ->get(['users.id', 'users.name', 'users.email', 'users.avatar_path']);

    return [
      'project' => $project,
      'role' => $project->organization->roleFor($request->user()),
      'viewer_id' => $request->user()->id,
      'boards' => $project->boards()->withCount('tickets')->get(),
      'members' => $members,
      'assignable_members' => $members->concat($admins)->unique('id')->values(),
      'labels' => $project->labels()->withCount('tickets')->orderBy('name')->get(),
      'tags' => $project
        ->tags()
        ->whereHas('tickets')
        ->withCount('tickets')
        ->orderBy('name')
        ->get(['id', 'project_id', 'name']),
    ];
  }

  public function update(
    Request $request,
    Project $project,
    AuthorizationService $auth,
    ActivityRecorder $activity,
  ): array {
    $project->load('organization');
    $auth->orgAdmin($request->user(), $project->organization);

    $data = $request->validate([
      'name' => 'sometimes|required|string|max:180',
      'description' => 'nullable|string|max:5000',
      'settings' => 'nullable|array',
      'archived' => 'nullable|boolean',
    ]);

    $before = $project->toArray();
    if (array_key_exists('archived', $data)) {
      $data['archived_at'] = $data['archived'] ? now() : null;
      unset($data['archived']);
    }

    $project->update($data);
    $activity->record(
      'project.updated',
      $project->organization,
      $project,
      $project,
      [
        'before' => $before,
        'after' => $project->fresh()->toArray(),
      ],
      [],
      $request->user(),
      $request,
    );

    return ['project' => $project->fresh()];
  }

  public function destroy(
    Request $request,
    Project $project,
    AuthorizationService $auth,
    ActivityRecorder $activity,
  ) {
    $project->load('organization');
    $auth->orgAdmin($request->user(), $project->organization);

    $activity->record(
      'project.deleted',
      $project->organization,
      $project,
      $project,
      [],
      ['soft_delete' => true],
      $request->user(),
      $request,
    );

    $project->delete();
    return response()->noContent();
  }
}
