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

use App\Http\Controllers\ExternalApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
  ->middleware(['api.credential', 'throttle:api'])
  ->group(function (): void {
    Route::get('/organizations/{organization}', [ExternalApiController::class, 'organization']);

    Route::get('/organizations/{organization}/projects', [
      ExternalApiController::class,
      'projects',
    ]);

    Route::post('/organizations/{organization}/notifications', [
      ExternalApiController::class,
      'notifyUsers',
    ]);

    Route::get('/organizations/{organization}/documents', [
      ExternalApiController::class,
      'documents',
    ]);

    Route::post('/organizations/{organization}/documents', [
      ExternalApiController::class,
      'createDocument',
    ]);

    Route::get('/documents/{document}', [ExternalApiController::class, 'document']);
    Route::patch('/documents/{document}', [ExternalApiController::class, 'updateDocument']);
    Route::delete('/documents/{document}', [ExternalApiController::class, 'deleteDocument']);

    Route::post('/documents/{document}/comments', [
      ExternalApiController::class,
      'documentComment',
    ]);

    Route::post('/organizations/{organization}/projects', [
      ExternalApiController::class,
      'createProject',
    ]);

    Route::get('/projects/{project}', [ExternalApiController::class, 'project']);
    Route::patch('/projects/{project}', [ExternalApiController::class, 'updateProject']);
    Route::delete('/projects/{project}', [ExternalApiController::class, 'deleteProject']);
    Route::get('/projects/{project}/members', [ExternalApiController::class, 'members']);
    Route::get('/projects/{project}/labels', [ExternalApiController::class, 'labels']);
    Route::get('/projects/{project}/tags', [ExternalApiController::class, 'tags']);
    Route::get('/projects/{project}/boards', [ExternalApiController::class, 'boards']);
    Route::post('/projects/{project}/boards', [ExternalApiController::class, 'createBoard']);

    Route::put('/projects/{project}/boards/reorder', [
      ExternalApiController::class,
      'reorderBoards',
    ]);

    Route::patch('/boards/{board}', [ExternalApiController::class, 'updateBoard']);
    Route::delete('/boards/{board}', [ExternalApiController::class, 'deleteBoard']);
    Route::get('/projects/{project}/tickets', [ExternalApiController::class, 'tickets']);
    Route::get('/projects/{project}/analytics', [ExternalApiController::class, 'analytics']);
    Route::post('/projects/{project}/tickets', [ExternalApiController::class, 'createTicket']);

    Route::put('/projects/{project}/tickets/reorder', [
      ExternalApiController::class,
      'reorderTickets',
    ]);

    Route::get('/tickets/{ticket}', [ExternalApiController::class, 'ticket']);
    Route::patch('/tickets/{ticket}', [ExternalApiController::class, 'updateTicket']);
    Route::delete('/tickets/{ticket}', [ExternalApiController::class, 'deleteTicket']);
    Route::post('/tickets/{ticket}/move', [ExternalApiController::class, 'moveTicket']);
    Route::post('/tickets/{ticket}/comments', [ExternalApiController::class, 'comment']);
  });
