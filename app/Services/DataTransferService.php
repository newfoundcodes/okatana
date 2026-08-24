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

use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Board;
use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\Label;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketCommentRevision;
use App\Models\TicketRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DataTransferService
{
  public const SCHEMA = 'okatana.export.v1';

  public function exportOrganization(Organization $org): array
  {
    $org->load('members');

    return [
      'schema' => self::SCHEMA,
      'scope' => 'organization',
      'generated_at' => now()->toIso8601String(),
      'organization' => [
        'name' => $org->name,
        'slug' => $org->slug,
        'description' => $org->description,
        'settings' => $org->settings,
        'members' => $org->members
          ->map(
            fn($u) => [
              'name' => $u->name,
              'email' => $u->email,
              'role' => $u->pivot->role,
            ],
          )
          ->values()
          ->all(),
        'projects' => $org
          ->projects()
          ->get()
          ->map(fn($p) => $this->projectData($p))
          ->values()
          ->all(),
        'documents' => $org
          ->documents()
          ->whereNull('project_id')
          ->with(['author', 'editors', 'comments.user'])
          ->get()
          ->map(fn($d) => $this->documentData($d))
          ->values()
          ->all(),
        'audit_logs' => $this->auditData(
          AuditLog::where('organization_id', $org->id)
            ->whereNull('project_id')
            ->orderBy('occurred_at')
            ->get(),
        ),
      ],
    ];
  }

  public function exportProject(Project $project): array
  {
    return [
      'schema' => self::SCHEMA,
      'scope' => 'project',
      'generated_at' => now()->toIso8601String(),
      'project' => $this->projectData($project),
    ];
  }

  public function exportTimeline(Organization $org, ?Project $project = null): array
  {
    $q = AuditLog::where('organization_id', $org->id);
    if ($project) {
      $q->where('project_id', $project->id);
    }

    return [
      'schema' => self::SCHEMA,
      'scope' => 'timeline',
      'generated_at' => now()->toIso8601String(),
      'organization' => [
        'name' => $org->name,
        'slug' => $org->slug,
      ],
      'project' => $project?->only(['name', 'key']),
      'audit_logs' => $this->auditData($q->orderBy('occurred_at')->get()),
    ];
  }

  private function projectData(Project $p): array
  {
    $p->load([
      'members',
      'organization.members',
      'boards',
      'labels',
      'tags',
      'tickets.assignees',
      'tickets.labels',
      'tickets.tags',
      'tickets.attachments.uploader',
      'tickets.revisions.user',
      'tickets.comments.user',
      'tickets.comments.attachments.uploader',
      'tickets.comments.revisions.user',
      'documents.author',
      'documents.editors',
      'documents.tags',
      'documents.comments.user',
    ]);

    return [
      'source_id' => $p->id,
      'name' => $p->name,
      'key' => $p->key,
      'description' => $p->description,
      'settings' => $p->settings,
      'archived_at' => $p->archived_at?->toIso8601String(),
      'members' => $p->members
        ->map(
          fn($u) => [
            'source_id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
          ],
        )
        ->values()
        ->all(),
      'mention_users' => $p->members
        ->concat(
          $p->organization->members->filter(
            fn($u) => in_array($u->pivot->role, ['owner', 'admin'], true),
          ),
        )
        ->unique('id')
        ->map(
          fn($u) => [
            'source_id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
          ],
        )
        ->values()
        ->all(),
      'boards' => $p->boards
        ->map(
          fn($b) => [
            'source_id' => $b->id,
            'name' => $b->name,
            'slug' => $b->slug,
            'position' => $b->position,
            'color' => $b->color,
            'wip_limit' => $b->wip_limit,
            'is_done' => $b->is_done,
            'is_hidden' => $b->is_hidden,
          ],
        )
        ->values()
        ->all(),
      'labels' => $p->labels
        ->map(
          fn($l) => [
            'source_id' => $l->id,
            'name' => $l->name,
            'color' => $l->color,
          ],
        )
        ->values()
        ->all(),
      'tags' => $p->tags
        ->map(
          fn($tag) => [
            'source_id' => $tag->id,
            'name' => $tag->name,
          ],
        )
        ->values()
        ->all(),
      'documents' => $p->documents->map(fn($d) => $this->documentData($d))->values()->all(),
      'tickets' => $p->tickets
        ->map(
          fn($t) => [
            'source_id' => $t->id,
            'board_source_id' => $t->board_id,
            'number' => $t->number,
            'title' => $t->title,
            'description_html' => $t->description_html,
            'priority' => $t->priority,
            'position' => $t->position,
            'due_at' => $t->due_at?->toIso8601String(),
            'started_at' => $t->started_at?->toIso8601String(),
            'completed_at' => $t->completed_at?->toIso8601String(),
            'edited_at' => $t->edited_at?->toIso8601String(),
            'archived_at' => $t->archived_at?->toIso8601String(),
            'creator_email' => $t->creator?->email,
            'assignee_emails' => $t->assignees->pluck('email')->all(),
            'label_source_ids' => $t->labels->pluck('id')->all(),
            'tag_source_ids' => $t->tags->pluck('id')->all(),
            'attachments' => $t->attachments
              ->map(fn($a) => $this->attachmentData($a))
              ->values()
              ->all(),
            'revisions' => $t->revisions
              ->map(
                fn($r) => [
                  'version' => $r->version,
                  'editor_email' => $r->user?->email,
                  'snapshot' => $r->snapshot,
                  'created_at' => $r->created_at?->toIso8601String(),
                ],
              )
              ->values()
              ->all(),
            'comments' => $t->comments
              ->map(
                fn($c) => [
                  'source_id' => $c->id,
                  'author_email' => $c->user?->email ?? ($c->author_snapshot['email'] ?? null),
                  'author_snapshot' => $c->author_snapshot,
                  'body_html' => $c->body_html,
                  'kind' => $c->kind,
                  'edited_at' => $c->edited_at?->toIso8601String(),
                  'created_at' => $c->created_at?->toIso8601String(),
                  'attachments' => $c->attachments
                    ->map(fn($a) => $this->attachmentData($a))
                    ->values()
                    ->all(),
                  'revisions' => $c->revisions
                    ->map(
                      fn($r) => [
                        'version' => $r->version,
                        'editor_email' => $r->user?->email,
                        'snapshot' => $r->snapshot,
                        'created_at' => $r->created_at?->toIso8601String(),
                      ],
                    )
                    ->values()
                    ->all(),
                ],
              )
              ->values()
              ->all(),
          ],
        )
        ->values()
        ->all(),
      'assets' => $this->editorAssetsForProject($p),
      'audit_logs' => $this->auditData(
        AuditLog::where('project_id', $p->id)->orderBy('occurred_at')->get(),
      ),
    ];
  }

  private function auditData($logs): array
  {
    return $logs
      ->map(
        fn($l) => [
          'source_id' => $l->id,
          'event' => $l->event,
          'actor_type' => $l->actor_type,
          'actor_name' => $l->actor_name,
          'actor_email' => $l->actor_email,
          'subject_type' => $l->subject_type,
          'subject_id' => $l->subject_id,
          'subject_label' => $l->subject_label,
          'changes' => $l->changes,
          'metadata' => $l->metadata,
          'ip_address' => $l->ip_address,
          'user_agent' => $l->user_agent,
          'occurred_at' => $l->occurred_at?->toIso8601String(),
        ],
      )
      ->values()
      ->all();
  }

  public function importTimeline(Organization $org, ?Project $project, array $payload): int
  {
    $this->validatePayload($payload, 'timeline');
    $logs = $payload['audit_logs'] ?? [];

    $this->restoreAuditLogs($org, $project, $logs);
    return count($logs);
  }

  public function importOrganization(array $payload, User $actor, InvitationService $invites): array
  {
    $this->validatePayload($payload, 'organization');
    $src = $payload['organization'];

    return DB::transaction(function () use ($src, $actor, $invites) {
      $slug = $this->uniqueOrgSlug($src['slug'] ?? $src['name']);
      $org = Organization::create([
        'name' => $src['name'] . ' (Imported)',
        'slug' => $slug,
        'description' => $src['description'] ?? null,
        'settings' => $src['settings'] ?? [],
      ]);

      OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $actor->id,
        'role' => 'owner',
      ]);

      $warnings = [];
      foreach ($src['members'] ?? [] as $m) {
        if (strtolower($m['email']) === strtolower($actor->email)) {
          continue;
        }

        $u = User::where('email', strtolower($m['email']))->first();
        if ($u) {
          OrganizationMember::updateOrCreate(
            ['organization_id' => $org->id, 'user_id' => $u->id],
            [
              'role' => in_array($m['role'] ?? 'user', ['owner', 'admin', 'user'], true)
                ? $m['role']
                : 'user',
            ],
          );
        } else {
          $invites->invite(
            $org,
            null,
            $actor,
            $m['email'],
            in_array($m['role'] ?? 'user', ['owner', 'admin', 'user'], true) ? $m['role'] : 'user',
          );

          $warnings[] = 'Invitation sent to ' . $m['email'];
        }
      }

      foreach ($src['projects'] ?? [] as $p) {
        $this->importProjectData($org, $p, $actor, $invites, $warnings);
      }

      $documentMap = [];
      foreach ($src['documents'] ?? [] as $document) {
        $this->importDocumentData($org, null, $document, $actor, $documentMap);
      }

      $this->restoreAuditLogs($org, null, $src['audit_logs'] ?? [], $documentMap);
      return ['organization' => $org, 'warnings' => $warnings];
    });
  }

  public function importProject(
    Organization $org,
    array $payload,
    User $actor,
    InvitationService $invites,
  ): array {
    $this->validatePayload($payload, 'project');

    $warnings = [];
    $project = DB::transaction(function () use ($org, $payload, $actor, $invites, &$warnings) {
      return $this->importProjectData($org, $payload['project'], $actor, $invites, $warnings);
    });

    return ['project' => $project, 'warnings' => $warnings];
  }

  private function importProjectData(
    Organization $org,
    array $src,
    User $actor,
    InvitationService $invites,
    array &$warnings,
  ): Project {
    $key = $this->uniqueProjectKey($org, $src['key'] ?? 'IMP');
    $p = Project::create([
      'organization_id' => $org->id,
      'name' => ($src['name'] ?? 'Imported Project') . ' (Imported)',
      'key' => $key,
      'description' => $src['description'] ?? null,
      'settings' => $src['settings'] ?? [],
      'archived_at' => $src['archived_at'] ?? null,
    ]);
    $this->restoreEditorAssets($src['assets'] ?? []);

    $boardMap = [];
    $labelMap = [];
    $tagMap = [];
    $ticketMap = [];
    $documentMap = [];
    $mentionMap = [];

    foreach ($src['members'] ?? [] as $m) {
      $u = User::where('email', strtolower($m['email']))->first();
      if ($u && $org->roleFor($u) !== null) {
        ProjectMember::firstOrCreate(['project_id' => $p->id, 'user_id' => $u->id]);
      } elseif (!$u) {
        $invites->invite($org, $p, $actor, $m['email'], 'user');
        $warnings[] = 'Project invitation sent to ' . $m['email'];
      }
    }

    foreach ($src['mention_users'] ?? ($src['members'] ?? []) as $m) {
      $sourceId = $m['source_id'] ?? null;
      if (!$sourceId || empty($m['email'])) {
        continue;
      }

      $u = User::where('email', strtolower($m['email']))->first();
      if ($u && $p->hasAccess($u)) {
        $mentionMap[$sourceId] = $u->id;
      }
    }

    foreach ($src['boards'] ?? [] as $b) {
      $model = Board::create([
        'project_id' => $p->id,
        'name' => $b['name'],
        'slug' => $b['slug'] ?? Str::slug($b['name'], '_'),
        'position' => $b['position'] ?? 1000,
        'color' => $b['color'] ?? null,
        'wip_limit' => $b['wip_limit'] ?? null,
        'is_done' => $b['is_done'] ?? false,
        'is_hidden' => $b['is_hidden'] ?? false,
      ]);
      $boardMap[$b['source_id'] ?? $model->id] = $model->id;
    }

    if (!$boardMap) {
      $b = Board::create([
        'project_id' => $p->id,
        'name' => 'Open',
        'slug' => 'open',
        'position' => 1000,
      ]);
      $boardMap['default'] = $b->id;
    }

    foreach ($src['labels'] ?? [] as $l) {
      $model = Label::create([
        'project_id' => $p->id,
        'name' => $l['name'],
        'color' => $l['color'] ?? null,
      ]);
      $labelMap[$l['source_id'] ?? $model->id] = $model->id;
    }

    foreach ($src['tags'] ?? [] as $tag) {
      $name = app(TagService::class)->normalizeNames([$tag['name'] ?? ''])[0] ?? null;
      if (!$name) {
        continue;
      }

      $model = Tag::firstOrCreate(
        ['project_id' => $p->id, 'normalized_name' => Str::lower($name)],
        ['name' => $name],
      );
      $tagMap[$tag['source_id'] ?? $model->id] = $model->id;
    }

    foreach ($src['tickets'] ?? [] as $t) {
      $creator = isset($t['creator_email'])
        ? User::where('email', strtolower($t['creator_email']))->first()
        : $actor;

      if ($creator && !$p->hasAccess($creator)) {
        $creator = $actor;
      }

      $model = Ticket::create([
        'project_id' => $p->id,
        'board_id' => $boardMap[$t['board_source_id'] ?? ''] ?? array_values($boardMap)[0],
        'number' => $t['number'] ?? (int) $p->tickets()->max('number') + 1,
        'title' => $t['title'],
        'description_html' => isset($t['description_html'])
          ? app(HtmlSanitizer::class)->sanitize(
            $this->remapMentions($t['description_html'], $mentionMap),
          )
          : null,
        'priority' => $t['priority'] ?? 'normal',
        'position' => $t['position'] ?? 1000,
        'due_at' => $t['due_at'] ?? null,
        'started_at' => $t['started_at'] ?? null,
        'completed_at' => $t['completed_at'] ?? null,
        'edited_at' => $t['edited_at'] ?? null,
        'archived_at' => $t['archived_at'] ?? null,
        'created_by' => $creator?->id ?? $actor->id,
        'updated_by' => $actor->id,
      ]);

      $ticketMap[$t['source_id'] ?? $model->id] = $model->id;
      $candidateAssignees = User::whereIn(
        'email',
        array_map('strtolower', $t['assignee_emails'] ?? []),
      )->pluck('id');

      $adminIds = $org
        ->members()
        ->wherePivotIn('role', ['owner', 'admin'])
        ->pluck('users.id');

      $allowedIds = $p->members()->pluck('users.id')->merge($adminIds)->unique();

      $assignees = $candidateAssignees->intersect($allowedIds)->values()->all();

      $model->assignees()->sync($assignees);
      $model
        ->labels()
        ->sync(
          array_values(
            array_filter(
              array_map(fn($id) => $labelMap[$id] ?? null, $t['label_source_ids'] ?? []),
            ),
          ),
        );

      $tagIds = array_values(
        array_filter(array_map(fn($id) => $tagMap[$id] ?? null, $t['tag_source_ids'] ?? [])),
      );

      if ($tagIds) {
        $model->tags()->sync($tagIds);
      } elseif (isset($t['tag_names'])) {
        app(TagService::class)->syncTicket($model, $t['tag_names']);
      }

      $this->restoreAttachments($p, $model, null, $t['attachments'] ?? [], $actor);
      $model->mentions()->sync(app(MentionService::class)->extractIds($model->description_html));

      foreach ($t['revisions'] ?? [] as $r) {
        $editor = isset($r['editor_email'])
          ? User::where('email', strtolower($r['editor_email']))->first()
          : null;

        TicketRevision::create([
          'ticket_id' => $model->id,
          'version' => $r['version'] ?? (int) $model->revisions()->max('version') + 1,
          'user_id' => $editor?->id,
          'snapshot' => $this->remapRevisionSnapshot($r['snapshot'] ?? [], $mentionMap),
          'created_at' => $r['created_at'] ?? now(),
          'updated_at' => $r['created_at'] ?? now(),
        ]);
      }

      if (!$model->revisions()->exists()) {
        app(RevisionService::class)->recordTicket($model->fresh(), $actor, $model->created_at);
      }

      foreach ($t['comments'] ?? [] as $c) {
        $author = isset($c['author_email'])
          ? User::where('email', strtolower($c['author_email']))->first()
          : null;

        if ($author && $org->roleFor($author) === null) {
          $author = null;
        }

        $comment = TicketComment::create([
          'ticket_id' => $model->id,
          'user_id' => $author?->id,
          'body_html' => app(HtmlSanitizer::class)->sanitize(
            $this->remapMentions($c['body_html'] ?? '', $mentionMap),
          ),
          'kind' => in_array($c['kind'] ?? 'comment', ['comment', 'movement'], true)
            ? $c['kind'] ?? 'comment'
            : 'comment',
          'author_snapshot' => $c['author_snapshot'] ?? [
            'email' => $c['author_email'] ?? null,
          ],
          'edited_at' => $c['edited_at'] ?? null,
          'created_at' => $c['created_at'] ?? now(),
          'updated_at' => $c['created_at'] ?? now(),
        ]);

        $this->restoreAttachments($p, null, $comment, $c['attachments'] ?? [], $actor);
        if ($comment->kind === 'comment') {
          $comment->mentions()->sync(app(MentionService::class)->extractIds($comment->body_html));
        }

        foreach ($c['revisions'] ?? [] as $r) {
          $editor = isset($r['editor_email'])
            ? User::where('email', strtolower($r['editor_email']))->first()
            : null;

          TicketCommentRevision::create([
            'ticket_comment_id' => $comment->id,
            'version' => $r['version'] ?? (int) $comment->revisions()->max('version') + 1,
            'user_id' => $editor?->id,
            'snapshot' => $this->remapRevisionSnapshot($r['snapshot'] ?? [], $mentionMap),
            'created_at' => $r['created_at'] ?? now(),
            'updated_at' => $r['created_at'] ?? now(),
          ]);
        }

        if ($comment->kind === 'comment' && !$comment->revisions()->exists()) {
          app(RevisionService::class)->recordComment($comment, $author, $comment->created_at);
        }
      }
    }

    foreach ($src['documents'] ?? [] as $document) {
      $this->importDocumentData($org, $p, $document, $actor, $documentMap);
    }

    $this->restoreAuditLogs(
      $org,
      $p,
      $src['audit_logs'] ?? [],
      array_merge($ticketMap, $documentMap),
    );

    return $p;
  }

  private function documentData(Document $document): array
  {
    $document->loadMissing(['author', 'editors', 'tags', 'comments.user']);
    $html = [$document->content_html, ...$document->comments->pluck('body_html')->all()];

    return [
      'source_id' => $document->id,
      'title' => $document->title,
      'caption' => $document->caption,
      'content_html' => $document->content_html,
      'status' => $document->status,
      'published_at' => $document->published_at?->toIso8601String(),
      'archived_at' => $document->archived_at?->toIso8601String(),
      'author_email' => $document->author?->email,
      'editor_emails' => $document->editors->pluck('email')->all(),
      'tag_names' => $document->tags->pluck('name')->all(),
      'comments' => $document->comments
        ->map(
          fn($comment) => [
            'source_id' => $comment->id,
            'author_email' =>
              $comment->user?->email ?? ($comment->author_snapshot['email'] ?? null),
            'author_snapshot' => $comment->author_snapshot,
            'body_html' => $comment->body_html,
            'edited_at' => $comment->edited_at?->toIso8601String(),
            'created_at' => $comment->created_at?->toIso8601String(),
          ],
        )
        ->values()
        ->all(),
      'assets' => $this->editorAssetsForHtml($html),
    ];
  }

  private function importDocumentData(
    Organization $org,
    ?Project $project,
    array $src,
    User $actor,
    array &$subjectMap,
  ): Document {
    $this->restoreEditorAssets($src['assets'] ?? []);

    $author = isset($src['author_email'])
      ? User::where('email', strtolower($src['author_email']))->first()
      : $actor;
    if (
      !$author ||
      $org->roleFor($author) === null ||
      ($project && !$project->hasAccess($author))
    ) {
      $author = $actor;
    }

    $status = ($src['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $document = Document::create([
      'organization_id' => $org->id,
      'project_id' => $project?->id,
      'author_id' => $author->id,
      'title' => substr((string) ($src['title'] ?? 'Imported document'), 0, 500),
      'caption' => $src['caption'] ?? null,
      'content_html' => app(HtmlSanitizer::class)->sanitize($src['content_html'] ?? null),
      'status' => $status,
      'published_at' => $status === 'published' ? $src['published_at'] ?? now() : null,
      'archived_at' => $src['archived_at'] ?? null,
    ]);
    $subjectMap[$src['source_id'] ?? $document->id] = $document->id;

    $candidates = User::whereIn(
      'email',
      array_map('strtolower', $src['editor_emails'] ?? []),
    )->pluck('id');

    $allowed = collect(app(DocumentAccessService::class)->allowedEditorIds($org, $project));
    $document
      ->editors()
      ->syncWithPivotValues(
        $candidates->intersect($allowed)->reject(fn($id) => $id === $author->id)->values()->all(),
        ['added_by' => $actor->id],
      );
    app(DocumentTagService::class)->syncDocument($document, $src['tag_names'] ?? []);

    foreach ($src['comments'] ?? [] as $row) {
      $commentAuthor = isset($row['author_email'])
        ? User::where('email', strtolower($row['author_email']))->first()
        : null;

      if (
        $commentAuthor &&
        ($org->roleFor($commentAuthor) === null ||
          ($project && !$project->hasAccess($commentAuthor)))
      ) {
        $commentAuthor = null;
      }

      $comment = DocumentComment::create([
        'document_id' => $document->id,
        'user_id' => $commentAuthor?->id,
        'body_html' => app(HtmlSanitizer::class)->sanitize($row['body_html'] ?? ''),
        'author_snapshot' => $row['author_snapshot'] ?? [
          'email' => $row['author_email'] ?? null,
        ],
        'edited_at' => $row['edited_at'] ?? null,
        'created_at' => $row['created_at'] ?? now(),
        'updated_at' => $row['created_at'] ?? now(),
      ]);
      $subjectMap[$row['source_id'] ?? $comment->id] = $comment->id;
    }

    return $document;
  }

  private function editorAssetsForHtml(array $htmlValues): array
  {
    $paths = [];
    foreach ($htmlValues as $html) {
      if (!$html) {
        continue;
      }

      preg_match_all(
        '#(?:https?://[^/]+)?/storage/(editor-images/[A-Za-z0-9_./-]+)#i',
        $html,
        $storageMatches,
      );

      foreach ($storageMatches[1] ?? [] as $path) {
        if (!str_contains($path, '..')) {
          $paths[$path] = true;
        }
      }

      preg_match_all(
        '#(?:https?://[^/]+)?/app-api/editor/images/([A-Za-z0-9_-]+)/([A-Za-z0-9_.-]+)#i',
        $html,
        $servedMatches,
        PREG_SET_ORDER,
      );

      foreach ($servedMatches as $match) {
        $path = 'editor-images/' . $match[1] . '/' . $match[2];
        if (!str_contains($path, '..')) {
          $paths[$path] = true;
        }
      }
    }

    $disk = Storage::disk('public');
    $assets = [];

    foreach (array_keys($paths) as $path) {
      if (!$disk->exists($path)) {
        continue;
      }

      $content = $disk->get($path);
      if (strlen($content) > 5 * 1024 * 1024) {
        continue;
      }

      $assets[] = [
        'path' => $path,
        'mime_type' => $disk->mimeType($path) ?: 'application/octet-stream',
        'content_base64' => base64_encode($content),
      ];
    }

    return $assets;
  }

  private function restoreAuditLogs(
    Organization $org,
    ?Project $project,
    array $logs,
    array $subjectMap = [],
  ): void {
    foreach ($logs as $l) {
      AuditLog::create([
        'organization_id' => $org->id,
        'project_id' => $project?->id,
        'event' => $l['event'] ?? 'imported.event',
        'actor_type' => $l['actor_type'] ?? 'import',
        'actor_name' => $l['actor_name'] ?? null,
        'actor_email' => $l['actor_email'] ?? null,
        'subject_type' => $l['subject_type'] ?? null,
        'subject_id' => $subjectMap[$l['subject_id'] ?? ''] ?? ($l['subject_id'] ?? null),
        'subject_label' => $l['subject_label'] ?? null,
        'changes' => $l['changes'] ?? null,
        'metadata' => array_merge($l['metadata'] ?? [], [
          'imported' => true,
          'source_audit_id' => $l['source_id'] ?? null,
        ]),
        'ip_address' => $l['ip_address'] ?? null,
        'user_agent' => $l['user_agent'] ?? null,
        'occurred_at' => $l['occurred_at'] ?? now(),
      ]);
    }
  }

  private function editorAssetsForProject(Project $project): array
  {
    $html = [];

    foreach ($project->tickets as $ticket) {
      $html[] = $ticket->description_html;

      $html = array_merge($html, $ticket->comments->pluck('body_html')->all());
      $html = array_merge(
        $html,
        $ticket->revisions
          ->map(fn($revision) => $revision->snapshot['description_html'] ?? null)
          ->all(),
      );

      $html = array_merge(
        $html,
        $ticket->comments
          ->flatMap(
            fn($comment) => $comment->revisions->map(
              fn($revision) => $revision->snapshot['body_html'] ?? null,
            ),
          )
          ->all(),
      );
    }

    return $this->editorAssetsForHtml($html);
  }

  private function restoreEditorAssets(array $assets): void
  {
    $disk = Storage::disk('public');

    foreach ($assets as $asset) {
      $path = (string) ($asset['path'] ?? '');
      $encoded = (string) ($asset['content_base64'] ?? '');

      if (
        !str_starts_with($path, 'editor-images/') ||
        str_contains($path, '..') ||
        $encoded === ''
      ) {
        continue;
      }

      $content = base64_decode($encoded, true);
      if ($content === false || strlen($content) > 5 * 1024 * 1024) {
        continue;
      }

      $disk->put($path, $content);
    }
  }

  private function attachmentData(Attachment $attachment): array
  {
    $disk = Storage::disk($attachment->disk);
    $content = $disk->exists($attachment->path) ? $disk->get($attachment->path) : null;

    return [
      'source_id' => $attachment->id,
      'original_name' => $attachment->original_name,
      'mime_type' => $attachment->mime_type,
      'size_bytes' => $attachment->size_bytes,
      'uploader_email' => $attachment->uploader?->email,
      'content_base64' =>
        $content !== null && strlen($content) <= 200 * 1024 * 1024 ? base64_encode($content) : null,
    ];
  }

  private function restoreAttachments(
    Project $project,
    ?Ticket $ticket,
    ?TicketComment $comment,
    array $attachments,
    User $fallbackUploader,
  ): void {
    foreach ($attachments as $row) {
      $encoded = (string) ($row['content_base64'] ?? '');
      if ($encoded === '') {
        continue;
      }

      $content = base64_decode($encoded, true);
      if ($content === false || strlen($content) > 200 * 1024 * 1024) {
        continue;
      }

      $uploader = isset($row['uploader_email'])
        ? User::where('email', strtolower($row['uploader_email']))->first()
        : $fallbackUploader;
      if ($uploader && !$project->hasAccess($uploader)) {
        $uploader = $fallbackUploader;
      }

      $folder =
        'attachments/' .
        $project->id .
        '/' .
        ($ticket ? 'tickets/' . $ticket->id : 'comments/' . $comment->id);
      $path = $folder . '/' . Str::ulid();

      Storage::disk('local')->put($path, $content);
      Attachment::create([
        'project_id' => $project->id,
        'ticket_id' => $ticket?->id,
        'ticket_comment_id' => $comment?->id,
        'uploaded_by' => $uploader?->id,
        'original_name' => substr((string) ($row['original_name'] ?? 'attachment'), 0, 255),
        'mime_type' => $row['mime_type'] ?? 'application/octet-stream',
        'size_bytes' => strlen($content),
        'disk' => 'local',
        'path' => $path,
      ]);
    }
  }

  private function remapMentions(?string $html, array $mentionMap): ?string
  {
    if ($html === null) {
      return null;
    }

    return preg_replace_callback(
      '/\\sdata-mention-user-id=["\\\']([A-Za-z0-9]{20,32})["\\\']/i',
      function ($match) use ($mentionMap) {
        $target = $mentionMap[$match[1]] ?? null;

        return $target ? ' data-mention-user-id="' . e($target) . '"' : '';
      },
      $html,
    );
  }

  private function remapRevisionSnapshot(array $snapshot, array $mentionMap): array
  {
    if (array_key_exists('description_html', $snapshot)) {
      $snapshot['description_html'] = $this->remapMentions(
        $snapshot['description_html'],
        $mentionMap,
      );
    }

    if (array_key_exists('body_html', $snapshot)) {
      $snapshot['body_html'] = $this->remapMentions($snapshot['body_html'], $mentionMap);
    }

    return $snapshot;
  }

  private function validatePayload(array $payload, string $scope): void
  {
    abort_unless(
      ($payload['schema'] ?? null) === self::SCHEMA && ($payload['scope'] ?? null) === $scope,
      422,
      'Unsupported Okatana export format or scope.',
    );
  }

  private function uniqueOrgSlug(string $value): string
  {
    $base = Str::slug($value) ?: 'imported';
    $slug = $base;

    $i = 2;
    while (Organization::withTrashed()->where('slug', $slug)->exists()) {
      $slug = $base . '-' . $i++;
    }

    return $slug;
  }

  private function uniqueProjectKey(Organization $org, string $value): string
  {
    $base = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $value) ?: 'IMP', 0, 8));
    $key = $base;

    $i = 2;
    while ($org->projects()->withTrashed()->where('key', $key)->exists()) {
      $key = substr($base, 0, 8 - strlen((string) $i)) . $i++;
    }

    return $key;
  }
}
