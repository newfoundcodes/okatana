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

  public function __construct(
    public Invitation $invitation,
    public string $plainToken,
    public bool $accountExists = false,
  ) {
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

    $inviterName = $this->invitation->inviter?->name ?? 'A team member';
    $subject = 'You have been invited to join ' . $scope . ' on Okatana';

    if ($this->accountExists) {
      return (new MailMessage())
        ->subject($subject)
        ->greeting('Hello,')
        ->line($inviterName . ' has invited you to join ' . $scope . ' as ' . $this->invitation->role . '.')
        ->line('Because you already have an Okatana account, your invitation is waiting in your notification panel. Open Okatana and click the Notifications bell to review and respond.')
        ->action('Open Okatana', url('/app'))
        ->line('This invitation expires at ' . $this->invitation->expires_at->toIso8601String() . '.');
    }

    return (new MailMessage())
      ->subject($subject)
      ->greeting('You are invited to Okatana')
      ->line($inviterName . ' has invited you to join ' . $scope . ' as ' . $this->invitation->role . '.')
      ->line('Use the button below to create your account and set a strong password before entering the workspace.')
      ->action('Accept invitation', url('/join/' . $this->plainToken))
      ->line('This invitation expires at ' . $this->invitation->expires_at->toIso8601String() . '.');
  }
}
