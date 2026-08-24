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

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EditorImageUploadTest extends TestCase {
  use RefreshDatabase;

  public function test_authenticated_user_can_upload_and_render_an_editor_image_without_a_public_symlink(): void {
    Storage::fake('public');

    $user = User::create([
      'name' => 'Editor',
      'email' => 'editor@example.test',
      'password' => 'Password!12345',
    ]);

    $png = base64_decode(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
      true,
    );
    $file = UploadedFile::fake()->createWithContent('pixel.png', $png);

    $response = $this->actingAs($user)->post(
      '/app-api/editor/images',
      ['image' => $file],
      ['Accept' => 'application/json'],
    );

    $response->assertCreated()->assertJsonStructure(['url', 'path']);
    self::assertStringStartsWith(
      '/app-api/editor/images/' . $user->id . '/',
      $response->json('url'),
    );

    Storage::disk('public')->assertExists($response->json('path'));

    $this->actingAs($user)
      ->get($response->json('url'))
      ->assertOk()
      ->assertHeader('X-Content-Type-Options', 'nosniff');
  }
}
