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

use App\Models\Project;
use App\Services\AuthorizationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
  public function project(Request $request, Project $project, AuthorizationService $auth)
  {
    $project->load('organization');
    $auth->projectMember($request->user(), $project);

    $memberId = trim((string) $request->query('member_id'));
    $priority = trim((string) $request->query('priority'));
    $boardId = trim((string) $request->query('board_id'));
    $labelId = trim((string) $request->query('label_id'));

    $tagIds = array_values(
      array_unique(
        array_filter(array_map('trim', explode(',', (string) $request->query('tag_ids')))),
      ),
    );

    $overdueOnly = $request->boolean('overdue');
    $archivedOnly = $request->boolean('archived');

    if ($memberId !== '') {
      $directMember = DB::table('project_members')
        ->where('project_id', $project->id)
        ->where('user_id', $memberId)
        ->exists();

      $organizationAdmin = DB::table('organization_members')
        ->where('organization_id', $project->organization_id)
        ->where('user_id', $memberId)
        ->whereIn('role', ['owner', 'admin'])
        ->exists();

      abort_unless(
        $directMember || $organizationAdmin,
        422,
        'Select a member who has access to this project.',
      );
    }

    if ($priority !== '') {
      abort_unless(
        in_array($priority, ['lowest', 'low', 'normal', 'high', 'highest', 'critical'], true),
        422,
        'Select a valid priority.',
      );
    }

    if ($boardId !== '') {
      abort_unless(
        $project->boards()->whereKey($boardId)->exists(),
        422,
        'Select a phase that belongs to this project.',
      );
    }

    if ($labelId !== '') {
      abort_unless(
        $project->labels()->whereKey($labelId)->exists(),
        422,
        'Select a label that belongs to this project.',
      );
    }

    if ($tagIds) {
      abort_unless(
        $project->tags()->whereIn('id', $tagIds)->count() === count($tagIds),
        422,
        'All selected tags must belong to this project.',
      );
    }

    $applyFilters = function ($query) use (
      $memberId,
      $priority,
      $boardId,
      $labelId,
      $tagIds,
      $overdueOnly,
      $archivedOnly,
    ) {
      if ($archivedOnly) {
        $query->whereNotNull('archived_at');
      } else {
        $query->whereNull('archived_at');
      }

      if ($memberId !== '') {
        $query->whereHas(
          'assignees',
          fn(Builder $assignees) => $assignees->where('users.id', $memberId),
        );
      }

      if ($priority !== '') {
        $query->where('priority', $priority);
      }

      if ($boardId !== '') {
        $query->where('board_id', $boardId);
      }

      if ($labelId !== '') {
        $query->whereHas('labels', fn(Builder $labels) => $labels->where('labels.id', $labelId));
      }

      foreach ($tagIds as $tagId) {
        $query->whereHas('tags', fn(Builder $tags) => $tags->where('tags.id', $tagId));
      }

      if ($overdueOnly) {
        $query
          ->whereNotNull('due_at')
          ->where('due_at', '<', now())
          ->whereHas('board', fn(Builder $board) => $board->where('is_done', false));
      }

      return $query;
    };

    $filteredTickets = function () use ($project, $applyFilters) {
      return $applyFilters($project->tickets());
    };

    $byBoard = $project
      ->boards()
      ->withCount([
        'tickets' => function (Builder $query) use ($applyFilters): void {
          $applyFilters($query);
        },
      ])
      ->get()
      ->map(
        fn($board) => [
          'id' => $board->id,
          'name' => $board->name,
          'color' => $board->color,
          'is_done' => $board->is_done,
          'count' => $board->tickets_count,
        ],
      );

    $byPriority = $filteredTickets()
      ->select('priority', DB::raw('COUNT(*) as aggregate'))
      ->groupBy('priority')
      ->pluck('aggregate', 'priority');
    $total = $filteredTickets()->count();
    $done = $filteredTickets()
      ->whereHas('board', fn(Builder $query) => $query->where('is_done', true))
      ->count();
    $overdue = $filteredTickets()
      ->whereNotNull('due_at')
      ->where('due_at', '<', now())
      ->whereHas('board', fn(Builder $query) => $query->where('is_done', false))
      ->count();

    $createdDaily = $applyFilters(
      $project->tickets()->where('created_at', '>=', now()->subDays(29)->startOfDay()),
    )
      ->get(['created_at'])
      ->groupBy(fn($ticket) => $ticket->created_at->format('Y-m-d'))
      ->map->count();

    $completedDaily = $applyFilters(
      $project
        ->tickets()
        ->whereNotNull('completed_at')
        ->where('completed_at', '>=', now()->subDays(29)->startOfDay()),
    )
      ->get(['completed_at'])
      ->groupBy(fn($ticket) => $ticket->completed_at->format('Y-m-d'))
      ->map->count();

    $series = collect(range(29, 0))->map(function ($days) use ($createdDaily, $completedDaily) {
      $date = now()->subDays($days)->format('Y-m-d');

      return [
        'date' => $date,
        'created' => $createdDaily[$date] ?? 0,
        'completed' => $completedDaily[$date] ?? 0,
      ];
    });

    return [
      'member_id' => $memberId !== '' ? $memberId : null,
      'filters' => [
        'member_id' => $memberId !== '' ? $memberId : null,
        'priority' => $priority !== '' ? $priority : null,
        'board_id' => $boardId !== '' ? $boardId : null,
        'label_id' => $labelId !== '' ? $labelId : null,
        'tag_ids' => $tagIds,
        'overdue' => $overdueOnly,
        'archived' => $archivedOnly,
      ],
      'summary' => [
        'total' => $total,
        'done' => $done,
        'open' => $total - $done,
        'overdue' => $overdue,
        'completion_percent' => $total ? round(($done / $total) * 100, 1) : 0,
      ],
      'by_board' => $byBoard,
      'by_priority' => $byPriority,
      'daily' => $series,
    ];
  }
}
