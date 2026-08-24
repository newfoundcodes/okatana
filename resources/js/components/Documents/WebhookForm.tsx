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
import { Button, Check, Field, Select, TextInput } from '../Primitives';
import { documentEvents } from '../../lib/page';
import Modal from '../Modal';

export default function WebhookForm({ busy, onCancel, onSubmit, projects }: any) {
  const [form, setForm] = useState({
    name: '',
    url: '',
    project_id: '',
    events: ['document.created', 'document.updated'],
  });

  const toggle = (event: string) =>
    setForm({
      ...form,
      events: form.events.includes(event)
        ? form.events.filter((value) => value !== event)
        : [...form.events, event],
    });

  return (
    <Modal open onClose={onCancel} title="Create document webhook">
      <form
        className="grid gap-6"
        onSubmit={(event) => {
          event.preventDefault();
          onSubmit({ ...form, project_id: form.project_id || null });
        }}
      >
        <Field label="Name">
          <TextInput
            required
            value={form.name}
            onChange={(event: any) => setForm({ ...form, name: event.target.value })}
          />
        </Field>
        <Field label="Endpoint URL">
          <TextInput
            required
            type="url"
            value={form.url}
            onChange={(event: any) => setForm({ ...form, url: event.target.value })}
          />
        </Field>
        <Field
          label="Project"
          hint="Optional. Leave blank for all document events in the organization."
        >
          <Select
            value={form.project_id}
            onChange={(event: any) => setForm({ ...form, project_id: event.target.value })}
          >
            <option value="">All projects</option>
            {projects.map((project: any) => (
              <option key={project.id} value={project.id}>
                {project.name}
              </option>
            ))}
          </Select>
        </Field>
        <div>
          <div className="mb-2 text-sm text-ink-muted">Events</div>
          <div className="grid gap-2 md:grid-cols-2">
            {documentEvents.map((name) => (
              <Check key={name} checked={form.events.includes(name)} onChange={() => toggle(name)}>
                {name}
              </Check>
            ))}
          </div>
        </div>
        <div className="flex gap-2">
          <Button onClick={onCancel}>Cancel</Button>
          <Button type="submit" variant="primary" disabled={busy || !form.events.length}>
            {busy ? 'Creating…' : 'Create webhook'}
          </Button>
        </div>
      </form>
    </Modal>
  );
}
