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

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;

class RevisionController extends Controller
{
  public function ticket(Request $request, Ticket $ticket, AuthorizationService $auth): array
  {
    $ticket->load('project.organization');
    $auth->projectMember($request->user(), $ticket->project);

    return [
      'type' => 'ticket',
      'revisions' => $ticket
        ->revisions()
        ->with('user:id,name,email,avatar_path')
        ->reorder('version', 'desc')
        ->get(),
    ];
  }

  public function comment(
    Request $request,
    TicketComment $comment,
    AuthorizationService $auth,
  ): array {
    $comment->load('ticket.project.organization');
    $auth->projectMember($request->user(), $comment->ticket->project);
    abort_if($comment->kind !== 'comment', 404);

    return [
      'type' => 'comment',
      'revisions' => $comment
        ->revisions()
        ->with('user:id,name,email,avatar_path')
        ->reorder('version', 'desc')
        ->get(),
    ];
  }
}
