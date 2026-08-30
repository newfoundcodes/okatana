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

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\ActivityRecorder;
use App\Services\DocumentAccessService;
use App\Services\DocumentPdfService;
use App\Services\DocumentTagService;
use App\Services\HtmlSanitizer;
use App\Services\MentionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use function Spatie\LaravelPdf\Support\pdf;

class DocumentController extends Controller
{
  public function index(Request $request, DocumentAccessService $access): array
  {
    $data = $request->validate([
      'scope' => 'nullable|in:articles,favorites',
      'q' => 'nullable|string|max:200',
      'page' => 'nullable|integer|min:1',
      'per_page' => 'nullable|integer|in:10,20,50,100',
    ]);

    $user = $request->user();
    $query = $access
      ->visibleQuery($user)
      ->with([
        'organization:id,name',
        'project:id,organization_id,name,key',
        'author:id,name,email,avatar_path',
        'apiCredential:id,name',
        'tags:id,name',
      ])
      ->withCount('comments');

    if (($data['scope'] ?? 'articles') === 'favorites') {
      $query->whereHas(
        'favoritedBy',
        fn(Builder $favorite) => $favorite->where('users.id', $user->id),
      );
    }

    if ($search = trim((string) ($data['q'] ?? ''))) {
      $needle = '%' . strtolower($search) . '%';
      $query->where(function (Builder $matches) use ($needle): void {
        $matches
          ->whereRaw('LOWER(documents.title) LIKE ?', [$needle])
          ->orWhereRaw('LOWER(documents.caption) LIKE ?', [$needle])
          ->orWhereRaw('LOWER(documents.status) LIKE ?', [$needle])
          ->orWhereHas(
            'author',
            fn(Builder $author) => $author
              ->whereRaw('LOWER(name) LIKE ?', [$needle])
              ->orWhereRaw('LOWER(email) LIKE ?', [$needle]),
          )
          ->orWhereHas(
            'organization',
            fn(Builder $organization) => $organization->whereRaw('LOWER(name) LIKE ?', [$needle]),
          )
          ->orWhereHas(
            'project',
            fn(Builder $project) => $project->whereRaw('LOWER(name) LIKE ?', [$needle]),
          )
          ->orWhereHas(
            'tags',
            fn(Builder $tags) => $tags->whereRaw('LOWER(document_tags.name) LIKE ?', [$needle]),
          );
      });
    }

    $page = $query
      ->orderByRaw('CASE WHEN published_at IS NULL THEN 1 ELSE 0 END')
      ->orderByDesc('published_at')
      ->orderByDesc('updated_at')
      ->paginate((int) ($data['per_page'] ?? 10));

    $editorIds = Document::query()
      ->whereIn('id', collect($page->items())->pluck('id'))
      ->whereHas('editors', fn(Builder $q) => $q->where('users.id', $user->id))
      ->pluck('id')
      ->all();

    $favoriteIds = $user
      ->favoriteDocuments()
      ->whereIn('documents.id', collect($page->items())->pluck('id'))
      ->pluck('documents.id')
      ->all();

    $rows = collect($page->items())
      ->map(fn(Document $document) => $this->listRow($document, $user, $editorIds, $favoriteIds))
      ->values();

    return ['documents' => $rows, 'pagination' => $this->pagination($page)];
  }

  public function editorOptions(Request $request): array
  {
    $user = $request->user();
    $organizations = $user
      ->organizations()
      ->orderBy('name')
      ->get(['organizations.id', 'organizations.name'])
      ->map(
        fn($organization) => [
          'id' => $organization->id,
          'name' => $organization->name,
          'role' => $organization->pivot->role,
        ],
      )
      ->values();

    $projects = Project::query()
      ->with('organization:id,name')
      ->where(function (Builder $access) use ($user): void {
        $access
          ->whereHas('organization.members', function (Builder $members) use ($user): void {
            $members
              ->where('users.id', $user->id)
              ->whereIn('organization_members.role', ['owner', 'admin']);
          })
          ->orWhereHas('members', fn(Builder $members) => $members->where('users.id', $user->id));
      })
      ->orderBy('name')
      ->get(['id', 'organization_id', 'name', 'key']);

    return [
      'organizations' => $organizations,
      'projects' => $projects,
    ];
  }

  public function tags(
    Request $request,
    Organization $organization,
    DocumentTagService $tags,
  ): array {
    abort_unless($organization->roleFor($request->user()) !== null, 403);

    return ['tags' => $tags->usedTags($organization)];
  }

  public function editorCandidates(Request $request, DocumentAccessService $access): array
  {
    $data = $request->validate([
      'organization_id' => 'required|string',
      'project_id' => 'nullable|string',
      'q' => 'nullable|string|max:200',
    ]);

    $organization = Organization::findOrFail($data['organization_id']);
    abort_unless($organization->roleFor($request->user()) !== null, 403);

    $project = null;
    if (!empty($data['project_id'])) {
      $project = $organization->projects()->findOrFail($data['project_id']);
      abort_unless($access->canAccessScope($request->user(), $organization, $project), 403);
    }

    $allowedIds = $access->allowedEditorIds($organization, $project);
    $query = User::query()->whereIn('id', $allowedIds)->where('id', '!=', $request->user()->id);

    if ($search = trim((string) ($data['q'] ?? ''))) {
      $needle = '%' . strtolower($search) . '%';
      $query->where(
        fn(Builder $match) => $match
          ->whereRaw('LOWER(name) LIKE ?', [$needle])
          ->orWhereRaw('LOWER(email) LIKE ?', [$needle]),
      );
    }

    return [
      'users' => $query
        ->orderBy('name')
        ->limit(12)
        ->get(['id', 'name', 'email', 'avatar_path']),
    ];
  }

  public function mentionCandidates(Request $request, DocumentAccessService $access): array
  {
    $data = $request->validate([
      'organization_id' => 'required|string',
      'project_id' => 'nullable|string',
    ]);
    $organization = Organization::findOrFail($data['organization_id']);

    abort_unless($organization->roleFor($request->user()) !== null, 403);
    $project = null;

    if (!empty($data['project_id'])) {
      $project = $organization->projects()->findOrFail($data['project_id']);
      abort_unless($access->canAccessScope($request->user(), $organization, $project), 403);
    }

    $allowedIds = $access->allowedEditorIds($organization, $project);

    return [
      'users' => User::query()
        ->whereIn('id', $allowedIds)
        ->orderBy('name')
        ->get(['id', 'name', 'email', 'avatar_path']),
    ];
  }

  public function store(
    Request $request,
    DocumentAccessService $access,
    HtmlSanitizer $sanitizer,
    ActivityRecorder $activity,
    DocumentTagService $tags,
    MentionService $mentions,
  ) {
    $data = $this->validateDocument($request, true);
    [$organization, $project] = $this->scopeForUser(
      $request->user(),
      $data['organization_id'],
      $data['project_id'] ?? null,
      $access,
    );

    $editorIds = array_values(array_unique($data['editor_ids'] ?? []));
    $this->assertEditors($access, $organization, $project, $editorIds);

    $content = $sanitizer->sanitize($data['content_html'] ?? null);
    $mentions->validateDocumentHtml($organization, $project, $content);

    $status = $data['status'] ?? 'draft';
    $document = DB::transaction(function () use (
      $request,
      $data,
      $organization,
      $project,
      $editorIds,
      $content,
      $status,
      $tags,
    ) {
      $document = Document::create([
        'organization_id' => $organization->id,
        'project_id' => $project?->id,
        'author_id' => $request->user()->id,
        'title' => $data['title'],
        'caption' => $data['caption'] ?? null,
        'content_html' => $content,
        'status' => $status,
        'published_at' => $status === 'published' ? now() : null,
      ]);

      $document->editors()->syncWithPivotValues($editorIds, ['added_by' => $request->user()->id]);
      $tags->syncDocument($document, $data['tag_names'] ?? []);

      return $document;
    });

    $document->load(['organization', 'project']);
    $mentions->syncDocument($document, $request->user());

    $activity->record(
      'document.created',
      $organization,
      $project,
      $document,
      [],
      [
        'status' => $status,
        'editor_ids' => $editorIds,
        'tag_names' => $data['tag_names'] ?? [],
      ],
      $request->user(),
      $request,
    );

    if ($status === 'published') {
      $activity->record(
        'document.published',
        $organization,
        $project,
        $document,
        [],
        [],
        $request->user(),
        $request,
      );
    }

    return response()->json(
      [
        'document' => $this->detail($document->fresh(), $request->user(), $access),
      ],
      201,
    );
  }

  public function show(Request $request, Document $document, DocumentAccessService $access): array
  {
    $document->load(['organization', 'project']);
    $access->assertView($request->user(), $document);

    return [
      'document' => $this->detail($document, $request->user(), $access),
    ];
  }

  public function update(
    Request $request,
    Document $document,
    DocumentAccessService $access,
    HtmlSanitizer $sanitizer,
    ActivityRecorder $activity,
    DocumentTagService $tags,
    MentionService $mentions,
  ): array {
    $document->load(['organization', 'project', 'editors', 'tags']);
    $access->assertEdit($request->user(), $document);

    $isAuthor = $document->author_id === $request->user()->id;
    $data = $this->validateDocument($request, false);

    if (
      array_key_exists('organization_id', $data) &&
      $data['organization_id'] !== $document->organization_id
    ) {
      abort(422, 'A document cannot be moved to another organization after creation.');
    }

    if (
      !$isAuthor &&
      (array_key_exists('project_id', $data) || array_key_exists('editor_ids', $data))
    ) {
      abort(403, 'Only the document author can change project or editors.');
    }

    $organization = $document->organization;
    $project = $document->project;

    if ($isAuthor && array_key_exists('project_id', $data)) {
      [$organization, $project] = $this->scopeForUser(
        $request->user(),
        $document->organization_id,
        $data['project_id'],
        $access,
      );
    }

    if ($isAuthor && array_key_exists('editor_ids', $data)) {
      $this->assertEditors($access, $organization, $project, $data['editor_ids']);
    }

    $before = $document->only([
      'organization_id',
      'project_id',
      'title',
      'caption',
      'content_html',
      'status',
      'published_at',
      'archived_at',
    ]);

    $beforeEditorIds = $document->editors->pluck('id')->all();
    $beforeTagNames = $document->tags->pluck('name')->all();

    $oldStatus = $document->status;
    $update = [];

    foreach (['title', 'caption', 'status'] as $key) {
      if (array_key_exists($key, $data)) {
        $update[$key] = $data[$key];
      }
    }

    $nextContent = array_key_exists('content_html', $data)
      ? $sanitizer->sanitize($data['content_html'])
      : $document->content_html;

    if (array_key_exists('content_html', $data)) {
      $update['content_html'] = $nextContent;
    }

    if (
      array_key_exists('content_html', $data) ||
      ($isAuthor && array_key_exists('project_id', $data))
    ) {
      $mentions->validateDocumentHtml($organization, $project, $nextContent);
    }

    if ($isAuthor) {
      $update['organization_id'] = $organization->id;
      $update['project_id'] = $project?->id;
    }

    if (array_key_exists('status', $data)) {
      if ($data['status'] === 'published' && $oldStatus !== 'published') {
        $update['published_at'] = now();
      }

      if ($data['status'] === 'draft') {
        $update['published_at'] = null;
      }
    }

    DB::transaction(function () use ($document, $update, $data, $request, $isAuthor, $tags): void {
      $document->update($update);

      if ($isAuthor && array_key_exists('editor_ids', $data)) {
        $document->editors()->syncWithPivotValues(array_values(array_unique($data['editor_ids'])), [
          'added_by' => $request->user()->id,
        ]);
      }

      if (array_key_exists('tag_names', $data)) {
        $tags->syncDocument($document, $data['tag_names']);
      }
    });

    $fresh = $document->fresh(['organization', 'project', 'editors', 'tags']);
    $mentions->syncDocument($fresh, $request->user());

    $activity->record(
      'document.updated',
      $fresh->organization,
      $fresh->project,
      $fresh,
      [
        'before' => [...$before, 'editor_ids' => $beforeEditorIds, 'tag_names' => $beforeTagNames],
        'after' => [
          ...$fresh->only(array_keys($before)),
          'editor_ids' => $fresh->editors->pluck('id')->all(),
          'tag_names' => $fresh->tags->pluck('name')->all(),
        ],
      ],
      [],
      $request->user(),
      $request,
    );

    if ($oldStatus !== $fresh->status) {
      $activity->record(
        $fresh->status === 'published' ? 'document.published' : 'document.drafted',
        $fresh->organization,
        $fresh->project,
        $fresh,
        [],
        [],
        $request->user(),
        $request,
      );
    }

    return [
      'document' => $this->detail($fresh, $request->user(), $access),
    ];
  }

  public function archive(
    Request $request,
    Document $document,
    DocumentAccessService $access,
    ActivityRecorder $activity,
  ): array {
    $document->load(['organization', 'project']);
    $access->assertEdit($request->user(), $document);

    $data = $request->validate(['archived' => 'required|boolean']);
    $document->update(['archived_at' => $data['archived'] ? now() : null]);
    $activity->record(
      $data['archived'] ? 'document.archived' : 'document.unarchived',
      $document->organization,
      $document->project,
      $document,
      [],
      [],
      $request->user(),
      $request,
    );

    return [
      'document' => $this->detail($document->fresh(), $request->user(), $access),
    ];
  }

  public function destroy(
    Request $request,
    Document $document,
    DocumentAccessService $access,
    ActivityRecorder $activity,
  ) {
    $document->load(['organization', 'project']);
    $access->assertDelete($request->user(), $document);

    $activity->record(
      'document.deleted',
      $document->organization,
      $document->project,
      $document,
      [],
      ['soft_delete' => true],
      $request->user(),
      $request,
    );

    $document->delete();

    return response()->noContent();
  }

  public function favorite(
    Request $request,
    Document $document,
    DocumentAccessService $access,
  ): array {
    $document->load(['organization', 'project']);
    $access->assertView($request->user(), $document);
    $data = $request->validate(['saved' => 'required|boolean']);

    if ($data['saved']) {
      $document->favoritedBy()->syncWithoutDetaching([$request->user()->id]);
    } else {
      $document->favoritedBy()->detach($request->user()->id);
    }

    return ['saved' => $data['saved']];
  }

  public function audit(Request $request, DocumentAccessService $access): array
  {
    $data = $request->validate([
      'q' => 'nullable|string|max:200',
      'page' => 'nullable|integer|min:1',
      'per_page' => 'nullable|integer|in:10,20,50,100',
    ]);

    $visibleDocumentIds = $access
      ->visibleQuery($request->user())
      ->withTrashed()
      ->pluck('documents.id');

    $visibleCommentIds = DocumentComment::withTrashed()
      ->whereIn('document_id', $visibleDocumentIds)
      ->pluck('id');

    $query = AuditLog::query()
      ->where('event', 'like', 'document.%')
      ->where(function (Builder $visible) use ($visibleDocumentIds, $visibleCommentIds): void {
        $visible
          ->where(function (Builder $documents) use ($visibleDocumentIds): void {
            $documents
              ->where('subject_type', Document::class)
              ->whereIn('subject_id', $visibleDocumentIds);
          })
          ->orWhere(function (Builder $comments) use ($visibleCommentIds): void {
            $comments
              ->where('subject_type', DocumentComment::class)
              ->whereIn('subject_id', $visibleCommentIds);
          });
      });

    if ($search = trim((string) ($data['q'] ?? ''))) {
      $needle = '%' . strtolower($search) . '%';

      $query->where(
        fn(Builder $q) => $q
          ->whereRaw('LOWER(event) LIKE ?', [$needle])
          ->orWhereRaw('LOWER(actor_name) LIKE ?', [$needle])
          ->orWhereRaw('LOWER(actor_email) LIKE ?', [$needle])
          ->orWhereRaw('LOWER(subject_label) LIKE ?', [$needle]),
      );
    }

    $page = $query->latest('occurred_at')->paginate((int) ($data['per_page'] ?? 10));
    $actorIds = collect($page->items())
      ->where('actor_type', 'user')
      ->pluck('actor_id')
      ->filter()
      ->unique();
    $users = User::whereIn('id', $actorIds)
      ->get(['id', 'avatar_path', 'updated_at'])
      ->keyBy('id');

    $rows = collect($page->items())->map(function (AuditLog $log) use ($users) {
      $log->setAttribute('actor_avatar_url', $users->get($log->actor_id)?->avatar_url);

      return $log;
    });

    return [
      'audit_logs' => $rows,
      'pagination' => $this->pagination($page),
    ];
  }

  public function pdf(
    Request $request,
    Document $document,
    DocumentAccessService $access,
    DocumentPdfService $pdfService,
  ) {
    $document->load(['organization', 'project', 'author', 'tags']);
    $access->assertView($request->user(), $document);

    $content = $pdfService->inlineLocalImages($document->content_html);
    $filename = Str::slug($document->title ?: 'document') . '.pdf';

    return pdf()
      ->view('pdf.document', [
        'document' => $document,
        'content' => $content,
      ])
      ->format('a4')
      ->name($filename)
      ->download();
  }

  private function validateDocument(Request $request, bool $creating): array
  {
    return $request->validate([
      'organization_id' => $creating ? ['required', 'string'] : ['sometimes', 'required', 'string'],
      'project_id' => ['sometimes', 'nullable', 'string'],
      'title' => $creating
        ? ['required', 'string', 'max:500']
        : ['sometimes', 'required', 'string', 'max:500'],
      'caption' => ['sometimes', 'nullable', 'string', 'max:2000'],
      'content_html' => ['sometimes', 'nullable', 'string', 'max:1000000'],
      'status' => $creating
        ? ['required', Rule::in(['draft', 'published'])]
        : ['sometimes', 'required', Rule::in(['draft', 'published'])],
      'editor_ids' => 'sometimes|array|max:100',
      'editor_ids.*' => 'string|distinct',
      'tag_names' => 'sometimes|array|max:20',
      'tag_names.*' => 'string|max:50',
    ]);
  }

  private function scopeForUser(
    User $user,
    string $organizationId,
    ?string $projectId,
    DocumentAccessService $access,
  ): array {
    $organization = Organization::findOrFail($organizationId);
    abort_unless(
      $organization->roleFor($user) !== null,
      403,
      'Organization membership is required.',
    );

    $project = null;
    if ($projectId) {
      $project = $organization->projects()->findOrFail($projectId);
    }

    abort_unless($access->canAccessScope($user, $organization, $project), 403);

    return [$organization, $project];
  }

  private function assertEditors(
    DocumentAccessService $access,
    Organization $organization,
    ?Project $project,
    array $editorIds,
  ): void {
    if (!$editorIds) {
      return;
    }

    $allowed = collect($access->allowedEditorIds($organization, $project));
    abort_unless(
      $allowed->intersect($editorIds)->count() === count(array_unique($editorIds)),
      422,
      'Every selected editor must have access to the document scope.',
    );
  }

  private function listRow(
    Document $document,
    User $user,
    array $editorIds,
    array $favoriteIds,
  ): array {
    $canEdit = $document->author_id === $user->id || in_array($document->id, $editorIds, true);

    return [
      ...$document->only([
        'id',
        'organization_id',
        'project_id',
        'title',
        'caption',
        'status',
        'published_at',
        'archived_at',
        'created_at',
        'updated_at',
      ]),
      'organization' => $document->organization,
      'project' => $document->project,
      'author' => $document->author,
      'tags' => $document->tags,
      'comments_count' => $document->comments_count,
      'can_edit' => $canEdit,
      'can_delete' =>
        $document->author_id === $user->id || ($document->organization?->canAdmin($user) ?? false),
      'is_favorite' => in_array($document->id, $favoriteIds, true),
    ];
  }

  private function detail(Document $document, User $user, DocumentAccessService $access): array
  {
    $document->loadMissing([
      'organization:id,name',
      'project:id,organization_id,name,key',
      'author:id,name,email,avatar_path',
      'apiCredential:id,name',
      'editors:id,name,email,avatar_path',
      'tags:id,name',
      'comments' => fn($query) => $query
        ->with('user:id,name,email,avatar_path')
        ->orderBy('created_at'),
    ]);

    $organization = $document->organization ?: Organization::find($document->organization_id);
    abort_unless($organization, 404, 'Document organization is unavailable.');

    $document->setRelation('organization', $organization);

    return [
      ...$document->toArray(),
      'can_edit' => $access->canEdit($user, $document),
      'can_delete' => $access->canDelete($user, $document),
      'is_author' => $document->author_id === $user->id,
      'is_favorite' => $document->favoritedBy()->whereKey($user->id)->exists(),
      'viewer_id' => $user->id,
      'mention_users' => User::query()
        ->whereIn('id', $access->allowedEditorIds($document->organization, $document->project))
        ->orderBy('name')
        ->get(['id', 'name', 'email', 'avatar_path']),
      'can_moderate_comments' => $document->organization?->canAdmin($user) ?? false,
    ];
  }

  private function pagination($page): array
  {
    return [
      'current_page' => $page->currentPage(),
      'per_page' => $page->perPage(),
      'total' => $page->total(),
      'last_page' => $page->lastPage(),
      'from' => $page->firstItem(),
      'to' => $page->lastItem(),
    ];
  }
}
