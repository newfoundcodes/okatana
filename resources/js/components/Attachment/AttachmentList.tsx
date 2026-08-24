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

import { useState } from 'react';
import { del } from '../../lib/api';
import { Button } from '../Primitives';
import { formatFileSize } from '../../lib/attachments';
import ConfirmDialog from '../ConfirmDialog';

export default function AttachmentList({
  attachments = [],
  onRemoved,
  canRemove = true,
}: AttachmentListProps) {
  const [target, setTarget] = useState<Attachment | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  const remove = async () => {
    if (!target) {
      return;
    }

    setBusy(true);
    setError('');

    try {
      await del(`/app-api/attachments/${target.id}`);

      onRemoved?.(target.id);
      setTarget(null);
    } catch (requestError: any) {
      setError(requestError.message);
    } finally {
      setBusy(false);
    }
  };

  if (!attachments.length) {
    return null;
  }

  return (
    <div className="grid gap-2">
      {error && (
        <div className="text-sm text-error" role="alert">
          {error}
        </div>
      )}
      <ul>
        {attachments.map((attachment) => (
          <li
            key={attachment.id}
            className="flex flex-wrap items-center justify-between gap-4 border-b border-line py-4 text-sm"
          >
            <a
              href={attachment.download_url}
              className="min-w-0 truncate border-b border-line-strong text-ink hover:border-accent hover:text-accent"
            >
              {attachment.original_name}
            </a>
            <span className="flex items-center gap-4 text-xs text-ink-muted tabular-nums">
              <span>{formatFileSize(attachment.size_bytes)}</span>
              {(typeof canRemove === 'function' ? canRemove(attachment) : canRemove) && (
                <Button compact variant="danger" onClick={() => setTarget(attachment)}>
                  Delete
                </Button>
              )}
            </span>
          </li>
        ))}
      </ul>
      <ConfirmDialog
        open={Boolean(target)}
        title="Delete attachment"
        description={
          target
            ? `Delete “${target.original_name}”? The file will no longer be available from this ticket or comment.`
            : 'Delete this attachment?'
        }
        confirmLabel="Delete attachment"
        onCancel={() => setTarget(null)}
        onConfirm={remove}
        busy={busy}
      />
    </div>
  );
}
