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
import { del, patch } from '../../lib/api';
import { toast } from '../../lib/toast';
import ConfirmDialog from '../ConfirmDialog';
import DropdownMenu from '../DropdownMenu';
import RichTextEditor from '../RichTextEditor';
import { Avatar, Button } from '../Primitives';

export default function DocumentComment({ value, document, setDocument }: any) {
  const [editing, setEditing] = useState(false);
  const [body, setBody] = useState(value.body_html);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [busy, setBusy] = useState(false);

  const viewerCanAdmin = document.can_moderate_comments;
  const viewerId = document.viewer_id;
  const canManage = value.user_id === viewerId || viewerCanAdmin;

  const save = async () => {
    setBusy(true);

    try {
      const response = await patch(`/app-api/document-comments/${value.id}`, {
        body_html: body,
      });

      setDocument({
        ...document,
        comments: document.comments.map((comment: any) =>
          comment.id === value.id ? response.comment : comment,
        ),
      });

      setEditing(false);
      toast.success('Comment saved.');
    } catch (error: any) {
      toast.error(error.message);
    } finally {
      setBusy(false);
    }
  };

  const remove = async () => {
    setBusy(true);

    try {
      await del(`/app-api/document-comments/${value.id}`);
      setDocument({
        ...document,
        comments: document.comments.filter((comment: any) => comment.id !== value.id),
      });

      setDeleteOpen(false);
      toast.success('Comment deleted.');
    } catch (error: any) {
      toast.error(error.message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <article className="border-b border-line py-6">
      <header className="flex flex-wrap items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <Avatar
            user={value.user || { name: value.author_snapshot?.name || 'Unknown user' }}
            size="sm"
          />
          <strong className="font-semibold">
            {value.user?.name || value.author_snapshot?.name || 'Unknown user'}
          </strong>
        </div>
        <div className="flex items-center gap-2 text-xs text-ink-muted tabular-nums">
          {value.edited_at && <span>Edited</span>}
          <span>{new Date(value.created_at).toLocaleString()}</span>
          {canManage && !editing && (
            <DropdownMenu
              compact
              ariaLabel="Comment actions"
              items={[
                { label: 'Edit comment', onSelect: () => setEditing(true) },
                {
                  label: 'Delete comment',
                  tone: 'danger',
                  onSelect: () => setDeleteOpen(true),
                },
              ]}
            />
          )}
        </div>
      </header>
      {editing ? (
        <div className="mt-4 grid gap-4">
          <RichTextEditor
            value={body}
            onChange={setBody}
            size="compact"
            mentionUsers={document.mention_users || []}
          />
          <div className="flex gap-2">
            <Button
              compact
              onClick={() => {
                setEditing(false);
                setBody(value.body_html);
              }}
            >
              Cancel
            </Button>
            <Button compact variant="primary" disabled={busy} onClick={save}>
              {busy ? 'Saving…' : 'Save comment'}
            </Button>
          </div>
        </div>
      ) : (
        <div
          className="wysiwyg-content mt-4"
          dangerouslySetInnerHTML={{ __html: value.body_html }}
        />
      )}
      <ConfirmDialog
        open={deleteOpen}
        title="Delete comment"
        description="Delete this comment? The comment is soft-deleted and its audit history remains available."
        confirmLabel="Delete comment"
        onCancel={() => setDeleteOpen(false)}
        onConfirm={remove}
        busy={busy}
      />
    </article>
  );
}
