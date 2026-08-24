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
import { del, navigate, patch } from '../../lib/api';
import ConfirmDialog from '../ConfirmDialog';
import {
  Button,
  Field,
  GridLayout,
  Panel,
  SectionHeading,
  TextArea,
  TextInput,
} from '../Primitives';

export default function OrgSettings({ data, owner, reload, setError, setNotice }: any) {
  const [form, setForm] = useState({
    name: data.organization.name,
    description: data.organization.description || '',
  });

  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deleting, setDeleting] = useState(false);

  const save = async (event: any) => {
    event.preventDefault();

    try {
      await patch(`/app-api/organizations/${data.organization.id}`, form);

      setNotice('Organization settings saved.');
      reload();
    } catch (requestError: any) {
      setError(requestError.message);
    }
  };

  const destroy = async () => {
    setDeleting(true);

    try {
      await del(`/app-api/organizations/${data.organization.id}`);

      setDeleteOpen(false);
      navigate('/app');
    } catch (requestError: any) {
      setError(requestError.message);
    } finally {
      setDeleting(false);
    }
  };

  return (
    <GridLayout>
      <Panel className="col-span-4 md:col-span-5 xl:col-span-7">
        <SectionHeading title="Organization settings" />
        <form className="grid gap-4" onSubmit={save}>
          <Field label="Name">
            <TextInput
              value={form.name}
              onChange={(event: any) => setForm({ ...form, name: event.target.value })}
            />
          </Field>
          <Field label="Description">
            <TextArea
              rows={5}
              value={form.description}
              onChange={(event: any) => setForm({ ...form, description: event.target.value })}
            />
          </Field>
          <div className="mt-4">
            <Button type="submit" variant="primary">
              Save changes
            </Button>
          </div>
        </form>
      </Panel>
      {owner && (
        <Panel className="col-span-4 border-error md:col-span-3 xl:col-span-5">
          <SectionHeading
            title="Delete organization"
            description="Only an owner can delete the organization. Audit history remains immutable."
          />
          <Button variant="danger" onClick={() => setDeleteOpen(true)}>
            Delete organization
          </Button>
        </Panel>
      )}
      <ConfirmDialog
        open={deleteOpen}
        title="Delete organization"
        description={`Delete “${data.organization.name}”? The organization is soft-deleted and its protected audit history remains immutable.`}
        confirmLabel="Delete organization"
        onCancel={() => setDeleteOpen(false)}
        onConfirm={destroy}
        busy={deleting}
      />
    </GridLayout>
  );
}
