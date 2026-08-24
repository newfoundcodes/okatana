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

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationNotification extends Notification implements ShouldQueue
{
  use Queueable;

  public function __construct(public Invitation $invitation, public string $plainToken)
  {
    $this->afterCommit();
  }

  public function via(object $notifiable): array
  {
    return ['mail'];
  }

  public function toMail(object $notifiable): MailMessage
  {
    $scope = $this->invitation->project
      ? 'project ' . $this->invitation->project->name
      : 'organization ' . $this->invitation->organization->name;

    return (new MailMessage())
      ->subject('Join ' . $scope . ' on Okatana')
      ->greeting('You are invited to Okatana')
      ->line('You were invited to ' . $scope . ' as ' . $this->invitation->role . '.')
      ->line('The Join page requires a strong password before membership is activated.')
      ->action('Join', url('/join/' . $this->plainToken))
      ->line(
        'This invitation expires at ' . $this->invitation->expires_at->toIso8601String() . '.',
      );
  }
}
