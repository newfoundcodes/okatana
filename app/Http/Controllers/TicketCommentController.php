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

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Services\ActivityRecorder;
use App\Services\AuthorizationService;
use App\Services\HtmlSanitizer;
use App\Services\MentionService;
use App\Services\NotificationService;
use App\Services\RevisionService;
use Illuminate\Http\Request;

class TicketCommentController extends Controller
{
  public function store(
    Request $request,
    Ticket $ticket,
    AuthorizationService $auth,
    HtmlSanitizer $sanitizer,
    ActivityRecorder $activity,
    RevisionService $revisions,
    MentionService $mentions,
    NotificationService $notifications,
  ) {
    $ticket->load('project.organization');
    $auth->projectMember($request->user(), $ticket->project);

    $data = $request->validate([
      'body_html' => 'required|string|max:200000',
    ]);
    $body = $sanitizer->sanitize($data['body_html']);

    $mentions->validateHtml($ticket->project, $body);
    $comment = TicketComment::create([
      'ticket_id' => $ticket->id,
      'user_id' => $request->user()->id,
      'kind' => 'comment',
      'body_html' => $body,
      'author_snapshot' => [
        'name' => $request->user()->name,
        'email' => $request->user()->email,
      ],
    ]);

    $revisions->recordComment($comment, $request->user(), $comment->created_at);
    $mentions->syncComment($comment->fresh('ticket.project'), $request->user());

    $mentionedIds = $mentions->extractIds($body);
    $recipients = $notifications->ticketParticipants(
      $ticket,
      array_merge([$request->user()->id], $mentionedIds),
    );

    if ($recipients->isNotEmpty()) {
      $notifications->commented($ticket, $recipients, $request->user());
    }

    $ticket->touch();
    $activity->record(
      'comment.created',
      $ticket->project->organization,
      $ticket->project,
      $comment,
      [],
      ['ticket_id' => $ticket->id],
      $request->user(),
      $request,
    );

    return response()->json(
      [
        'comment' => $comment->load([
          'user:id,name,email,avatar_path',
          'attachments.uploader:id,name,email,avatar_path',
        ]),
        'ticket_updated_at' => $ticket->fresh()->updated_at,
      ],
      201,
    );
  }

  public function update(
    Request $request,
    TicketComment $comment,
    AuthorizationService $auth,
    HtmlSanitizer $sanitizer,
    ActivityRecorder $activity,
    RevisionService $revisions,
    MentionService $mentions,
  ) {
    $comment->load('ticket.project.organization');
    abort_if($comment->kind !== 'comment', 403, 'System activity comments cannot be edited.');

    $auth->commentOwnerOrAdmin($request->user(), $comment);
    $data = $request->validate([
      'body_html' => 'required|string|max:200000',
    ]);

    $beforeSnapshot = $revisions->commentSnapshot($comment);
    $nextBody = $sanitizer->sanitize($data['body_html']);

    $mentions->validateHtml($comment->ticket->project, $nextBody);
    if ($nextBody !== $comment->body_html) {
      $revisions->ensureCommentInitial($comment, $beforeSnapshot);
      $comment->update([
        'body_html' => $nextBody,
        'edited_at' => now(),
      ]);

      $mentions->syncComment($comment->fresh('ticket.project'), $request->user());
      $revisions->recordComment($comment->fresh(), $request->user());
    }

    $comment->ticket->touch();
    $activity->record(
      'comment.updated',
      $comment->ticket->project->organization,
      $comment->ticket->project,
      $comment,
      [
        'body_html' => [
          'before' => $beforeSnapshot['body_html'],
          'after' => $comment->fresh()->body_html,
        ],
      ],
      ['ticket_id' => $comment->ticket_id],
      $request->user(),
      $request,
    );

    return [
      'comment' => $comment
        ->fresh()
        ->load([
          'user:id,name,email,avatar_path',
          'attachments.uploader:id,name,email,avatar_path',
        ]),
      'ticket_updated_at' => $comment->ticket->fresh()->updated_at,
    ];
  }

  public function destroy(
    Request $request,
    TicketComment $comment,
    AuthorizationService $auth,
    ActivityRecorder $activity,
  ) {
    $comment->load('ticket.project.organization');
    abort_if($comment->kind !== 'comment', 403, 'System activity comments cannot be deleted.');

    $auth->commentOwnerOrAdmin($request->user(), $comment);
    $comment->ticket->touch();

    $activity->record(
      'comment.deleted',
      $comment->ticket->project->organization,
      $comment->ticket->project,
      $comment,
      [],
      [
        'ticket_id' => $comment->ticket_id,
        'soft_delete' => true,
      ],
      $request->user(),
      $request,
    );

    $comment->delete();
    return response()->noContent();
  }
}
