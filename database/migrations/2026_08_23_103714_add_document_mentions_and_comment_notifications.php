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
      $table->boolean('notify_document_comments')->default(true)->after('email_notify_mentions');
    });

    Schema::create('document_mentions', function (Blueprint $table) {
      $table->id();
      $table->foreignUlid('document_id')->constrained()->cascadeOnDelete();
      $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
      $table->timestamps();
      $table->unique(['document_id', 'user_id']);
      $table->index(['user_id', 'created_at']);
    });

    Schema::create('document_comment_mentions', function (Blueprint $table) {
      $table->id();
      $table
        ->foreignUlid('document_comment_id')
        ->constrained('document_comments')
        ->cascadeOnDelete();
      $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
      $table->timestamps();
      $table->unique(['document_comment_id', 'user_id']);
      $table->index(['user_id', 'created_at']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('document_comment_mentions');
    Schema::dropIfExists('document_mentions');
    Schema::table('users', function (Blueprint $table) {
      $table->dropColumn('notify_document_comments');
    });
  }
};
