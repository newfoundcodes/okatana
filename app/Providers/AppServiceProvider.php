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

namespace App\Providers;

use App\Services\AccountSecurityService;
use App\Services\ActivityRecorder;
use App\Services\AuthorizationService;
use App\Services\HtmlSanitizer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
  public function register(): void
  {
    $this->app->singleton(AuthorizationService::class);
    $this->app->singleton(HtmlSanitizer::class);
    $this->app->singleton(ActivityRecorder::class);
    $this->app->singleton(AccountSecurityService::class);
  }

  public function boot(): void
  {
    RateLimiter::for('api', function (Request $request) {
      $cred = $request->attributes->get('api_credential');

      return Limit::perMinute((int) config('okatana.api_rate_limit', 120))->by(
        $cred?->id ?: $request->ip(),
      );
    });

    RateLimiter::for(
      'auth',
      fn(Request $request) => Limit::perMinute(10)->by(
        strtolower((string) $request->input('email')) . '|' . $request->ip(),
      ),
    );

    RateLimiter::for(
      'security',
      fn(Request $request) => Limit::perMinute(10)->by(
        ($request->user()?->id ?: $request->session()->get('two_factor_login_user_id', 'guest')) .
          '|' .
          $request->ip(),
      ),
    );
  }
}
