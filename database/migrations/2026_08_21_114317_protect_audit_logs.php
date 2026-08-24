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
use Illuminate\Support\Facades\DB;

return new class extends Migration {
  public function up(): void
  {
    $driver = DB::getDriverName();

    if ($driver === 'sqlite') {
      DB::unprepared(
        "CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit_logs are immutable'); END;",
      );

      DB::unprepared(
        "CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit_logs cannot be deleted'); END;",
      );
    } elseif ($driver === 'mysql') {
      DB::unprepared(
        "CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs are immutable'",
      );

      DB::unprepared(
        "CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs cannot be deleted'",
      );
    } elseif ($driver === 'pgsql') {
      DB::unprepared(
        "CREATE OR REPLACE FUNCTION prevent_audit_log_mutation() RETURNS trigger AS \\$\\$ BEGIN RAISE EXCEPTION 'audit_logs are immutable'; END; \\$\\$ LANGUAGE plpgsql",
      );

      DB::unprepared(
        'CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs FOR EACH ROW EXECUTE FUNCTION prevent_audit_log_mutation()',
      );

      DB::unprepared(
        'CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs FOR EACH ROW EXECUTE FUNCTION prevent_audit_log_mutation()',
      );
    }
  }

  public function down(): void
  {
    $driver = DB::getDriverName();

    if ($driver === 'sqlite') {
      DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update');
      DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete');
    } elseif ($driver === 'mysql') {
      DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update');
      DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete');
    } elseif ($driver === 'pgsql') {
      DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update ON audit_logs');
      DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete ON audit_logs');
      DB::unprepared('DROP FUNCTION IF EXISTS prevent_audit_log_mutation()');
    }
  }
};
