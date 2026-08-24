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

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiDocumentationTest extends TestCase {
  public function test_openapi_document_is_served_for_scalar(): void {
    $response = $this->get('/docs/openapi.yaml');
    $response->assertOk();
    $response->assertHeader('X-Content-Type-Options', 'nosniff');

    $specification = file_get_contents(base_path('docs/openapi.yaml'));
    self::assertStringContainsString('openapi: 3.1.0', $specification);
    self::assertStringContainsString('Okatana External API', $specification);
  }

  public function test_scalar_reference_route_is_registered_at_configured_path(): void {
    self::assertTrue(Route::has('scalar'));
    self::assertSame(url('/docs/api'), route('scalar'));
  }

  public function test_documentation_can_be_restricted_to_authenticated_users(): void {
    config()->set('okatana.api_docs.public', false);
    $this->get('/docs/api')->assertForbidden();
    $this->get('/docs/openapi.yaml')->assertForbidden();
  }
}
