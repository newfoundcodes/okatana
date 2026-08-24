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
use App\Services\AccountSecurityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
  public function register(Request $request, AccountSecurityService $security)
  {
    $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
    $data = $request->validate([
      'name' => 'required|string|max:120',
      'email' => 'required|email:rfc|max:255|unique:users,email',
      'password' => ['required', 'confirmed', Password::min(14)->mixedCase()->numbers()->symbols()],
    ]);

    $user = User::create([
      'name' => trim($data['name']),
      'email' => strtolower($data['email']),
      'password' => $data['password'],
      'security_setup_required_at' => now(),
    ]);

    Auth::login($user, false);

    $request->session()->regenerate();
    $security->issueEmailCode($user, true);

    return response()->json(
      [
        'status' => 'email_verification_required',
        'user' => $user->fresh(),
        'security' => $security->state($user->fresh()),
      ],
      201,
    );
  }

  public function login(Request $request, AccountSecurityService $security)
  {
    $request->merge([
      'email' => strtolower(trim((string) $request->input('email'))),
    ]);
    $data = $request->validate([
      'email' => 'required|email',
      'password' => 'required|string',
    ]);

    $user = User::where('email', strtolower($data['email']))->first();
    if (!$user || !$user->password || !Hash::check($data['password'], $user->password)) {
      return response()->json(['message' => 'Invalid credentials.'], 422);
    }

    $state = $security->state($user);
    if ($state['email_required'] && !$state['email_verified']) {
      Auth::login($user, false);

      $request->session()->regenerate();
      $security->issueEmailCode($user);

      return [
        'status' => 'email_verification_required',
        'user' => $user,
        'security' => $security->state($user),
      ];
    }

    if ($state['two_factor_required'] && !$state['two_factor_confirmed']) {
      Auth::login($user, false);
      $request->session()->regenerate();

      return [
        'status' => 'two_factor_setup_required',
        'user' => $user,
        'security' => $security->state($user),
      ];
    }

    if (!$state['two_factor_required']) {
      Auth::login($user, false);
      $request->session()->regenerate();

      return [
        'status' => 'authenticated',
        'user' => $user,
        'security' => $security->state($user),
      ];
    }

    Auth::logout();

    $request->session()->regenerate();
    $request->session()->put('two_factor_login_user_id', $user->id);
    $request->session()->put('two_factor_login_remember', false);

    return [
      'status' => 'two_factor_challenge_required',
      'account' => [
        'email' => $user->email,
        'name' => $user->name,
      ],
    ];
  }

  public function pendingTwoFactorChallenge(Request $request, AccountSecurityService $security)
  {
    if (!$security->twoFactorRequired()) {
      $request->session()->forget(['two_factor_login_user_id', 'two_factor_login_remember']);

      return [
        'pending' => false,
        'account' => null,
        'two_factor_required' => false,
      ];
    }

    $userId = $request->session()->get('two_factor_login_user_id');
    $user = $userId ? User::find($userId) : null;

    return [
      'pending' => (bool) $user,
      'account' => $user
        ? [
          'email' => $user->email,
          'name' => $user->name,
        ]
        : null,
      'two_factor_required' => true,
    ];
  }

  public function twoFactorChallenge(Request $request, AccountSecurityService $security)
  {
    abort_unless(
      $security->twoFactorRequired(),
      409,
      'Two-factor authentication is not required by this deployment.',
    );

    $data = $request->validate([
      'code' => ['required', 'string', 'regex:/^\s*\d{6}\s*$/'],
    ]);
    $userId = $request->session()->get('two_factor_login_user_id');
    $user = $userId ? User::find($userId) : null;

    abort_unless($user, 419, 'The two-factor login challenge has expired. Start the login again.');

    if (!$security->verifyLoginCode($user, $data['code'])) {
      return response()->json(
        [
          'message' => 'The authenticator code is invalid or has already been used.',
        ],
        422,
      );
    }

    $remember = (bool) $request->session()->pull('two_factor_login_remember', false);
    $request->session()->forget('two_factor_login_user_id');

    Auth::login($user, $remember);
    $request->session()->regenerate();

    return [
      'status' => 'authenticated',
      'user' => $user->fresh(),
      'security' => $security->state($user->fresh()),
    ];
  }

  public function verifyEmailCode(Request $request, AccountSecurityService $security)
  {
    $data = $request->validate([
      'code' => ['required', 'string', 'max:16'],
    ]);
    $user = $request->user();

    if (!$security->verifyEmailCode($user, $data['code'])) {
      return response()->json(
        [
          'message' =>
            'The confirmation code is invalid, expired, or has reached its attempt limit. Request a new code and try again.',
        ],
        422,
      );
    }

    $request->session()->regenerate();

    return [
      'user' => $user->fresh(),
      'security' => $security->state($user->fresh()),
    ];
  }

  public function resendEmailCode(Request $request, AccountSecurityService $security)
  {
    abort_if($request->user()->email_verified_at, 409, 'The email address is already confirmed.');

    $sent = $security->issueEmailCode($request->user());

    return response()->json(
      [
        'sent' => $sent,
        'message' => $sent
          ? 'A new confirmation code was sent.'
          : 'A confirmation code was sent recently. Use that code or try again after the resend interval.',
      ],
      202,
    );
  }

  public function twoFactorSetup(Request $request, AccountSecurityService $security)
  {
    abort_unless(
      $security->twoFactorRequired(),
      409,
      'Two-factor authentication is not required by this deployment.',
    );

    return ['setup' => $security->setup($request->user())];
  }

  public function confirmTwoFactor(Request $request, AccountSecurityService $security)
  {
    abort_unless(
      $security->twoFactorRequired(),
      409,
      'Two-factor authentication is not required by this deployment.',
    );

    $data = $request->validate([
      'code' => ['required', 'string', 'regex:/^\s*\d{6}\s*$/'],
    ]);
    $user = $request->user();

    if (!$security->confirmSetup($user, $data['code'])) {
      return response()->json(
        [
          'message' =>
            'The authenticator code is invalid. Check the device time and enter the current six-digit code.',
        ],
        422,
      );
    }

    $request->session()->regenerate();

    return [
      'status' => 'authenticated',
      'user' => $user->fresh(),
      'security' => $security->state($user->fresh()),
    ];
  }

  public function logout(Request $request)
  {
    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return response()->noContent();
  }

  public function me(Request $request, AccountSecurityService $security)
  {
    $user = $request->user();

    return [
      'user' => $user,
      'security' => $security->state($user),
    ];
  }

  public function config()
  {
    return [
      'oauth' => collect(config('okatana.oauth', []))
        ->filter(fn($value) => $value['enabled'] ?? false)
        ->keys()
        ->values(),
      'sso' => array_keys(config('okatana.sso_drivers', [])),
      'two_factor_required' => (bool) config('okatana.security.two_factor_required', true),
    ];
  }
}
