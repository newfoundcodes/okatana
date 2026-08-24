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

use App\Models\Document;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DocumentAccessService
{
  public function visibleQuery(User $user): Builder
  {
    $organizationIds = $user->organizations()->pluck('organizations.id');
    $adminOrganizationIds = $user
      ->organizations()
      ->wherePivotIn('role', ['owner', 'admin'])
      ->pluck('organizations.id');
    $explicitProjectIds = $user->projects()->pluck('projects.id');

    return Document::query()
      ->whereIn('organization_id', $organizationIds)
      ->where(function (Builder $activeScope): void {
        $activeScope->whereNull('project_id')->orWhereHas('project');
      })
      ->where(function (Builder $scope) use ($adminOrganizationIds, $explicitProjectIds): void {
        $scope
          ->whereNull('project_id')
          ->orWhereIn('organization_id', $adminOrganizationIds)
          ->orWhereIn('project_id', $explicitProjectIds);
      })
      ->where(function (Builder $visibility) use ($user): void {
        $visibility
          ->where(function (Builder $published): void {
            $published->where('status', 'published')->whereNull('archived_at');
          })
          ->orWhere('author_id', $user->id)
          ->orWhereHas('editors', fn(Builder $editors) => $editors->where('users.id', $user->id));
      });
  }

  public function canView(User $user, Document $document): bool
  {
    if (!$document->organization || ($document->project_id && !$document->project)) {
      return false;
    }

    if (!$this->canAccessScope($user, $document->organization, $document->project)) {
      return false;
    }

    if ($this->canEdit($user, $document)) {
      return true;
    }

    return $document->status === 'published' && $document->archived_at === null;
  }

  public function canEdit(User $user, Document $document): bool
  {
    if (!$document->organization || ($document->project_id && !$document->project)) {
      return false;
    }

    if (!$this->canAccessScope($user, $document->organization, $document->project)) {
      return false;
    }

    return $document->author_id === $user->id ||
      $document->editors()->whereKey($user->id)->exists();
  }

  public function canDelete(User $user, Document $document): bool
  {
    if (!$document->organization || ($document->project_id && !$document->project)) {
      return false;
    }

    if (!$this->canAccessScope($user, $document->organization, $document->project)) {
      return false;
    }

    return $document->author_id === $user->id || $document->organization->canAdmin($user);
  }

  public function assertView(User $user, Document $document): void
  {
    abort_unless($this->canView($user, $document), 403, 'Document access is required.');
  }

  public function assertEdit(User $user, Document $document): void
  {
    abort_unless($this->canEdit($user, $document), 403, 'Document editor permission is required.');
  }

  public function assertDelete(User $user, Document $document): void
  {
    abort_unless(
      $this->canDelete($user, $document),
      403,
      'Document author or organization admin permission is required.',
    );
  }

  public function canAccessScope(User $user, Organization $organization, ?Project $project): bool
  {
    $role = $organization->roleFor($user);
    if ($role === null) {
      return false;
    }

    if (!$project) {
      return true;
    }

    if (in_array($role, ['owner', 'admin'], true)) {
      return true;
    }

    return $project->members()->whereKey($user->id)->exists();
  }

  public function allowedEditorIds(Organization $organization, ?Project $project): array
  {
    if (!$project) {
      return $organization->members()->pluck('users.id')->all();
    }

    $admins = $organization
      ->members()
      ->wherePivotIn('role', ['owner', 'admin'])
      ->pluck('users.id');

    return $project->members()->pluck('users.id')->merge($admins)->unique()->values()->all();
  }
}
