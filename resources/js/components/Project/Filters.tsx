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

import { activeFilterCount, priorities } from '../../lib/project';
import {
  Button,
  Check,
  Field,
  GridLayout,
  Panel,
  SectionHeading,
  Select,
  TextInput,
} from '../Primitives';

export default function Filters({ open, filters, setFilters, info, context = 'Board' }: any) {
  const activeCount = activeFilterCount(filters);
  const toggleBoard = (boardId: string) =>
    setFilters({
      ...filters,
      hiddenBoards: filters.hiddenBoards.includes(boardId)
        ? filters.hiddenBoards.filter((id: string) => id !== boardId)
        : [...filters.hiddenBoards, boardId],
    });

  if (!open) {
    return null;
  }

  return (
    <Panel className="mb-8">
      <SectionHeading
        title="Filter tickets"
        description={`${activeCount || 'No'} active filter${activeCount === 1 ? '' : 's'}. ${context} results use these filters; the same filters are shared between Board and Gantt.`}
        actions={
          <Button
            compact
            onClick={() =>
              setFilters({
                q: '',
                priority: '',
                assignee: '',
                label: '',
                tags: [],
                hiddenBoards: [],
                overdue: false,
                archived: false,
              })
            }
          >
            Clear filters
          </Button>
        }
      />
      <GridLayout>
        <Field label="Search" className="col-span-4 md:col-span-4 xl:col-span-4">
          <TextInput
            placeholder="Ticket key or title"
            value={filters.q}
            onChange={(event: any) => setFilters({ ...filters, q: event.target.value })}
          />
        </Field>
        <Field label="Priority" className="col-span-2 md:col-span-2 xl:col-span-2">
          <Select
            value={filters.priority}
            onChange={(event: any) => setFilters({ ...filters, priority: event.target.value })}
          >
            <option value="">All priorities</option>
            {priorities.map((priority) => (
              <option key={priority} value={priority}>
                {priority}
              </option>
            ))}
          </Select>
        </Field>
        <Field label="Assignee" className="col-span-2 md:col-span-2 xl:col-span-2">
          <Select
            value={filters.assignee}
            onChange={(event: any) => setFilters({ ...filters, assignee: event.target.value })}
          >
            <option value="">All assignees</option>
            {(info.assignable_members || info.members).map((member: any) => (
              <option value={member.id} key={member.id}>
                {member.name}
              </option>
            ))}
          </Select>
        </Field>
        <Field label="Label" className="col-span-2 md:col-span-2 xl:col-span-2">
          <Select
            value={filters.label}
            onChange={(event: any) => setFilters({ ...filters, label: event.target.value })}
          >
            <option value="">All labels</option>
            {info.labels.map((label: any) => (
              <option value={label.id} key={label.id}>
                {label.name}
              </option>
            ))}
          </Select>
        </Field>
        <div className="col-span-2 flex flex-col justify-end gap-2 md:col-span-2 xl:col-span-2">
          <Check
            checked={filters.overdue}
            onChange={(event: any) => setFilters({ ...filters, overdue: event.target.checked })}
          >
            Overdue only
          </Check>
          <Check
            checked={filters.archived}
            onChange={(event: any) => setFilters({ ...filters, archived: event.target.checked })}
          >
            Archived only
          </Check>
        </div>
      </GridLayout>

      <div className="mt-6 border-t border-line pt-4">
        <div className="text-sm text-ink-muted">
          Tags <span className="text-xs">(match all selected)</span>
        </div>
        {!info.tags?.length ? (
          <div className="mt-2 text-sm text-ink-muted">No tags are in use yet.</div>
        ) : (
          <div className="mt-2 flex flex-wrap gap-x-6 gap-y-2">
            {info.tags.map((tag: any) => (
              <Check
                key={tag.id}
                checked={filters.tags.includes(tag.id)}
                onChange={() =>
                  setFilters({
                    ...filters,
                    tags: filters.tags.includes(tag.id)
                      ? filters.tags.filter((id: string) => id !== tag.id)
                      : [...filters.tags, tag.id],
                  })
                }
              >
                #{tag.name}{' '}
                <span className="tabular-nums text-ink-muted">({tag.tickets_count})</span>
              </Check>
            ))}
          </div>
        )}
      </div>

      <div className="mt-6 border-t border-line pt-4">
        <div className="text-sm text-ink-muted">Included phases</div>
        <div className="mt-2 flex flex-wrap gap-x-6 gap-y-2">
          {info.boards
            .filter((board: any) => !board.is_hidden)
            .map((board: any) => (
              <Check
                key={board.id}
                checked={!filters.hiddenBoards.includes(board.id)}
                onChange={() => toggleBoard(board.id)}
              >
                {board.name}
              </Check>
            ))}
        </div>
      </div>
    </Panel>
  );
}
