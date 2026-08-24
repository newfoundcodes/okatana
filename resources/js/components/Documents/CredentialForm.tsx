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
import Modal from '../Modal';
import { Button, Check, Field, TextInput } from '../Primitives';

export default function CredentialForm({ busy, onCancel, onSubmit }: any) {
  const [form, setForm] = useState({ name: '', scopes: ['documents:read'], expires_at: '' });
  const scopes = ['documents:read', 'documents:write', 'document_comments:write'];
  const toggle = (scope: string) =>
    setForm({
      ...form,
      scopes: form.scopes.includes(scope)
        ? form.scopes.filter((value) => value !== scope)
        : [...form.scopes, scope],
    });

  return (
    <Modal open onClose={onCancel} title="Create document API credential">
      <form
        className="grid gap-6"
        onSubmit={(event) => {
          event.preventDefault();
          onSubmit({ ...form, expires_at: form.expires_at || null });
        }}
      >
        <Field label="Name">
          <TextInput
            value={form.name}
            required
            onChange={(event: any) => setForm({ ...form, name: event.target.value })}
          />
        </Field>
        <div>
          <div className="mb-2 text-sm text-ink-muted">Scopes</div>
          <div className="grid gap-2">
            {scopes.map((scope) => (
              <Check
                key={scope}
                checked={form.scopes.includes(scope)}
                onChange={() => toggle(scope)}
              >
                {scope}
              </Check>
            ))}
          </div>
        </div>
        <Field label="Expires at" hint="Optional">
          <TextInput
            type="datetime-local"
            value={form.expires_at}
            onChange={(event: any) => setForm({ ...form, expires_at: event.target.value })}
          />
        </Field>
        <div className="flex gap-2">
          <Button onClick={onCancel}>Cancel</Button>
          <Button type="submit" variant="primary" disabled={busy || !form.scopes.length}>
            {busy ? 'Creating…' : 'Create credential'}
          </Button>
        </div>
      </form>
    </Modal>
  );
}
