{{--
  Okatana - A Newfoundcodes project.

  Copyright (C) 2026 Jonathan Eldy Baldivicio

  Author: Jonathan Eldy Baldivicio
  Contact: jonathaneldy.baldivicio@newfoundcodes.com

  SPDX-License-Identifier: AGPL-3.0-or-later

  This program is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published
  by the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  GNU Affero General Public License for more details.

  You should have received a copy of the GNU Affero General Public License
  along with this program. If not, see <https://www.gnu.org/licenses/>.
 --}}

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $document->title }}</title>
  <style>
    @page {
      margin: 18mm 18mm 20mm;
    }

    body {
      margin: 0;
      color: #111111;
      font-family: DejaVu Sans, Arial, sans-serif;
      font-size: 10pt;
      line-height: 1.5;
    }

    .kicker {
      margin-bottom: 8pt;
      color: #595959;
      font-size: 8pt;
      text-transform: uppercase;
      letter-spacing: .08em;
    }

    h1 {
      margin: 0 0 10pt;
      font-size: 24pt;
      line-height: 1.12;
      font-weight: 600;
    }

    .caption {
      margin: 0 0 16pt;
      color: #595959;
      font-size: 11pt;
    }

    .meta {
      margin: 0 0 8pt;
      padding-top: 8pt;
      border-top: .5pt solid #b0b0b0;
      color: #595959;
      font-size: 8.5pt;
    }

    .tags {
      margin: 0 0 20pt;
      color: #595959;
      font-size: 8.5pt;
    }

    .content h1 {
      margin: 20pt 0 8pt;
      font-size: 20pt;
    }

    .content h2 {
      margin: 18pt 0 8pt;
      font-size: 16pt;
    }

    .content h3 {
      margin: 16pt 0 6pt;
      font-size: 13pt;
    }

    .content p, .content ul, .content ol, .content blockquote, .content pre, .content table {
      margin: 0 0 10pt; }

    .content blockquote {
      border-left: 2pt solid #b0b0b0;
      padding-left: 10pt;
      color: #595959;
    }

    .content pre {
      border: .5pt solid #dedede;
      background: #f4f4f4;
      padding: 8pt;
      white-space: pre-wrap;
    }

    .content a {
      color: #d7263d;
      text-decoration: underline;
    }

    .content img { 
      max-width: 100%;
      height: auto;
    }

    .content table {
      width: 100%;
      border-collapse: collapse;
    }

    .content th,
    .content td {
      padding: 6pt;
      border: .5pt solid #b0b0b0;
      vertical-align: top;
      text-align: left;
    }

    .content th {
      font-weight: 600;
    }
  </style>
</head>
<body>
  <div class="kicker">Okatana document</div>

  <h1>{{ $document->title }}</h1>

  @if($document->caption)<p class="caption">{{ $document->caption }}</p>@endif

  <div class="meta">
    {{ $document->author?->name ?? 'Unknown author' }}
    · {{ $document->organization?->name }}
    @if($document->project) · {{ $document->project->name }} @endif
    @if($document->published_at) · Published {{ $document->published_at->format('Y-m-d H:i') }} @endif
  </div>

  @if($document->tags?->isNotEmpty())<div class="tags">{{ $document->tags->map(fn($tag) => '#'.$tag->name)->join(' · ') }}</div>@endif
  <div class="content">{!!$content ?: '<p>No content.</p>' !!}</div>
</body>
</html>
