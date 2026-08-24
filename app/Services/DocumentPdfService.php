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

namespace App\Services;

use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\Storage;

class DocumentPdfService
{
  public function inlineLocalImages(?string $html): ?string
  {
    if (!$html || !str_contains($html, '<img')) {
      return $html;
    }

    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');

    $dom->loadHTML(
      '<?xml encoding="utf-8" ?><div id="okatana-pdf-root">' . $html . '</div>',
      LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
    );

    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $root = $dom->getElementById('okatana-pdf-root');
    if (!$root) {
      return $html;
    }

    foreach (iterator_to_array($root->getElementsByTagName('img')) as $image) {
      if (!($image instanceof DOMElement)) {
        continue;
      }

      $src = $image->getAttribute('src');
      if (
        preg_match(
          '#^/app-api/editor/images/([A-Za-z0-9_-]+)/([A-Fa-f0-9-]+\.(?:jpe?g|png|gif|webp))$#i',
          $src,
          $match,
        )
      ) {
        $path = 'editor-images/' . $match[1] . '/' . $match[2];
      } elseif (
        preg_match(
          '#^/storage/(editor-images/[A-Za-z0-9_-]+/[A-Fa-f0-9-]+\.(?:jpe?g|png|gif|webp))$#i',
          $src,
          $match,
        )
      ) {
        $path = $match[1];
      } else {
        continue;
      }

      $disk = Storage::disk('public');
      if (!$disk->exists($path)) {
        continue;
      }

      $mime = $disk->mimeType($path) ?: 'application/octet-stream';
      $image->setAttribute('src', 'data:' . $mime . ';base64,' . base64_encode($disk->get($path)));
    }

    $output = '';
    foreach ($root->childNodes as $child) {
      $output .= $dom->saveHTML($child);
    }

    return $output;
  }
}
