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
      $table->boolean('email_notify_assigned')->default(true)->after('avatar_path');
      $table->boolean('email_notify_mentions')->default(true)->after('email_notify_assigned');
    });

    Schema::create('notifications', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->string('type');
      $table->string('notifiable_type');
      $table->ulid('notifiable_id');
      $table->text('data');
      $table->timestamp('read_at')->nullable();
      $table->timestamps();
      $table->index(['notifiable_type', 'notifiable_id']);
      $table->index(['notifiable_id', 'read_at', 'created_at']);
    });

    Schema::create('ticket_mentions', function (Blueprint $table) {
      $table->id();
      $table->foreignUlid('ticket_id')->constrained()->cascadeOnDelete();
      $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
      $table->timestamps();
      $table->unique(['ticket_id', 'user_id']);
      $table->index(['user_id', 'created_at']);
    });

    Schema::create('ticket_comment_mentions', function (Blueprint $table) {
      $table->id();
      $table->foreignUlid('ticket_comment_id')->constrained('ticket_comments')->cascadeOnDelete();
      $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
      $table->timestamps();
      $table->unique(['ticket_comment_id', 'user_id']);
      $table->index(['user_id', 'created_at']);
    });

    Schema::create('attachments', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
      $table->foreignUlid('ticket_id')->nullable()->constrained()->cascadeOnDelete();
      $table
        ->foreignUlid('ticket_comment_id')
        ->nullable()
        ->constrained('ticket_comments')
        ->cascadeOnDelete();
      $table->foreignUlid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
      $table->string('original_name', 255);
      $table->string('mime_type', 255)->nullable();
      $table->unsignedBigInteger('size_bytes');
      $table->string('disk', 32)->default('local');
      $table->string('path', 1024);
      $table->timestamps();
      $table->index(['ticket_id', 'created_at']);
      $table->index(['ticket_comment_id', 'created_at']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('attachments');
    Schema::dropIfExists('ticket_comment_mentions');
    Schema::dropIfExists('ticket_mentions');
    Schema::dropIfExists('notifications');

    Schema::table('users', function (Blueprint $table) {
      $table->dropColumn(['email_notify_assigned', 'email_notify_mentions']);
    });
  }
};
