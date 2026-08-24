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

use App\Jobs\DeliverWebhookJob;
use App\Models\Organization;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\OutboundUrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookSigningTest extends TestCase {
  use RefreshDatabase;

  public function test_webhook_delivery_contains_verifiable_hmac_signature(): void {
    config()->set('okatana.webhooks.allow_private_networks', true);
    Http::fake(['https://receiver.example.test/*' => Http::response(['ok' => true], 200)]);

    $creator = User::create([
      'name' => 'Owner',
      'email' => 'hook-owner@example.test',
      'password' => 'Password!12345',
    ]);

    $org = Organization::create([
      'name' => 'Hook Org',
      'slug' => 'hook-org',
      'settings' => []
    ]);

    $secret = 'whsec_test_secret';
    $endpoint = WebhookEndpoint::create([
      'organization_id' => $org->id,
      'name' => 'Receiver',
      'url' => 'https://receiver.example.test/events',
      'secret' => $secret,
      'events' => ['ticket.created'],
      'is_active' => true,
      'created_by' => $creator->id,
    ]);

    $delivery = WebhookDelivery::create([
      'webhook_endpoint_id' => $endpoint->id,
      'event' => 'ticket.created',
      'payload' => ['event' => 'ticket.created', 'subject' => ['id' => '01TEST']],
    ]);

    (new DeliverWebhookJob($delivery->id))
      ->handle(app(OutboundUrlGuard::class));

    Http::assertSent(function (Request $request) use ($secret) {
      $timestamp = $request->header('X-Okatana-Timestamp')[0] ?? '';
      $actual = $request->header('X-Okatana-Signature')[0] ?? '';
      $expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $request->body(), $secret);

      return hash_equals($expected, $actual) &&
        ($request->header('X-Okatana-Event')[0] ?? '') === 'ticket.created';
    });

    self::assertSame('delivered', $delivery->fresh()->status);
  }
}
