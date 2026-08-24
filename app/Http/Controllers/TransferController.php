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

use App\Models\DataTransfer;
use App\Models\Organization;
use App\Models\Project;
use App\Services\ActivityRecorder;
use App\Services\AuthorizationService;
use App\Services\DataTransferService;
use App\Services\InvitationService;
use Illuminate\Http\Request;
use Throwable;

class TransferController extends Controller
{
  public function exportOrganization(
    Request $request,
    Organization $organization,
    AuthorizationService $auth,
    DataTransferService $service,
    ActivityRecorder $activity,
  ) {
    $auth->orgAdmin($request->user(), $organization);

    $payload = $service->exportOrganization($organization);
    $transfer = DataTransfer::create([
      'organization_id' => $organization->id,
      'user_id' => $request->user()->id,
      'direction' => 'export',
      'scope' => 'organization',
      'status' => 'completed',
      'summary' => ['schema' => DataTransferService::SCHEMA],
      'started_at' => now(),
      'finished_at' => now(),
    ]);

    $activity->record(
      'export.completed',
      $organization,
      null,
      $transfer,
      [],
      ['scope' => 'organization'],
      $request->user(),
      $request,
    );

    return response()
      ->json($payload)
      ->header(
        'Content-Disposition',
        'attachment; filename="okatana-org-' .
          $organization->slug .
          '-' .
          now()->format('Ymd-His') .
          '.json"',
      );
  }

  public function exportProject(
    Request $request,
    Project $project,
    AuthorizationService $auth,
    DataTransferService $service,
    ActivityRecorder $activity,
  ) {
    $project->load('organization');
    $auth->projectMember($request->user(), $project);

    $payload = $service->exportProject($project);
    $transfer = DataTransfer::create([
      'organization_id' => $project->organization_id,
      'project_id' => $project->id,
      'user_id' => $request->user()->id,
      'direction' => 'export',
      'scope' => 'project',
      'status' => 'completed',
      'summary' => ['schema' => DataTransferService::SCHEMA],
      'started_at' => now(),
      'finished_at' => now(),
    ]);

    $activity->record(
      'export.completed',
      $project->organization,
      $project,
      $transfer,
      [],
      ['scope' => 'project'],
      $request->user(),
      $request,
    );

    return response()
      ->json($payload)
      ->header(
        'Content-Disposition',
        'attachment; filename="okatana-project-' .
          strtolower($project->key) .
          '-' .
          now()->format('Ymd-His') .
          '.json"',
      );
  }

  public function exportProjectTimeline(
    Request $request,
    Project $project,
    AuthorizationService $auth,
    DataTransferService $service,
    ActivityRecorder $activity,
  ) {
    $project->load('organization');
    $auth->projectMember($request->user(), $project);

    $payload = $service->exportTimeline($project->organization, $project);
    $transfer = DataTransfer::create([
      'organization_id' => $project->organization_id,
      'project_id' => $project->id,
      'user_id' => $request->user()->id,
      'direction' => 'export',
      'scope' => 'timeline',
      'status' => 'completed',
      'summary' => ['schema' => DataTransferService::SCHEMA],
      'started_at' => now(),
      'finished_at' => now(),
    ]);

    $activity->record(
      'export.completed',
      $project->organization,
      $project,
      $transfer,
      [],
      ['scope' => 'timeline'],
      $request->user(),
      $request,
    );

    return response()
      ->json($payload)
      ->header(
        'Content-Disposition',
        'attachment; filename="okatana-project-' .
          strtolower($project->key) .
          '-timeline-' .
          now()->format('Ymd-His') .
          '.json"',
      );
  }

  public function exportTimeline(
    Request $request,
    Organization $organization,
    AuthorizationService $auth,
    DataTransferService $service,
    ActivityRecorder $activity,
  ) {
    $auth->orgMember($request->user(), $organization);

    $project = null;
    if ($request->query('project_id')) {
      $project = $organization->projects()->findOrFail($request->query('project_id'));
      $auth->projectMember($request->user(), $project);
    }

    $payload = $service->exportTimeline($organization, $project);
    $transfer = DataTransfer::create([
      'organization_id' => $organization->id,
      'project_id' => $project?->id,
      'user_id' => $request->user()->id,
      'direction' => 'export',
      'scope' => 'timeline',
      'status' => 'completed',
      'summary' => ['schema' => DataTransferService::SCHEMA],
      'started_at' => now(),
      'finished_at' => now(),
    ]);

    $activity->record(
      'export.completed',
      $organization,
      $project,
      $transfer,
      [],
      ['scope' => 'timeline'],
      $request->user(),
      $request,
    );

    return response()
      ->json($payload)
      ->header(
        'Content-Disposition',
        'attachment; filename="okatana-timeline-' . now()->format('Ymd-His') . '.json"',
      );
  }

  public function importOrganization(
    Request $request,
    AuthorizationService $auth,
    DataTransferService $service,
    InvitationService $invites,
    ActivityRecorder $activity,
  ) {
    $data = $request->validate(['payload' => 'required|array']);
    $transfer = DataTransfer::create([
      'user_id' => $request->user()->id,
      'direction' => 'import',
      'scope' => 'organization',
      'status' => 'running',
      'started_at' => now(),
    ]);

    try {
      $result = $service->importOrganization($data['payload'], $request->user(), $invites);
      $transfer->update([
        'organization_id' => $result['organization']->id,
        'status' => 'completed',
        'summary' => ['warnings' => $result['warnings']],
        'finished_at' => now(),
      ]);

      $activity->record(
        'import.completed',
        $result['organization'],
        null,
        $transfer,
        [],
        [
          'scope' => 'organization',
          'warnings' => $result['warnings'],
        ],
        $request->user(),
        $request,
      );

      return response()->json(
        [
          'organization' => $result['organization'],
          'warnings' => $result['warnings'],
        ],
        201,
      );
    } catch (Throwable $e) {
      $transfer->update([
        'status' => 'failed',
        'errors' => [$e->getMessage()],
        'finished_at' => now(),
      ]);

      throw $e;
    }
  }

  public function importTimeline(
    Request $request,
    Organization $organization,
    AuthorizationService $auth,
    DataTransferService $service,
    ActivityRecorder $activity,
  ) {
    $auth->orgAdmin($request->user(), $organization);
    $data = $request->validate([
      'payload' => 'required|array',
      'project_id' => 'nullable|string',
    ]);

    $project = !empty($data['project_id'])
      ? $organization->projects()->findOrFail($data['project_id'])
      : null;

    $transfer = DataTransfer::create([
      'organization_id' => $organization->id,
      'project_id' => $project?->id,
      'user_id' => $request->user()->id,
      'direction' => 'import',
      'scope' => 'timeline',
      'status' => 'running',
      'started_at' => now(),
    ]);

    try {
      $count = $service->importTimeline($organization, $project, $data['payload']);
      $transfer->update([
        'status' => 'completed',
        'summary' => ['imported_audit_logs' => $count],
        'finished_at' => now(),
      ]);

      $activity->record(
        'import.completed',
        $organization,
        $project,
        $transfer,
        [],
        [
          'scope' => 'timeline',
          'imported_audit_logs' => $count,
        ],
        $request->user(),
        $request,
      );

      return response()->json(
        [
          'imported_audit_logs' => $count,
        ],
        201,
      );
    } catch (Throwable $e) {
      $transfer->update([
        'status' => 'failed',
        'errors' => [$e->getMessage()],
        'finished_at' => now(),
      ]);
      throw $e;
    }
  }

  public function importProject(
    Request $request,
    Organization $organization,
    AuthorizationService $auth,
    DataTransferService $service,
    InvitationService $invites,
    ActivityRecorder $activity,
  ) {
    $auth->orgAdmin($request->user(), $organization);

    $data = $request->validate(['payload' => 'required|array']);
    $transfer = DataTransfer::create([
      'organization_id' => $organization->id,
      'user_id' => $request->user()->id,
      'direction' => 'import',
      'scope' => 'project',
      'status' => 'running',
      'started_at' => now(),
    ]);

    try {
      $result = $service->importProject(
        $organization,
        $data['payload'],
        $request->user(),
        $invites,
      );

      $transfer->update([
        'project_id' => $result['project']->id,
        'status' => 'completed',
        'summary' => ['warnings' => $result['warnings']],
        'finished_at' => now(),
      ]);

      $activity->record(
        'import.completed',
        $organization,
        $result['project'],
        $transfer,
        [],
        [
          'scope' => 'project',
          'warnings' => $result['warnings'],
        ],
        $request->user(),
        $request,
      );

      return response()->json(
        [
          'project' => $result['project'],
          'warnings' => $result['warnings'],
        ],
        201,
      );
    } catch (Throwable $e) {
      $transfer->update([
        'status' => 'failed',
        'errors' => [$e->getMessage()],
        'finished_at' => now(),
      ]);

      throw $e;
    }
  }
}
