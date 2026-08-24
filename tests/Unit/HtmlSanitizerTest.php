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

namespace Tests\Unit;

use App\Services\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase {
  public function test_it_removes_script_and_unsafe_anchor_attributes(): void {
    $html =
      '<p>Hello<script>alert(1)</script><a href="javascript:alert(1)" onclick="alert(2)" target="_top">link</a></p>';
    $result = (new HtmlSanitizer())->sanitize($html);

    self::assertStringNotContainsString('<script', $result);
    self::assertStringNotContainsString('javascript:', $result);
    self::assertStringNotContainsString('onclick=', $result);
    self::assertStringNotContainsString('target=', $result);
    self::assertStringContainsString('rel="noopener noreferrer"', $result);
  }

  public function test_unknown_wrapper_cannot_bypass_descendant_sanitization(): void {
    $html = '<unknown><a href="javascript:alert(1)" onclick="alert(2)">safe text</a></unknown>';
    $result = (new HtmlSanitizer())->sanitize($html);

    self::assertStringNotContainsString('<unknown', $result);
    self::assertStringNotContainsString('javascript:', $result);
    self::assertStringNotContainsString('onclick=', $result);
    self::assertStringContainsString('safe text', $result);
  }

  public function test_it_allows_safe_images_and_removes_unsafe_image_sources(): void {
    $html =
      '<p><img src="/storage/editor-images/user/image.png" alt="Legacy"><img src="/app-api/editor/images/user/123e4567-e89b-12d3-a456-426614174000.png" alt="Diagram" onerror="alert(1)"><img src="javascript:alert(1)" alt="Bad"></p>';
    $result = (new HtmlSanitizer())->sanitize($html);

    self::assertStringContainsString('src="/storage/editor-images/user/image.png"', $result);
    self::assertStringContainsString(
      'src="/app-api/editor/images/user/123e4567-e89b-12d3-a456-426614174000.png"',
      $result,
    );

    self::assertStringContainsString('alt="Diagram"', $result);
    self::assertStringNotContainsString('onerror=', $result);
    self::assertStringNotContainsString('javascript:', $result);
    self::assertStringNotContainsString('alt="Bad"', $result);
  }

  public function test_it_preserves_safe_mentions_and_forces_non_editable_mention_tokens(): void {
    $id = '01M09BANQAKR36454PYTHHJP35';
    $html =
      '<p>Hello <span data-mention-user-id="' .
      $id .
      '" contenteditable="false">@Member</span><span data-mention-user-id="bad" contenteditable="true">@Bad</span></p>';
    $result = (new HtmlSanitizer())->sanitize($html);

    self::assertStringContainsString('data-mention-user-id="' . $id . '"', $result);
    self::assertStringContainsString('contenteditable="false"', $result);
    self::assertStringNotContainsString('data-mention-user-id="bad"', $result);
    self::assertStringNotContainsString('contenteditable="true"', $result);
  }

  public function test_it_preserves_safe_table_structure_and_cell_spans(): void {
    $html =
      '<table onclick="alert(1)"><tbody><tr><td rowspan="2" colspan="3" style="color:red">Merged</td><td colspan="999">Bad span</td></tr><tr><th scope="col" onmouseover="alert(2)">Header</th></tr></tbody></table>';
    $result = (new HtmlSanitizer())->sanitize($html);

    self::assertStringContainsString('<table>', $result);
    self::assertStringContainsString('<tbody>', $result);
    self::assertStringContainsString('rowspan="2"', $result);
    self::assertStringContainsString('colspan="3"', $result);
    self::assertStringContainsString('scope="col"', $result);
    self::assertStringNotContainsString('colspan="999"', $result);
    self::assertStringNotContainsString('style=', $result);
    self::assertStringNotContainsString('onclick=', $result);
    self::assertStringNotContainsString('onmouseover=', $result);
  }
}
