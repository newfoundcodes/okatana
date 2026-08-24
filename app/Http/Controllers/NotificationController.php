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

use App\Models\User;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
  public function index(Request $request): array
  {
    $data = $request->validate([
      'q' => 'nullable|string|max:200',
      'page' => 'nullable|integer|min:1',
      'per_page' => 'nullable|integer|in:10,20,50,100',
    ]);

    $user = $request->user();
    $query = trim((string) ($data['q'] ?? ''));
    $perPage = (int) ($data['per_page'] ?? 10);

    $notifications = $user
      ->notifications()
      ->when($query !== '', function ($builder) use ($query): void {
        $needle = '%' . strtolower($query) . '%';
        $builder->where(function ($search) use ($needle): void {
          $search
            ->whereRaw('LOWER(data) LIKE ?', [$needle])
            ->orWhereRaw('LOWER(type) LIKE ?', [$needle]);
        });
      })
      ->latest()
      ->paginate($perPage);

    $actorIds = $notifications
      ->getCollection()
      ->map(fn($notification) => data_get($notification->data, 'actor.id'))
      ->filter()
      ->unique()
      ->values();

    $actors = User::whereIn('id', $actorIds)
      ->get(['id', 'name', 'avatar_path', 'updated_at'])
      ->keyBy('id');

    $notifications->getCollection()->each(function ($notification) use ($actors): void {
      $data = $notification->data;
      $actorId = data_get($data, 'actor.id');
      $actor = $actorId ? $actors->get($actorId) : null;
      if ($actor) {
        $data['actor'] = array_merge($data['actor'] ?? [], [
          'id' => $actor->id,
          'name' => $actor->name,
          'avatar_url' => $actor->avatar_url,
        ]);
        $notification->setAttribute('data', $data);
      }
    });

    return [
      'notifications' => $notifications->items(),
      'pagination' => [
        'current_page' => $notifications->currentPage(),
        'per_page' => $notifications->perPage(),
        'total' => $notifications->total(),
        'last_page' => $notifications->lastPage(),
        'from' => $notifications->firstItem(),
        'to' => $notifications->lastItem(),
      ],
      'unread_count' => $user->unreadNotifications()->count(),
    ];
  }

  public function markRead(Request $request, string $notification): array
  {
    $row = $request->user()->notifications()->whereKey($notification)->firstOrFail();

    $row->markAsRead();
    return [
      'notification' => $row->fresh(),
      'unread_count' => $request->user()->unreadNotifications()->count(),
    ];
  }

  public function markAllRead(Request $request): array
  {
    $request->user()->unreadNotifications->markAsRead();
    return ['unread_count' => 0];
  }
}
