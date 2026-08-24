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

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\DocumentAccessService;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
  public function organization(
    Request $request,
    Organization $organization,
    AuthorizationService $auth,
    DocumentAccessService $documents,
  ): array {
    $auth->orgMember($request->user(), $organization);
    $q = AuditLog::where('organization_id', $organization->id);

    $this->restrictDocumentEvents($q, $request, $documents);
    if ($request->query('event')) {
      $q->where('event', 'like', $request->query('event') . '%');
    }

    if ($request->query('actor')) {
      $q->where(
        fn($x) => $x
          ->where('actor_name', 'like', '%' . $request->query('actor') . '%')
          ->orWhere('actor_email', 'like', '%' . $request->query('actor') . '%'),
      );
    }

    if ($search = trim((string) $request->query('q'))) {
      $projectIds = Project::withTrashed()
        ->where('organization_id', $organization->id)
        ->where('name', 'like', '%' . $search . '%')
        ->pluck('id');

      $q->where(function ($x) use ($search, $projectIds): void {
        $x->where('event', 'like', '%' . $search . '%')
          ->orWhere('actor_name', 'like', '%' . $search . '%')
          ->orWhere('actor_email', 'like', '%' . $search . '%')
          ->orWhere('subject_label', 'like', '%' . $search . '%')
          ->orWhere('subject_type', 'like', '%' . $search . '%');

        if ($projectIds->isNotEmpty()) {
          $x->orWhereIn('project_id', $projectIds);
        }
      });
    }

    $page = $q->latest('occurred_at')->paginate($this->perPage($request));

    $this->attachDisplayData($page->getCollection());

    return ['audit_logs' => $page];
  }

  public function project(
    Request $request,
    Project $project,
    AuthorizationService $auth,
    DocumentAccessService $documents,
  ): array {
    $project->load('organization');
    $auth->projectMember($request->user(), $project);

    $q = AuditLog::where('project_id', $project->id);
    $this->restrictDocumentEvents($q, $request, $documents);

    if ($request->query('event')) {
      $q->where('event', 'like', $request->query('event') . '%');
    }

    if ($search = trim((string) $request->query('q'))) {
      $q->where(
        fn($x) => $x
          ->where('event', 'like', '%' . $search . '%')
          ->orWhere('actor_name', 'like', '%' . $search . '%')
          ->orWhere('actor_email', 'like', '%' . $search . '%')
          ->orWhere('subject_label', 'like', '%' . $search . '%')
          ->orWhere('subject_type', 'like', '%' . $search . '%'),
      );
    }

    $page = $q->latest('occurred_at')->paginate($this->perPage($request));
    $this->attachDisplayData($page->getCollection());

    return ['audit_logs' => $page];
  }

  private function restrictDocumentEvents(
    $query,
    Request $request,
    DocumentAccessService $documents,
  ): void {
    $visibleDocumentIds = $documents
      ->visibleQuery($request->user())
      ->withTrashed()
      ->pluck('documents.id');
    $visibleCommentIds = DocumentComment::withTrashed()
      ->whereIn('document_id', $visibleDocumentIds)
      ->pluck('id');

    $query->where(function ($events) use ($visibleDocumentIds, $visibleCommentIds): void {
      $events
        ->where('event', 'not like', 'document.%')
        ->orWhere(function ($documentEvents) use ($visibleDocumentIds, $visibleCommentIds): void {
          $documentEvents
            ->where('event', 'like', 'document.%')
            ->where(function ($subjects) use ($visibleDocumentIds, $visibleCommentIds): void {
              $subjects
                ->where(function ($documents) use ($visibleDocumentIds): void {
                  $documents
                    ->where('subject_type', Document::class)
                    ->whereIn('subject_id', $visibleDocumentIds);
                })
                ->orWhere(function ($comments) use ($visibleCommentIds): void {
                  $comments
                    ->where('subject_type', DocumentComment::class)
                    ->whereIn('subject_id', $visibleCommentIds);
                });
            });
        });
    });
  }

  private function perPage(Request $request): int
  {
    $value = (int) $request->query('per_page', 10);

    return in_array($value, [10, 20, 50, 100], true) ? $value : 10;
  }

  private function attachDisplayData($rows): void
  {
    $actorIds = $rows->where('actor_type', 'user')->pluck('actor_id')->filter()->unique()->values();

    $users = User::whereIn('id', $actorIds)
      ->get(['id', 'avatar_path', 'updated_at'])
      ->keyBy('id');

    $projectIds = $rows->pluck('project_id')->filter()->unique()->values();

    $projects = Project::withTrashed()
      ->whereIn('id', $projectIds)
      ->get(['id', 'name', 'key'])
      ->keyBy('id');

    $commentIds = $rows
      ->filter(
        fn(AuditLog $row) => $row->subject_type === TicketComment::class &&
          !$row->subject_label &&
          $row->subject_id,
      )
      ->pluck('subject_id')
      ->unique()
      ->values();

    $comments = TicketComment::withTrashed()
      ->whereIn('id', $commentIds)
      ->get(['id', 'ticket_id'])
      ->keyBy('id');

    $ticketIds = $comments->pluck('ticket_id')->filter()->unique()->values();

    $tickets = Ticket::withTrashed()
      ->whereIn('id', $ticketIds)
      ->get(['id', 'project_id', 'number'])
      ->keyBy('id');

    $commentProjectIds = $tickets->pluck('project_id')->filter()->unique()->values();

    $commentProjects = Project::withTrashed()
      ->whereIn('id', $commentProjectIds)
      ->get(['id', 'name', 'key'])
      ->keyBy('id');

    $rows->each(function (AuditLog $row) use (
      $users,
      $projects,
      $comments,
      $tickets,
      $commentProjects,
    ): void {
      $row->setAttribute('actor_avatar_url', $users->get($row->actor_id)?->avatar_url);

      $project = $projects->get($row->project_id);
      $row->setAttribute('project_name', $project?->name);
      $row->setAttribute('project_key', $project?->key);

      if ($row->subject_type === TicketComment::class && !$row->subject_label) {
        $comment = $comments->get($row->subject_id);
        $ticket = $comment ? $tickets->get($comment->ticket_id) : null;

        $ticketProject = $ticket ? $commentProjects->get($ticket->project_id) : null;
        $row->setAttribute(
          'subject_label',
          $ticket
            ? 'Comment on ' .
              ($ticketProject?->key ? $ticketProject->key . '-' : '') .
              $ticket->number
            : 'Comment',
        );
      }
    });
  }
}
