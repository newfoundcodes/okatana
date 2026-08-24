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
use App\Services\UserDataExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserController extends Controller
{
  public function show(Request $request): array
  {
    return ['user' => $request->user()->fresh()];
  }

  public function update(Request $request): array
  {
    if ($request->has('name')) {
      $request->merge([
        'name' => trim((string) $request->input('name')),
      ]);
    }

    $data = $request->validate([
      'name' => ['sometimes', 'required', 'string', 'max:120'],
      'email_notify_assigned' => ['sometimes', 'boolean'],
      'email_notify_mentions' => ['sometimes', 'boolean'],
      'notify_document_comments' => ['sometimes', 'boolean'],
    ]);

    $request->user()->update($data);

    return [
      'user' => $request->user()->fresh(),
    ];
  }

  public function updateAvatar(Request $request): array
  {
    $data = $request->validate([
      'avatar' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
    ]);

    $user = $request->user();
    $file = $data['avatar'];
    $extension = strtolower($file->extension() ?: 'jpg');
    $path = $file->storeAs('avatars/' . $user->id, Str::ulid() . '.' . $extension, 'public');
    $oldPath = $user->avatar_path;

    $user->update(['avatar_path' => $path]);
    if ($oldPath && $oldPath !== $path) {
      Storage::disk('public')->delete($oldPath);
    }

    return ['user' => $user->fresh()];
  }

  public function destroyAvatar(Request $request): array
  {
    $user = $request->user();
    if ($user->avatar_path) {
      Storage::disk('public')->delete($user->avatar_path);
    }

    $user->update(['avatar_path' => null]);
    return ['user' => $user->fresh()];
  }

  public function avatar(Request $request, User $user)
  {
    if ($request->user()->id !== $user->id) {
      $sharesOrganization = DB::table('organization_members as viewer')
        ->join(
          'organization_members as target',
          'target.organization_id',
          '=',
          'viewer.organization_id',
        )
        ->where('viewer.user_id', $request->user()->id)
        ->where('target.user_id', $user->id)
        ->exists();
      abort_unless($sharesOrganization, 404);
    }

    abort_unless($user->avatar_path, 404);

    $disk = Storage::disk('public');
    abort_unless($disk->exists($user->avatar_path), 404);

    return response($disk->get($user->avatar_path), 200, [
      'Content-Type' => $disk->mimeType($user->avatar_path) ?: 'application/octet-stream',
      'Cache-Control' => 'private, max-age=3600',
      'X-Content-Type-Options' => 'nosniff',
    ]);
  }

  public function export(Request $request, UserDataExportService $service)
  {
    $payload = $service->export($request->user());
    $filename = 'okatana-user-data-' . now()->format('Ymd-His') . '.json';

    return response()
      ->json($payload)
      ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
  }
}
