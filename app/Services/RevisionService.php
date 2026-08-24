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

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketCommentRevision;
use App\Models\TicketRevision;
use App\Models\User;
use Illuminate\Support\Carbon;

class RevisionService
{
  public function ticketSnapshot(Ticket $ticket): array
  {
    $ticket->loadMissing([
      'assignees:id,name,email,avatar_path',
      'labels:id,name,color',
      'tags:id,name',
    ]);

    return [
      'title' => $ticket->title,
      'description_html' => $ticket->description_html,
      'priority' => $ticket->priority,
      'due_at' => $ticket->due_at?->toIso8601String(),
      'assignees' => $ticket->assignees
        ->sortBy('id')
        ->map(fn($user) => ['id' => $user->id, 'name' => $user->name])
        ->values()
        ->all(),
      'labels' => $ticket->labels
        ->sortBy('id')
        ->map(fn($label) => ['id' => $label->id, 'name' => $label->name])
        ->values()
        ->all(),
      'tags' => $ticket->tags
        ->sortBy(fn($tag) => strtolower($tag->name))
        ->map(fn($tag) => ['id' => $tag->id, 'name' => $tag->name])
        ->values()
        ->all(),
    ];
  }

  public function commentSnapshot(TicketComment $comment): array
  {
    return ['body_html' => $comment->body_html];
  }

  public function ensureTicketInitial(Ticket $ticket, ?array $snapshot = null): void
  {
    if ($ticket->revisions()->exists()) {
      return;
    }

    $this->createTicketRevision(
      $ticket,
      $snapshot ?? $this->ticketSnapshot($ticket),
      $ticket->created_by,
      $ticket->created_at,
    );
  }

  public function recordTicket(Ticket $ticket, ?User $editor, ?Carbon $at = null): TicketRevision
  {
    return $this->createTicketRevision($ticket, $this->ticketSnapshot($ticket), $editor?->id, $at);
  }

  public function ensureCommentInitial(TicketComment $comment, ?array $snapshot = null): void
  {
    if ($comment->revisions()->exists()) {
      return;
    }

    $this->createCommentRevision(
      $comment,
      $snapshot ?? $this->commentSnapshot($comment),
      $comment->user_id,
      $comment->created_at,
    );
  }

  public function recordComment(
    TicketComment $comment,
    ?User $editor,
    ?Carbon $at = null,
  ): TicketCommentRevision {
    return $this->createCommentRevision(
      $comment,
      $this->commentSnapshot($comment),
      $editor?->id,
      $at,
    );
  }

  private function createTicketRevision(
    Ticket $ticket,
    array $snapshot,
    ?string $userId,
    ?Carbon $at,
  ): TicketRevision {
    $revision = new TicketRevision([
      'ticket_id' => $ticket->id,
      'version' => ((int) $ticket->revisions()->max('version')) + 1,
      'user_id' => $userId,
      'snapshot' => $snapshot,
    ]);

    if ($at) {
      $revision->created_at = $at;
    }

    $revision->save();
    return $revision;
  }

  private function createCommentRevision(
    TicketComment $comment,
    array $snapshot,
    ?string $userId,
    ?Carbon $at,
  ): TicketCommentRevision {
    $revision = new TicketCommentRevision([
      'ticket_comment_id' => $comment->id,
      'version' => ((int) $comment->revisions()->max('version')) + 1,
      'user_id' => $userId,
      'snapshot' => $snapshot,
    ]);

    if ($at) {
      $revision->created_at = $at;
    }

    $revision->save();
    return $revision;
  }
}
