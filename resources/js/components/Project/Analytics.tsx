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

import { emptyAnalyticsFilters, priorities } from '../../lib/project';
import {
  Button,
  Check,
  Field,
  GridLayout,
  LoadingState,
  Panel,
  SectionHeading,
  Select,
} from '../Primitives';
import AnalyticsResults from './AnalyticsResults';

export default function Analytics({ value, info, filters, setFilters }: any) {
  const members = info.assignable_members || info.members || [];
  const activeCount =
    [filters.member_id, filters.priority, filters.board_id, filters.label_id].filter(Boolean)
      .length +
    filters.tags.length +
    (filters.overdue ? 1 : 0) +
    (filters.archived ? 1 : 0);

  return (
    <div className="grid gap-8">
      <Panel>
        <SectionHeading
          title="Analytics filters"
          description={`${activeCount || 'No'} active filter${activeCount === 1 ? '' : 's'}. Every KPI and chart below uses the same filtered ticket set.`}
          actions={
            <Button compact onClick={() => setFilters(emptyAnalyticsFilters())}>
              Clear filters
            </Button>
          }
        />
        <GridLayout>
          <Field label="Project member" className="col-span-4 md:col-span-4 xl:col-span-3">
            <Select
              value={filters.member_id}
              onChange={(event: any) => setFilters({ ...filters, member_id: event.target.value })}
            >
              <option value="">All project members</option>
              {members.map((member: any) => (
                <option key={member.id} value={member.id}>
                  {member.name} · {member.email}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Priority" className="col-span-2 md:col-span-2 xl:col-span-2">
            <Select
              value={filters.priority}
              onChange={(event: any) => setFilters({ ...filters, priority: event.target.value })}
            >
              <option value="">All priorities</option>
              {priorities.map((priority: string) => (
                <option key={priority} value={priority}>
                  {priority}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Phase" className="col-span-2 md:col-span-2 xl:col-span-3">
            <Select
              value={filters.board_id}
              onChange={(event: any) => setFilters({ ...filters, board_id: event.target.value })}
            >
              <option value="">All phases</option>
              {info.boards
                .filter((board: any) => !board.is_hidden)
                .map((board: any) => (
                  <option key={board.id} value={board.id}>
                    {board.name}
                  </option>
                ))}
            </Select>
          </Field>
          <Field label="Label" className="col-span-2 md:col-span-3 xl:col-span-2">
            <Select
              value={filters.label_id}
              onChange={(event: any) => setFilters({ ...filters, label_id: event.target.value })}
            >
              <option value="">All labels</option>
              {info.labels.map((label: any) => (
                <option key={label.id} value={label.id}>
                  {label.name}
                </option>
              ))}
            </Select>
          </Field>
          <div className="col-span-2 flex flex-col justify-end gap-2 md:col-span-3 xl:col-span-2">
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
      </Panel>

      {!value ? <LoadingState label="Loading analytics…" /> : <AnalyticsResults value={value} />}
    </div>
  );
}
