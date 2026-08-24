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

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\OutboundUrlGuard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class DeliverWebhookJob implements ShouldQueue
{
  use FoundationQueueable;

  public int $tries;

  public function __construct(public string $deliveryId)
  {
    $this->tries = (int) config('okatana.webhooks.max_attempts', 5);
  }

  public function backoff(): array
  {
    return [10, 60, 300, 900];
  }

  public function handle(OutboundUrlGuard $guard): void
  {
    $delivery = WebhookDelivery::with('endpoint')->findOrFail($this->deliveryId);
    $endpoint = $delivery->endpoint;
    if (!$endpoint || !$endpoint->is_active) {
      $delivery->update(['status' => 'disabled']);

      return;
    }

    $guard->assertSafe($endpoint->url);

    $body = json_encode(
      $delivery->payload,
      JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
    );
    $timestamp = (string) now()->timestamp;
    $signature = hash_hmac('sha256', $timestamp . '.' . $body, $endpoint->secret);

    try {
      $response = Http::timeout((int) config('okatana.webhooks.timeout_seconds', 8))
        ->withHeaders([
          'Content-Type' => 'application/json',
          'User-Agent' => 'Okatana-Webhook/1.0',
          'X-Okatana-Event' => $delivery->event,
          'X-Okatana-Delivery' => $delivery->id,
          'X-Okatana-Timestamp' => $timestamp,
          'X-Okatana-Signature' => 'sha256=' . $signature,
        ])
        ->withBody($body, 'application/json')
        ->post($endpoint->url);

      $delivery->update([
        'response_status' => $response->status(),
        'response_body_excerpt' => substr($response->body(), 0, 2000),
      ]);

      if (!$response->successful()) {
        throw new RuntimeException('Webhook returned HTTP ' . $response->status());
      }

      $delivery->update([
        'attempts' => $delivery->attempts + 1,
        'status' => 'delivered',
        'delivered_at' => now(),
        'last_error' => null,
      ]);
    } catch (Throwable $e) {
      $delivery->update([
        'attempts' => $delivery->attempts + 1,
        'last_error' => substr($e->getMessage(), 0, 2000),
        'status' => 'retrying',
      ]);

      throw $e;
    }
  }

  public function failed(Throwable $e): void
  {
    WebhookDelivery::whereKey($this->deliveryId)->update([
      'status' => 'failed',
      'last_error' => substr($e->getMessage(), 0, 2000),
    ]);
  }
}
