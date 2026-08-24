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
use App\Models\DocumentComment;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;

class MentionService
{
  public function __construct(private NotificationService $notifications) {}

  public function extractIds(?string $html): array
  {
    if (!$html) {
      return [];
    }

    preg_match_all('/data-mention-user-id=["\']([A-Za-z0-9]{20,32})["\']/i', $html, $matches);
    return array_values(array_unique($matches[1] ?? []));
  }

  public function validateHtml(Project $project, ?string $html): void
  {
    $this->validatedProjectIds($project, $this->extractIds($html));
  }

  public function validateDocumentHtml(
    Organization $organization,
    ?Project $project,
    ?string $html,
  ): void {
    $this->validatedDocumentIds($organization, $project, $this->extractIds($html));
  }

  public function syncTicket(
    Ticket $ticket,
    ?User $actor = null,
    ?ApiCredential $credential = null,
  ): void {
    $ids = $this->validatedProjectIds(
      $ticket->project,
      $this->extractIds($ticket->description_html),
    );
    $before = $ticket->mentions()->pluck('users.id')->all();
    $ticket->mentions()->sync($ids);

    $newIds = $this->withoutActor(array_values(array_diff($ids, $before)), $actor, $credential);
    if ($newIds) {
      $this->notifications->mentionedInTicket(
        $ticket,
        User::whereIn('id', $newIds)->get(),
        $actor,
        $credential,
      );
    }
  }

  public function syncComment(
    TicketComment $comment,
    ?User $actor = null,
    ?ApiCredential $credential = null,
  ): void {
    $comment->loadMissing('ticket.project');
    $ids = $this->validatedProjectIds(
      $comment->ticket->project,
      $this->extractIds($comment->body_html),
    );

    $before = $comment->mentions()->pluck('users.id')->all();
    $comment->mentions()->sync($ids);

    $newIds = $this->withoutActor(array_values(array_diff($ids, $before)), $actor, $credential);
    if ($newIds) {
      $this->notifications->mentionedInComment(
        $comment->ticket,
        User::whereIn('id', $newIds)->get(),
        $actor,
        $credential,
      );
    }
  }

  public function syncDocument(
    Document $document,
    ?User $actor = null,
    ?ApiCredential $credential = null,
  ): void {
    $document->loadMissing(['organization', 'project']);

    $ids = $this->validatedDocumentIds(
      $document->organization,
      $document->project,
      $this->extractIds($document->content_html),
    );
    $ids = $this->viewableDocumentMentionIds($document, $ids);
    $before = $document->mentions()->pluck('users.id')->all();
    $document->mentions()->sync($ids);

    $newIds = $this->withoutActor(array_values(array_diff($ids, $before)), $actor, $credential);
    if ($newIds) {
      $this->notifications->mentionedInDocument(
        $document,
        User::whereIn('id', $newIds)->get(),
        $actor,
        $credential,
      );
    }
  }

  public function syncDocumentComment(
    DocumentComment $comment,
    ?User $actor = null,
    ?ApiCredential $credential = null,
  ): void {
    $comment->loadMissing(['document.organization', 'document.project']);

    $document = $comment->document;
    $ids = $this->validatedDocumentIds(
      $document->organization,
      $document->project,
      $this->extractIds($comment->body_html),
    );

    $ids = $this->viewableDocumentMentionIds($document, $ids);
    $before = $comment->mentions()->pluck('users.id')->all();
    $comment->mentions()->sync($ids);

    $newIds = $this->withoutActor(array_values(array_diff($ids, $before)), $actor, $credential);
    if ($newIds) {
      $this->notifications->mentionedInDocumentComment(
        $document,
        User::whereIn('id', $newIds)->get(),
        $actor,
        $credential,
      );
    }
  }

  private function validatedProjectIds(Project $project, array $ids): array
  {
    if (!$ids) {
      return [];
    }

    $project->loadMissing('organization');
    $allowed = $project
      ->members()
      ->pluck('users.id')
      ->merge(
        $project->organization
          ->members()
          ->wherePivotIn('role', ['owner', 'admin'])
          ->pluck('users.id'),
      )
      ->unique();

    abort_unless(
      $allowed->intersect($ids)->count() === count($ids),
      422,
      'Mentions must reference users with project access.',
    );

    return $ids;
  }

  private function validatedDocumentIds(
    Organization $organization,
    ?Project $project,
    array $ids,
  ): array {
    if (!$ids) {
      return [];
    }

    if (!$project) {
      $allowed = $organization->members()->pluck('users.id')->unique();
    } else {
      abort_unless(
        $project->organization_id === $organization->id,
        422,
        'The mention scope does not match the document organization.',
      );

      $allowed = $project
        ->members()
        ->pluck('users.id')
        ->merge(
          $organization
            ->members()
            ->wherePivotIn('role', ['owner', 'admin'])
            ->pluck('users.id'),
        )
        ->unique();
    }

    abort_unless(
      $allowed->intersect($ids)->count() === count($ids),
      422,
      'Mentions must reference users with access to the document scope.',
    );

    return $ids;
  }

  private function viewableDocumentMentionIds(Document $document, array $ids): array
  {
    if ($document->status === 'published') {
      return $ids;
    }

    $document->loadMissing('editors');
    $visible = collect([$document->author_id])
      ->merge($document->editors->pluck('id'))
      ->filter()
      ->unique();

    return array_values($visible->intersect($ids)->all());
  }

  private function withoutActor(array $ids, ?User $actor, ?ApiCredential $credential): array
  {
    $exclude = array_values(array_filter([$actor?->id, $credential?->created_by]));
    return array_values(array_diff($ids, $exclude));
  }
}
