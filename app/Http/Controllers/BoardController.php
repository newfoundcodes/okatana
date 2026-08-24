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

use App\Models\Board;
use App\Models\Project;
use App\Models\TicketComment;
use App\Services\ActivityRecorder;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BoardController extends Controller
{
  public function store(
    Request $request,
    Project $project,
    AuthorizationService $auth,
    ActivityRecorder $activity,
  ) {
    $project->load('organization');
    $auth->orgAdmin($request->user(), $project->organization);

    $data = $request->validate([
      'name' => 'required|string|max:120',
      'color' => 'nullable|string|max:32',
      'wip_limit' => 'nullable|integer|min:1|max:100000',
      'is_done' => 'nullable|boolean',
    ]);

    $slug = Str::slug($data['name'], '_');
    $base = $slug;

    $i = 2;
    while ($project->boards()->withTrashed()->where('slug', $slug)->exists()) {
      $slug = $base . '_' . $i++;
    }

    $board = Board::create([
      'project_id' => $project->id,
      'name' => $data['name'],
      'slug' => $slug,
      'color' => $data['color'] ?? null,
      'wip_limit' => $data['wip_limit'] ?? null,
      'is_done' => $data['is_done'] ?? false,
      'position' => (int) $project->boards()->max('position') + 1000,
    ]);

    $activity->record(
      'board.created',
      $project->organization,
      $project,
      $board,
      [],
      [],
      $request->user(),
      $request,
    );

    return response()->json(
      [
        'board' => $board,
      ],
      201,
    );
  }

  public function update(
    Request $request,
    Board $board,
    AuthorizationService $auth,
    ActivityRecorder $activity,
  ) {
    $board->load('project.organization');
    $auth->orgAdmin($request->user(), $board->project->organization);

    $data = $request->validate([
      'name' => 'sometimes|required|string|max:120',
      'color' => 'nullable|string|max:32',
      'wip_limit' => 'nullable|integer|min:1|max:100000',
      'is_done' => 'nullable|boolean',
      'is_hidden' => 'nullable|boolean',
    ]);

    $before = $board->only(array_keys($data));
    $board->update($data);

    $activity->record(
      'board.updated',
      $board->project->organization,
      $board->project,
      $board,
      [
        'before' => $before,
        'after' => $board->only(array_keys($data)),
      ],
      [],
      $request->user(),
      $request,
    );

    return ['board' => $board];
  }

  public function reorder(
    Request $request,
    Project $project,
    AuthorizationService $auth,
    ActivityRecorder $activity,
  ) {
    $project->load('organization');
    $auth->orgAdmin($request->user(), $project->organization);

    $data = $request->validate([
      'board_ids' => 'required|array',
      'board_ids.*' => 'string',
    ]);

    $ids = $project->boards()->whereIn('id', $data['board_ids'])->pluck('id')->all();

    abort_unless(count($ids) === count(array_unique($data['board_ids'])), 422);
    DB::transaction(
      fn() => collect($data['board_ids'])->each(
        fn($id, $i) => Board::whereKey($id)->update(['position' => ($i + 1) * 1000]),
      ),
    );

    $activity->record(
      'board.reordered',
      $project->organization,
      $project,
      $project,
      [],
      [
        'board_ids' => $data['board_ids'],
      ],
      $request->user(),
      $request,
    );

    return ['boards' => $project->boards()->get()];
  }

  public function destroy(
    Request $request,
    Board $board,
    AuthorizationService $auth,
    ActivityRecorder $activity,
  ) {
    $board->load('project.organization');
    $auth->orgAdmin($request->user(), $board->project->organization);

    $data = $request->validate([
      'move_to_board_id' => 'nullable|string',
    ]);

    $target = null;
    if ($board->tickets()->exists()) {
      $target = $board->project
        ->boards()
        ->whereKey($data['move_to_board_id'] ?? '')
        ->first();

      abort_unless(
        $target && $target->id !== $board->id,
        422,
        'Select another board for existing tickets.',
      );

      $activeMoving = $board->tickets()->whereNull('archived_at')->count();

      if ($target->wip_limit) {
        $targetActive = $target->tickets()->whereNull('archived_at')->count();

        abort_if(
          $targetActive + $activeMoving > $target->wip_limit,
          422,
          'The destination board WIP limit would be exceeded.',
        );
      }
    }

    DB::transaction(function () use ($board, $target, $request): void {
      if ($target) {
        foreach ($board->tickets()->get() as $ticket) {
          $ticket->update([
            'board_id' => $target->id,
            'completed_at' => $target->is_done ? now() : null,
            'updated_by' => $request->user()->id,
          ]);

          TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'kind' => 'movement',
            'body_html' => '<p>' . e($board->name) . ' → ' . e($target->name) . '</p>',
            'author_snapshot' => [
              'name' => $request->user()->name,
              'email' => $request->user()->email,
            ],
          ]);
        }
      }

      $board->delete();
    });

    $activity->record(
      'board.deleted',
      $board->project->organization,
      $board->project,
      $board,
      [],
      ['soft_delete' => true],
      $request->user(),
      $request,
    );

    return response()->noContent();
  }
}
