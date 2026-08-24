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
import { Button } from '../Primitives';
import { formatFileSize, MAX_ATTACHMENT_BYTES } from '../../lib/attachments';

export default function AttachmentPicker({
  files = [],
  onChange,
  disabled = false,
  label = 'Attach files',
}: AttachmentPickerProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [error, setError] = useState('');

  const choose = (event: React.ChangeEvent<HTMLInputElement>) => {
    const selected = [...(event.target.files || [])];
    const tooLarge = selected.find((file) => file.size > MAX_ATTACHMENT_BYTES);

    if (tooLarge) {
      setError(`${tooLarge.name} is larger than 200 MB.`);
      event.target.value = '';

      return;
    }

    const next = [...files, ...selected].slice(0, 10);
    setError(
      selected.length + files.length > 10
        ? 'A maximum of 10 files can be attached at one time.'
        : '',
    );

    onChange?.(next);
    event.target.value = '';
  };

  return (
    <div className="grid gap-4 border-t border-line pt-4">
      <div className="flex flex-wrap items-center gap-4">
        <input ref={inputRef} hidden type="file" multiple onChange={choose} />
        <Button
          compact
          disabled={disabled || files.length >= 10}
          onClick={() => inputRef.current?.click()}
        >
          {label}
        </Button>
        <span className="text-xs text-ink-muted">
          Maximum 200 MB per file · up to 10 files per upload
        </span>
      </div>
      {error && (
        <div className="text-sm text-error" role="alert">
          {error}
        </div>
      )}
      {files.length > 0 && (
        <ul className="grid gap-2">
          {files.map((file, index) => (
            <li
              key={`${file.name}-${file.size}-${index}`}
              className="flex flex-wrap items-center justify-between gap-4 border-b border-line py-2 text-sm"
            >
              <span className="min-w-0 truncate text-ink">
                {file.name}{' '}
                <span className="text-ink-muted tabular-nums">· {formatFileSize(file.size)}</span>
              </span>
              <Button
                compact
                onClick={() => onChange?.(files.filter((_, rowIndex) => rowIndex !== index))}
              >
                Remove
              </Button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
