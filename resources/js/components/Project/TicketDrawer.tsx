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

import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { del, get, patch, post } from '../../lib/api';
import { toLocalInput, priorities } from '../../lib/project';
import AttachmentList from '../Attachment/AttachmentList';
import AttachmentPicker from '../Attachment/AttachmentPicker';
import { uploadAttachments } from '../../lib/attachments';
import ConfirmDialog from '../ConfirmDialog';
import {
  Avatar,
  Button,
  Field,
  GridLayout,
  InlineAction,
  LoadingState,
  SectionHeading,
  Select,
  TextInput,
} from '../Primitives';
import RichTextEditor from '../RichTextEditor';
import Comment from './Comment';
import MultiChecks from './MultiChecks';
import Priority from './Priority';
import TagEditor from './TagEditor';
import TicketProperty from './TicketProperty';

export default function TicketDrawer({
  ticketId,
  close,
  info,
  reload,
  setError,
  setNotice,
  onRevision,
}: any) {
  const [ticket, setTicket] = useState<any>(null);
  const [editing, setEditing] = useState(false);
  const [comment, setComment] = useState('');
  const [selectedBoard, setSelectedBoard] = useState('');
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [archiving, setArchiving] = useState(false);
  const [ticketAttachmentFiles, setTicketAttachmentFiles] = useState([]);
  const [commentAttachmentFiles, setCommentAttachmentFiles] = useState([]);
  const [uploadingAttachments, setUploadingAttachments] = useState(false);

  if (!ticketId) {
    return null;
  }

  const save = async () => {
    try {
      await patch(`/app-api/tickets/${ticket.id}`, {
        title: ticket.title,
        description_html: ticket.description_html,
        priority: ticket.priority,
        due_at: ticket.due_at || null,
        assignee_ids: ticket.assignees.map((assignee: any) => assignee.id),
        label_ids: ticket.labels.map((label: any) => label.id),
        tag_names: (ticket.tags || []).map((tag: any) => tag.name),
      });

      if (selectedBoard && selectedBoard !== ticket.board_id) {
        await post(`/app-api/tickets/${ticket.id}/move`, { board_id: selectedBoard });
      }

      const refreshed = await get(`/app-api/tickets/${ticket.id}`);
      setTicket(refreshed.ticket);
      setSelectedBoard(refreshed.ticket.board_id);
      setEditing(false);
      setNotice('Ticket saved.');

      reload();
    } catch (requestError: any) {
      setError(requestError.message);
    }
  };

  const addComment = async () => {
    const hasBody = Boolean(comment.replace(/<[^>]+>/g, '').trim() || /<img\b/i.test(comment));
    if (!hasBody && !commentAttachmentFiles.length) {
      return;
    }

    try {
      const result = await post(`/app-api/tickets/${ticket.id}/comments`, {
        body_html: hasBody ? comment : '<p></p>',
      });

      let nextComment = result.comment;
      let uploadError = null;

      if (commentAttachmentFiles.length) {
        try {
          const attachments = await uploadAttachments(
            `/app-api/comments/${result.comment.id}/attachments`,
            commentAttachmentFiles,
          );
          nextComment = { ...nextComment, attachments };
        } catch (requestError: any) {
          uploadError = requestError;
        }
      }

      setTicket({
        ...ticket,
        updated_at: result.ticket_updated_at || ticket.updated_at,
        comments: [...ticket.comments, nextComment],
      });

      setComment('');
      setCommentAttachmentFiles([]);

      if (uploadError) {
        setError(
          `Comment posted, but one or more attachments were not uploaded: ${(uploadError as any).message}`,
        );
      } else {
        setNotice('Comment posted.');
      }
    } catch (requestError: any) {
      setError(requestError.message);
    }
  };

  const addTicketAttachments = async () => {
    if (!ticketAttachmentFiles.length) {
      return;
    }

    setUploadingAttachments(true);
    try {
      const attachments = await uploadAttachments(
        `/app-api/tickets/${ticket.id}/attachments`,
        ticketAttachmentFiles,
      );

      setTicket({ ...ticket, attachments: [...(ticket.attachments || []), ...attachments] });
      setTicketAttachmentFiles([]);

      setNotice('Files attached to ticket.');
    } catch (requestError: any) {
      setError(requestError.message);
    } finally {
      setUploadingAttachments(false);
    }
  };

  const toggleArchive = async () => {
    if (!ticket) {
      return;
    }

    setArchiving(true);
    try {
      const archived = !ticket.archived_at;
      const result = await patch(`/app-api/tickets/${ticket.id}`, { archived });

      setTicket(result.ticket);
      setSelectedBoard(result.ticket.board_id);
      setNotice(archived ? 'Ticket archived.' : 'Ticket restored from archive.');

      reload();
    } catch (requestError: any) {
      setError(requestError.message);
    } finally {
      setArchiving(false);
    }
  };

  const destroy = async () => {
    setDeleting(true);
    try {
      await del(`/app-api/tickets/${ticket.id}`);
      setDeleteOpen(false);
      close();

      setNotice('Ticket deleted.');
      reload();
    } catch (requestError: any) {
      setError(requestError.message);
    } finally {
      setDeleting(false);
    }
  };

  useEffect(() => {
    if (ticketId) {
      get(`/app-api/tickets/${ticketId}`)
        .then((data) => {
          setTicket(data.ticket);
          setSelectedBoard(data.ticket.board_id);
        })
        .catch((requestError) => setError(requestError.message));
    } else {
      setTicket(null);
      setEditing(false);
      setDeleteOpen(false);

      setTicketAttachmentFiles([]);
      setCommentAttachmentFiles([]);
    }
  }, [ticketId]);

  return createPortal(
    <div
      className="fixed inset-0 z-50 flex justify-end bg-ink/40"
      style={{ zIndex: 100 }}
      onMouseDown={(event) => event.target === event.currentTarget && close()}
    >
      <aside
        className="h-full w-full overflow-auto border-l border-line-strong bg-paper md:w-3/4 xl:w-1/2"
        role="dialog"
        aria-modal="true"
        aria-label="Ticket details"
      >
        <header className="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-line bg-paper p-6">
          <div>
            <div className="text-xs font-medium uppercase tracking-widest text-ink-muted tabular-nums">
              {info.project.key}-{ticket?.number}
            </div>
            <h2 className="mt-2 text-xl font-semibold tracking-display text-ink">
              {ticket?.title || 'Loading ticket…'}
            </h2>
            {ticket && (
              <div className="mt-4 flex flex-wrap items-center gap-2 text-sm text-ink-muted">
                <Avatar user={ticket.creator || { name: 'Unknown user' }} size="sm" />
                <span>
                  Created by{' '}
                  <span className="font-medium text-ink">
                    {ticket.creator?.name || 'Unknown user'}
                  </span>
                </span>
                {ticket.edited_at && (
                  <InlineAction
                    onClick={() =>
                      onRevision?.({
                        type: 'ticket',
                        id: ticket.id,
                        label: `${info.project.key}-${ticket.number}`,
                      })
                    }
                  >
                    Edited
                  </InlineAction>
                )}
              </div>
            )}
          </div>
          <div className="flex shrink-0 items-center gap-2">
            {ticket && (
              <Button compact disabled={archiving} onClick={toggleArchive}>
                {archiving ? 'Saving…' : ticket.archived_at ? 'Unarchive' : 'Archive'}
              </Button>
            )}
            <Button compact onClick={close}>
              Close
            </Button>
          </div>
        </header>

        {!ticket ? (
          <div className="p-6">
            <LoadingState label="Loading ticket…" />
          </div>
        ) : (
          <div className="grid gap-8 p-6">
            {editing ? (
              <>
                <Field label="Title">
                  <TextInput
                    value={ticket.title}
                    onChange={(event: any) => setTicket({ ...ticket, title: event.target.value })}
                  />
                </Field>
                <Field label="Description">
                  <RichTextEditor
                    value={ticket.description_html || ''}
                    onChange={(value: string) => setTicket({ ...ticket, description_html: value })}
                    mentionUsers={info.assignable_members || info.members}
                  />
                </Field>
                <GridLayout>
                  <Field label="Phase" className="col-span-2 md:col-span-4 xl:col-span-6">
                    <Select
                      value={selectedBoard}
                      onChange={(event: any) => setSelectedBoard(event.target.value)}
                    >
                      {info.boards
                        .filter((board: any) => !board.is_hidden)
                        .map((board: any) => (
                          <option key={board.id} value={board.id}>
                            {board.name}
                          </option>
                        ))}
                    </Select>
                  </Field>
                  <Field label="Priority" className="col-span-2 md:col-span-4 xl:col-span-3">
                    <Select
                      value={ticket.priority}
                      onChange={(event: any) =>
                        setTicket({
                          ...ticket,
                          priority: event.target.value,
                        })
                      }
                    >
                      {priorities.map((priority: string) => (
                        <option key={priority} value={priority}>
                          {priority}
                        </option>
                      ))}
                    </Select>
                  </Field>
                  <Field label="Due date" className="col-span-4 md:col-span-4 xl:col-span-3">
                    <TextInput
                      type="datetime-local"
                      value={toLocalInput(ticket.due_at)}
                      onChange={(event: any) =>
                        setTicket({ ...ticket, due_at: event.target.value })
                      }
                    />
                  </Field>
                </GridLayout>
                <MultiChecks
                  title="Assignees"
                  rows={info.assignable_members || info.members}
                  selected={ticket.assignees.map((assignee: any) => assignee.id)}
                  setSelected={(ids: string[]) =>
                    setTicket({
                      ...ticket,
                      assignees: (info.assignable_members || info.members).filter((member: any) =>
                        ids.includes(member.id),
                      ),
                    })
                  }
                />
                <MultiChecks
                  title="Labels"
                  rows={info.labels}
                  selected={ticket.labels.map((label: any) => label.id)}
                  setSelected={(ids: string[]) =>
                    setTicket({
                      ...ticket,
                      labels: info.labels.filter((label: any) => ids.includes(label.id)),
                    })
                  }
                />
                <TagEditor
                  value={(ticket.tags || []).map((tag: any) => tag.name)}
                  onChange={(names: string[]) =>
                    setTicket({
                      ...ticket,
                      tags: names.map(
                        (name) =>
                          (info.tags || []).find(
                            (tag: any) => tag.name.toLowerCase() === name.toLowerCase(),
                          ) || { id: `new:${name.toLowerCase()}`, name },
                      ),
                    })
                  }
                  suggestions={info.tags || []}
                />
                <div className="flex flex-wrap gap-2">
                  <Button variant="primary" onClick={save}>
                    Save ticket
                  </Button>
                  <Button
                    onClick={() => {
                      setEditing(false);
                      setSelectedBoard(ticket.board_id);
                    }}
                  >
                    Cancel
                  </Button>
                </div>
              </>
            ) : (
              <>
                <GridLayout>
                  <TicketProperty label="Phase" value={ticket.board.name} />
                  <TicketProperty label="Priority" value={<Priority value={ticket.priority} />} />
                  <TicketProperty
                    label="Due"
                    value={ticket.due_at ? new Date(ticket.due_at).toLocaleString() : 'No due date'}
                  />
                  <TicketProperty
                    label="Created"
                    value={new Date(ticket.created_at).toLocaleString()}
                  />
                  <TicketProperty
                    label="Last updated"
                    value={new Date(ticket.updated_at).toLocaleString()}
                  />
                  <TicketProperty
                    label="Status"
                    value={
                      ticket.archived_at
                        ? `Archived ${new Date(ticket.archived_at).toLocaleString()}`
                        : 'Active'
                    }
                  />
                  <TicketProperty
                    label="Assignees"
                    value={
                      ticket.assignees.map((assignee: any) => assignee.name).join(', ') ||
                      'No assignees'
                    }
                    wide
                  />
                  <TicketProperty
                    label="Tags"
                    value={
                      (ticket.tags || []).map((tag: any) => `#${tag.name}`).join(', ') || 'No tags'
                    }
                    wide
                  />
                </GridLayout>

                <section className="border-t border-line pt-6">
                  <h3 className="mb-4 text-md font-semibold text-ink">Description</h3>
                  <div
                    className="wysiwyg-content"
                    dangerouslySetInnerHTML={{
                      __html: ticket.description_html || '<p>No description.</p>',
                    }}
                  />
                </section>

                <section className="border-t border-line pt-6">
                  <h3 className="mb-4 text-md font-semibold text-ink">Attachments</h3>
                  <AttachmentList
                    attachments={ticket.attachments || []}
                    canRemove={(attachment: any) =>
                      ['owner', 'admin'].includes(info.role) ||
                      attachment.uploaded_by === info.viewer_id
                    }
                    onRemoved={(attachmentId: string) =>
                      setTicket({
                        ...ticket,
                        attachments: (ticket.attachments || []).filter(
                          (row: any) => row.id !== attachmentId,
                        ),
                      })
                    }
                  />
                  {!(ticket.attachments || []).length && (
                    <p className="text-sm text-ink-muted">No files attached to this ticket.</p>
                  )}
                  <div className="mt-4 grid gap-4">
                    <AttachmentPicker
                      files={ticketAttachmentFiles}
                      onChange={setTicketAttachmentFiles}
                      disabled={uploadingAttachments}
                      label="Choose files"
                    />
                    {ticketAttachmentFiles.length > 0 && (
                      <div>
                        <Button
                          compact
                          variant="primary"
                          disabled={uploadingAttachments}
                          onClick={addTicketAttachments}
                        >
                          {uploadingAttachments ? 'Uploading…' : 'Upload files'}
                        </Button>
                      </div>
                    )}
                  </div>
                </section>

                <div className="flex flex-wrap gap-2">
                  <Button variant="primary" onClick={() => setEditing(true)}>
                    Edit ticket
                  </Button>
                  <Button variant="danger" onClick={() => setDeleteOpen(true)}>
                    Delete ticket
                  </Button>
                </div>
              </>
            )}

            <section className="border-t-2 border-ink pt-6">
              <SectionHeading
                title="Comments"
                description={`${ticket.comments.length} timeline entr${ticket.comments.length === 1 ? 'y' : 'ies'}, including comments and ticket movement.`}
              />
              <div>
                {ticket.comments.length ? (
                  ticket.comments.map((value: any) => (
                    <Comment
                      key={value.id}
                      value={value}
                      ticket={ticket}
                      setTicket={setTicket}
                      info={info}
                      setError={setError}
                      setNotice={setNotice}
                      onRevision={onRevision}
                    />
                  ))
                ) : (
                  <div className="border-b border-line py-6 text-sm text-ink-muted">
                    No comments yet — post the first comment.
                  </div>
                )}
              </div>
              <div className="mt-8 grid gap-4">
                <RichTextEditor
                  value={comment}
                  onChange={setComment}
                  size="compact"
                  placeholder="Write a comment…"
                  mentionUsers={info.assignable_members || info.members}
                />
                <AttachmentPicker
                  files={commentAttachmentFiles}
                  onChange={setCommentAttachmentFiles}
                  label="Attach files to comment"
                />
                <div>
                  <Button variant="primary" onClick={addComment}>
                    Post comment
                  </Button>
                </div>
              </div>
            </section>
          </div>
        )}
      </aside>

      <ConfirmDialog
        open={deleteOpen}
        title="Delete ticket"
        description={
          ticket
            ? `Delete ${info.project.key}-${ticket.number} “${ticket.title}”? The ticket is soft-deleted and its audit history remains immutable.`
            : 'Delete this ticket?'
        }
        confirmLabel="Delete ticket"
        onCancel={() => setDeleteOpen(false)}
        onConfirm={destroy}
        busy={deleting}
      />
    </div>,
    document.body,
  );
}
