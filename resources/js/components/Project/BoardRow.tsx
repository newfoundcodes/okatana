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
import { del, patch } from '../../lib/api';
import ConfirmDialog from '../ConfirmDialog';
import {
  Button,
  Check,
  ColorValueInput,
  CopyValueButton,
  Field,
  Select,
  TextInput,
} from '../Primitives';
import DragHandleIcon from './DragHandleIcon';

export default function BoardRow({
  board,
  boards,
  dragOver,
  onDragStart,
  onDragOver,
  onDragLeave,
  onDrop,
  onDragEnd,
  moveByKeyboard,
  reload,
  setError,
  setNotice,
}: any) {
  const [form, setForm] = useState({
    name: board.name,
    color: board.color || '#595959',
    wip_limit: board.wip_limit || '',
    is_done: Boolean(board.is_done),
    is_hidden: Boolean(board.is_hidden),
  });

  const [target, setTarget] = useState('');
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deleting, setDeleting] = useState(false);

  const save = async () => {
    try {
      await patch(`/app-api/boards/${board.id}`, {
        ...form,
        wip_limit: form.wip_limit ? Number(form.wip_limit) : null,
      });

      setNotice(`Saved ${form.name}.`);
      reload();
    } catch (requestError: any) {
      setError(requestError.message);
    }
  };

  const remove = async () => {
    setDeleting(true);

    try {
      await del(`/app-api/boards/${board.id}`, { move_to_board_id: target || null });

      setDeleteOpen(false);
      setNotice(`${board.name} deleted.`);

      reload();
    } catch (requestError: any) {
      setError(requestError.message);
    } finally {
      setDeleting(false);
    }
  };

  useEffect(() => {
    setForm({
      name: board.name,
      color: board.color || '#595959',
      wip_limit: board.wip_limit || '',
      is_done: Boolean(board.is_done),
      is_hidden: Boolean(board.is_hidden),
    });
  }, [board.id, board.name, board.color, board.wip_limit, board.is_done, board.is_hidden]);

  return (
    <div
      className={`grid grid-cols-4 gap-6 border-b py-6 md:grid-cols-8 xl:grid-cols-12 ${dragOver ? 'border-accent' : 'border-line'}`}
      onDragOver={onDragOver}
      onDragLeave={onDragLeave}
      onDrop={onDrop}
    >
      <div className="col-span-4 flex items-end md:col-span-2 xl:col-span-1">
        <Button
          compact
          draggable
          className="w-10 cursor-grab justify-center px-0 active:cursor-grabbing"
          title="Reorder phase"
          onDragStart={onDragStart}
          onDragEnd={onDragEnd}
          onKeyDown={(event: any) => {
            if (event.key === 'ArrowUp') {
              event.preventDefault();
              moveByKeyboard(-1);
            }
            if (event.key === 'ArrowDown') {
              event.preventDefault();
              moveByKeyboard(1);
            }
          }}
          aria-label={`Drag ${form.name} to reorder. Use Up or Down arrow keys for keyboard reordering.`}
        >
          <DragHandleIcon />
        </Button>
      </div>
      <div className="col-span-4 md:col-span-4 xl:col-span-2">
        <Field label="Name">
          <TextInput
            value={form.name}
            onChange={(event: any) => setForm({ ...form, name: event.target.value })}
          />
        </Field>
        <div className="mt-2 flex min-w-0 items-center gap-2 text-xs text-ink-muted">
          <span className="shrink-0">Key</span>
          <span className="min-w-0 truncate font-medium tabular-nums text-ink" title={board.slug}>
            {board.slug}
          </span>
          <CopyValueButton value={board.slug} label={`Copy ${form.name} phase key`} />
        </div>
      </div>
      <Field label="Stored color value" className="col-span-2 md:col-span-2 xl:col-span-2">
        <ColorValueInput
          value={form.color}
          onChange={(color: string) => setForm({ ...form, color })}
          ariaLabel={`Choose color for ${form.name}`}
        />
      </Field>
      <Field label="WIP limit" className="col-span-2 md:col-span-2 xl:col-span-2">
        <TextInput
          type="number"
          min="1"
          value={form.wip_limit}
          onChange={(event: any) => setForm({ ...form, wip_limit: event.target.value })}
        />
      </Field>
      <div className="col-span-2 grid content-end gap-2 md:col-span-2 xl:col-span-2">
        <Check
          checked={form.is_done}
          onChange={(event: any) => setForm({ ...form, is_done: event.target.checked })}
        >
          Completed phase
        </Check>
        <Check
          checked={form.is_hidden}
          onChange={(event: any) => setForm({ ...form, is_hidden: event.target.checked })}
        >
          Hidden phase
        </Check>
      </div>
      <div className="col-span-2 flex flex-wrap content-end gap-2 md:col-span-6 xl:col-span-3">
        <Button compact variant="primary" onClick={save}>
          Save phase
        </Button>
        {board.tickets_count > 0 && (
          <Select
            className="min-w-40"
            value={target}
            onChange={(event: any) => setTarget(event.target.value)}
          >
            <option value="">Move tickets to…</option>
            {boards
              .filter((other: any) => other.id !== board.id)
              .map((other: any) => (
                <option key={other.id} value={other.id}>
                  {other.name}
                </option>
              ))}
          </Select>
        )}
        <Button compact variant="danger" onClick={() => setDeleteOpen(true)}>
          Delete phase
        </Button>
      </div>
      <ConfirmDialog
        open={deleteOpen}
        title="Delete phase"
        description={
          board.tickets_count > 0 && !target
            ? `Select a destination phase before deleting “${board.name}”.`
            : `Delete “${board.name}”?${board.tickets_count > 0 ? ' Existing tickets will be moved to the selected destination phase.' : ''}`
        }
        confirmLabel="Delete phase"
        onCancel={() => setDeleteOpen(false)}
        onConfirm={remove}
        busy={deleting}
        confirmDisabled={board.tickets_count > 0 && !target}
      />
    </div>
  );
}
