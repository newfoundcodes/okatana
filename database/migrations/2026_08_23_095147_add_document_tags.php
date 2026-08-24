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
    Schema::create('document_tags', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
      $table->string('name', 50);
      $table->string('normalized_name', 50);
      $table->timestamps();
      $table->unique(['organization_id', 'normalized_name']);
      $table->index(['organization_id', 'name']);
    });

    Schema::create('document_tag_assignments', function (Blueprint $table) {
      $table->id();
      $table->foreignUlid('document_id')->constrained()->cascadeOnDelete();
      $table->foreignUlid('document_tag_id')->constrained('document_tags')->cascadeOnDelete();
      $table->timestamps();
      $table->unique(['document_id', 'document_tag_id']);
      $table->index(['document_tag_id', 'document_id']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('document_tag_assignments');
    Schema::dropIfExists('document_tags');
  }
};
