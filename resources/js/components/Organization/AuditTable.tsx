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

import { Avatar, EmptyState, Table, Td, Th } from '../Primitives';

export default function AuditTable({ rows, searching = false }: any) {
  if (!rows.length) {
    return (
      <EmptyState
        title={searching ? 'No audit records match your search' : 'No audit records yet'}
        description={
          searching
            ? 'Change the search text to see other audit records.'
            : 'Recorded organization activity will appear here.'
        }
      />
    );
  }

  return (
    <Table>
      <thead>
        <tr>
          <Th numeric className="pr-6">
            Time
          </Th>
          <Th className="pr-6">Project</Th>
          <Th className="pr-6">Event</Th>
          <Th>Actor and subject</Th>
        </tr>
      </thead>
      <tbody>
        {rows.map((row: any) => (
          <tr key={row.id}>
            <Td numeric className="pr-6 whitespace-nowrap text-ink-muted">
              {new Date(row.occurred_at).toLocaleString()}
            </Td>
            <Td className="pr-6 text-ink-muted">{row.project_name || 'Organization'}</Td>
            <Td className="pr-6 font-medium">{row.event}</Td>
            <Td className="text-ink-muted">
              <div className="flex items-center gap-4">
                <Avatar
                  user={{
                    name: row.actor_name || row.actor_type,
                    avatar_url: row.actor_avatar_url,
                  }}
                  size="sm"
                />
                <span>
                  {row.actor_name || row.actor_type} ·{' '}
                  {row.subject_label || row.subject_type || 'Organization'}
                </span>
              </div>
            </Td>
          </tr>
        ))}
      </tbody>
    </Table>
  );
}
