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

class ProfileAvatarTest extends TestCase {
  use RefreshDatabase;

  public function test_user_can_set_read_replace_and_remove_profile_picture(): void {
    Storage::fake('public');

    $user = User::create([
      'name' => 'Avatar User',
      'email' => 'avatar@example.test',
      'password' => 'Password!12345',
    ]);

    $png = base64_decode(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
      true,
    );

    $upload = UploadedFile::fake()->createWithContent('avatar.png', $png);
    $response = $this->actingAs($user)->post('/app-api/user/avatar', ['avatar' => $upload]);
    $response->assertOk();

    $avatarUrl = $response->json('user.avatar_url');
    self::assertNotNull($avatarUrl);

    $this->assertDatabaseMissing('users', [
      'id' => $user->id,
      'avatar_path' => null
    ]);

    $this->actingAs($user)
      ->get($avatarUrl)
      ->assertOk()
      ->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->actingAs($user)
      ->deleteJson('/app-api/user/avatar')
      ->assertOk()
      ->assertJsonPath('user.avatar_url', null);

    $this->assertDatabaseHas('users', [
      'id' => $user->id,
      'avatar_path' => null
    ]);
  }
}
