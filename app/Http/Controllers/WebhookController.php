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
use App\Models\WebhookEndpoint;
use App\Services\ActivityRecorder;
use App\Services\AuthorizationService;
use App\Services\OutboundUrlGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
  public function index(Request $request, Organization $organization, AuthorizationService $auth)
  {
    $auth->orgAdmin($request->user(), $organization);

    return [
      'webhooks' => $organization
        ->webhooks()
        ->with('project:id,name,key')
        ->withCount('deliveries')
        ->latest()
        ->get([
          'id',
          'organization_id',
          'project_id',
          'name',
          'url',
          'events',
          'is_active',
          'last_rotated_at',
          'created_at',
        ]),
      'events' => $this->events(),
    ];
  }

  public function store(
    Request $request,
    Organization $organization,
    AuthorizationService $auth,
    ActivityRecorder $activity,
    OutboundUrlGuard $guard,
  ) {
    $auth->orgAdmin($request->user(), $organization);

    $data = $request->validate([
      'name' => 'required|string|max:120',
      'url' => 'required|url:http,https|max:2000',
      'project_id' => 'nullable|string',
      'events' => 'required|array|min:1',
      'events.*' => 'string|max:120',
    ]);

    foreach ($data['events'] as $event) {
      abort_unless(
        in_array($event, $this->events(), true),
        422,
        'Unsupported webhook event: ' . $event,
      );
    }

    if (!empty($data['project_id'])) {
      $organization->projects()->findOrFail($data['project_id']);
    }

    $guard->assertSafe($data['url']);

    $secret = 'whsec_' . Str::random(48);
    $hook = WebhookEndpoint::create([
      'organization_id' => $organization->id,
      'project_id' => $data['project_id'] ?? null,
      'name' => $data['name'],
      'url' => $data['url'],
      'secret' => $secret,
      'events' => array_values(array_unique($data['events'])),
      'created_by' => $request->user()->id,
      'last_rotated_at' => now(),
    ]);
    $project = $hook->project;

    $activity->record(
      'webhook.created',
      $organization,
      $project,
      $hook,
      [],
      ['events' => $hook->events],
      $request->user(),
      $request,
    );

    return response()->json(
      [
        'webhook' => $hook->only(['id', 'project_id', 'name', 'url', 'events', 'is_active']),
        'secret' => $secret,
      ],
      201,
    );
  }

  public function update(
    Request $request,
    WebhookEndpoint $webhook,
    AuthorizationService $auth,
    ActivityRecorder $activity,
    OutboundUrlGuard $guard,
  ) {
    $webhook->load('organization', 'project');
    $auth->orgAdmin($request->user(), $webhook->organization);

    $data = $request->validate([
      'name' => 'sometimes|required|string|max:120',
      'url' => 'sometimes|required|url:http,https|max:2000',
      'events' => 'sometimes|required|array|min:1',
      'events.*' => 'string|max:120',
      'is_active' => 'sometimes|boolean',
    ]);

    if (isset($data['events'])) {
      foreach ($data['events'] as $event) {
        abort_unless(
          in_array($event, $this->events(), true),
          422,
          'Unsupported webhook event: ' . $event,
        );
      }
    }

    if (isset($data['url'])) {
      $guard->assertSafe($data['url']);
    }

    $webhook->update($data);
    $activity->record(
      'webhook.updated',
      $webhook->organization,
      $webhook->project,
      $webhook,
      [],
      [],
      $request->user(),
      $request,
    );

    return [
      'webhook' => $webhook->only(['id', 'project_id', 'name', 'url', 'events', 'is_active']),
    ];
  }

  public function rotate(
    Request $request,
    WebhookEndpoint $webhook,
    AuthorizationService $auth,
    ActivityRecorder $activity,
  ) {
    $webhook->load('organization', 'project');
    $auth->orgAdmin($request->user(), $webhook->organization);

    $secret = 'whsec_' . Str::random(48);
    $webhook->update([
      'secret' => $secret,
      'last_rotated_at' => now(),
    ]);

    $activity->record(
      'webhook.secret_rotated',
      $webhook->organization,
      $webhook->project,
      $webhook,
      [],
      [],
      $request->user(),
      $request,
    );

    return ['secret' => $secret];
  }

  public function destroy(
    Request $request,
    WebhookEndpoint $webhook,
    AuthorizationService $auth,
    ActivityRecorder $activity,
  ) {
    $webhook->load('organization', 'project');
    $auth->orgAdmin($request->user(), $webhook->organization);
    $webhook->update(['is_active' => false]);

    $activity->record(
      'webhook.revoked',
      $webhook->organization,
      $webhook->project,
      $webhook,
      [],
      [],
      $request->user(),
      $request,
    );

    return response()->noContent();
  }

  public function deliveries(Request $request, WebhookEndpoint $webhook, AuthorizationService $auth)
  {
    $webhook->load('organization');
    $auth->orgAdmin($request->user(), $webhook->organization);

    return [
      'deliveries' => $webhook
        ->deliveries()
        ->latest()
        ->paginate(max(1, min((int) $request->query('per_page', 50), 200))),
    ];
  }

  private function events(): array
  {
    return [
      '*',
      'organization.created',
      'organization.updated',
      'organization.deleted',
      'organization.member_role_changed',
      'organization.member_removed',
      'project.created',
      'project.updated',
      'project.deleted',
      'project.members_changed',
      'board.created',
      'board.updated',
      'board.reordered',
      'board.deleted',
      'ticket.created',
      'ticket.updated',
      'ticket.moved',
      'ticket.reordered',
      'ticket.deleted',
      'comment.created',
      'comment.updated',
      'comment.deleted',
      'label.created',
      'label.updated',
      'label.deleted',
      'document.created',
      'document.updated',
      'document.published',
      'document.drafted',
      'document.archived',
      'document.unarchived',
      'document.deleted',
      'document.comment.created',
      'document.comment.updated',
      'document.comment.deleted',
      'invitation.created',
      'invitation.accepted',
      'invitation.revoked',
      'webhook.created',
      'webhook.updated',
      'webhook.secret_rotated',
      'webhook.revoked',
      'api_credential.created',
      'api_credential.revoked',
      'import.completed',
      'export.completed',
    ];
  }
}
