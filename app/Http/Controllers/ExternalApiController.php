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
use App\Models\Board;
use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\ActivityRecorder;
use App\Services\DocumentAccessService;
use App\Services\DocumentTagService;
use App\Services\HtmlSanitizer;
use App\Services\MentionService;
use App\Services\NotificationService;
use App\Services\RevisionService;
use App\Services\TagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExternalApiController extends Controller
{
  public function organization(Request $request, Organization $organization)
  {
    $c = $this->authorize($request, 'organization:read', $organization);

    return [
      'data' => $organization->only([
        'id',
        'name',
        'slug',
        'description',
        'settings',
        'created_at',
      ]),
    ];
  }

  public function projects(Request $request, Organization $organization)
  {
    $this->authorize($request, 'projects:read', $organization);

    return [
      'data' => $organization->projects()->withCount('tickets')->orderBy('name')->get(),
    ];
  }

  public function notifyUsers(
    Request $request,
    Organization $organization,
    NotificationService $notifications,
    ActivityRecorder $activity,
  ) {
    $c = $this->authorize($request, 'notifications:write', $organization);
    $data = $request->validate([
      'user_ids' => 'required|array|min:1|max:100',
      'user_ids.*' => 'string',
      'title' => 'required|string|max:180',
      'body' => 'required|string|max:2000',
      'url' => 'nullable|string|max:1000',
    ]);

    $allowed = $organization
      ->members()
      ->whereIn('users.id', $data['user_ids'])
      ->get(['users.id', 'users.name', 'users.email', 'users.avatar_path']);

    if (isset($data['url'])) {
      abort_unless(
        (bool) preg_match('/^\/app(?:\/|$)/', $data['url']),
        422,
        'Notification URL must be an internal /app path.',
      );
    }

    abort_unless(
      $allowed->count() === count(array_unique($data['user_ids'])),
      422,
      'Every notification recipient must belong to the organization.',
    );

    foreach ($allowed as $user) {
      $notifications->send(
        $user,
        [
          'kind' => 'api.notification',
          'title' => $data['title'],
          'body' => $data['body'],
          'url' => $data['url'] ?? '/app',
          'actor' => ['name' => $c->name . ' (API)'],
        ],
        'general',
      );
    }

    $activity->record(
      'notification.sent',
      $organization,
      null,
      $organization,
      [],
      [
        'source' => 'api',
        'recipient_ids' => $allowed->pluck('id')->all(),
        'recipient_count' => $allowed->count(),
        'title' => $data['title'],
      ],
      null,
      $request,
      'api_key',
      $c->id,
      $c->name,
    );

    return response()->json(
      [
        'data' => [
          'recipient_count' => $allowed->count(),
        ],
      ],
      201,
    );
  }

  public function createProject(
    Request $request,
    Organization $organization,
    ActivityRecorder $activity,
  ) {
    $c = $this->authorize($request, 'projects:write', $organization);
    $data = $request->validate([
      'name' => 'required|string|max:180',
      'key' => 'required|string|max:12|regex:/^[A-Za-z][A-Za-z0-9_-]*$/',
      'description' => 'nullable|string|max:5000',
    ]);
    $data['key'] = strtoupper($data['key']);

    abort_if(
      $organization->projects()->withTrashed()->where('key', $data['key'])->exists(),
      422,
      'Project key already exists in this organization.',
    );

    $project = DB::transaction(function () use ($organization, $data) {
      $p = Project::create([
        'organization_id' => $organization->id,
        'name' => $data['name'],
        'key' => $data['key'],
        'description' => $data['description'] ?? null,
        'settings' => [],
      ]);

      foreach (
        [
          ['Open', false],
          ['Hold', false],
          ['In-progress', false],
          ['Pull Request', false],
          ['Deployed', true],
        ]
        as $i => $b
      ) {
        Board::create([
          'project_id' => $p->id,
          'name' => $b[0],
          'slug' => Str::slug($b[0], '_'),
          'position' => ($i + 1) * 1000,
          'is_done' => $b[1],
        ]);
      }

      return $p;
    });

    $activity->record(
      'project.created',
      $organization,
      $project,
      $project,
      [],
      ['source' => 'api'],
      null,
      $request,
      'api_key',
      $c->id,
      $c->name,
    );

    return response()->json(['data' => $project->load('boards')], 201);
  }

  public function project(Request $request, Project $project)
  {
    $project->load('organization');
    $this->authorize($request, 'projects:read', $project->organization);

    return [
      'data' => $project,
    ];
  }

  public function boards(Request $request, Project $project)
  {
    $project->load('organization');
    $this->authorize($request, 'boards:read', $project->organization);

    return [
      'data' => $project->boards()->withCount('tickets')->get(),
    ];
  }

  public function tickets(Request $request, Project $project)
  {
    $project->load('organization');
    $this->authorize($request, 'tickets:read', $project->organization);

    $q = $project
      ->tickets()
      ->with([
        'board:id,name,slug,is_done',
        'assignees:id,name,email,avatar_path',
        'labels:id,name,color',
        'tags:id,name',
      ]);

    if ($request->query('board_id')) {
      $q->where('board_id', $request->query('board_id'));
    }

    if ($request->query('q')) {
      $q->where('title', 'like', '%' . $request->query('q') . '%');
    }

    if ($request->query('tag_ids')) {
      foreach (
        array_unique(array_filter(explode(',', (string) $request->query('tag_ids'))))
        as $tagId
      ) {
        $q->whereHas('tags', fn($x) => $x->where('tags.id', $tagId));
      }
    }

    return [
      'data' => $q
        ->orderBy('number', 'desc')
        ->paginate(max(1, min((int) $request->query('per_page', 50), 200))),
    ];
  }

  public function createTicket(
    Request $request,
    Project $project,
    HtmlSanitizer $sanitizer,
    ActivityRecorder $activity,
    RevisionService $revisions,
    TagService $tags,
    MentionService $mentions,
    NotificationService $notifications,
  ) {
    $project->load('organization');
    $c = $this->authorize($request, 'tickets:write', $project->organization);

    $data = $request->validate([
      'board_id' => 'nullable|string',
      'board_slug' => 'nullable|string',
      'title' => 'required|string|max:500',
      'description_html' => 'nullable|string|max:200000',
      'priority' => 'nullable|in:lowest,low,normal,high,highest,critical',
      'due_at' => 'nullable|date',
      'assignee_ids' => 'nullable|array',
      'assignee_ids.*' => 'string',
      'label_ids' => 'nullable|array',
      'label_ids.*' => 'string',
      'tag_names' => 'nullable|array|max:20',
      'tag_names.*' => 'string|max:50',
    ]);

    $data['description_html'] = $sanitizer->sanitize($data['description_html'] ?? null);
    $mentions->validateHtml($project, $data['description_html']);

    $board = !empty($data['board_id'])
      ? $project->boards()->findOrFail($data['board_id'])
      : $project
        ->boards()
        ->where('slug', $data['board_slug'] ?? 'open')
        ->firstOrFail();
    $this->assertRelations($project, $data);

    if (
      $board->wip_limit &&
      $board->tickets()->whereNull('archived_at')->count() >= $board->wip_limit
    ) {
      abort(422, 'Destination WIP limit reached.');
    }

    $creator = User::find($c->created_by);
    $ticket = DB::transaction(function () use ($project, $board, $data, $creator, $tags) {
      Project::whereKey($project->id)->lockForUpdate()->first();

      $t = Ticket::create([
        'project_id' => $project->id,
        'board_id' => $board->id,
        'number' => (int) $project->tickets()->withTrashed()->max('number') + 1,
        'title' => $data['title'],
        'description_html' => $data['description_html'],
        'priority' => $data['priority'] ?? 'normal',
        'position' => (int) $board->tickets()->max('position') + 1000,
        'due_at' => $data['due_at'] ?? null,
        'created_by' => $creator?->id,
        'updated_by' => $creator?->id,
        'completed_at' => $board->is_done ? now() : null,
        'started_at' => $board->is_done ? null : now(),
      ]);

      $t->assignees()->sync($data['assignee_ids'] ?? []);
      $t->labels()->sync($data['label_ids'] ?? []);
      $tags->syncTicket($t, $data['tag_names'] ?? []);

      return $t;
    });

    $revisions->recordTicket($ticket->fresh(), $creator, $ticket->created_at);
    $mentions->syncTicket($ticket->fresh('project'), $creator, $c);

    if (!empty($data['assignee_ids'])) {
      $notifications->assigned(
        $ticket->fresh('project'),
        User::whereIn('id', $data['assignee_ids'])->get(),
        $creator,
        $c,
      );
    }

    $activity->record(
      'ticket.created',
      $project->organization,
      $project,
      $ticket,
      [],
      [
        'source' => 'api',
        'ticket_key' => $project->key . '-' . $ticket->number,
      ],
      null,
      $request,
      'api_key',
      $c->id,
      $c->name,
    );

    return response()->json(
      [
        'data' => $ticket->load(['board', 'assignees', 'labels', 'tags']),
      ],
      201,
    );
  }

  public function ticket(Request $request, Ticket $ticket)
  {
    $ticket->load('project.organization');
    $this->authorize($request, 'tickets:read', $ticket->project->organization);

    return [
      'data' => $ticket->load([
        'board',
        'assignees',
        'labels',
        'tags',
        'attachments.uploader:id,name,email,avatar_path',
        'comments.user:id,name,email,avatar_path',
        'comments.attachments.uploader:id,name,email,avatar_path',
      ]),
    ];
  }

  public function updateTicket(
    Request $request,
    Ticket $ticket,
    HtmlSanitizer $sanitizer,
    ActivityRecorder $activity,
    RevisionService $revisions,
    TagService $tags,
    MentionService $mentions,
    NotificationService $notifications,
  ) {
    $ticket->load('project.organization', 'assignees', 'labels', 'tags');

    $c = $this->authorize($request, 'tickets:write', $ticket->project->organization);
    $data = $request->validate([
      'title' => 'sometimes|required|string|max:500',
      'description_html' => 'sometimes|nullable|string|max:200000',
      'priority' => 'sometimes|in:lowest,low,normal,high,highest,critical',
      'due_at' => 'sometimes|nullable|date',
      'assignee_ids' => 'sometimes|array',
      'assignee_ids.*' => 'string',
      'label_ids' => 'sometimes|array',
      'label_ids.*' => 'string',
      'tag_names' => 'sometimes|array|max:20',
      'tag_names.*' => 'string|max:50',
      'archived' => 'sometimes|boolean',
    ]);

    if (array_key_exists('description_html', $data)) {
      $data['description_html'] = $sanitizer->sanitize($data['description_html']);
      $mentions->validateHtml($ticket->project, $data['description_html']);
    }

    $this->assertRelations($ticket->project, $data);
    $before = $revisions->ticketSnapshot($ticket);
    $beforeAssigneeIds = $ticket->assignees->pluck('id')->all();

    $update = [];
    foreach (['title', 'priority', 'due_at'] as $k) {
      if (array_key_exists($k, $data)) {
        $update[$k] = $data[$k];
      }
    }

    if (array_key_exists('description_html', $data)) {
      $update['description_html'] = $data['description_html'];
    }

    if (array_key_exists('archived', $data)) {
      $update['archived_at'] = $data['archived'] ? now() : null;
    }

    $update['updated_by'] = $c->created_by;
    $ticket->update($update);

    if (isset($data['assignee_ids'])) {
      $ticket->assignees()->sync($data['assignee_ids']);
    }

    if (isset($data['label_ids'])) {
      $ticket->labels()->sync($data['label_ids']);
    }

    if (array_key_exists('tag_names', $data)) {
      $tags->syncTicket($ticket, $data['tag_names']);
    }

    $editor = User::find($c->created_by);
    $mentions->syncTicket($ticket->fresh('project'), $editor, $c);

    if (array_key_exists('assignee_ids', $data)) {
      $newAssigneeIds = array_values(array_diff($data['assignee_ids'], $beforeAssigneeIds));

      if ($newAssigneeIds) {
        $notifications->assigned(
          $ticket->fresh('project'),
          User::whereIn('id', $newAssigneeIds)->get(),
          $editor,
          $c,
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
      $revisions->recordTicket($ticket->fresh(), $editor);
    }

    $activity->record(
      'ticket.updated',
      $ticket->project->organization,
      $ticket->project,
      $ticket,
      ['before' => $before, 'after' => $after],
      ['source' => 'api'],
      null,
      $request,
      'api_key',
      $c->id,
      $c->name,
    );

    return [
      'data' => $ticket->fresh()->load(['board', 'assignees', 'labels', 'tags']),
    ];
  }

  public function moveTicket(
    Request $request,
    Ticket $ticket,
    ActivityRecorder $activity,
    NotificationService $notifications,
  ) {
    $ticket->load('project.organization', 'board');
    $c = $this->authorize($request, 'tickets:write', $ticket->project->organization);

    $data = $request->validate([
      'board_id' => 'required|string',
      'position' => 'nullable|integer|min:0',
    ]);

    $target = $ticket->project->boards()->findOrFail($data['board_id']);

    if (
      $target->wip_limit &&
      $target->id !== $ticket->board_id &&
      $target->tickets()->count() >= $target->wip_limit
    ) {
      abort(422, 'Destination WIP limit reached.');
    }

    $from = $ticket->board;
    $changedBoard = $target->id !== $from->id;

    DB::transaction(function () use ($ticket, $target, $data, $from, $changedBoard, $c): void {
      $ticket->update([
        'board_id' => $target->id,
        'position' => $data['position'] ?? (int) $target->tickets()->max('position') + 1000,
        'completed_at' => $target->is_done ? now() : null,
        'updated_by' => $c->created_by,
      ]);

      if ($changedBoard) {
        TicketComment::create([
          'ticket_id' => $ticket->id,
          'user_id' => $c->created_by,
          'kind' => 'movement',
          'body_html' => '<p>' . e($from->name) . ' → ' . e($target->name) . '</p>',
          'author_snapshot' => [
            'name' => $c->name . ' (API)',
            'api_credential_id' => $c->id,
          ],
        ]);
      }
    });

    $activity->record(
      'ticket.moved',
      $ticket->project->organization,
      $ticket->project,
      $ticket,
      ['board' => ['before' => $from->id, 'after' => $target->id]],
      [
        'source' => 'api',
        'from' => $from->name,
        'to' => $target->name,
      ],
      null,
      $request,
      'api_key',
      $c->id,
      $c->name,
    );

    if ($changedBoard) {
      $moved = $ticket->fresh('project');
      $exclude = $c->created_by ? [$c->created_by] : [];
      $recipients = $notifications->ticketParticipants($moved, $exclude);

      if ($recipients->isNotEmpty()) {
        $notifications->moved($moved, $from->name, $target->name, $recipients, null, $c);
      }
    }

    return [
      'data' => $ticket
        ->fresh()
        ->load([
          'board',
          'attachments.uploader:id,name,email,avatar_path',
          'comments.user:id,name,email,avatar_path',
          'comments.attachments.uploader:id,name,email,avatar_path',
        ]),
    ];
  }

  public function deleteTicket(Request $request, Ticket $ticket, ActivityRecorder $activity)
  {
    $ticket->load('project.organization');
    $c = $this->authorize($request, 'tickets:write', $ticket->project->organization);

    $activity->record(
      'ticket.deleted',
      $ticket->project->organization,
      $ticket->project,
      $ticket,
      [],
      ['source' => 'api', 'soft_delete' => true],
      null,
      $request,
      'api_key',
      $c->id,
      $c->name,
    );

    $ticket->delete();

    return response()->noContent();
  }

  public function comment(
    Request $request,
    Ticket $ticket,
    HtmlSanitizer $sanitizer,
    ActivityRecorder $activity,
    RevisionService $revisions,
    MentionService $mentions,
    NotificationService $notifications,
  ) {
    $ticket->load('project.organization');
    $c = $this->authorize($request, 'comments:write', $ticket->project->organization);

    $data = $request->validate([
      'body_html' => 'required|string|max:200000',
    ]);
    $body = $sanitizer->sanitize($data['body_html']);
    $mentions->validateHtml($ticket->project, $body);

    $comment = TicketComment::create([
      'ticket_id' => $ticket->id,
      'kind' => 'comment',
      'body_html' => $body,
      'author_snapshot' => ['name' => $c->name, 'api_credential_id' => $c->id],
    ]);
    $author = User::find($c->created_by);

    $revisions->recordComment($comment, $author, $comment->created_at);
    $mentions->syncComment($comment->fresh('ticket.project'), $author, $c);

    $mentionedIds = $mentions->extractIds($body);
    $exclude = array_values(
      array_filter(array_merge($mentionedIds, $c->created_by ? [$c->created_by] : [])),
    );

    $recipients = $notifications->ticketParticipants($ticket, $exclude);
    if ($recipients->isNotEmpty()) {
      $notifications->commented($ticket, $recipients, $author, $c);
    }

    $ticket->touch();
    $activity->record(
      'comment.created',
      $ticket->project->organization,
      $ticket->project,
      $comment,
      [],
      ['source' => 'api', 'ticket_id' => $ticket->id],
      null,
      $request,
      'api_key',
      $c->id,
      $c->name,
    );

    return response()->json(['data' => $comment], 201);
  }

  public function documents(Request $request, Organization $organization)
  {
    $this->authorize($request, 'documents:read', $organization);
    $q = $organization
      ->documents()
      ->with(['project:id,name,key', 'author:id,name,email,avatar_path', 'tags:id,name'])
      ->withCount('comments');

    if ($request->query('project_id')) {
      $q->where('project_id', $request->query('project_id'));
    }

    if ($request->query('status')) {
      $q->where('status', $request->query('status'));
    }

    if ($request->query('q')) {
      $needle = '%' . strtolower((string) $request->query('q')) . '%';
      $q->where(
        fn($match) => $match
          ->whereRaw('LOWER(documents.title) LIKE ?', [$needle])
          ->orWhereRaw('LOWER(documents.caption) LIKE ?', [$needle])
          ->orWhereHas(
            'tags',
            fn($tags) => $tags->whereRaw('LOWER(document_tags.name) LIKE ?', [$needle]),
          ),
      );
    }

    if ($request->query('tags')) {
      foreach (
        array_unique(
          array_filter(array_map('trim', explode(',', (string) $request->query('tags')))),
        )
        as $name
      ) {
        $normalized = Str::lower(ltrim($name, '#'));
        $q->whereHas(
          'tags',
          fn($tags) => $tags->where('document_tags.normalized_name', $normalized),
        );
      }
    }

    return [
      'data' => $q
        ->orderByDesc('updated_at')
        ->paginate(max(1, min((int) $request->query('per_page', 50), 200))),
    ];
  }

  public function createDocument(
    Request $request,
    Organization $organization,
    HtmlSanitizer $sanitizer,
    ActivityRecorder $activity,
    DocumentTagService $tags,
    MentionService $mentions,
  ) {
    $c = $this->authorize($request, 'documents:write', $organization);
    $data = $request->validate([
      'project_id' => 'nullable|string',
      'title' => 'required|string|max:500',
      'caption' => 'nullable|string|max:2000',
      'content_html' => 'nullable|string|max:1000000',
      'status' => 'nullable|in:draft,published',
      'editor_ids' => 'nullable|array|max:100',
      'editor_ids.*' => 'string',
      'tag_names' => 'nullable|array|max:20',
      'tag_names.*' => 'string|max:50',
    ]);

    $project = !empty($data['project_id'])
      ? $organization->projects()->findOrFail($data['project_id'])
      : null;
    $this->assertDocumentEditors($organization, $project, $data['editor_ids'] ?? []);

    $content = $sanitizer->sanitize($data['content_html'] ?? null);
    $mentions->validateDocumentHtml($organization, $project, $content);

    $status = $data['status'] ?? 'draft';
    $document = Document::create([
      'organization_id' => $organization->id,
      'project_id' => $project?->id,
      'author_id' => $c->created_by,
      'title' => $data['title'],
      'caption' => $data['caption'] ?? null,
      'content_html' => $content,
      'status' => $status,
      'published_at' => $status === 'published' ? now() : null,
    ]);

    if (!empty($data['editor_ids'])) {
      $document->editors()->syncWithPivotValues(array_values(array_unique($data['editor_ids'])), [
        'added_by' => $c->created_by,
      ]);
    }

    $tags->syncDocument($document, $data['tag_names'] ?? []);
    $mentions->syncDocument($document->fresh(['organization', 'project']), null, $c);

    $activity->record(
      'document.created',
      $organization,
      $project,
      $document,
      [],
      ['source' => 'api', 'status' => $status],
      null,
      $request,
      'api_key',
      $c->id,
      $c->name,
    );

    if ($status === 'published') {
      $activity->record(
        'document.published',
        $organization,
        $project,
        $document,
        [],
        ['source' => 'api'],
        null,
        $request,
        'api_key',
        $c->id,
        $c->name,
      );
    }

    return response()->json(
      [
        'data' => $document->load(['project', 'author', 'editors', 'tags']),
      ],
      201,
    );
  }

  public function document(Request $request, Document $document)
  {
    $document->load('organization');
    $this->authorize($request, 'documents:read', $document->organization);

    return [
      'data' => $document->load([
        'project',
        'author',
        'editors',
        'tags',
        'comments.user:id,name,email,avatar_path',
      ]),
    ];
  }

  public function updateDocument(
    Request $request,
    Document $document,
    HtmlSanitizer $sanitizer,
    ActivityRecorder $activity,
    DocumentTagService $tags,
    MentionService $mentions,
  ) {
    $document->load(['organization', 'project', 'tags']);
    $c = $this->authorize($request, 'documents:write', $document->organization);

    $data = $request->validate([
      'project_id' => 'sometimes|nullable|string',
      'title' => 'sometimes|required|string|max:500',
      'caption' => 'sometimes|nullable|string|max:2000',
      'content_html' => 'sometimes|nullable|string|max:1000000',
      'status' => 'sometimes|in:draft,published',
      'archived' => 'sometimes|boolean',
      'editor_ids' => 'sometimes|array|max:100',
      'editor_ids.*' => 'string',
      'tag_names' => 'sometimes|array|max:20',
      'tag_names.*' => 'string|max:50',
    ]);

    $project = $document->project;
    if (array_key_exists('project_id', $data)) {
      $project = $data['project_id']
        ? $document->organization->projects()->findOrFail($data['project_id'])
        : null;
    }

    if (array_key_exists('editor_ids', $data)) {
      $this->assertDocumentEditors($document->organization, $project, $data['editor_ids']);
    }

    $nextContent = array_key_exists('content_html', $data)
      ? $sanitizer->sanitize($data['content_html'])
      : $document->content_html;

    if (array_key_exists('content_html', $data) || array_key_exists('project_id', $data)) {
      $mentions->validateDocumentHtml($document->organization, $project, $nextContent);
    }

    $before = [
      ...$document->only([
        'project_id',
        'title',
        'caption',
        'content_html',
        'status',
        'published_at',
        'archived_at',
      ]),
      'tag_names' => $document->tags->pluck('name')->all(),
    ];

    $oldStatus = $document->status;
    $wasArchived = (bool) $document->archived_at;
    $update = [];

    foreach (['title', 'caption', 'status'] as $k) {
      if (array_key_exists($k, $data)) {
        $update[$k] = $data[$k];
      }
    }

    if (array_key_exists('project_id', $data)) {
      $update['project_id'] = $project?->id;
    }

    if (array_key_exists('content_html', $data)) {
      $update['content_html'] = $nextContent;
    }

    if (array_key_exists('archived', $data)) {
      $update['archived_at'] = $data['archived'] ? now() : null;
    }

    if (array_key_exists('status', $data)) {
      $update['published_at'] =
        $data['status'] === 'published' ? ($document->published_at ?: now()) : null;
    }

    $document->update($update);
    if (array_key_exists('editor_ids', $data)) {
      $document->editors()->syncWithPivotValues(array_values(array_unique($data['editor_ids'])), [
        'added_by' => $c->created_by,
      ]);
    } elseif (array_key_exists('project_id', $data)) {
      $allowed = collect(
        app(DocumentAccessService::class)->allowedEditorIds($document->organization, $project),
      );

      $document
        ->editors()
        ->sync($document->editors()->pluck('users.id')->intersect($allowed)->values()->all());
    }

    if (array_key_exists('tag_names', $data)) {
      $tags->syncDocument($document, $data['tag_names']);
    }

    $fresh = $document->fresh(['organization', 'project', 'author', 'editors', 'tags']);
    $mentions->syncDocument($fresh, null, $c);

    $activity->record(
      'document.updated',
      $document->organization,
      $fresh->project,
      $fresh,
      [
        'before' => $before,
        'after' => [
          ...$fresh->only([
            'project_id',
            'title',
            'caption',
            'content_html',
            'status',
            'published_at',
            'archived_at',
          ]),
          'tag_names' => $fresh->tags->pluck('name')->all(),
        ],
      ],
      ['source' => 'api'],
      null,
      $request,
      'api_key',
      $c->id,
      $c->name,
    );

    if ($oldStatus !== $fresh->status) {
      $activity->record(
        $fresh->status === 'published' ? 'document.published' : 'document.drafted',
        $document->organization,
        $fresh->project,
        $fresh,
        [],
        ['source' => 'api'],
        null,
        $request,
        'api_key',
        $c->id,
        $c->name,
      );
    }

    $isArchived = (bool) $fresh->archived_at;
    if ($wasArchived !== $isArchived) {
      $activity->record(
        $isArchived ? 'document.archived' : 'document.unarchived',
        $document->organization,
        $fresh->project,
        $fresh,
        [],
        ['source' => 'api'],
        null,
        $request,
        'api_key',
        $c->id,
        $c->name,
      );
    }

    return ['data' => $fresh];
  }

  public function deleteDocument(Request $request, Document $document, ActivityRecorder $activity)
  {
    $document->load(['organization', 'project']);

    $c = $this->authorize($request, 'documents:write', $document->organization);
    $activity->record(
      'document.deleted',
      $document->organization,
      $document->project,
      $document,
      [],
      [
        'source' => 'api',
        'soft_delete' => true,
      ],
      null,
      $request,
      'api_key',
      $c->id,
      $c->name,
    );

    $document->delete();
    return response()->noContent();
  }

  public function documentComment(
    Request $request,
    Document $document,
    HtmlSanitizer $sanitizer,
    ActivityRecorder $activity,
    MentionService $mentions,
    NotificationService $notifications,
  ) {
    $document->load(['organization', 'project', 'author', 'editors']);

    $c = $this->authorize($request, 'document_comments:write', $document->organization);
    $data = $request->validate([
      'body_html' => 'required|string|max:200000',
    ]);
    $body = $sanitizer->sanitize($data['body_html']);
    abort_if(!$body || trim(strip_tags($body)) === '', 422, 'Comment content is required.');

    $mentions->validateDocumentHtml($document->organization, $document->project, $body);
    $comment = DocumentComment::create([
      'document_id' => $document->id,
      'user_id' => $c->created_by,
      'body_html' => $body,
      'author_snapshot' => ['name' => $c->name . ' (API)', 'api_credential_id' => $c->id],
    ]);

    $mentions->syncDocumentComment(
      $comment->fresh(['document.organization', 'document.project']),
      null,
      $c,
    );

    $mentionedIds = $mentions->extractIds($body);
    $exclude = array_values(
      array_filter(array_merge($mentionedIds, $c->created_by ? [$c->created_by] : [])),
    );

    $recipients = $notifications->documentParticipants($document, $exclude);
    if ($recipients->isNotEmpty()) {
      $notifications->documentCommented($document, $recipients, null, $c);
    }

    $activity->record(
      'document.comment.created',
      $document->organization,
      $document->project,
      $comment,
      [],
      [
        'source' => 'api',
        'document_id' => $document->id,
        'document_title' => $document->title,
      ],
      null,
      $request,
      'api_key',
      $c->id,
      $c->name,
    );

    return response()->json(['data' => $comment], 201);
  }

  public function updateProject(Request $request, Project $project, ActivityRecorder $activity)
  {
    $project->load('organization');
    $c = $this->authorize($request, 'projects:write', $project->organization);

    $data = $request->validate([
      'name' => 'sometimes|required|string|max:180',
      'description' => 'sometimes|nullable|string|max:5000',
      'archived' => 'sometimes|boolean',
    ]);

    $before = $project->only(['name', 'description', 'archived_at']);

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
        'after' => $project->fresh()->only(['name', 'description', 'archived_at']),
      ],
      ['source' => 'api'],
      null,
      $request,
      'api_key',
      $c->id,
      $c->name,
    );

    return ['data' => $project->fresh()];
  }

  public function deleteProject(Request $request, Project $project, ActivityRecorder $activity)
  {
    $project->load('organization');

    $c = $this->authorize($request, 'projects:write', $project->organization);
    $activity->record(
      'project.deleted',
      $project->organization,
      $project,
      $project,
      [],
      [
        'source' => 'api',
        'soft_delete' => true,
      ],
      null,
      $request,
      'api_key',
      $c->id,
      $c->name,
    );

    $project->delete();
    return response()->noContent();
  }

  public function members(Request $request, Project $project)
  {
    $project->load('organization');
    $this->authorize($request, 'projects:read', $project->organization);

    $explicit = $project
      ->members()
      ->get(['users.id', 'users.name', 'users.email', 'users.avatar_path']);

    $admins = $project->organization
      ->members()
      ->wherePivotIn('role', ['owner', 'admin'])
      ->get(['users.id', 'users.name', 'users.email', 'users.avatar_path']);

    return [
      'data' => $explicit->concat($admins)->unique('id')->values(),
    ];
  }

  public function labels(Request $request, Project $project)
  {
    $project->load('organization');
    $this->authorize($request, 'projects:read', $project->organization);

    return [
      'data' => $project
        ->labels()
        ->orderBy('name')
        ->get(['id', 'project_id', 'name', 'color']),
    ];
  }

  public function tags(Request $request, Project $project)
  {
    $project->load('organization');
    $this->authorize($request, 'projects:read', $project->organization);

    return [
      'data' => $project
        ->tags()
        ->whereHas('tickets')
        ->withCount('tickets')
        ->orderBy('name')
        ->get(['id', 'project_id', 'name']),
    ];
  }

  public function createBoard(Request $request, Project $project, ActivityRecorder $activity)
  {
    $project->load('organization');

    $c = $this->authorize($request, 'boards:write', $project->organization);
    $data = $request->validate([
      'name' => 'required|string|max:120',
      'color' => 'nullable|string|max:32',
      'wip_limit' => 'nullable|integer|min:1|max:100000',
      'is_done' => 'nullable|boolean',
    ]);

    $slug = Str::slug($data['name'], '_');
    $base = $slug ?: 'board';
    $slug = $base;

    $i = 2;
    while ($project->boards()->withTrashed()->where('slug', $slug)->exists()) {
      $slug = $base . '_' . $i++;
    }

    $board = Board::create([
      'project_id' => $project->id,
      'name' => $data['name'],
      'slug' => $slug,
      'color' => $data['color'] ?? null,
      'wip_limit' => $data['wip_limit'] ?? null,
      'is_done' => $data['is_done'] ?? false,
      'position' => (int) $project->boards()->max('position') + 1000,
    ]);

    $activity->record(
      'board.created',
      $project->organization,
      $project,
      $board,
      [],
      ['source' => 'api'],
      null,
      $request,
      'api_key',
      $c->id,
      $c->name,
    );

    return response()->json(['data' => $board], 201);
  }

  public function updateBoard(Request $request, Board $board, ActivityRecorder $activity)
  {
    $board->load('project.organization');

    $c = $this->authorize($request, 'boards:write', $board->project->organization);
    $data = $request->validate([
      'name' => 'sometimes|required|string|max:120',
      'color' => 'sometimes|nullable|string|max:32',
      'wip_limit' => 'sometimes|nullable|integer|min:1|max:100000',
      'is_done' => 'sometimes|boolean',
      'is_hidden' => 'sometimes|boolean',
    ]);
    $before = $board->only(array_keys($data));

    $board->update($data);
    $activity->record(
      'board.updated',
      $board->project->organization,
      $board->project,
      $board,
      [
        'before' => $before,
        'after' => $board->fresh()->only(array_keys($data)),
      ],
      ['source' => 'api'],
      null,
      $request,
      'api_key',
      $c->id,
      $c->name,
    );

    return ['data' => $board->fresh()];
  }

  public function deleteBoard(Request $request, Board $board, ActivityRecorder $activity)
  {
    $board->load('project.organization');

    $c = $this->authorize($request, 'boards:write', $board->project->organization);
    $data = $request->validate(['move_to_board_id' => 'nullable|string']);

    $target = null;
    if ($board->tickets()->exists()) {
      $target = $board->project
        ->boards()
        ->whereKey($data['move_to_board_id'] ?? '')
        ->first();

      abort_unless(
        $target && $target->id !== $board->id,
        422,
        'Select another board for existing tickets.',
      );

      $moving = $board->tickets()->whereNull('archived_at')->count();
      if (
        $target->wip_limit &&
        $target->tickets()->whereNull('archived_at')->count() + $moving > $target->wip_limit
      ) {
        abort(422, 'Destination WIP limit reached.');
      }
    }

    DB::transaction(function () use ($board, $target, $c): void {
      if ($target) {
        foreach ($board->tickets()->get() as $ticket) {
          $ticket->update([
            'board_id' => $target->id,
            'completed_at' => $target->is_done ? now() : null,
            'updated_by' => $c->created_by,
          ]);

          TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $c->created_by,
            'kind' => 'movement',
            'body_html' => '<p>' . e($board->name) . ' → ' . e($target->name) . '</p>',
            'author_snapshot' => [
              'name' => $c->name . ' (API)',
              'api_credential_id' => $c->id,
            ],
          ]);
        }
      }
      $board->delete();
    });

    $activity->record(
      'board.deleted',
      $board->project->organization,
      $board->project,
      $board,
      [],
      ['source' => 'api', 'soft_delete' => true],
      null,
      $request,
      'api_key',
      $c->id,
      $c->name,
    );

    return response()->noContent();
  }

  public function reorderBoards(Request $request, Project $project, ActivityRecorder $activity)
  {
    $project->load('organization');

    $c = $this->authorize($request, 'boards:write', $project->organization);
    $data = $request->validate(['board_ids' => 'required|array', 'board_ids.*' => 'string']);
    $valid = $project->boards()->whereIn('id', $data['board_ids'])->pluck('id')->all();

    abort_unless(
      count($valid) === count(array_unique($data['board_ids'])),
      422,
      'Every board must belong to the project.',
    );

    DB::transaction(
      fn() => collect($data['board_ids'])->each(
        fn($id, $i) => Board::whereKey($id)->update(['position' => ($i + 1) * 1000]),
      ),
    );

    $activity->record(
      'board.reordered',
      $project->organization,
      $project,
      $project,
      [],
      [
        'source' => 'api',
        'board_ids' => $data['board_ids'],
      ],
      null,
      $request,
      'api_key',
      $c->id,
      $c->name,
    );

    return ['data' => $project->boards()->get()];
  }

  public function reorderTickets(Request $request, Project $project, ActivityRecorder $activity)
  {
    $project->load('organization');

    $c = $this->authorize($request, 'tickets:write', $project->organization);
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
      'Every ticket must belong to the selected board.',
    );

    DB::transaction(
      fn() => collect($data['ticket_ids'])->each(
        fn($id, $i) => Ticket::whereKey($id)->update(['position' => ($i + 1) * 1000]),
      ),
    );

    $activity->record(
      'ticket.reordered',
      $project->organization,
      $project,
      $board,
      [],
      [
        'source' => 'api',
        'ticket_ids' => $data['ticket_ids'],
      ],
      null,
      $request,
      'api_key',
      $c->id,
      $c->name,
    );

    return response()->noContent();
  }

  public function analytics(Request $request, Project $project)
  {
    $project->load('organization');
    $this->authorize($request, 'analytics:read', $project->organization);
    $byBoard = $project
      ->boards()
      ->withCount(['tickets' => fn($q) => $q->whereNull('archived_at')])
      ->get(['id', 'name', 'color', 'is_done']);
    $total = $byBoard->sum('tickets_count');
    $done = $byBoard->where('is_done', true)->sum('tickets_count');
    $overdue = $project
      ->tickets()
      ->whereNull('archived_at')
      ->whereNotNull('due_at')
      ->where('due_at', '<', now())
      ->whereHas('board', fn($q) => $q->where('is_done', false))
      ->count();

    return [
      'data' => [
        'total' => $total,
        'done' => $done,
        'open' => $total - $done,
        'overdue' => $overdue,
        'completion_percent' => $total ? round(($done / $total) * 100, 2) : 0,
        'by_board' => $byBoard,
      ],
    ];
  }

  private function assertDocumentEditors(
    Organization $organization,
    ?Project $project,
    array $editorIds,
  ): void {
    if (!$editorIds) {
      return;
    }
    $allowed = $project
      ? $project
        ->members()
        ->pluck('users.id')
        ->merge(
          $organization
            ->members()
            ->wherePivotIn('role', ['owner', 'admin'])
            ->pluck('users.id'),
        )
        ->unique()
      : $organization->members()->pluck('users.id');

    abort_unless(
      $allowed->intersect($editorIds)->count() === count(array_unique($editorIds)),
      422,
      'Every selected editor must have access to the document scope.',
    );
  }

  private function authorize(Request $request, string $scope, Organization $org): ApiCredential
  {
    $c = $request->attributes->get('api_credential');

    abort_unless(
      $c instanceof ApiCredential && $c->organization_id === $org->id,
      403,
      'Credential is not valid for this organization.',
    );
    abort_unless($c->allows($scope), 403, 'Credential lacks scope ' . $scope . '.');

    return $c;
  }

  private function assertRelations(Project $p, array $data): void
  {
    if (isset($data['assignee_ids'])) {
      $orgAdminIds = $p->organization
        ->members()
        ->wherePivotIn('role', ['owner', 'admin'])
        ->pluck('users.id');

      $allowed = $p->members()->pluck('users.id')->merge($orgAdminIds)->unique();

      abort_unless(
        $allowed->intersect($data['assignee_ids'])->count() ===
          count(array_unique($data['assignee_ids'])),
        422,
        'Invalid assignee.',
      );
    }

    if (isset($data['label_ids'])) {
      abort_unless(
        $p->labels()->whereIn('id', $data['label_ids'])->count() ===
          count(array_unique($data['label_ids'])),
        422,
        'Invalid label.',
      );
    }
  }
}
