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
    Schema::create('users', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->string('name');
      $table->string('email')->unique();
      $table->timestamp('email_verified_at')->nullable();
      $table->string('password')->nullable();
      $table->rememberToken();
      $table->timestamps();
    });

    Schema::create('password_reset_tokens', function (Blueprint $table) {
      $table->string('email')->primary();
      $table->string('token');
      $table->timestamp('created_at')->nullable();
    });

    Schema::create('sessions', function (Blueprint $table) {
      $table->string('id')->primary();
      $table->foreignUlid('user_id')->nullable()->index();
      $table->string('ip_address', 45)->nullable();
      $table->text('user_agent')->nullable();
      $table->longText('payload');
      $table->integer('last_activity')->index();
    });

    Schema::create('cache', function (Blueprint $table) {
      $table->string('key')->primary();
      $table->mediumText('value');
      $table->integer('expiration');
    });

    Schema::create('cache_locks', function (Blueprint $table) {
      $table->string('key')->primary();
      $table->string('owner');
      $table->integer('expiration');
    });

    Schema::create('jobs', function (Blueprint $table) {
      $table->bigIncrements('id');
      $table->string('queue')->index();
      $table->longText('payload');
      $table->unsignedTinyInteger('attempts');
      $table->unsignedInteger('reserved_at')->nullable();
      $table->unsignedInteger('available_at');
      $table->unsignedInteger('created_at');
    });

    Schema::create('job_batches', function (Blueprint $table) {
      $table->string('id')->primary();
      $table->string('name');
      $table->integer('total_jobs');
      $table->integer('pending_jobs');
      $table->integer('failed_jobs');
      $table->longText('failed_job_ids');
      $table->mediumText('options')->nullable();
      $table->integer('cancelled_at')->nullable();
      $table->integer('created_at');
      $table->integer('finished_at')->nullable();
    });

    Schema::create('failed_jobs', function (Blueprint $table) {
      $table->id();
      $table->string('uuid')->unique();
      $table->text('connection');
      $table->text('queue');
      $table->longText('payload');
      $table->longText('exception');
      $table->timestamp('failed_at')->useCurrent();
    });

    Schema::create('organizations', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->string('name');
      $table->string('slug')->unique();
      $table->text('description')->nullable();
      $table->json('settings')->nullable();
      $table->timestamps();
      $table->softDeletes();
    });

    Schema::create('organization_members', function (Blueprint $table) {
      $table->id();
      $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
      $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
      $table->string('role', 20)->default('user');
      $table->timestamps();
      $table->unique(['organization_id', 'user_id']);
      $table->index(['organization_id', 'role']);
    });

    Schema::create('projects', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
      $table->string('name');
      $table->string('key', 16);
      $table->text('description')->nullable();
      $table->json('settings')->nullable();
      $table->timestamp('archived_at')->nullable();
      $table->timestamps();
      $table->softDeletes();
      $table->unique(['organization_id', 'key']);
    });

    Schema::create('project_members', function (Blueprint $table) {
      $table->id();
      $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
      $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
      $table->timestamps();
      $table->unique(['project_id', 'user_id']);
    });

    Schema::create('boards', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
      $table->string('name');
      $table->string('slug');
      $table->unsignedInteger('position')->default(0);
      $table->string('color', 32)->nullable();
      $table->unsignedInteger('wip_limit')->nullable();
      $table->boolean('is_done')->default(false);
      $table->boolean('is_hidden')->default(false);
      $table->timestamps();
      $table->softDeletes();
      $table->unique(['project_id', 'slug']);
      $table->index(['project_id', 'position']);
    });

    Schema::create('tickets', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
      $table->foreignUlid('board_id')->constrained()->restrictOnDelete();
      $table->unsignedBigInteger('number');
      $table->string('title', 500);
      $table->longText('description_html')->nullable();
      $table->string('priority', 20)->default('normal');
      $table->unsignedInteger('position')->default(0);
      $table->timestamp('due_at')->nullable();
      $table->timestamp('started_at')->nullable();
      $table->timestamp('completed_at')->nullable();
      $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
      $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
      $table->timestamp('archived_at')->nullable();
      $table->timestamps();
      $table->softDeletes();
      $table->unique(['project_id', 'number']);
      $table->index(['project_id', 'board_id', 'position']);
      $table->index(['project_id', 'priority', 'due_at']);
    });

    Schema::create('labels', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
      $table->string('name');
      $table->string('color', 32)->nullable();
      $table->timestamps();
      $table->unique(['project_id', 'name']);
    });

    Schema::create('ticket_assignees', function (Blueprint $table) {
      $table->id();
      $table->foreignUlid('ticket_id')->constrained()->cascadeOnDelete();
      $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
      $table->timestamps();
      $table->unique(['ticket_id', 'user_id']);
    });

    Schema::create('ticket_labels', function (Blueprint $table) {
      $table->id();
      $table->foreignUlid('ticket_id')->constrained()->cascadeOnDelete();
      $table->foreignUlid('label_id')->constrained()->cascadeOnDelete();
      $table->timestamps();
      $table->unique(['ticket_id', 'label_id']);
    });

    Schema::create('ticket_comments', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->foreignUlid('ticket_id')->constrained()->cascadeOnDelete();
      $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
      $table->longText('body_html');
      $table->json('author_snapshot')->nullable();
      $table->timestamp('edited_at')->nullable();
      $table->timestamps();
      $table->softDeletes();
      $table->index(['ticket_id', 'created_at']);
    });

    Schema::create('invitations', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
      $table->foreignUlid('project_id')->nullable()->constrained()->cascadeOnDelete();
      $table->string('email');
      $table->string('role', 20)->default('user');
      $table->string('token_hash', 64)->unique();
      $table->foreignUlid('invited_by')->nullable()->constrained('users')->nullOnDelete();
      $table->timestamp('expires_at');
      $table->timestamp('accepted_at')->nullable();
      $table->timestamp('revoked_at')->nullable();
      $table->timestamps();
      $table->index(['email', 'expires_at']);
    });

    Schema::create('audit_logs', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->ulid('organization_id')->nullable()->index();
      $table->ulid('project_id')->nullable()->index();
      $table->string('event', 120)->index();
      $table->string('actor_type', 40)->default('user');
      $table->ulid('actor_id')->nullable();
      $table->string('actor_name')->nullable();
      $table->string('actor_email')->nullable();
      $table->string('subject_type', 120)->nullable();
      $table->string('subject_id', 64)->nullable();
      $table->string('subject_label')->nullable();
      $table->json('changes')->nullable();
      $table->json('metadata')->nullable();
      $table->string('ip_address', 45)->nullable();
      $table->text('user_agent')->nullable();
      $table->timestamp('occurred_at')->useCurrent();
      $table->index(['organization_id', 'occurred_at']);
      $table->index(['project_id', 'occurred_at']);
      $table->index(['subject_type', 'subject_id']);
    });

    Schema::create('webhook_endpoints', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
      $table->foreignUlid('project_id')->nullable()->constrained()->cascadeOnDelete();
      $table->string('name');
      $table->text('url');
      $table->text('secret');
      $table->json('events');
      $table->boolean('is_active')->default(true);
      $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
      $table->timestamp('last_rotated_at')->nullable();
      $table->timestamps();
      $table->index(['organization_id', 'is_active']);
    });

    Schema::create('webhook_deliveries', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->foreignUlid('webhook_endpoint_id')->constrained()->cascadeOnDelete();
      $table->string('event', 120);
      $table->json('payload');
      $table->string('status', 30)->default('pending');
      $table->unsignedInteger('attempts')->default(0);
      $table->unsignedSmallInteger('response_status')->nullable();
      $table->text('response_body_excerpt')->nullable();
      $table->text('last_error')->nullable();
      $table->timestamp('delivered_at')->nullable();
      $table->timestamp('next_attempt_at')->nullable();
      $table->timestamps();
      $table->index(['webhook_endpoint_id', 'status', 'created_at']);
    });

    Schema::create('api_credentials', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
      $table->string('name');
      $table->string('public_id', 24)->unique();
      $table->string('secret_hash', 64);
      $table->json('scopes');
      $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
      $table->timestamp('last_used_at')->nullable();
      $table->timestamp('expires_at')->nullable();
      $table->timestamp('revoked_at')->nullable();
      $table->timestamps();
      $table->index(['organization_id', 'revoked_at']);
    });

    Schema::create('data_transfers', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->foreignUlid('organization_id')->nullable()->constrained()->nullOnDelete();
      $table->foreignUlid('project_id')->nullable()->constrained()->nullOnDelete();
      $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
      $table->string('direction', 10);
      $table->string('scope', 30);
      $table->string('status', 30);
      $table->string('filename')->nullable();
      $table->json('summary')->nullable();
      $table->json('errors')->nullable();
      $table->timestamp('started_at')->nullable();
      $table->timestamp('finished_at')->nullable();
      $table->timestamps();
    });
  }

  public function down(): void
  {
    foreach (
      [
        'data_transfers',
        'api_credentials',
        'webhook_deliveries',
        'webhook_endpoints',
        'audit_logs',
        'invitations',
        'ticket_comments',
        'ticket_labels',
        'ticket_assignees',
        'labels',
        'tickets',
        'boards',
        'project_members',
        'projects',
        'organization_members',
        'organizations',
        'failed_jobs',
        'job_batches',
        'jobs',
        'cache_locks',
        'cache',
        'sessions',
        'password_reset_tokens',
        'users',
      ]
      as $table
    ) {
      Schema::dropIfExists($table);
    }
  }
};
