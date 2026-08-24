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

use App\Services\AccountSecurityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountSecurity
{
  public function __construct(private readonly AccountSecurityService $security) {}

  public function handle(Request $request, Closure $next): Response
  {
    $user = $request->user();
    $state = $user ? $this->security->state($user) : null;

    if ($state && !$state['complete']) {
      $message =
        $state['email_required'] && !$state['email_verified']
          ? 'Confirm your email address before accessing the workspace.'
          : 'Set up an authenticator app before accessing the workspace.';

      return response()->json(
        [
          'message' => $message,
          'code' => 'account_security_setup_required',
          'security' => $state,
        ],
        403,
      );
    }

    return $next($request);
  }
}
