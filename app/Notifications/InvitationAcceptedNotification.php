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

class InvitationAcceptedNotification extends Notification implements ShouldQueue
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

    $destination = $this->invitation->project_id
      ? '/app/projects/' . $this->invitation->project_id
      : '/app/organizations/' . $this->invitation->organization_id;

    return (new MailMessage())
      ->subject($this->invitee->name . ' accepted your invitation to ' . $scope)
      ->greeting('Hello ' . $notifiable->name . ',')
      ->line($this->invitee->name . ' (' . $this->invitee->email . ') accepted your invitation to join ' . $scope . ' as ' . $this->invitation->role . '.')
      ->action('Open in Okatana', rtrim((string) config('app.url'), '/') . $destination);
  }

  public function toArray(object $notifiable): array
  {
    $scope = $this->invitation->project
      ? 'project ' . $this->invitation->project->name
      : 'organization ' . $this->invitation->organization->name;

    $destination = $this->invitation->project_id
      ? '/app/projects/' . $this->invitation->project_id
      : '/app/organizations/' . $this->invitation->organization_id;

    return [
      'kind' => 'invitation.accepted',
      'title' => $this->invitee->name . ' accepted your invitation',
      'body' => $this->invitee->name . ' joined ' . $scope . ' as ' . $this->invitation->role . '.',
      'url' => $destination,
      'actor' => [
        'id' => $this->invitee->id,
        'name' => $this->invitee->name,
        'avatar_url' => $this->invitee->avatar_url,
      ],
    ];
  }
}

