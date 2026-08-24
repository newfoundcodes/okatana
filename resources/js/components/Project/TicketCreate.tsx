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
import { post } from '../../lib/api';
import { priorities } from '../../lib/project';
import AttachmentPicker from '../Attachment/AttachmentPicker';
import { uploadAttachments } from '../../lib/attachments';
import { Button, Field, GridLayout, Select, TextInput } from '../Primitives';
import RichTextEditor from '../RichTextEditor';
import MultiChecks from './MultiChecks';
import TagEditor from './TagEditor';

export default function TicketCreate({ info, onCreated, setError }: any) {
  const [attachmentFiles, setAttachmentFiles] = useState([]);
  const [submitting, setSubmitting] = useState(false);
  const [form, setForm] = useState({
    board_id: info.boards[0]?.id || '',
    title: '',
    description_html: '',
    priority: 'normal',
    due_at: '',
    assignee_ids: [],
    label_ids: [],
    tag_names: [],
  });

  const submit = async (event: any) => {
    event.preventDefault();
    setSubmitting(true);

    try {
      const result = await post(`/app-api/projects/${info.project.id}/tickets`, {
        ...form,
        due_at: form.due_at || null,
      });

      if (attachmentFiles.length) {
        try {
          await uploadAttachments(
            `/app-api/tickets/${result.ticket.id}/attachments`,
            attachmentFiles,
          );
        } catch (uploadError: any) {
          setError(
            `Ticket created, but one or more attachments were not uploaded: ${uploadError.message}`,
          );
          onCreated();
          return;
        }
      }

      onCreated();
    } catch (requestError: any) {
      setError(requestError.message);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <form className="grid gap-6" onSubmit={submit}>
      <GridLayout>
        <Field label="Title" className="col-span-4 md:col-span-5 xl:col-span-7">
          <TextInput
            required
            value={form.title}
            onChange={(event: any) => setForm({ ...form, title: event.target.value })}
          />
        </Field>
        <Field label="Phase" className="col-span-2 md:col-span-3 xl:col-span-5">
          <Select
            value={form.board_id}
            onChange={(event: any) => setForm({ ...form, board_id: event.target.value })}
          >
            {info.boards
              .filter((board: any) => !board.is_hidden)
              .map((board: any) => (
                <option value={board.id} key={board.id}>
                  {board.name}
                </option>
              ))}
          </Select>
        </Field>
        <Field label="Priority" className="col-span-2 md:col-span-3 xl:col-span-4">
          <Select
            value={form.priority}
            onChange={(event: any) => setForm({ ...form, priority: event.target.value })}
          >
            {priorities.map((priority: string) => (
              <option key={priority} value={priority}>
                {priority}
              </option>
            ))}
          </Select>
        </Field>
        <Field label="Due date" className="col-span-2 md:col-span-3 xl:col-span-4">
          <TextInput
            type="datetime-local"
            value={form.due_at}
            onChange={(event: any) => setForm({ ...form, due_at: event.target.value })}
          />
        </Field>
      </GridLayout>

      <Field label="Description">
        <RichTextEditor
          value={form.description_html}
          onChange={(value: string) => setForm({ ...form, description_html: value })}
          mentionUsers={info.assignable_members || info.members}
        />
      </Field>
      <AttachmentPicker
        files={attachmentFiles}
        onChange={setAttachmentFiles}
        disabled={submitting}
        label="Attach files to ticket"
      />
      <MultiChecks
        title="Assignees"
        rows={info.assignable_members || info.members}
        selected={form.assignee_ids}
        setSelected={(value: any) => setForm({ ...form, assignee_ids: value })}
      />
      <MultiChecks
        title="Labels"
        rows={info.labels}
        selected={form.label_ids}
        setSelected={(value: any) => setForm({ ...form, label_ids: value })}
      />
      <TagEditor
        value={form.tag_names}
        onChange={(value: any) => setForm({ ...form, tag_names: value })}
        suggestions={info.tags || []}
      />
      <div>
        <Button type="submit" variant="primary" disabled={submitting}>
          {submitting ? 'Creating…' : 'Create ticket'}
        </Button>
      </div>
    </form>
  );
}
