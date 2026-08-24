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

use App\Models\EmailVerificationCode;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\EmailVerificationCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AccountSecurityOnboardingTest extends TestCase {
  use RefreshDatabase;

  public function test_signup_requires_email_code_then_totp_before_workspace_access(): void {
    config()->set('okatana.security.two_factor_required', true);

    Notification::fake();
    $response = $this->postJson('/app-api/auth/register', [
      'name' => 'Secure User',
      'email' => 'secure@example.test',
      'password' => 'StrongPassword!1234',
      'password_confirmation' => 'StrongPassword!1234',
    ])
      ->assertCreated()
      ->assertJsonPath('status', 'email_verification_required');

    $user = User::where('email', 'secure@example.test')->firstOrFail();
    self::assertNull($user->email_verified_at);
    self::assertNotNull($user->security_setup_required_at);

    $code = null;
    Notification::assertSentTo($user, EmailVerificationCodeNotification::class, function (
      $notification,
    ) use (&$code) {
      $code = Crypt::decryptString($notification->encryptedCode);
      return preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', $code) === 1;
    });

    self::assertNotNull($code);

    $verification = EmailVerificationCode::where('user_id', $user->id)->firstOrFail();
    $remainingSeconds = now()->diffInSeconds($verification->expires_at, false);

    self::assertGreaterThanOrEqual(11 * 60, $remainingSeconds);
    self::assertLessThanOrEqual(12 * 60, $remainingSeconds);

    $this->getJson('/app-api/organizations')
      ->assertForbidden()
      ->assertJsonPath('code', 'account_security_setup_required');

    $this->postJson('/app-api/auth/email/verify-code', ['code' => $code])
      ->assertOk()
      ->assertJsonPath('security.email_verified', true)
      ->assertJsonPath('security.two_factor_confirmed', false);

    $setup = $this->getJson('/app-api/auth/two-factor/setup')
      ->assertOk()
      ->assertJsonStructure([
        'setup' => [
          'qr_data_url',
          'manual_key',
          'issuer',
          'account'
        ]
      ])
      ->json('setup');
    self::assertStringStartsWith('data:image/png;base64,', $setup['qr_data_url']);

    $otp = (new Google2FA())->getCurrentOtp($setup['manual_key']);
    $this->postJson('/app-api/auth/two-factor/confirm', ['code' => $otp])
      ->assertOk()
      ->assertJsonPath('security.complete', true);

    $this->getJson('/app-api/organizations')->assertOk();
  }

  public function test_new_invitee_must_setup_totp_after_setting_password_before_workspace_access(): void {
    config()->set('okatana.security.two_factor_required', true);

    $organization = Organization::create([
      'name' => 'Secure Invite Org',
      'slug' => 'secure-invite-org',
      'settings' => [],
    ]);

    $inviter = User::create([
      'name' => 'Invite Owner',
      'email' => 'invite-owner-security@example.test',
      'password' => 'StrongPassword!1234',
    ]);
    $plain = 'secure-onboarding-invite-token';

    Invitation::create([
      'organization_id' => $organization->id,
      'email' => 'invitee-security@example.test',
      'role' => 'user',
      'token_hash' => hash('sha256', $plain),
      'invited_by' => $inviter->id,
      'expires_at' => now()->addDay(),
    ]);

    $response = $this->postJson("/app-api/invitations/{$plain}/accept", [
      'name' => 'Secure Invitee',
      'password' => 'StrongPassword!1234',
      'password_confirmation' => 'StrongPassword!1234',
    ])
      ->assertOk()
      ->assertJsonPath('status', 'two_factor_setup_required');

    $invitee = User::where('email', 'invitee-security@example.test')->firstOrFail();
    self::assertNotNull($invitee->email_verified_at);
    self::assertNotNull($invitee->security_setup_required_at);
    self::assertNull($invitee->two_factor_confirmed_at);

    $this->getJson('/app-api/organizations')
      ->assertForbidden()
      ->assertJsonPath('code', 'account_security_setup_required');

    $setup = $this->getJson('/app-api/auth/two-factor/setup')->assertOk()->json('setup');
    $otp = (new Google2FA())->getCurrentOtp($setup['manual_key']);

    $this->postJson('/app-api/auth/two-factor/confirm', ['code' => $otp])
      ->assertOk()
      ->assertJsonPath('security.complete', true);
    $this->getJson('/app-api/organizations')->assertOk();
  }

  public function test_completed_totp_account_requires_authenticator_challenge_after_password_login(): void {
    config()->set('okatana.security.two_factor_required', true);

    $google2fa = new Google2FA();
    $user = User::create([
      'name' => 'Two Factor User',
      'email' => '2fa@example.test',
      'password' => 'StrongPassword!1234',
      'email_verified_at' => now(),
      'security_setup_required_at' => now(),
      'two_factor_secret' => $google2fa->generateSecretKey(32),
      'two_factor_confirmed_at' => now(),
    ]);

    $this->postJson('/app-api/auth/login', [
      'email' => $user->email,
      'password' => 'StrongPassword!1234',
    ])
      ->assertOk()
      ->assertJsonPath('status', 'two_factor_challenge_required');

    $this->getJson('/app-api/auth/two-factor/pending')
      ->assertOk()
      ->assertJsonPath('pending', true)
      ->assertJsonPath('account.email', $user->email);
  }

  public function test_legacy_account_keeps_password_only_login_when_two_factor_enforcement_is_disabled(): void {
    config()->set('okatana.security.two_factor_required', false);

    $user = User::create([
      'name' => 'Legacy User',
      'email' => 'legacy@example.test',
      'password' => 'StrongPassword!1234',
    ]);

    $this->postJson('/app-api/auth/login', [
      'email' => $user->email,
      'password' => 'StrongPassword!1234',
    ])
      ->assertOk()
      ->assertJsonPath('status', 'authenticated')
      ->assertJsonPath('security.two_factor_required', false);
  }

  public function test_signup_requires_email_confirmation_but_skips_totp_when_two_factor_enforcement_is_disabled(): void {
    config()->set('okatana.security.two_factor_required', false);
    Notification::fake();

    $this->postJson('/app-api/auth/register', [
      'name' => 'Email Only User',
      'email' => 'email-only@example.test',
      'password' => 'StrongPassword!1234',
      'password_confirmation' => 'StrongPassword!1234',
    ])
      ->assertCreated()
      ->assertJsonPath('status', 'email_verification_required')
      ->assertJsonPath('security.two_factor_required', false);

    $user = User::where('email', 'email-only@example.test')->firstOrFail();
    $code = null;

    Notification::assertSentTo($user, EmailVerificationCodeNotification::class, function (
      $notification,
    ) use (&$code) {
      $code = Crypt::decryptString($notification->encryptedCode);
      return true;
    });

    $this->postJson('/app-api/auth/email/verify-code', ['code' => $code])
      ->assertOk()
      ->assertJsonPath('security.email_verified', true)
      ->assertJsonPath('security.two_factor_required', false)
      ->assertJsonPath('security.complete', true);

    $this->getJson('/app-api/organizations')->assertOk();
    $this->getJson('/app-api/auth/two-factor/setup')->assertStatus(409);
  }

  public function test_redeploying_with_two_factor_required_forces_existing_unenrolled_users_to_setup_totp(): void {
    config()->set('okatana.security.two_factor_required', false);

    $user = User::create([
      'name' => 'Toggle User',
      'email' => 'toggle@example.test',
      'password' => 'StrongPassword!1234',
      'email_verified_at' => now(),
      'security_setup_required_at' => now(),
    ]);

    $this->postJson('/app-api/auth/login', [
      'email' => $user->email,
      'password' => 'StrongPassword!1234',
    ])
      ->assertOk()
      ->assertJsonPath('status', 'authenticated');

    $this->postJson('/app-api/auth/logout')->assertNoContent();
    config()->set('okatana.security.two_factor_required', true);

    $this->postJson('/app-api/auth/login', [
      'email' => $user->email,
      'password' => 'StrongPassword!1234',
    ])
      ->assertOk()
      ->assertJsonPath('status', 'two_factor_setup_required')
      ->assertJsonPath('security.two_factor_required', true)
      ->assertJsonPath('security.complete', false);
  }

  public function test_disabling_two_factor_enforcement_preserves_existing_totp_data_and_skips_challenge(): void {
    $google2fa = new Google2FA();
    $secret = $google2fa->generateSecretKey(32);
    $confirmedAt = now()->subDay();

    $user = User::create([
      'name' => 'Preserved Two Factor User',
      'email' => 'preserved-2fa@example.test',
      'password' => 'StrongPassword!1234',
      'email_verified_at' => now(),
      'security_setup_required_at' => now(),
      'two_factor_secret' => $secret,
      'two_factor_confirmed_at' => $confirmedAt,
    ]);

    config()->set('okatana.security.two_factor_required', false);
    $this->postJson('/app-api/auth/login', [
      'email' => $user->email,
      'password' => 'StrongPassword!1234',
    ])
      ->assertOk()
      ->assertJsonPath('status', 'authenticated')
      ->assertJsonPath('security.two_factor_required', false);

    $fresh = $user->fresh();
    self::assertSame($secret, $fresh->two_factor_secret);
    self::assertNotNull($fresh->two_factor_confirmed_at);
  }

  public function test_invitee_skips_totp_setup_when_two_factor_enforcement_is_disabled(): void {
    config()->set('okatana.security.two_factor_required', false);

    $organization = Organization::create([
      'name' => 'No Two Factor Invite Org',
      'slug' => 'no-two-factor-invite-org',
      'settings' => [],
    ]);

    $inviter = User::create([
      'name' => 'Invite Owner',
      'email' => 'invite-owner-no-2fa@example.test',
      'password' => 'StrongPassword!1234',
    ]);

    $plain = 'no-two-factor-invite-token';
    Invitation::create([
      'organization_id' => $organization->id,
      'email' => 'invitee-no-2fa@example.test',
      'role' => 'user',
      'token_hash' => hash('sha256', $plain),
      'invited_by' => $inviter->id,
      'expires_at' => now()->addDay(),
    ]);

    $this->postJson("/app-api/invitations/{$plain}/accept", [
      'name' => 'No Two Factor Invitee',
      'password' => 'StrongPassword!1234',
      'password_confirmation' => 'StrongPassword!1234',
    ])
      ->assertOk()
      ->assertJsonPath('status', 'authenticated')
      ->assertJsonPath('security.two_factor_required', false)
      ->assertJsonPath('security.complete', true);

    $invitee = User::where('email', 'invitee-no-2fa@example.test')->firstOrFail();
    self::assertNotNull($invitee->email_verified_at);
    self::assertNull($invitee->two_factor_secret);
    self::assertNull($invitee->two_factor_confirmed_at);

    $this->getJson('/app-api/organizations')->assertOk();
  }
}
