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

use App\Models\Document;
use App\Models\DocumentComment;
use App\Services\ActivityRecorder;
use App\Services\DocumentAccessService;
use App\Services\HtmlSanitizer;
use App\Services\MentionService;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class DocumentCommentController extends Controller
{
  public function store(
    Request $request,
    Document $document,
    DocumentAccessService $access,
    HtmlSanitizer $sanitizer,
    ActivityRecorder $activity,
    MentionService $mentions,
    NotificationService $notifications,
  ) {
    $document->load(['organization', 'project', 'author', 'editors']);

    $access->assertView($request->user(), $document);
    $data = $request->validate([
      'body_html' => 'required|string|max:200000',
    ]);
    $body = $sanitizer->sanitize($data['body_html']);

    abort_if(!$body || trim(strip_tags($body)) === '', 422, 'Comment content is required.');

    $mentions->validateDocumentHtml($document->organization, $document->project, $body);
    $comment = DocumentComment::create([
      'document_id' => $document->id,
      'user_id' => $request->user()->id,
      'body_html' => $body,
      'author_snapshot' => [
        'name' => $request->user()->name,
        'email' => $request->user()->email,
      ],
    ]);

    $mentions->syncDocumentComment(
      $comment->fresh(['document.organization', 'document.project']),
      $request->user(),
    );

    $mentionedIds = $mentions->extractIds($body);
    $recipients = $notifications->documentParticipants(
      $document,
      array_values(array_unique(array_merge([$request->user()->id], $mentionedIds))),
    );

    if ($recipients->isNotEmpty()) {
      $notifications->documentCommented($document, $recipients, $request->user());
    }

    $activity->record(
      'document.comment.created',
      $document->organization,
      $document->project,
      $comment,
      [],
      [
        'document_id' => $document->id,
        'document_title' => $document->title,
      ],
      $request->user(),
      $request,
    );

    return response()->json(
      [
        'comment' => $comment->load('user:id,name,email,avatar_path'),
      ],
      201,
    );
  }

  public function update(
    Request $request,
    DocumentComment $comment,
    DocumentAccessService $access,
    HtmlSanitizer $sanitizer,
    ActivityRecorder $activity,
    MentionService $mentions,
  ): array {
    $comment->load(['document.organization', 'document.project']);
    $access->assertView($request->user(), $comment->document);
    abort_unless(
      $comment->user_id === $request->user()->id ||
        $comment->document->organization->canAdmin($request->user()),
      403,
    );

    $data = $request->validate(['body_html' => 'required|string|max:200000']);
    $body = $sanitizer->sanitize($data['body_html']);
    abort_if(!$body || trim(strip_tags($body)) === '', 422, 'Comment content is required.');

    $mentions->validateDocumentHtml(
      $comment->document->organization,
      $comment->document->project,
      $body,
    );

    $before = $comment->body_html;
    $comment->update(['body_html' => $body, 'edited_at' => now()]);
    $mentions->syncDocumentComment(
      $comment->fresh(['document.organization', 'document.project']),
      $request->user(),
    );

    $activity->record(
      'document.comment.updated',
      $comment->document->organization,
      $comment->document->project,
      $comment,
      [
        'before' => $before,
        'after' => $body,
      ],
      [
        'document_id' => $comment->document_id,
        'document_title' => $comment->document->title,
      ],
      $request->user(),
      $request,
    );

    return [
      'comment' => $comment->fresh()->load('user:id,name,email,avatar_path'),
    ];
  }

  public function destroy(
    Request $request,
    DocumentComment $comment,
    DocumentAccessService $access,
    ActivityRecorder $activity,
  ) {
    $comment->load(['document.organization', 'document.project']);
    $access->assertView($request->user(), $comment->document);
    abort_unless(
      $comment->user_id === $request->user()->id ||
        $comment->document->organization->canAdmin($request->user()),
      403,
    );

    $activity->record(
      'document.comment.deleted',
      $comment->document->organization,
      $comment->document->project,
      $comment,
      [],
      [
        'document_id' => $comment->document_id,
        'document_title' => $comment->document->title,
        'soft_delete' => true,
      ],
      $request->user(),
      $request,
    );

    $comment->delete();

    return response()->noContent();
  }
}
