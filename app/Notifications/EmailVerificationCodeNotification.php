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
use Illuminate\Support\Facades\Crypt;

class EmailVerificationCodeNotification extends Notification implements ShouldQueue
{
  use Queueable;

  public string $encryptedCode;

  public function __construct(string $code, public int $ttlMinutes)
  {
    $this->encryptedCode = Crypt::encryptString($code);
    $this->afterCommit();
  }

  public function via(object $notifiable): array
  {
    return ['mail'];
  }

  public function toMail(object $notifiable): MailMessage
  {
    $code = Crypt::decryptString($this->encryptedCode);
    return (new MailMessage())
      ->subject('Confirm your Okatana email address')
      ->greeting('Confirm your email address')
      ->line('Use this confirmation code to finish creating your Okatana account:')
      ->line($code)
      ->line(
        'The code expires in ' .
          $this->ttlMinutes .
          ' minutes. If you did not create this account, you can ignore this email.',
      );
  }
}
