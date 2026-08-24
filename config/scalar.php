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

use App\Http\Middleware\EnsureApiDocumentationAccess;

return [
  'path' => env('OKATANA_API_DOCS_PATH', '/docs/api'),
  'middleware' => ['web', EnsureApiDocumentationAccess::class],
  'url' => env('OKATANA_OPENAPI_PATH', '/docs/openapi.yaml'),
  'cdn' => env('OKATANA_SCALAR_CDN', 'https://cdn.jsdelivr.net/npm/@scalar/api-reference'),

  'configuration' => [
    'theme' => 'none',
    'layout' => 'modern',
    'proxyUrl' => null,
    'showSidebar' => true,
    'hideModels' => false,
    'modelsSectionLabel' => 'Schemas',
    'hideDownloadButton' => false,
    'hideTestRequestButton' => false,
    'hideSearch' => false,
    'darkMode' => false,
    'forceDarkModeState' => 'light',
    'hideDarkModeToggle' => true,
    'searchHotKey' => 'k',
    'metaData' => [
      'title' => 'Okatana API Reference',
      'description' => 'Interactive OpenAPI reference for the Okatana External API v1.',
    ],
    'favicon' => '',
    'hiddenClients' => [],
    'defaultHttpClient' => [
      'targetId' => 'shell',
      'clientKey' => 'curl',
    ],
    'withDefaultFonts' => false,
    'defaultOpenAllTags' => false,
    'customCss' => <<<'CSS'
    :root {
        --scalar-color-accent: #D7263D;
        --scalar-radius: 2px;
        --scalar-font: 'Suisse Intl', Inter, -apple-system, 'Helvetica Neue', Arial, sans-serif;
    }
    .light-mode {
        --scalar-color-1: #111111;
        --scalar-color-2: #595959;
        --scalar-color-3: #595959;
        --scalar-color-accent: #D7263D;
        --scalar-background-1: #FFFFFF;
        --scalar-background-2: #F4F4F4;
        --scalar-background-3: #FFFFFF;
        --scalar-background-accent: #F4F4F4;
        --scalar-border-color: #DEDEDE;
        --scalar-color-green: #595959;
        --scalar-color-yellow: #595959;
        --scalar-color-blue: #595959;
        --scalar-color-orange: #595959;
        --scalar-color-purple: #595959;
        --scalar-color-red: #C62828;
        --scalar-button-1: #111111;
        --scalar-button-1-hover: #D7263D;
        --scalar-button-1-color: #FFFFFF;
    }
    * {
        box-shadow: none !important;
    }
    body, button, input, select, textarea {
        font-family: 'Suisse Intl', Inter, -apple-system, 'Helvetica Neue', Arial, sans-serif !important;
    }
    button, input, select, textarea, [class*='card'], [class*='modal'], [class*='popover'] {
        border-radius: 2px !important;
    }
    button, input, select, textarea {
        transition: color 150ms ease-out, background-color 150ms ease-out, border-color 150ms ease-out, outline-color 150ms ease-out !important;
    }
    body {
        color: #111111;
        background: #FFFFFF;
        font-variant-numeric: tabular-nums;
        text-align: left;
    }
    *:focus-visible {
        outline: 2px solid #D7263D !important;
        outline-offset: 2px !important;
    }
    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after {
            transition-duration: 0ms !important;
            animation-duration: 0ms !important;
            animation-iteration-count: 1 !important;
        }
    }
    CSS
  ,
  ],
];
