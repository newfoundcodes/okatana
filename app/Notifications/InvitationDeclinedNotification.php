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
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationDeclinedNotification extends Notification implements ShouldQueue
{
  use Queueable;

  public function __construct(
    public Invitation $invitation,
    public User $invitee,
  ) {
    $this->afterCommit();
  }

  public function via(object $notifiable): array
  {
    return ['mail', 'database'];
  }

  public function toMail(object $notifiable): MailMessage
  {
    $scope = $this->invitation->project
      ? 'project ' . $this->invitation->project->name
      : 'organization ' . $this->invitation->organization->name;

    $membersUrl = '/app/organizations/' . $this->invitation->organization_id . '?tab=members';

    return (new MailMessage())
      ->subject($this->invitee->name . ' declined your invitation to ' . $scope)
      ->greeting('Hello ' . $notifiable->name . ',')
      ->line($this->invitee->name . ' (' . $this->invitee->email . ') declined your invitation to join ' . $scope . '.')
      ->line('You can send a new invitation at any time from the Members tab.')
      ->action('Go to Members', rtrim((string) config('app.url'), '/') . $membersUrl);
  }

  public function toArray(object $notifiable): array
  {
    $scope = $this->invitation->project
      ? 'project ' . $this->invitation->project->name
      : 'organization ' . $this->invitation->organization->name;

    return [
      'kind' => 'invitation.declined',
      'title' => $this->invitee->name . ' declined your invitation',
      'body' => $this->invitee->name . ' declined the invitation to join ' . $scope . '.',
      'url' => '/app/organizations/' . $this->invitation->organization_id . '?tab=members',
      'actor' => [
        'id' => $this->invitee->id,
        'name' => $this->invitee->name,
        'avatar_url' => $this->invitee->avatar_url,
      ],
    ];
  }
}

