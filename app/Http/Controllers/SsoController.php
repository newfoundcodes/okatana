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

use App\Services\AccountSecurityService;
use App\Services\SsoManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SsoController extends Controller
{
  public function redirect(Request $request, string $driver, SsoManager $manager)
  {
    return $manager->driver($driver)->redirect($request);
  }

  public function callback(
    Request $request,
    string $driver,
    SsoManager $manager,
    AccountSecurityService $security,
  ) {
    $user = $manager->driver($driver)->userFromCallback($request);

    if (
      $security->twoFactorRequired() &&
      !$user->two_factor_confirmed_at &&
      !$user->security_setup_required_at
    ) {
      $user
        ->forceFill([
          'security_setup_required_at' => now(),
        ])
        ->save();
    }

    if ($security->twoFactorRequired() && $user->two_factor_confirmed_at) {
      Auth::logout();

      $request->session()->regenerate();
      $request->session()->put('two_factor_login_user_id', $user->id);
      $request->session()->put('two_factor_login_remember', false);

      return redirect('/?two_factor=required');
    }

    Auth::login($user, false);
    $request->session()->regenerate();

    return redirect('/');
  }
}
