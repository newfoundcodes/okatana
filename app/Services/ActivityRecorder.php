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

use App\Jobs\DeliverWebhookJob;
use App\Models\AuditLog;
use App\Models\DocumentComment;
use App\Models\Organization;
use App\Models\Project;
use App\Models\TicketComment;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityRecorder
{
  public function record(
    string $event,
    Organization $organization,
    ?Project $project = null,
    ?Model $subject = null,
    array $changes = [],
    array $metadata = [],
    ?User $actor = null,
    ?Request $request = null,
    string $actorType = 'user',
    ?string $actorId = null,
    ?string $actorName = null,
    ?string $actorEmail = null,
  ): AuditLog {
    $actor ??= $request?->user();

    $log = AuditLog::create([
      'organization_id' => $organization->id,
      'project_id' => $project?->id,
      'event' => $event,
      'actor_type' => $actorType,
      'actor_id' => $actor?->id ?? $actorId,
      'actor_name' => $actor?->name ?? $actorName,
      'actor_email' => $actor?->email ?? $actorEmail,
      'subject_type' => $subject ? $subject::class : null,
      'subject_id' => $subject?->getKey(),
      'subject_label' => $subject ? $this->subjectLabel($subject) : null,
      'changes' => $changes ?: null,
      'metadata' => $metadata ?: null,
      'ip_address' => $request?->ip(),
      'user_agent' => substr((string) $request?->userAgent(), 0, 2000) ?: null,
      'occurred_at' => now(),
    ]);

    $this->queueWebhooks($log, $organization, $project);
    return $log;
  }

  private function subjectLabel(Model $subject): ?string
  {
    if ($subject instanceof DocumentComment) {
      $document = $subject->relationLoaded('document')
        ? $subject->document
        : $subject->document()->withTrashed()->first();

      return $document ? 'Comment on ' . $document->title : 'Document comment';
    }

    if ($subject instanceof TicketComment) {
      $ticket = $subject->relationLoaded('ticket')
        ? $subject->ticket
        : $subject
          ->ticket()
          ->withTrashed()
          ->with(['project' => fn($query) => $query->withTrashed()])
          ->first();

      if ($ticket) {
        return 'Comment on ' .
          ($ticket->project?->key ? $ticket->project->key . '-' : '') .
          $ticket->number;
      }

      return 'Comment';
    }

    return $subject->title ?? ($subject->name ?? class_basename($subject));
  }

  private function queueWebhooks(AuditLog $log, Organization $organization, ?Project $project): void
  {
    WebhookEndpoint::query()
      ->where('organization_id', $organization->id)
      ->where('is_active', true)
      ->where(
        fn($q) => $q
          ->whereNull('project_id')
          ->when($project, fn($q) => $q->orWhere('project_id', $project->id)),
      )
      ->get()
      ->each(function (WebhookEndpoint $endpoint) use ($log, $organization, $project): void {
        $events = $endpoint->events ?? [];
        if (!in_array('*', $events, true) && !in_array($log->event, $events, true)) {
          return;
        }

        $payload = [
          'id' => $log->id,
          'event' => $log->event,
          'occurred_at' => $log->occurred_at?->toIso8601String(),
          'organization' => [
            'id' => $organization->id,
            'name' => $organization->name,
          ],
          'project' => $project?->only(['id', 'name', 'key']),
          'actor' => [
            'type' => $log->actor_type,
            'id' => $log->actor_id,
            'name' => $log->actor_name,
            'email' => $log->actor_email,
          ],
          'subject' => [
            'type' => $log->subject_type,
            'id' => $log->subject_id,
            'label' => $log->subject_label,
          ],
          'changes' => $log->changes,
          'metadata' => $log->metadata,
        ];

        $delivery = WebhookDelivery::create([
          'webhook_endpoint_id' => $endpoint->id,
          'event' => $log->event,
          'payload' => $payload,
          'status' => 'pending',
        ]);

        DeliverWebhookJob::dispatch($delivery->id);
      });
  }
}
