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

import { priorities } from '../../lib/project';
import Bars from '../Chart/Bars';
import ThroughputChart from '../Chart/ThroughputChart';
import { GridLayout, Panel, SectionHeading, Stat, Table, Td, Th } from '../Primitives';

export default function AnalyticsResults({ value }: any) {
  const priorityRows = priorities.map((priority: string) => ({
    priority,
    count: Number(value.by_priority?.[priority] || 0),
  }));

  return (
    <>
      <GridLayout>
        <div className="col-span-2 md:col-span-4 xl:col-span-3">
          <Stat label="Completion" value={`${value.summary.completion_percent}%`} tone="accent" />
        </div>
        <div className="col-span-2 md:col-span-4 xl:col-span-3">
          <Stat label="Total tickets" value={value.summary.total} />
        </div>
        <div className="col-span-2 md:col-span-4 xl:col-span-3">
          <Stat label="Open" value={value.summary.open} />
        </div>
        <div className="col-span-2 md:col-span-4 xl:col-span-3">
          <Stat
            label="Overdue"
            value={value.summary.overdue}
            tone={value.summary.overdue ? 'error' : 'ink'}
          />
        </div>
      </GridLayout>

      <GridLayout>
        <Panel className="col-span-4 md:col-span-8 xl:col-span-8">
          <SectionHeading
            title="Tickets by phase"
            description="Completed phases use the product accent; all other phases remain neutral."
          />
          <Bars rows={value.by_board} />
        </Panel>
        <Panel className="col-span-4 md:col-span-8 xl:col-span-4">
          <SectionHeading title="Priority distribution" />
          <Table>
            <thead>
              <tr>
                <Th className="pr-6">Priority</Th>
                <Th numeric>Tickets</Th>
              </tr>
            </thead>
            <tbody>
              {priorityRows.map((row) => (
                <tr key={row.priority}>
                  <Td className="pr-6 capitalize">{row.priority}</Td>
                  <Td numeric>{row.count}</Td>
                </tr>
              ))}
            </tbody>
          </Table>
        </Panel>
      </GridLayout>

      <Panel>
        <SectionHeading
          title="30-day throughput"
          description="Created tickets use ink; completed tickets use the single product accent."
        />
        <ThroughputChart data={value.daily} />
      </Panel>
    </>
  );
}
