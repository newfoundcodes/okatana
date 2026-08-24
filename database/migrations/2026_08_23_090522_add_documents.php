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
    Schema::create('documents', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
      $table->foreignUlid('project_id')->nullable()->constrained()->nullOnDelete();
      $table->foreignUlid('author_id')->nullable()->constrained('users')->nullOnDelete();
      $table->string('title', 500);
      $table->text('caption')->nullable();
      $table->longText('content_html')->nullable();
      $table->string('status', 20)->default('draft');
      $table->timestamp('published_at')->nullable();
      $table->timestamp('archived_at')->nullable();
      $table->timestamps();
      $table->softDeletes();
      $table->index(['organization_id', 'status', 'published_at']);
      $table->index(['project_id', 'status']);
      $table->index(['author_id', 'updated_at']);
    });

    Schema::create('document_editors', function (Blueprint $table) {
      $table->id();
      $table->foreignUlid('document_id')->constrained()->cascadeOnDelete();
      $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
      $table->foreignUlid('added_by')->nullable()->constrained('users')->nullOnDelete();
      $table->timestamps();
      $table->unique(['document_id', 'user_id']);
      $table->index(['user_id', 'document_id']);
    });

    Schema::create('document_favorites', function (Blueprint $table) {
      $table->id();
      $table->foreignUlid('document_id')->constrained()->cascadeOnDelete();
      $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
      $table->timestamps();
      $table->unique(['document_id', 'user_id']);
      $table->index(['user_id', 'created_at']);
    });

    Schema::create('document_comments', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->foreignUlid('document_id')->constrained()->cascadeOnDelete();
      $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
      $table->longText('body_html');
      $table->json('author_snapshot')->nullable();
      $table->timestamp('edited_at')->nullable();
      $table->timestamps();
      $table->softDeletes();
      $table->index(['document_id', 'created_at']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('document_comments');
    Schema::dropIfExists('document_favorites');
    Schema::dropIfExists('document_editors');
    Schema::dropIfExists('documents');
  }
};
