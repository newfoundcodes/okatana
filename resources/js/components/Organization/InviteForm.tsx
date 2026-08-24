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
import { Button, Field, Select, TextInput } from '../Primitives';

export default function InviteForm({ projects, onSubmit }: any) {
  const [form, setForm] = useState({ email: '', role: 'user', project_id: '' });
  return (
    <form
      className="grid gap-4"
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit({ ...form, project_id: form.project_id || null });
      }}
    >
      <Field label="Email">
        <TextInput
          type="email"
          required
          value={form.email}
          onChange={(event: any) => setForm({ ...form, email: event.target.value })}
        />
      </Field>
      <Field label="Organization role">
        <Select
          value={form.role}
          onChange={(event: any) => setForm({ ...form, role: event.target.value })}
        >
          <option value="user">User</option>
          <option value="admin">Admin</option>
          <option value="owner">Owner</option>
        </Select>
      </Field>
      <Field label="Project access" hint="Optional">
        <Select
          value={form.project_id}
          onChange={(event: any) => setForm({ ...form, project_id: event.target.value })}
        >
          <option value="">Organization only</option>
          {projects.map((project: any) => (
            <option value={project.id} key={project.id}>
              {project.name}
            </option>
          ))}
        </Select>
      </Field>
      <p className="text-sm text-ink-muted">
        The Join link requires the invitee to set a strong password before membership activates.
      </p>
      <div className="mt-4">
        <Button type="submit" variant="primary">
          Send invitation
        </Button>
      </div>
    </form>
  );
}
