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

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class AuditImmutabilityTest extends TestCase {
  use RefreshDatabase;

  public function test_audit_model_rejects_updates(): void {
    $org = Organization::create([
      'name' => 'Audit Org',
      'slug' => 'audit-org',
      'settings' => [],
    ]);

    $log = AuditLog::create([
      'organization_id' => $org->id,
      'event' => 'test.created',
      'actor_type' => 'system',
      'occurred_at' => now(),
    ]);

    $this->expectException(LogicException::class);
    $log->update(['event' => 'test.tampered']);
  }

  public function test_database_trigger_rejects_direct_audit_update(): void {
    $org = Organization::create([
      'name' => 'Audit DB',
      'slug' => 'audit-db',
      'settings' => []
    ]);

    $log = AuditLog::create([
      'organization_id' => $org->id,
      'event' => 'test.created',
      'actor_type' => 'system',
      'occurred_at' => now(),
    ]);

    $this->expectException(QueryException::class);
    DB::table('audit_logs')
      ->where('id', $log->id)
      ->update(['event' => 'test.tampered']);
  }
}
