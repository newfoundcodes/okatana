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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OAuthController extends Controller
{
  public function redirect(Request $request, string $provider)
  {
    $cfg = config("okatana.oauth.{$provider}");
    abort_unless($cfg && ($cfg['enabled'] ?? false), 404, 'OAuth provider is not enabled.');

    $state = Str::random(48);
    $verifier = Str::random(96);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $request->session()->put("oauth.{$provider}", [
      'state' => $state,
      'verifier' => $verifier,
    ]);

    $query = http_build_query([
      'client_id' => $cfg['client_id'],
      'redirect_uri' => url("/auth/oauth/{$provider}/callback"),
      'response_type' => 'code',
      'scope' => $cfg['scopes'],
      'state' => $state,
      'code_challenge' => $challenge,
      'code_challenge_method' => 'S256',
    ]);

    return redirect()->away($cfg['authorization_url'] . '?' . $query);
  }

  public function callback(Request $request, string $provider, AccountSecurityService $security)
  {
    $cfg = config("okatana.oauth.{$provider}");
    abort_unless($cfg && ($cfg['enabled'] ?? false), 404);

    $session = $request->session()->pull("oauth.{$provider}");
    abort_unless(
      $session && is_string($request->state) && hash_equals($session['state'], $request->state),
      419,
      'OAuth state validation failed.',
    );

    $token = Http::asForm()
      ->timeout(10)
      ->post($cfg['token_url'], [
        'grant_type' => 'authorization_code',
        'code' => $request->string('code')->toString(),
        'redirect_uri' => url("/auth/oauth/{$provider}/callback"),
        'client_id' => $cfg['client_id'],
        'client_secret' => $cfg['client_secret'],
        'code_verifier' => $session['verifier'],
      ])
      ->throw()
      ->json();

    $profile = Http::withToken($token['access_token'])
      ->timeout(10)
      ->get($cfg['userinfo_url'])
      ->throw()
      ->json();

    $email = strtolower((string) ($profile[$cfg['email_field']] ?? ''));
    abort_unless(
      filter_var($email, FILTER_VALIDATE_EMAIL),
      422,
      'OAuth provider did not return a usable email.',
    );

    $verifiedField = $cfg['email_verified_field'] ?? 'email_verified';
    if ($cfg['require_verified_email'] ?? true) {
      abort_unless(
        array_key_exists($verifiedField, $profile) &&
          filter_var($profile[$verifiedField], FILTER_VALIDATE_BOOLEAN),
        422,
        'OAuth provider did not confirm a verified email.',
      );
    }

    $user = User::firstOrCreate(
      ['email' => $email],
      [
        'name' => (string) ($profile[$cfg['name_field']] ?? $email),
        'email_verified_at' => now(),
        'security_setup_required_at' => now(),
      ],
    );

    if (!$user->email_verified_at) {
      $user->forceFill(['email_verified_at' => now()])->save();
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
