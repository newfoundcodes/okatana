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

import { useRef, useState } from 'react';
import { del, navigate, patch, post, put } from '../../lib/api';
import { labelMatches } from '../../lib/project';
import ConfirmDialog from '../ConfirmDialog';
import Modal from '../Modal';
import TableSearch from '../Pagination/TableSearch';
import PaginationBar from '../Pagination/PaginationBar';
import { useClientPagination } from '../../lib/pagination';
import {
  Avatar,
  Button,
  Check,
  ColorValueInput,
  EmptyState,
  Field,
  GridLayout,
  Panel,
  SectionHeading,
  Table,
  Td,
  TextArea,
  TextInput,
  Th,
} from '../Primitives';
import BoardsSettings from './BoardsSettings';

export default function ProjectSettings({ info, orgMembers, reload, setError, setNotice }: any) {
  const project = info.project;
  const timelineRef = useRef<any>(null);

  const [form, setForm] = useState({
    name: project.name,
    description: project.description || '',
  });
  const [selected, setSelected] = useState<string[]>(info.members.map((member: any) => member.id));
  const [label, setLabel] = useState({ name: '', color: '#595959' });
  const [editingLabel, setEditingLabel] = useState<any>(null);
  const [deletingLabel, setDeletingLabel] = useState<any>(null);
  const [labelBusy, setLabelBusy] = useState(false);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const labelPager = useClientPagination(info.labels || [], labelMatches);

  const save = async (event: any) => {
    event.preventDefault();

    try {
      await patch(`/app-api/projects/${project.id}`, form);

      setNotice('Project settings saved.');
      reload();
    } catch (requestError: any) {
      setError(requestError.message);
    }
  };

  const sync = async () => {
    try {
      await put(`/app-api/projects/${project.id}/members`, { user_ids: selected });

      setNotice('Project access saved.');
      reload();
    } catch (requestError: any) {
      setError(requestError.message);
    }
  };

  const addLabel = async (event: any) => {
    event.preventDefault();

    try {
      await post(`/app-api/projects/${project.id}/labels`, label);

      setLabel({ name: '', color: '#595959' });
      setNotice('Label created.');
      reload();
    } catch (requestError: any) {
      setError(requestError.message);
    }
  };

  const saveLabel = async (event: any) => {
    event.preventDefault();

    if (!editingLabel) {
      return;
    }

    setLabelBusy(true);
    try {
      await patch(`/app-api/labels/${editingLabel.id}`, {
        name: editingLabel.name,
        color: editingLabel.color,
      });

      setNotice('Label saved.');
      setEditingLabel(null);
      reload();
    } catch (requestError: any) {
      setError(requestError.message);
    } finally {
      setLabelBusy(false);
    }
  };

  const removeLabel = async () => {
    if (!deletingLabel) {
      return;
    }

    setLabelBusy(true);
    try {
      await del(`/app-api/labels/${deletingLabel.id}`);

      setNotice('Label deleted.');
      setDeletingLabel(null);
      reload();
    } catch (requestError: any) {
      setError(requestError.message);
    } finally {
      setLabelBusy(false);
    }
  };

  const saveLabelColor = async (item: any, color: string) => {
    try {
      await patch(`/app-api/labels/${item.id}`, { color });

      setNotice('Label color saved.');
      reload();
    } catch (requestError: any) {
      setError(requestError.message);
    }
  };

  const importTimeline = async (event: any) => {
    const file = event.target.files?.[0];
    if (!file) {
      return;
    }

    try {
      const result = await post(
        `/app-api/organizations/${project.organization_id}/imports/timeline`,
        {
          project_id: project.id,
          payload: JSON.parse(await file.text()),
        },
      );

      setNotice(`Imported ${result.imported_audit_logs} audit records.`);
      reload();
    } catch (requestError: any) {
      setError(requestError.message);
    } finally {
      event.target.value = '';
    }
  };

  const destroy = async () => {
    setDeleting(true);

    try {
      await del(`/app-api/projects/${project.id}`);

      setDeleteOpen(false);
      navigate(`/app/organizations/${project.organization_id}`);
    } catch (requestError: any) {
      setError(requestError.message);
    } finally {
      setDeleting(false);
    }
  };

  return (
    <div className="grid gap-8">
      <GridLayout>
        <Panel className="col-span-4 md:col-span-5 xl:col-span-7">
          <SectionHeading title="Project details" />
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
            <div className="mt-4 flex flex-wrap gap-2">
              <Button type="submit" variant="primary">
                Save project
              </Button>
              <input
                ref={timelineRef}
                hidden
                type="file"
                accept="application/json,.json"
                onChange={importTimeline}
              />
              <Button onClick={() => timelineRef.current?.click()}>Import audit log</Button>
            </div>
          </form>
          <div className="mt-12 border-t border-error pt-6">
            <h3 className="text-md font-semibold text-ink">Delete project</h3>
            <p className="mt-2 text-sm text-ink-muted">
              Deleting the project does not make its protected audit history editable.
            </p>
            <div className="mt-4">
              <Button variant="danger" onClick={() => setDeleteOpen(true)}>
                Delete project
              </Button>
            </div>
          </div>
        </Panel>

        <Panel className="col-span-4 md:col-span-3 xl:col-span-5">
          <SectionHeading
            title="Project access"
            description="Select organization users who can access this project. Organization owners and admins always have access."
          />
          <div className="grid gap-2">
            {orgMembers?.members
              ?.filter((member: any) => member.role === 'user')
              .map((member: any) => (
                <Check
                  key={member.id}
                  checked={selected.includes(member.id)}
                  onChange={() =>
                    setSelected(
                      selected.includes(member.id)
                        ? selected.filter((id: string) => id !== member.id)
                        : [...selected, member.id],
                    )
                  }
                >
                  <span className="flex items-center gap-2">
                    <Avatar user={member} size="sm" />
                    <span>
                      <span className="font-medium">{member.name}</span>
                      <span className="ml-2 text-xs text-ink-muted">{member.email}</span>
                    </span>
                  </span>
                </Check>
              )) || <span className="text-sm text-ink-muted">Loading members…</span>}
          </div>
          <div className="mt-6">
            <Button onClick={sync}>Save project access</Button>
          </div>

          <section className="mt-12 border-t border-line pt-6">
            <SectionHeading
              title="Labels"
              description="Color values remain import/export metadata; the interface renders labels in the neutral palette."
            />
            <form className="grid gap-4" onSubmit={addLabel}>
              <Field label="Label name">
                <TextInput
                  value={label.name}
                  onChange={(event: any) => setLabel({ ...label, name: event.target.value })}
                />
              </Field>
              <Field label="Stored color value">
                <ColorValueInput
                  value={label.color}
                  onChange={(color: string) => setLabel({ ...label, color })}
                  ariaLabel="Choose label color"
                />
              </Field>
              <div>
                <Button type="submit">Create label</Button>
              </div>
            </form>
            <div className="mt-8 border-t border-line pt-6">
              <TableSearch
                value={labelPager.query}
                onChange={labelPager.setQuery}
                label="Search labels"
                placeholder="Label name or stored color value"
              />
              {!labelPager.total ? (
                <EmptyState
                  title="No labels"
                  description={
                    labelPager.query
                      ? 'No labels match the current search.'
                      : 'Create a label to classify tickets in this project.'
                  }
                />
              ) : (
                <>
                  <div className="border-t-2 border-ink">
                    <Table>
                      <thead>
                        <tr>
                          <Th className="pr-6">Label</Th>
                          <Th className="pr-6">Stored color value</Th>
                          <Th numeric className="pr-6">
                            Tickets
                          </Th>
                          <Th>Actions</Th>
                        </tr>
                      </thead>
                      <tbody>
                        {labelPager.rows.map((item: any) => (
                          <tr key={item.id}>
                            <Td className="pr-6 font-medium">{item.name}</Td>
                            <Td className="pr-6">
                              <div className="flex items-center gap-2">
                                <input
                                  type="color"
                                  value={
                                    /^#[0-9a-f]{6}$/i.test(item.color || '')
                                      ? item.color
                                      : '#595959'
                                  }
                                  onChange={(event) => saveLabelColor(item, event.target.value)}
                                  aria-label={`Choose color for ${item.name}`}
                                  title={`Choose color for ${item.name}`}
                                  className="h-8 w-8 shrink-0 cursor-pointer rounded-sm border border-line bg-paper p-2"
                                />
                                <span className="text-xs tabular-nums text-ink-muted">
                                  {item.color || 'Not set'}
                                </span>
                              </div>
                            </Td>
                            <Td numeric className="pr-6 tabular-nums">
                              {item.tickets_count || 0}
                            </Td>
                            <Td>
                              <div className="flex flex-wrap gap-2">
                                <Button compact onClick={() => setEditingLabel({ ...item })}>
                                  Edit
                                </Button>
                                <Button
                                  compact
                                  variant="danger"
                                  onClick={() => setDeletingLabel(item)}
                                >
                                  Delete
                                </Button>
                              </div>
                            </Td>
                          </tr>
                        ))}
                      </tbody>
                    </Table>
                  </div>
                  <PaginationBar
                    label="Project labels"
                    page={labelPager.page}
                    perPage={labelPager.perPage}
                    total={labelPager.total}
                    onPageChange={labelPager.setPage}
                    onPerPageChange={labelPager.setPerPage}
                  />
                </>
              )}
            </div>
          </section>
        </Panel>
      </GridLayout>

      <BoardsSettings info={info} reload={reload} setError={setError} setNotice={setNotice} />
      <Modal open={Boolean(editingLabel)} onClose={() => setEditingLabel(null)} title="Edit label">
        {editingLabel && (
          <form className="grid gap-6" onSubmit={saveLabel}>
            <Field label="Label name">
              <TextInput
                required
                value={editingLabel.name}
                onChange={(event: any) =>
                  setEditingLabel({ ...editingLabel, name: event.target.value })
                }
              />
            </Field>
            <Field label="Stored color value">
              <ColorValueInput
                value={editingLabel.color || ''}
                onChange={(color: string) => setEditingLabel({ ...editingLabel, color })}
                ariaLabel="Choose label color"
              />
            </Field>
            <div className="flex flex-wrap gap-2">
              <Button onClick={() => setEditingLabel(null)}>Cancel</Button>
              <Button type="submit" variant="primary" disabled={labelBusy}>
                {labelBusy ? 'Saving…' : 'Save label'}
              </Button>
            </div>
          </form>
        )}
      </Modal>
      <ConfirmDialog
        open={Boolean(deletingLabel)}
        title="Delete label"
        description={
          deletingLabel
            ? `Delete “${deletingLabel.name}”? It will be removed from tickets that use it.`
            : ''
        }
        confirmLabel="Delete label"
        onCancel={() => setDeletingLabel(null)}
        onConfirm={removeLabel}
        busy={labelBusy}
      />
      <ConfirmDialog
        open={deleteOpen}
        title="Delete project"
        description={`Delete “${project.name}”? The project is soft-deleted and its audit history remains immutable.`}
        confirmLabel="Delete project"
        onCancel={() => setDeleteOpen(false)}
        onConfirm={destroy}
        busy={deleting}
      />
    </div>
  );
}
