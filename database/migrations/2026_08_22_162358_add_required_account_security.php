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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('users', function (Blueprint $table) {
      $table->timestamp('security_setup_required_at')->nullable()->after('password');
      $table->text('two_factor_secret')->nullable()->after('security_setup_required_at');
      $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
      $table
        ->unsignedBigInteger('two_factor_last_used_step')
        ->nullable()
        ->after('two_factor_confirmed_at');
    });

    Schema::create('email_verification_codes', function (Blueprint $table) {
      $table->id();
      $table->foreignUlid('user_id')->unique()->constrained()->cascadeOnDelete();
      $table->string('code_hash');
      $table->timestamp('expires_at');
      $table->timestamp('sent_at');
      $table->unsignedSmallInteger('attempts')->default(0);
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('email_verification_codes');

    Schema::table('users', function (Blueprint $table) {
      $table->dropColumn([
        'security_setup_required_at',
        'two_factor_secret',
        'two_factor_confirmed_at',
        'two_factor_last_used_step',
      ]);
    });
  }
};
