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

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EditorAssetController extends Controller
{
  public function storeImage(Request $request)
  {
    $data = $request->validate([
      'image' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
    ]);

    $file = $data['image'];
    $extension = strtolower(
      $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin',
    );
    $filename = Str::uuid() . '.' . $extension;

    $owner = $request->user()->id;
    $path = $file->storeAs('editor-images/' . $owner, $filename, 'public');

    abort_unless($path, 500, 'The image could not be stored.');

    return response()->json(
      [
        'url' => '/app-api/editor/images/' . $owner . '/' . $filename,
        'path' => $path,
      ],
      201,
    );
  }

  public function showImage(Request $request, string $owner, string $filename): StreamedResponse
  {
    abort_unless((bool) preg_match('/^[A-Za-z0-9_-]+$/', $owner), 404);
    abort_unless((bool) preg_match('/^[A-Fa-f0-9-]+\.(?:jpe?g|png|gif|webp)$/i', $filename), 404);

    $path = 'editor-images/' . $owner . '/' . $filename;
    $disk = Storage::disk('public');
    abort_unless($disk->exists($path), 404);

    return $disk->response($path, $filename, [
      'Cache-Control' => 'private, max-age=86400',
      'X-Content-Type-Options' => 'nosniff',
    ]);
  }
}
