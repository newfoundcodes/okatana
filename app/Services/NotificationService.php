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

use App\Models\ApiCredential;
use App\Models\Document;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\InAppWorkspaceNotification;
use App\Notifications\WorkspaceNotificationMail;
use Illuminate\Support\Collection;

class NotificationService
{
  public function send(User $recipient, array $payload, string $emailCategory = 'general'): void
  {
    if ($emailCategory === 'document_comment' && !$recipient->notify_document_comments) {
      return;
    }

    $payload = array_merge(
      [
        'kind' => 'general',
        'title' => 'Okatana notification',
        'body' => '',
        'url' => '/app',
        'actor' => null,
        'project' => null,
        'ticket' => null,
        'document' => null,
      ],
      $payload,
    );
    $recipient->notifyNow(new InAppWorkspaceNotification($payload));

    $emailEnabled = match ($emailCategory) {
      'assigned' => (bool) $recipient->email_notify_assigned,
      'mention' => (bool) $recipient->email_notify_mentions,
      'document_comment' => true,
      default => true,
    };

    if ($emailEnabled && $recipient->email) {
      $recipient->notify(new WorkspaceNotificationMail($payload));
    }
  }

  public function ticketParticipants(Ticket $ticket, array $excludeIds = []): Collection
  {
    $ticket->loadMissing(['creator', 'assignees']);

    return collect([$ticket->creator])
      ->merge($ticket->assignees)
      ->filter(fn($user) => $user instanceof User)
      ->unique('id')
      ->reject(fn(User $user) => in_array($user->id, $excludeIds, true))
      ->values();
  }

  public function documentParticipants(Document $document, array $excludeIds = []): Collection
  {
    $document->loadMissing(['author', 'editors']);
    return collect([$document->author])
      ->merge($document->editors)
      ->filter(fn($user) => $user instanceof User)
      ->unique('id')
      ->reject(fn(User $user) => in_array($user->id, $excludeIds, true))
      ->values();
  }

  public function assigned(
    Ticket $ticket,
    iterable $users,
    ?User $actor = null,
    ?ApiCredential $credential = null,
  ): void {
    $ticket->loadMissing('project');
    foreach ($users as $user) {
      if (!($user instanceof User)) {
        continue;
      }

      $actorName = $actor?->name ?? ($credential ? $credential->name . ' (API)' : 'Okatana');
      $this->send(
        $user,
        [
          'kind' => 'ticket.assigned',
          'title' => 'Assigned to ' . $ticket->project->key . '-' . $ticket->number,
          'body' => $actorName . ' assigned you to “' . $ticket->title . '”.',
          'url' => '/app/projects/' . $ticket->project_id . '/' . $ticket->number,
          'actor' => $actor
            ? [
              'id' => $actor->id,
              'name' => $actor->name,
              'avatar_url' => $actor->avatar_url,
            ]
            : ['name' => $actorName],
          'project' => [
            'id' => $ticket->project_id,
            'name' => $ticket->project->name,
            'key' => $ticket->project->key,
          ],
          'ticket' => [
            'id' => $ticket->id,
            'number' => $ticket->number,
            'title' => $ticket->title,
          ],
        ],
        'assigned',
      );
    }
  }

  public function commented(
    Ticket $ticket,
    iterable $users,
    ?User $actor = null,
    ?ApiCredential $credential = null,
  ): void {
    $ticket->loadMissing('project');

    foreach ($users as $user) {
      if (!($user instanceof User)) {
        continue;
      }

      $actorName = $actor?->name ?? ($credential ? $credential->name . ' (API)' : 'Okatana');
      $this->send($user, [
        'kind' => 'comment.created',
        'title' => 'New comment on ' . $ticket->project->key . '-' . $ticket->number,
        'body' => $actorName . ' posted a comment on “' . $ticket->title . '”.',
        'url' => '/app/projects/' . $ticket->project_id . '/' . $ticket->number,
        'actor' => $actor
          ? [
            'id' => $actor->id,
            'name' => $actor->name,
            'avatar_url' => $actor->avatar_url,
          ]
          : ['name' => $actorName],
        'project' => [
          'id' => $ticket->project_id,
          'name' => $ticket->project->name,
          'key' => $ticket->project->key,
        ],
        'ticket' => [
          'id' => $ticket->id,
          'number' => $ticket->number,
          'title' => $ticket->title,
        ],
      ]);
    }
  }

  public function moved(
    Ticket $ticket,
    string $from,
    string $to,
    iterable $users,
    ?User $actor = null,
    ?ApiCredential $credential = null,
  ): void {
    $ticket->loadMissing('project');

    foreach ($users as $user) {
      if (!($user instanceof User)) {
        continue;
      }

      $actorName = $actor?->name ?? ($credential ? $credential->name . ' (API)' : 'Okatana');
      $this->send($user, [
        'kind' => 'ticket.moved',
        'title' => $ticket->project->key . '-' . $ticket->number . ' moved to ' . $to,
        'body' => $actorName . ' moved “' . $ticket->title . '” from ' . $from . ' to ' . $to . '.',
        'url' => '/app/projects/' . $ticket->project_id . '/' . $ticket->number,
        'actor' => $actor
          ? [
            'id' => $actor->id,
            'name' => $actor->name,
            'avatar_url' => $actor->avatar_url,
          ]
          : ['name' => $actorName],
        'project' => [
          'id' => $ticket->project_id,
          'name' => $ticket->project->name,
          'key' => $ticket->project->key,
        ],
        'ticket' => [
          'id' => $ticket->id,
          'number' => $ticket->number,
          'title' => $ticket->title,
        ],
      ]);
    }
  }

  public function mentionedInTicket(
    Ticket $ticket,
    iterable $users,
    ?User $actor = null,
    ?ApiCredential $credential = null,
  ): void {
    $ticket->loadMissing('project');

    foreach ($users as $user) {
      if (!($user instanceof User) || $this->isActor($user, $actor, $credential)) {
        continue;
      }

      $actorName = $actor?->name ?? ($credential ? $credential->name . ' (API)' : 'Okatana');
      $this->send(
        $user,
        [
          'kind' => 'ticket.mentioned',
          'title' => 'Mentioned in ' . $ticket->project->key . '-' . $ticket->number,
          'body' => $actorName . ' mentioned you in “' . $ticket->title . '”.',
          'url' => '/app/projects/' . $ticket->project_id . '/' . $ticket->number,
          'actor' => $actor
            ? [
              'id' => $actor->id,
              'name' => $actor->name,
              'avatar_url' => $actor->avatar_url,
            ]
            : ['name' => $actorName],
          'project' => [
            'id' => $ticket->project_id,
            'name' => $ticket->project->name,
            'key' => $ticket->project->key,
          ],
          'ticket' => [
            'id' => $ticket->id,
            'number' => $ticket->number,
            'title' => $ticket->title,
          ],
        ],
        'mention',
      );
    }
  }

  public function mentionedInComment(
    Ticket $ticket,
    iterable $users,
    ?User $actor = null,
    ?ApiCredential $credential = null,
  ): void {
    $ticket->loadMissing('project');
    foreach ($users as $user) {
      if (!($user instanceof User) || $this->isActor($user, $actor, $credential)) {
        continue;
      }

      $actorName = $actor?->name ?? ($credential ? $credential->name . ' (API)' : 'Okatana');
      $this->send(
        $user,
        [
          'kind' => 'comment.mentioned',
          'title' => 'Mentioned in a comment on ' . $ticket->project->key . '-' . $ticket->number,
          'body' => $actorName . ' mentioned you in a comment on “' . $ticket->title . '”.',
          'url' => '/app/projects/' . $ticket->project_id . '/' . $ticket->number,
          'actor' => $actor
            ? [
              'id' => $actor->id,
              'name' => $actor->name,
              'avatar_url' => $actor->avatar_url,
            ]
            : ['name' => $actorName],
          'project' => [
            'id' => $ticket->project_id,
            'name' => $ticket->project->name,
            'key' => $ticket->project->key,
          ],
          'ticket' => [
            'id' => $ticket->id,
            'number' => $ticket->number,
            'title' => $ticket->title,
          ],
        ],
        'mention',
      );
    }
  }

  public function mentionedInDocument(
    Document $document,
    iterable $users,
    ?User $actor = null,
    ?ApiCredential $credential = null,
  ): void {
    $document->loadMissing(['organization', 'project']);

    foreach ($users as $user) {
      if (!($user instanceof User) || $this->isActor($user, $actor, $credential)) {
        continue;
      }

      $actorName = $actor?->name ?? ($credential ? $credential->name . ' (API)' : 'Okatana');
      $this->send(
        $user,
        [
          'kind' => 'document.mentioned',
          'title' => 'Mentioned in “' . $document->title . '”',
          'body' => $actorName . ' mentioned you in an article.',
          'url' => '/app/documents/' . $document->id,
          'actor' => $actor
            ? [
              'id' => $actor->id,
              'name' => $actor->name,
              'avatar_url' => $actor->avatar_url,
            ]
            : ['name' => $actorName],
          'project' => $document->project
            ? [
              'id' => $document->project->id,
              'name' => $document->project->name,
              'key' => $document->project->key,
            ]
            : null,
          'document' => [
            'id' => $document->id,
            'title' => $document->title,
          ],
        ],
        'mention',
      );
    }
  }

  public function mentionedInDocumentComment(
    Document $document,
    iterable $users,
    ?User $actor = null,
    ?ApiCredential $credential = null,
  ): void {
    $document->loadMissing(['organization', 'project']);

    foreach ($users as $user) {
      if (!($user instanceof User) || $this->isActor($user, $actor, $credential)) {
        continue;
      }

      $actorName = $actor?->name ?? ($credential ? $credential->name . ' (API)' : 'Okatana');
      $this->send(
        $user,
        [
          'kind' => 'document.comment.mentioned',
          'title' => 'Mentioned in a comment on “' . $document->title . '”',
          'body' => $actorName . ' mentioned you in an article comment.',
          'url' => '/app/documents/' . $document->id,
          'actor' => $actor
            ? [
              'id' => $actor->id,
              'name' => $actor->name,
              'avatar_url' => $actor->avatar_url,
            ]
            : ['name' => $actorName],
          'project' => $document->project
            ? [
              'id' => $document->project->id,
              'name' => $document->project->name,
              'key' => $document->project->key,
            ]
            : null,
          'document' => [
            'id' => $document->id,
            'title' => $document->title,
          ],
        ],
        'mention',
      );
    }
  }

  public function documentCommented(
    Document $document,
    iterable $users,
    ?User $actor = null,
    ?ApiCredential $credential = null,
  ): void {
    $document->loadMissing(['organization', 'project']);

    foreach ($users as $user) {
      if (!($user instanceof User) || $this->isActor($user, $actor, $credential)) {
        continue;
      }

      $actorName = $actor?->name ?? ($credential ? $credential->name . ' (API)' : 'Okatana');
      $this->send(
        $user,
        [
          'kind' => 'document.comment.created',
          'title' => 'New comment on “' . $document->title . '”',
          'body' => $actorName . ' posted a comment on an article you own or edit.',
          'url' => '/app/documents/' . $document->id,
          'actor' => $actor
            ? [
              'id' => $actor->id,
              'name' => $actor->name,
              'avatar_url' => $actor->avatar_url,
            ]
            : ['name' => $actorName],
          'project' => $document->project
            ? [
              'id' => $document->project->id,
              'name' => $document->project->name,
              'key' => $document->project->key,
            ]
            : null,
          'document' => [
            'id' => $document->id,
            'title' => $document->title,
          ],
        ],
        'document_comment',
      );
    }
  }

  private function isActor(User $recipient, ?User $actor, ?ApiCredential $credential): bool
  {
    if ($actor && $recipient->id === $actor->id) {
      return true;
    }

    return !$actor && $credential?->created_by && $recipient->id === $credential->created_by;
  }
}
