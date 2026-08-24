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

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceNotificationMail extends Notification implements ShouldQueue
{
  use Queueable;

  public function __construct(public array $payload) {}

  public function via(object $notifiable): array
  {
    return ['mail'];
  }

  public function toMail(object $notifiable): MailMessage
  {
    $mail = (new MailMessage())
      ->subject((string) ($this->payload['title'] ?? 'Okatana notification'))
      ->greeting('Hello ' . $notifiable->name . ',')
      ->line((string) ($this->payload['body'] ?? 'You have a new Okatana notification.'));

    if (!empty($this->payload['url'])) {
      $mail->action(
        'Open in Okatana',
        rtrim((string) config('app.url'), '/') . $this->payload['url'],
      );
    }

    return $mail->line('You can manage notification preferences in Okatana settings.');
  }
}
