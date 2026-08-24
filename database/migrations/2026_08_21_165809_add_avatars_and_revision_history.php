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
      $table->string('avatar_path', 500)->nullable()->after('email_verified_at');
    });

    Schema::table('tickets', function (Blueprint $table) {
      $table->timestamp('edited_at')->nullable()->after('completed_at')->index();
    });

    Schema::create('ticket_revisions', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->foreignUlid('ticket_id')->constrained()->cascadeOnDelete();
      $table->unsignedInteger('version');
      $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
      $table->json('snapshot');
      $table->timestamps();
      $table->unique(['ticket_id', 'version']);
      $table->index(['ticket_id', 'created_at']);
    });

    Schema::create('ticket_comment_revisions', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->foreignUlid('ticket_comment_id')->constrained('ticket_comments')->cascadeOnDelete();
      $table->unsignedInteger('version');
      $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
      $table->json('snapshot');
      $table->timestamps();
      $table->unique(['ticket_comment_id', 'version']);
      $table->index(['ticket_comment_id', 'created_at']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('ticket_comment_revisions');
    Schema::dropIfExists('ticket_revisions');

    Schema::table('tickets', function (Blueprint $table) {
      $table->dropIndex(['edited_at']);
      $table->dropColumn('edited_at');
    });

    Schema::table('users', function (Blueprint $table) {
      $table->dropColumn('avatar_path');
    });
  }
};
