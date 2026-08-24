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
import { Button, Check, ColorValueInput, Field, TextInput } from '../Primitives';

export default function BoardCreate({ projectId, onCreated, setError }: any) {
  const [form, setForm] = useState({ name: '', color: '#595959', wip_limit: '', is_done: false });
  const submit = async (event: any) => {
    event.preventDefault();

    try {
      await post(`/app-api/projects/${projectId}/boards`, {
        ...form,
        wip_limit: form.wip_limit ? Number(form.wip_limit) : null,
      });

      onCreated();
    } catch (requestError: any) {
      setError(requestError.message);
    }
  };

  return (
    <form className="grid gap-4" onSubmit={submit}>
      <Field label="Name">
        <TextInput
          required
          value={form.name}
          onChange={(event: any) => setForm({ ...form, name: event.target.value })}
        />
      </Field>
      <Field
        label="Stored color value"
        hint="Metadata only. The Okatana interface uses one functional accent color."
      >
        <ColorValueInput
          value={form.color}
          onChange={(color: string) => setForm({ ...form, color })}
          ariaLabel="Choose phase color"
        />
      </Field>
      <Field label="WIP limit" hint="Optional">
        <TextInput
          type="number"
          min="1"
          value={form.wip_limit}
          onChange={(event: any) => setForm({ ...form, wip_limit: event.target.value })}
        />
      </Field>
      <Check
        checked={form.is_done}
        onChange={(event: any) => setForm({ ...form, is_done: event.target.checked })}
      >
        Tickets in this phase count as completed
      </Check>
      <div className="mt-4">
        <Button type="submit" variant="primary">
          Create phase
        </Button>
      </div>
    </form>
  );
}
