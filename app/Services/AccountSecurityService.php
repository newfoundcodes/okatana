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

namespace App\Services;

use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\EmailVerificationCodeNotification;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class AccountSecurityService
{
  public function __construct(private readonly Google2FA $google2fa = new Google2FA()) {}

  public function twoFactorRequired(): bool
  {
    return (bool) config('okatana.security.two_factor_required', true);
  }

  public function state(User $user): array
  {
    $twoFactorRequired = $this->twoFactorRequired();
    $emailVerified = $user->email_verified_at !== null;
    $twoFactorConfirmed = $user->two_factor_confirmed_at !== null;
    $emailRequired =
      $user->security_setup_required_at !== null || ($twoFactorRequired && !$emailVerified);

    return [
      'required' => $emailRequired || $twoFactorRequired,
      'email_required' => $emailRequired,
      'two_factor_required' => $twoFactorRequired,
      'email_verified' => $emailVerified,
      'two_factor_confirmed' => $twoFactorConfirmed,
      'complete' =>
        (!$emailRequired || $emailVerified) && (!$twoFactorRequired || $twoFactorConfirmed),
    ];
  }

  public function issueEmailCode(User $user, bool $force = false): bool
  {
    if ($user->email_verified_at) {
      return false;
    }

    $cooldown = max(15, (int) config('okatana.security.email_code_resend_seconds', 60));
    $current = EmailVerificationCode::where('user_id', $user->id)->first();

    if (!$force && $current?->sent_at?->isAfter(now()->subSeconds($cooldown))) {
      return false;
    }

    $raw = $this->randomEmailCode();
    $formatted = substr($raw, 0, 4) . '-' . substr($raw, 4, 4);
    $ttl = max(5, (int) config('okatana.security.email_code_ttl_minutes', 12));

    EmailVerificationCode::updateOrCreate(
      ['user_id' => $user->id],
      [
        'code_hash' => Hash::make($raw),
        'expires_at' => now()->addMinutes($ttl),
        'sent_at' => now(),
        'attempts' => 0,
      ],
    );

    $user->notify(new EmailVerificationCodeNotification($formatted, $ttl));
    return true;
  }

  public function verifyEmailCode(User $user, string $submitted): bool
  {
    if ($user->email_verified_at) {
      return true;
    }

    $record = EmailVerificationCode::where('user_id', $user->id)->first();
    if (!$record || !$record->expires_at?->isFuture()) {
      return false;
    }

    $maxAttempts = max(3, (int) config('okatana.security.email_code_max_attempts', 8));
    if ($record->attempts >= $maxAttempts) {
      return false;
    }

    $normalized = strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $submitted));
    $record->increment('attempts');

    if (
      !preg_match('/^[A-Z0-9]{8}$/', $normalized) ||
      !Hash::check($normalized, $record->code_hash)
    ) {
      return false;
    }

    $user
      ->forceFill([
        'email_verified_at' => now(),
      ])
      ->save();
    $record->delete();

    return true;
  }

  public function setup(User $user): array
  {
    abort_unless(
      $this->twoFactorRequired(),
      409,
      'Two-factor authentication is not required by this deployment.',
    );

    abort_unless(
      $user->email_verified_at,
      409,
      'Confirm your email address before setting up two-factor authentication.',
    );

    abort_if(
      $user->two_factor_confirmed_at,
      409,
      'Two-factor authentication is already configured.',
    );

    if (!$user->two_factor_secret) {
      $user
        ->forceFill([
          'two_factor_secret' => $this->google2fa->generateSecretKey(32),
        ])
        ->save();
      $user->refresh();
    }

    $issuer = (string) config('app.name', 'Okatana');
    $uri = $this->google2fa->getQRCodeUrl($issuer, $user->email, $user->two_factor_secret);

    $writer = new Writer(new GDLibRenderer(320));
    $png = $writer->writeString($uri);

    return [
      'qr_data_url' => 'data:image/png;base64,' . base64_encode($png),
      'manual_key' => $user->two_factor_secret,
      'issuer' => $issuer,
      'account' => $user->email,
    ];
  }

  public function confirmSetup(User $user, string $code): bool
  {
    if (!$this->twoFactorRequired()) {
      return false;
    }

    if (!$user->email_verified_at || !$user->two_factor_secret) {
      return false;
    }

    if ($user->two_factor_confirmed_at) {
      return true;
    }

    $normalized = preg_replace('/\D/', '', $code) ?? '';
    if (!preg_match('/^\d{6}$/', $normalized)) {
      return false;
    }

    $step = $this->google2fa->verifyKeyNewer($user->two_factor_secret, $normalized, 0);
    if ($step === false) {
      return false;
    }

    $user
      ->forceFill([
        'two_factor_confirmed_at' => now(),
        'two_factor_last_used_step' => $step,
        'remember_token' => null,
      ])
      ->save();

    return true;
  }

  public function verifyLoginCode(User $user, string $code): bool
  {
    if (!$this->twoFactorRequired()) {
      return false;
    }

    if (!$user->two_factor_confirmed_at || !$user->two_factor_secret) {
      return false;
    }

    $normalized = preg_replace('/\D/', '', $code) ?? '';
    if (!preg_match('/^\d{6}$/', $normalized)) {
      return false;
    }

    $step = $this->google2fa->verifyKeyNewer(
      $user->two_factor_secret,
      $normalized,
      (int) ($user->two_factor_last_used_step ?? 0),
    );

    if ($step === false) {
      return false;
    }

    $user
      ->forceFill([
        'two_factor_last_used_step' => $step,
      ])
      ->save();
    return true;
  }

  private function randomEmailCode(): string
  {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $last = strlen($alphabet) - 1;
    $code = '';

    for ($index = 0; $index < 8; $index++) {
      $code .= $alphabet[random_int(0, $last)];
    }

    return $code;
  }
}
