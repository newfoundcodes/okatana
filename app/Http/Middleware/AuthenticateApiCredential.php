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

namespace App\Http\Middleware;

use App\Models\ApiCredential;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiCredential
{
  public function handle(Request $request, Closure $next): Response
  {
    $token = $request->bearerToken();
    if (!$token || !str_contains($token, '.')) {
      return response()->json(
        [
          'message' => 'A valid Okatana API credential is required.',
        ],
        401,
      );
    }

    [$public, $secret] = explode('.', $token, 2);
    $credential = ApiCredential::where('public_id', $public)->first();

    if (
      !$credential ||
      !$credential->active() ||
      !hash_equals($credential->secret_hash, hash('sha256', $secret))
    ) {
      return response()->json(
        [
          'message' => 'Invalid or revoked API credential.',
        ],
        401,
      );
    }

    $credential
      ->forceFill([
        'last_used_at' => now(),
      ])
      ->saveQuietly();
    $request->attributes->set('api_credential', $credential);

    return $next($request);
  }
}
