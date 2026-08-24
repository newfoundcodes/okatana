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
import { Button, Check, Field, TextArea, TextInput } from '../Primitives';

export default function ProjectForm({ onSubmit }: any) {
  const [form, setForm] = useState({
    name: '',
    key: '',
    description: '',
    create_default_boards: true,
  });

  return (
    <form
      className="grid gap-4"
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit(form);
      }}
    >
      <Field label="Name">
        <TextInput
          required
          value={form.name}
          onChange={(event: any) => setForm({ ...form, name: event.target.value })}
        />
      </Field>
      <Field label="Project key" hint="Maximum 12 characters">
        <TextInput
          required
          maxLength={12}
          value={form.key}
          onChange={(event: any) => setForm({ ...form, key: event.target.value.toUpperCase() })}
        />
      </Field>
      <Field label="Description">
        <TextArea
          rows={4}
          value={form.description}
          onChange={(event: any) => setForm({ ...form, description: event.target.value })}
        />
      </Field>
      <Check
        checked={form.create_default_boards}
        onChange={(event: any) => setForm({ ...form, create_default_boards: event.target.checked })}
      >
        Create Open, Hold, In-progress, Pull Request, and Deployed phases
      </Check>
      <div className="mt-4">
        <Button type="submit" variant="primary">
          Create project
        </Button>
      </div>
    </form>
  );
}
