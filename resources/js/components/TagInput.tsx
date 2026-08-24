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

import { useRef, useState } from 'react';

export default function TagInput({
  value = [],
  onChange,
  suggestions = [],
  label = 'Tags',
  ariaLabel = 'Add tag',
  countKey = 'documents_count',
}: TagInputProps) {
  const [draft, setDraft] = useState('');
  const inputRef = useRef<HTMLInputElement>(null);

  const normalize = (raw: string) =>
    raw.replace(/^#+/, '').trim().replace(/\s+/g, ' ').slice(0, 50);

  const addNames = (raw: string) => {
    const next = [...value];
    const seen = new Set(value.map((name) => name.toLowerCase()));

    raw
      .split(',')
      .map(normalize)
      .filter(Boolean)
      .forEach((name) => {
        const key = name.toLowerCase();

        if (!seen.has(key) && next.length < 20) {
          next.push(name);
          seen.add(key);
        }
      });
    if (next.length !== value.length) {
      onChange(next);
    }

    setDraft('');
  };

  const removeAt = (index: number) => onChange(value.filter((_, current) => current !== index));
  const query = draft.trim().toLowerCase();
  const matchingSuggestions = suggestions
    .filter((tag) => !value.some((name) => name.toLowerCase() === tag.name.toLowerCase()))
    .filter((tag) => !query || tag.name.toLowerCase().includes(query))
    .slice(0, 6);

  return (
    <fieldset className="border-t border-line pt-4">
      <legend className="mb-2 text-sm text-ink-muted">{label}</legend>
      <p className="mb-4 text-xs text-ink-muted">
        Type a tag and press Enter or comma. Backspace removes the nearest tag when the text field
        is empty.
      </p>
      <div
        className="flex min-h-10 w-full flex-wrap items-center gap-2 rounded-sm border border-line bg-paper px-2 py-2 hover:border-line-strong focus-within:border-line-strong"
        onClick={() => inputRef.current?.focus()}
      >
        {value.map((name, index) => (
          <span
            key={`${name.toLowerCase()}-${index}`}
            className="inline-flex min-h-6 items-center gap-2 border border-line bg-paper px-2 text-xs text-ink"
          >
            <span>#{name}</span>
            <button
              type="button"
              className="text-ink-muted hover:text-accent"
              onClick={(event) => {
                event.stopPropagation();
                removeAt(index);
              }}
              aria-label={`Remove tag ${name}`}
              title={`Remove #${name}`}
            >
              ×
            </button>
          </span>
        ))}
        <input
          ref={inputRef}
          className="min-h-6 min-w-32 flex-1 border-0 bg-transparent px-0 py-0 text-base text-ink outline-none placeholder:text-ink-muted"
          value={draft}
          maxLength={200}
          placeholder={value.length ? 'Add another tag…' : 'runbook, release, architecture'}
          onChange={(event) => {
            const next = event.target.value;
            if (next.includes(',')) {
              addNames(next);
            } else {
              setDraft(next);
            }
          }}
          onBlur={() => draft.trim() && addNames(draft)}
          onKeyDown={(event) => {
            if (event.key === 'Enter' || event.key === ',') {
              event.preventDefault();
              if (draft.trim()) {
                addNames(draft);
              }
            } else if (event.key === 'Backspace' && !draft && value.length) {
              event.preventDefault();
              removeAt(value.length - 1);
            }
          }}
          aria-label={ariaLabel}
        />
      </div>
      {matchingSuggestions.length > 0 && value.length < 20 && (
        <div className="mt-2 border border-line bg-paper" aria-label="Suggested tags">
          {matchingSuggestions.map((tag) => (
            <button
              key={tag.id}
              type="button"
              className="block w-full border-b border-line px-4 py-2 text-left text-xs text-ink-muted last:border-b-0 hover:bg-surface hover:text-ink"
              onMouseDown={(event) => event.preventDefault()}
              onClick={() => {
                addNames(tag.name);
                inputRef.current?.focus();
              }}
            >
              #{tag.name}
              {tag[countKey] != null ? (
                <span className="tabular-nums"> ({tag[countKey]})</span>
              ) : null}
            </button>
          ))}
        </div>
      )}
    </fieldset>
  );
}
