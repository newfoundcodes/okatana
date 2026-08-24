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

use App\Models\Organization;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;

class AuthorizationService
{
  public function orgAdmin(User $user, Organization $organization): void
  {
    abort_unless(
      $organization->canAdmin($user),
      403,
      'Organization owner or admin permission is required.',
    );
  }

  public function orgOwner(User $user, Organization $organization): void
  {
    abort_unless($organization->isOwner($user), 403, 'Organization owner permission is required.');
  }

  public function orgMember(User $user, Organization $organization): void
  {
    abort_unless($organization->roleFor($user) !== null, 403);
  }

  public function projectMember(User $user, Project $project): void
  {
    abort_unless($project->hasAccess($user), 403, 'Project access is required.');
  }

  public function commentOwnerOrAdmin(User $user, TicketComment $comment): void
  {
    $project = $comment->ticket->project;
    abort_unless($comment->user_id === $user->id || $project->organization->canAdmin($user), 403);
  }

  public function assertProjectBelongsToOrganization(
    Project $project,
    Organization $organization,
  ): void {
    abort_unless($project->organization_id === $organization->id, 404);
  }

  public function assertTicketBelongsToProject(Ticket $ticket, Project $project): void
  {
    abort_unless($ticket->project_id === $project->id, 404);
  }
}
