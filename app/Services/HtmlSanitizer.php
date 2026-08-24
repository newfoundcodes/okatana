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
use DOMNode;

class HtmlSanitizer
{
  private array $tags = [
    'p',
    'br',
    'strong',
    'b',
    'em',
    'i',
    'u',
    's',
    'blockquote',
    'pre',
    'code',
    'ul',
    'ol',
    'li',
    'h1',
    'h2',
    'h3',
    'h4',
    'a',
    'span',
    'img',
    'table',
    'caption',
    'thead',
    'tbody',
    'tfoot',
    'tr',
    'th',
    'td',
  ];

  private array $anchorAttributes = ['href', 'title', 'target', 'rel'];

  private array $imageAttributes = ['src', 'alt', 'title'];

  private array $spanAttributes = ['data-mention-user-id', 'contenteditable'];

  private array $cellAttributes = ['colspan', 'rowspan', 'scope'];

  private array $dropWithContents = [
    'script',
    'style',
    'iframe',
    'object',
    'embed',
    'svg',
    'math',
    'form',
    'input',
    'button',
    'textarea',
    'select',
    'option',
    'meta',
    'link',
    'base',
  ];

  public function sanitize(?string $html): ?string
  {
    if ($html === null || trim($html) === '') {
      return null;
    }

    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');

    $dom->loadHTML(
      '<?xml encoding="utf-8" ?><div id="okatana-root">' . $html . '</div>',
      LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
    );

    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $root = $dom->getElementById('okatana-root');
    if (!$root) {
      return null;
    }

    $this->cleanChildren($root);

    $out = '';
    foreach ($root->childNodes as $child) {
      $out .= $dom->saveHTML($child);
    }

    return $out;
  }

  private function cleanChildren(DOMNode $parent): void
  {
    $children = [];
    foreach ($parent->childNodes as $child) {
      $children[] = $child;
    }

    foreach ($children as $node) {
      if ($node instanceof DOMElement) {
        $tag = strtolower($node->tagName);

        if (!in_array($tag, $this->tags, true)) {
          if (in_array($tag, $this->dropWithContents, true)) {
            $parent->removeChild($node);
            continue;
          }

          $this->cleanChildren($node);
          while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
          }

          $parent->removeChild($node);
          continue;
        }

        foreach (iterator_to_array($node->attributes) as $attr) {
          $name = strtolower($attr->name);
          $allowed =
            $tag === 'a'
              ? in_array($name, $this->anchorAttributes, true)
              : ($tag === 'img'
                ? in_array($name, $this->imageAttributes, true)
                : ($tag === 'span'
                  ? in_array($name, $this->spanAttributes, true)
                  : in_array($tag, ['td', 'th'], true) &&
                    in_array($name, $this->cellAttributes, true)));

          if (!$allowed) {
            $node->removeAttribute($attr->name);
            continue;
          }

          if ($tag === 'a' && $name === 'href' && !$this->safeUrl($attr->value)) {
            $node->removeAttribute('href');
          }

          if ($tag === 'a' && $name === 'target' && $attr->value !== '_blank') {
            $node->removeAttribute('target');
          }

          if ($tag === 'img' && $name === 'src' && !$this->safeImageUrl($attr->value)) {
            $node->removeAttribute('src');
          }

          if (
            $tag === 'span' &&
            $name === 'data-mention-user-id' &&
            !preg_match('/^[A-Za-z0-9]{20,32}$/', $attr->value)
          ) {
            $node->removeAttribute($name);
          }

          if (
            $tag === 'span' &&
            $name === 'contenteditable' &&
            strtolower($attr->value) !== 'false'
          ) {
            $node->removeAttribute($name);
          }

          if (in_array($tag, ['td', 'th'], true) && in_array($name, ['colspan', 'rowspan'], true)) {
            $span = (int) $attr->value;
            if ($span < 1 || $span > 100 || (string) $span !== trim($attr->value)) {
              $node->removeAttribute($name);
            }
          }

          if (
            $tag === 'th' &&
            $name === 'scope' &&
            !in_array(strtolower($attr->value), ['row', 'col', 'rowgroup', 'colgroup'], true)
          ) {
            $node->removeAttribute($name);
          }

          if ($tag === 'td' && $name === 'scope') {
            $node->removeAttribute($name);
          }
        }

        if ($tag === 'a') {
          $node->setAttribute('rel', 'noopener noreferrer');
        }

        if ($tag === 'img' && !$node->hasAttribute('src')) {
          $parent->removeChild($node);

          continue;
        }

        $this->cleanChildren($node);
        continue;
      }

      if (!in_array($node->nodeType, [XML_TEXT_NODE, XML_CDATA_SECTION_NODE], true)) {
        $parent->removeChild($node);
      }
    }
  }

  private function safeUrl(string $url): bool
  {
    $url = trim($url);
    if ($url === '' || str_starts_with($url, '/') || str_starts_with($url, '#')) {
      return true;
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https', 'mailto'], true);
  }

  private function safeImageUrl(string $url): bool
  {
    $url = trim($url);
    if ($url === '' || str_starts_with($url, '/')) {
      return true;
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true);
  }
}
