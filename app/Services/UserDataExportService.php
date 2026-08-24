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

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserDataExportService
{
  public const SCHEMA = 'okatana.user-export.v1';

  public function export(User $user): array
  {
    $userId = $user->id;
    $email = strtolower($user->email);

    return [
      'schema' => self::SCHEMA,
      'exported_at' => now()->toIso8601String(),
      'account' => [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'email_verified_at' => $user->email_verified_at?->toIso8601String(),
        'two_factor_confirmed_at' => $user->two_factor_confirmed_at?->toIso8601String(),
        'created_at' => $user->created_at?->toIso8601String(),
        'updated_at' => $user->updated_at?->toIso8601String(),
        'email_notify_assigned' => (bool) $user->email_notify_assigned,
        'email_notify_mentions' => (bool) $user->email_notify_mentions,
        'notify_document_comments' => (bool) $user->notify_document_comments,
        'avatar' => $this->avatarData($user),
      ],
      'organization_memberships' => DB::table('organization_members as membership')
        ->join(
          'organizations as organization',
          'organization.id',
          '=',
          'membership.organization_id',
        )
        ->where('membership.user_id', $userId)
        ->orderBy('organization.name')
        ->get([
          'organization.id as organization_id',
          'organization.name as organization_name',
          'organization.slug as organization_slug',
          'membership.role',
          'membership.created_at as joined_at',
          'membership.updated_at',
        ])
        ->map(fn($row) => (array) $row)
        ->all(),
      'project_memberships' => DB::table('project_members as membership')
        ->join('projects as project', 'project.id', '=', 'membership.project_id')
        ->join('organizations as organization', 'organization.id', '=', 'project.organization_id')
        ->where('membership.user_id', $userId)
        ->orderBy('organization.name')
        ->orderBy('project.name')
        ->get([
          'project.id as project_id',
          'project.name as project_name',
          'project.key as project_key',
          'organization.id as organization_id',
          'organization.name as organization_name',
          'membership.created_at as joined_at',
          'membership.updated_at',
        ])
        ->map(fn($row) => (array) $row)
        ->all(),
      'ticket_assignments' => DB::table('ticket_assignees as assignment')
        ->join('tickets as ticket', 'ticket.id', '=', 'assignment.ticket_id')
        ->join('projects as project', 'project.id', '=', 'ticket.project_id')
        ->where('assignment.user_id', $userId)
        ->orderBy('assignment.created_at')
        ->get([
          'ticket.id as ticket_id',
          'ticket.number',
          'ticket.title',
          'project.id as project_id',
          'project.name as project_name',
          'assignment.created_at as assigned_at',
        ])
        ->map(fn($row) => (array) $row)
        ->all(),
      'tickets_created' => DB::table('tickets as ticket')
        ->join('projects as project', 'project.id', '=', 'ticket.project_id')
        ->where('ticket.created_by', $userId)
        ->orderBy('ticket.created_at')
        ->get([
          'ticket.id',
          'ticket.project_id',
          'project.name as project_name',
          'ticket.board_id',
          'ticket.number',
          'ticket.title',
          'ticket.description_html',
          'ticket.priority',
          'ticket.position',
          'ticket.due_at',
          'ticket.started_at',
          'ticket.completed_at',
          'ticket.edited_at',
          'ticket.archived_at',
          'ticket.created_at',
          'ticket.updated_at',
          'ticket.deleted_at',
        ])
        ->map(fn($row) => (array) $row)
        ->all(),
      'comments_authored' => DB::table('ticket_comments as comment')
        ->join('tickets as ticket', 'ticket.id', '=', 'comment.ticket_id')
        ->join('projects as project', 'project.id', '=', 'ticket.project_id')
        ->where('comment.user_id', $userId)
        ->orderBy('comment.created_at')
        ->get([
          'comment.id',
          'comment.ticket_id',
          'ticket.number as ticket_number',
          'ticket.title as ticket_title',
          'project.id as project_id',
          'project.name as project_name',
          'comment.body_html',
          'comment.kind',
          'comment.author_snapshot',
          'comment.edited_at',
          'comment.created_at',
          'comment.updated_at',
          'comment.deleted_at',
        ])
        ->map(fn($row) => (array) $row)
        ->all(),
      'ticket_revisions_saved' => DB::table('ticket_revisions as revision')
        ->join('tickets as ticket', 'ticket.id', '=', 'revision.ticket_id')
        ->join('projects as project', 'project.id', '=', 'ticket.project_id')
        ->where('revision.user_id', $userId)
        ->orderBy('revision.created_at')
        ->get([
          'revision.id',
          'revision.ticket_id',
          'ticket.number as ticket_number',
          'ticket.title as current_ticket_title',
          'project.id as project_id',
          'project.name as project_name',
          'revision.version',
          'revision.snapshot',
          'revision.created_at',
        ])
        ->map(fn($row) => (array) $row)
        ->all(),
      'comment_revisions_saved' => DB::table('ticket_comment_revisions as revision')
        ->join('ticket_comments as comment', 'comment.id', '=', 'revision.ticket_comment_id')
        ->join('tickets as ticket', 'ticket.id', '=', 'comment.ticket_id')
        ->join('projects as project', 'project.id', '=', 'ticket.project_id')
        ->where('revision.user_id', $userId)
        ->orderBy('revision.created_at')
        ->get([
          'revision.id',
          'revision.ticket_comment_id',
          'comment.ticket_id',
          'ticket.number as ticket_number',
          'ticket.title as ticket_title',
          'project.id as project_id',
          'project.name as project_name',
          'revision.version',
          'revision.snapshot',
          'revision.created_at',
        ])
        ->map(fn($row) => (array) $row)
        ->all(),
      'invitations_received' => DB::table('invitations as invitation')
        ->join(
          'organizations as organization',
          'organization.id',
          '=',
          'invitation.organization_id',
        )
        ->leftJoin('projects as project', 'project.id', '=', 'invitation.project_id')
        ->whereRaw('LOWER(invitation.email) = ?', [$email])
        ->orderBy('invitation.created_at')
        ->get([
          'invitation.id',
          'organization.id as organization_id',
          'organization.name as organization_name',
          'project.id as project_id',
          'project.name as project_name',
          'invitation.email',
          'invitation.role',
          'invitation.expires_at',
          'invitation.accepted_at',
          'invitation.revoked_at',
          'invitation.created_at',
        ])
        ->map(fn($row) => (array) $row)
        ->all(),
      'invitations_sent' => DB::table('invitations as invitation')
        ->join(
          'organizations as organization',
          'organization.id',
          '=',
          'invitation.organization_id',
        )
        ->leftJoin('projects as project', 'project.id', '=', 'invitation.project_id')
        ->where('invitation.invited_by', $userId)
        ->orderBy('invitation.created_at')
        ->get([
          'invitation.id',
          'organization.id as organization_id',
          'organization.name as organization_name',
          'project.id as project_id',
          'project.name as project_name',
          'invitation.email',
          'invitation.role',
          'invitation.expires_at',
          'invitation.accepted_at',
          'invitation.revoked_at',
          'invitation.created_at',
        ])
        ->map(fn($row) => (array) $row)
        ->all(),
      'audit_activity' => DB::table('audit_logs')
        ->where('actor_type', 'user')
        ->where('actor_id', $userId)
        ->orderBy('occurred_at')
        ->get([
          'id',
          'organization_id',
          'project_id',
          'event',
          'actor_type',
          'actor_id',
          'actor_name',
          'actor_email',
          'subject_type',
          'subject_id',
          'subject_label',
          'changes',
          'metadata',
          'ip_address',
          'user_agent',
          'occurred_at',
        ])
        ->map(fn($row) => (array) $row)
        ->all(),
      'data_transfers' => DB::table('data_transfers')
        ->where('user_id', $userId)
        ->orderBy('created_at')
        ->get([
          'id',
          'organization_id',
          'project_id',
          'direction',
          'scope',
          'status',
          'filename',
          'summary',
          'errors',
          'started_at',
          'finished_at',
          'created_at',
          'updated_at',
        ])
        ->map(fn($row) => (array) $row)
        ->all(),
      'webhooks_created' => DB::table('webhook_endpoints')
        ->where('created_by', $userId)
        ->orderBy('created_at')
        ->get([
          'id',
          'organization_id',
          'project_id',
          'name',
          'url',
          'events',
          'is_active',
          'last_rotated_at',
          'created_at',
          'updated_at',
        ])
        ->map(fn($row) => (array) $row)
        ->all(),
      'notifications' => DB::table('notifications')
        ->where('notifiable_type', User::class)
        ->where('notifiable_id', $userId)
        ->orderBy('created_at')
        ->get(['id', 'type', 'data', 'read_at', 'created_at', 'updated_at'])
        ->map(fn($row) => (array) $row)
        ->all(),
      'attachments_uploaded' => DB::table('attachments')
        ->where('uploaded_by', $userId)
        ->orderBy('created_at')
        ->get([
          'id',
          'project_id',
          'ticket_id',
          'ticket_comment_id',
          'original_name',
          'mime_type',
          'size_bytes',
          'created_at',
          'updated_at',
        ])
        ->map(fn($row) => (array) $row)
        ->all(),
      'api_credentials_created' => DB::table('api_credentials')
        ->where('created_by', $userId)
        ->orderBy('created_at')
        ->get([
          'id',
          'organization_id',
          'name',
          'public_id',
          'scopes',
          'last_used_at',
          'expires_at',
          'revoked_at',
          'created_at',
          'updated_at',
        ])
        ->map(fn($row) => (array) $row)
        ->all(),
    ];
  }

  private function avatarData(User $user): ?array
  {
    if (!$user->avatar_path) {
      return null;
    }

    $disk = Storage::disk('public');
    if (!$disk->exists($user->avatar_path)) {
      return null;
    }

    $content = $disk->get($user->avatar_path);
    if (strlen($content) > 5 * 1024 * 1024) {
      return null;
    }

    return [
      'mime_type' => $disk->mimeType($user->avatar_path) ?: 'application/octet-stream',
      'content_base64' => base64_encode($content),
    ];
  }
}
