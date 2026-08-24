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

use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Services\ActivityRecorder;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentController extends Controller
{
  public function storeForTicket(
    Request $request,
    Ticket $ticket,
    AuthorizationService $auth,
    ActivityRecorder $activity,
  ): array {
    $ticket->load('project.organization');
    $auth->projectMember($request->user(), $ticket->project);

    $attachments = $this->store($request, $ticket->project_id, $ticket->id, null);
    foreach ($attachments as $attachment) {
      $activity->record(
        'attachment.created',
        $ticket->project->organization,
        $ticket->project,
        $attachment,
        [],
        [
          'ticket_id' => $ticket->id,
          'filename' => $attachment->original_name,
          'size_bytes' => $attachment->size_bytes,
        ],
        $request->user(),
        $request,
      );
    }

    return ['attachments' => $attachments];
  }

  public function storeForComment(
    Request $request,
    TicketComment $comment,
    AuthorizationService $auth,
    ActivityRecorder $activity,
  ): array {
    $comment->load('ticket.project.organization');
    abort_if($comment->kind !== 'comment', 422, 'Files can only be attached to user comments.');

    $auth->projectMember($request->user(), $comment->ticket->project);
    $attachments = $this->store($request, $comment->ticket->project_id, null, $comment->id);

    foreach ($attachments as $attachment) {
      $activity->record(
        'attachment.created',
        $comment->ticket->project->organization,
        $comment->ticket->project,
        $attachment,
        [],
        [
          'ticket_id' => $comment->ticket_id,
          'comment_id' => $comment->id,
          'filename' => $attachment->original_name,
          'size_bytes' => $attachment->size_bytes,
        ],
        $request->user(),
        $request,
      );
    }

    $comment->ticket->touch();

    return [
      'attachments' => $attachments,
      'ticket_updated_at' => $comment->ticket->fresh()->updated_at,
    ];
  }

  public function download(Request $request, Attachment $attachment, AuthorizationService $auth)
  {
    $attachment->load('project.organization');
    $auth->projectMember($request->user(), $attachment->project);

    $disk = Storage::disk($attachment->disk);
    abort_unless($disk->exists($attachment->path), 404);

    return $disk->download($attachment->path, $attachment->original_name, [
      'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
      'X-Content-Type-Options' => 'nosniff',
      'Cache-Control' => 'private, no-store',
    ]);
  }

  public function destroy(
    Request $request,
    Attachment $attachment,
    AuthorizationService $auth,
    ActivityRecorder $activity,
  ) {
    $attachment->load('project.organization');
    $auth->projectMember($request->user(), $attachment->project);

    $canAdmin = $attachment->project->organization->canAdmin($request->user());
    abort_unless(
      $canAdmin || $attachment->uploaded_by === $request->user()->id,
      403,
      'Only the uploader or an organization administrator may remove this attachment.',
    );

    $activity->record(
      'attachment.deleted',
      $attachment->project->organization,
      $attachment->project,
      $attachment,
      [],
      [
        'ticket_id' => $attachment->ticket_id,
        'comment_id' => $attachment->ticket_comment_id,
        'filename' => $attachment->original_name,
      ],
      $request->user(),
      $request,
    );

    Storage::disk($attachment->disk)->delete($attachment->path);
    $attachment->delete();

    return response()->noContent();
  }

  private function store(Request $request, string $projectId, ?string $ticketId, ?string $commentId)
  {
    $data = $request->validate([
      'files' => ['required', 'array', 'min:1', 'max:10'],
      'files.*' => ['required', 'file', 'max:204800'],
    ]);

    return collect($data['files'])
      ->map(function ($file) use ($request, $projectId, $ticketId, $commentId) {
        $original = Str::limit(basename((string) $file->getClientOriginalName()), 255, '');
        $folder =
          'attachments/' .
          $projectId .
          '/' .
          ($ticketId ? 'tickets/' . $ticketId : 'comments/' . $commentId);
        $path = $file->storeAs($folder, (string) Str::ulid(), 'local');

        return Attachment::create([
          'project_id' => $projectId,
          'ticket_id' => $ticketId,
          'ticket_comment_id' => $commentId,
          'uploaded_by' => $request->user()->id,
          'original_name' => $original ?: 'attachment',
          'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
          'size_bytes' => $file->getSize(),
          'disk' => 'local',
          'path' => $path,
        ])->load('uploader:id,name,email,avatar_path');
      })
      ->values();
  }
}
