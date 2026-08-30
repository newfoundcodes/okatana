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
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\ActivityRecorder;
use App\Services\AuthorizationService;
use App\Services\HtmlSanitizer;
use App\Services\MentionService;
use App\Services\NotificationService;
use App\Services\RevisionService;
use App\Services\TagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
  public function index(Request $request, Project $project, AuthorizationService $auth)
  {
    $project->load('organization');
    $auth->projectMember($request->user(), $project);

    $q = $project
      ->tickets()
      ->with([
        'board:id,name,color,is_done',
        'assignees:id,name,email,avatar_path',
        'labels:id,name,color',
        'tags:id,name',
        'creator:id,name,email,avatar_path',
        'apiCredential:id,name',
      ]);

    if ($s = trim((string) $request->query('q'))) {
      $q->where(fn($x) => $x->where('title', 'like', '%' . $s . '%')->orWhere('number', $s));
    }

    foreach (
      [
        'board_ids' => 'board_id',
        'priorities' => 'priority',
        'created_by' => 'created_by',
      ]
      as $key => $col
    ) {
      $v = $request->query($key);
      if ($v) {
        $q->whereIn($col, is_array($v) ? $v : explode(',', $v));
      }
    }

    if ($v = $request->query('assignee_ids')) {
      $arr = is_array($v) ? $v : explode(',', $v);
      $q->whereHas('assignees', fn($x) => $x->whereIn('users.id', $arr));
    }

    if ($v = $request->query('label_ids')) {
      $arr = is_array($v) ? $v : explode(',', $v);
      $q->whereHas('labels', fn($x) => $x->whereIn('labels.id', $arr));
    }

    if ($v = $request->query('tag_ids')) {
      $arr = array_values(array_unique(array_filter(is_array($v) ? $v : explode(',', $v))));

      foreach ($arr as $tagId) {
        $q->whereHas('tags', fn($x) => $x->where('tags.id', $tagId));
      }
    }

    if ($request->boolean('overdue')) {
      $q->whereNotNull('due_at')
        ->where('due_at', '<', now())
        ->whereHas('board', fn($x) => $x->where('is_done', false));
    }

    if ($request->boolean('archived')) {
      $q->whereNotNull('archived_at');
    } else {
      $q->whereNull('archived_at');
    }

    $sort = in_array(
      $request->query('sort'),
      ['created_at', 'updated_at', 'due_at', 'priority', 'number', 'position'],
      true,
    )
      ? $request->query('sort')
      : 'position';

    $dir = $request->query('direction') === 'desc' ? 'desc' : 'asc';

    return [
      'tickets' => $q
        ->orderBy($sort, $dir)
        ->limit(max(1, min((int) $request->query('limit', 500), 1000)))
        ->get(),
    ];
  }

  public function store(
    Request $request,
    Project $project,
    AuthorizationService $auth,
    HtmlSanitizer $sanitizer,
    ActivityRecorder $activity,
    RevisionService $revisions,
    TagService $tags,
    MentionService $mentions,
    NotificationService $notifications,
  ) {
    $project->load('organization');
    $auth->projectMember($request->user(), $project);

    $data = $this->validated($request, false);
    $board = $project->boards()->findOrFail($data['board_id']);
    $data['description_html'] = $sanitizer->sanitize($data['description_html'] ?? null);

    $mentions->validateHtml($project, $data['description_html']);
    $this->assertAssignments($project, $data);

    if (
      $board->wip_limit &&
      $board->tickets()->whereNull('archived_at')->count() >= $board->wip_limit
    ) {
      abort(422, 'The destination board WIP limit has been reached.');
    }

    $ticket = DB::transaction(function () use ($project, $board, $data, $request, $tags) {
      Project::whereKey($project->id)->lockForUpdate()->first();

      $number = (int) $project->tickets()->withTrashed()->max('number') + 1;

      $ticket = Ticket::create([
        'project_id' => $project->id,
        'board_id' => $board->id,
        'number' => $number,
        'title' => $data['title'],
        'description_html' => $data['description_html'],
        'priority' => $data['priority'] ?? 'normal',
        'position' => (int) $board->tickets()->max('position') + 1000,
        'due_at' => $data['due_at'] ?? null,
        'started_at' => $board->is_done ? null : now(),
        'completed_at' => $board->is_done ? now() : null,
        'created_by' => $request->user()->id,
        'updated_by' => $request->user()->id,
      ]);

      $ticket->assignees()->sync($data['assignee_ids'] ?? []);
      $ticket->labels()->sync($data['label_ids'] ?? []);

      $tags->syncTicket($ticket, $data['tag_names'] ?? []);
      return $ticket;
    });

    $revisions->recordTicket($ticket->fresh(), $request->user(), $ticket->created_at);
    $mentions->syncTicket($ticket->fresh('project'), $request->user());

    if (!empty($data['assignee_ids'])) {
      $notifications->assigned(
        $ticket->fresh('project'),

        User::whereIn('id', $data['assignee_ids'])->get(),
        $request->user(),
      );
    }

    $activity->record(
      'ticket.created',
      $project->organization,
      $project,
      $ticket,
      [],
      [
        'ticket_key' => $project->key . '-' . $ticket->number,
      ],
      $request->user(),
      $request,
    );

    return response()->json(
      [
        'ticket' => $this->loadTicket($ticket->fresh()),
      ],
      201,
    );
  }

  public function show(Request $request, Ticket $ticket, AuthorizationService $auth)
  {
    $ticket->load('project.organization');
    $auth->projectMember($request->user(), $ticket->project);

    return ['ticket' => $this->loadTicket($ticket)];
  }

  public function showByNumber(
    Request $request,
    Project $project,
    int $number,
    AuthorizationService $auth,
  ) {
    $project->load('organization');
    $auth->projectMember($request->user(), $project);

    $ticket = $project->tickets()->where('number', $number)->firstOrFail();

    return [
      'ticket' => $this->loadTicket($ticket),
    ];
  }

  public function update(
    Request $request,
    Ticket $ticket,
    AuthorizationService $auth,
    HtmlSanitizer $sanitizer,
    ActivityRecorder $activity,
    RevisionService $revisions,
    TagService $tags,
    MentionService $mentions,
    NotificationService $notifications,
  ) {
    $ticket->load('project.organization', 'assignees', 'labels', 'tags');
    $auth->projectMember($request->user(), $ticket->project);

    $data = $this->validated($request, true);
    if (array_key_exists('description_html', $data)) {
      $data['description_html'] = $sanitizer->sanitize($data['description_html']);
      $mentions->validateHtml($ticket->project, $data['description_html']);
    }

    $this->assertAssignments($ticket->project, $data);

    $before = $revisions->ticketSnapshot($ticket);
    $beforeArchived = (bool) $ticket->archived_at;
    $beforeAssigneeIds = $ticket->assignees->pluck('id')->all();

    $update = [];
    foreach (['title', 'priority', 'due_at'] as $key) {
      if (array_key_exists($key, $data)) {
        $update[$key] = $data[$key];
      }
    }

    if (array_key_exists('description_html', $data)) {
      $update['description_html'] = $data['description_html'];
    }

    if (array_key_exists('archived', $data)) {
      $update['archived_at'] = $data['archived'] ? now() : null;
    }

    $update['updated_by'] = $request->user()->id;
    $ticket->update($update);

    if (array_key_exists('assignee_ids', $data)) {
      $ticket->assignees()->sync($data['assignee_ids']);
    }

    if (array_key_exists('label_ids', $data)) {
      $ticket->labels()->sync($data['label_ids']);
    }

    if (array_key_exists('tag_names', $data)) {
      $tags->syncTicket($ticket, $data['tag_names']);
    }

    $mentions->syncTicket($ticket->fresh('project'), $request->user());
    if (array_key_exists('assignee_ids', $data)) {
      $newAssigneeIds = array_values(array_diff($data['assignee_ids'], $beforeAssigneeIds));
      if ($newAssigneeIds) {
        $notifications->assigned(
          $ticket->fresh('project'),

          User::whereIn('id', $newAssigneeIds)->get(),
          $request->user(),
        );
      }
    }

    $fresh = $ticket
      ->fresh()
      ->load(['assignees:id,name,email,avatar_path', 'labels:id,name,color', 'tags:id,name']);

    $after = $revisions->ticketSnapshot($fresh);
    if ($before !== $after) {
      $revisions->ensureTicketInitial($ticket, $before);
      $ticket->forceFill(['edited_at' => now()])->saveQuietly();

      $fresh = $ticket
        ->fresh()
        ->load(['assignees:id,name,email,avatar_path', 'labels:id,name,color', 'tags:id,name']);
      $revisions->recordTicket($fresh, $request->user());
    }

    $auditChanges = [
      'before' => $before,
      'after' => $after,
    ];

    if (array_key_exists('archived', $data)) {
      $auditChanges['archived'] = [
        'before' => $beforeArchived,
        'after' => (bool) $ticket->fresh()->archived_at,
      ];
    }

    $activity->record(
      'ticket.updated',
      $ticket->project->organization,
      $ticket->project,
      $ticket,
      $auditChanges,
      [],
      $request->user(),
      $request,
    );

    return [
      'ticket' => $this->loadTicket($ticket->fresh()),
    ];
  }

  public function move(
    Request $request,
    Ticket $ticket,
    AuthorizationService $auth,
    ActivityRecorder $activity,
    NotificationService $notifications,
  ) {
    $ticket->load('project.organization', 'board');
    $auth->projectMember($request->user(), $ticket->project);

    $data = $request->validate([
      'board_id' => 'required|string',
      'position' => 'nullable|integer|min:0',
    ]);

    $target = $ticket->project->boards()->findOrFail($data['board_id']);

    if (
      $target->wip_limit &&
      $target->id !== $ticket->board_id &&
      $target->tickets()->whereNull('archived_at')->count() >= $target->wip_limit
    ) {
      abort(422, 'The destination board WIP limit has been reached.');
    }

    $from = $ticket->board;
    $pos = $data['position'] ?? (int) $target->tickets()->max('position') + 1000;
    $changedBoard = $target->id !== $from->id;

    DB::transaction(function () use ($ticket, $target, $from, $pos, $request, $changedBoard): void {
      $update = [
        'board_id' => $target->id,
        'position' => $pos,
        'updated_by' => $request->user()->id,
      ];

      if ($target->is_done) {
        $update['completed_at'] = now();
      } elseif ($from->is_done) {
        $update['completed_at'] = null;
      }

      if (!$ticket->started_at && !$target->is_done) {
        $update['started_at'] = now();
      }

      $ticket->update($update);
      if ($changedBoard) {
        TicketComment::create([
          'ticket_id' => $ticket->id,
          'user_id' => $request->user()->id,
          'kind' => 'movement',
          'body_html' => '<p>' . e($from->name) . ' → ' . e($target->name) . '</p>',
          'author_snapshot' => [
            'name' => $request->user()->name,
            'email' => $request->user()->email,
          ],
        ]);
      }
    });

    $activity->record(
      'ticket.moved',
      $ticket->project->organization,
      $ticket->project,
      $ticket,
      [
        'board' => [
          'before' => $from->id,
          'after' => $target->id,
        ],
      ],
      [
        'from' => $from->name,
        'to' => $target->name,
      ],
      $request->user(),
      $request,
    );

    if ($changedBoard) {
      $moved = $ticket->fresh('project');
      $recipients = $notifications->ticketParticipants($moved, [$request->user()->id]);

      if ($recipients->isNotEmpty()) {
        $notifications->moved($moved, $from->name, $target->name, $recipients, $request->user());
      }
    }

    return [
      'ticket' => $this->loadTicket($ticket->fresh()),
    ];
  }

  public function reorder(
    Request $request,
    Project $project,
    AuthorizationService $auth,
    ActivityRecorder $activity,
  ) {
    $project->load('organization');
    $auth->projectMember($request->user(), $project);

    $data = $request->validate([
      'board_id' => 'required|string',
      'ticket_ids' => 'required|array',
      'ticket_ids.*' => 'string',
    ]);
    $board = $project->boards()->findOrFail($data['board_id']);
    $valid = $board->tickets()->whereIn('id', $data['ticket_ids'])->pluck('id')->all();

    abort_unless(
      count($valid) === count(array_unique($data['ticket_ids'])),
      422,
      'All tickets must belong to the board.',
    );

    DB::transaction(
      fn() => collect($data['ticket_ids'])->each(
        fn($ticketId, $index) => Ticket::whereKey($ticketId)->update([
          'position' => ($index + 1) * 1000,
        ]),
      ),
    );

    $activity->record(
      'ticket.reordered',
      $project->organization,
      $project,
      $board,
      [],
      [
        'ticket_ids' => $data['ticket_ids'],
      ],
      $request->user(),
      $request,
    );

    return response()->noContent();
  }

  public function destroy(
    Request $request,
    Ticket $ticket,
    AuthorizationService $auth,
    ActivityRecorder $activity,
  ) {
    $ticket->load('project.organization');
    $auth->projectMember($request->user(), $ticket->project);

    $activity->record(
      'ticket.deleted',
      $ticket->project->organization,
      $ticket->project,
      $ticket,
      [],
      ['soft_delete' => true],
      $request->user(),
      $request,
    );

    $ticket->delete();
    return response()->noContent();
  }

  private function loadTicket(Ticket $ticket): Ticket
  {
    return $ticket->load([
      'board',
      'assignees:id,name,email,avatar_path',
      'labels:id,name,color',
      'tags:id,name',
      'creator:id,name,email,avatar_path',
      'apiCredential:id,name',
      'attachments.uploader:id,name,email,avatar_path',
      'comments.user:id,name,email,avatar_path',
      'comments.attachments.uploader:id,name,email,avatar_path',
    ]);
  }

  private function validated(Request $request, bool $partial): array
  {
    $required = $partial ? 'sometimes' : 'required';
    return $request->validate([
      'board_id' => [$required, 'string'],
      'title' => [$required, 'string', 'max:500'],
      'description_html' => 'nullable|string|max:200000',
      'priority' => [
        $partial ? 'sometimes' : 'nullable',
        'in:lowest,low,normal,high,highest,critical',
      ],
      'due_at' => 'nullable|date',
      'assignee_ids' => 'sometimes|array',
      'assignee_ids.*' => 'string',
      'label_ids' => 'sometimes|array',
      'label_ids.*' => 'string',
      'tag_names' => 'sometimes|array|max:20',
      'tag_names.*' => 'string|max:50',
      'archived' => 'sometimes|boolean',
    ]);
  }

  private function assertAssignments(Project $project, array $data): void
  {
    if (isset($data['assignee_ids'])) {
      $orgAdminIds = $project->organization
        ->members()
        ->wherePivotIn('role', ['owner', 'admin'])
        ->pluck('users.id');
      $allowed = $project->members()->pluck('users.id')->merge($orgAdminIds)->unique();

      abort_unless(
        $allowed->intersect($data['assignee_ids'])->count() ===
          count(array_unique($data['assignee_ids'])),
        422,
        'Assignees must have project access.',
      );
    }

    if (isset($data['label_ids'])) {
      abort_unless(
        $project->labels()->whereIn('id', $data['label_ids'])->count() ===
          count(array_unique($data['label_ids'])),
        422,
        'Labels must belong to the project.',
      );
    }
  }
}
