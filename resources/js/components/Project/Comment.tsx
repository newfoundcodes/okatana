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
import AttachmentList from '../Attachment/AttachmentList';
import ConfirmDialog from '../ConfirmDialog';
import DropdownMenu from '../DropdownMenu';
import { Avatar, Button, InlineAction } from '../Primitives';
import RichTextEditor from '../RichTextEditor';

export default function Comment({
  value,
  ticket,
  setTicket,
  info,
  setError,
  setNotice,
  onRevision,
}: any) {
  const [editing, setEditing] = useState(false);
  const [body, setBody] = useState(value.body_html);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deleting, setDeleting] = useState(false);

  const systemActivity = value.kind && value.kind !== 'comment';
  const canManage =
    !systemActivity && (['owner', 'admin'].includes(info.role) || value.user_id === info.viewer_id);

  const save = async () => {
    try {
      const result = await patch(`/app-api/comments/${value.id}`, { body_html: body });

      setTicket({
        ...ticket,
        updated_at: result.ticket_updated_at || ticket.updated_at,
        comments: ticket.comments.map((comment: any) =>
          comment.id === value.id ? result.comment : comment,
        ),
      });

      setEditing(false);
      setNotice('Comment saved.');
    } catch (requestError: any) {
      setError(requestError.message);
    }
  };

  const remove = async () => {
    setDeleting(true);

    try {
      await del(`/app-api/comments/${value.id}`);
      setTicket({
        ...ticket,
        comments: ticket.comments.filter((comment: any) => comment.id !== value.id),
      });

      setDeleteOpen(false);
      setNotice('Comment deleted.');
    } catch (requestError: any) {
      setError(requestError.message);
    } finally {
      setDeleting(false);
    }
  };

  return (
    <article
      className={`border-b border-line py-6 ${systemActivity ? 'border-l-2 border-l-line-strong pl-4' : ''}`}
    >
      <header className="flex flex-wrap items-baseline justify-between gap-2">
        <div className="flex flex-wrap items-center gap-2">
          <Avatar
            user={
              value.user || {
                name: value.author_snapshot?.name || 'Imported/API user',
              }
            }
            size="sm"
          />
          <strong className="font-semibold">
            {value.user?.name || value.author_snapshot?.name || 'Imported/API user'}
          </strong>
          {systemActivity && (
            <span className="text-xs font-medium uppercase tracking-widest text-ink-muted">
              Movement
            </span>
          )}
        </div>
        <div className="flex items-center gap-2 text-xs text-ink-muted tabular-nums">
          {value.edited_at && !systemActivity && (
            <InlineAction
              className="text-xs"
              onClick={() => onRevision?.({ type: 'comment', id: value.id, label: 'Comment' })}
            >
              Edited
            </InlineAction>
          )}
          <span>{new Date(value.created_at).toLocaleString()}</span>
          {canManage && !editing && (
            <DropdownMenu
              compact
              align="right"
              ariaLabel={`Comment actions for ${value.user?.name || value.author_snapshot?.name || 'comment author'}`}
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
            mentionUsers={info.assignable_members || info.members}
          />
          <div className="flex flex-wrap gap-2">
            <Button compact variant="primary" onClick={save}>
              Save comment
            </Button>
            <Button compact onClick={() => setEditing(false)}>
              Cancel
            </Button>
          </div>
        </div>
      ) : (
        <div
          className="wysiwyg-content mt-4"
          dangerouslySetInnerHTML={{ __html: value.body_html }}
        />
      )}
      {(value.attachments || []).length > 0 && (
        <div className="mt-4">
          <AttachmentList
            attachments={value.attachments}
            canRemove={(attachment: any) =>
              ['owner', 'admin'].includes(info.role) || attachment.uploaded_by === info.viewer_id
            }
            onRemoved={(attachmentId: string) =>
              setTicket({
                ...ticket,
                comments: ticket.comments.map((comment: any) =>
                  comment.id === value.id
                    ? {
                        ...comment,
                        attachments: (comment.attachments || []).filter(
                          (row: any) => row.id !== attachmentId,
                        ),
                      }
                    : comment,
                ),
              })
            }
          />
        </div>
      )}
      <ConfirmDialog
        open={deleteOpen}
        title="Delete comment"
        description="Delete this comment? It will be removed permanently, though the revision history of the ticket will retain an immutable record."
        confirmLabel="Delete comment"
        onCancel={() => setDeleteOpen(false)}
        onConfirm={remove}
        busy={deleting}
      />
    </article>
  );
}
