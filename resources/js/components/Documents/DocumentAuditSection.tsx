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

import PaginationBar from '../Pagination/PaginationBar';
import TableSearch from '../Pagination/TableSearch';
import { Avatar, EmptyState, LoadingState, Table, Td, Th } from '../Primitives';

export default function DocumentAuditSection({ audit, query, onQuery, onPage, onPerPage }: any) {
  if (!audit) {
    return <LoadingState label="Loading document audit log…" />;
  }

  return (
    <section aria-label="Document audit log">
      <TableSearch
        value={query}
        onChange={onQuery}
        label="Search audit log"
        placeholder="Event, actor, or article"
      />
      {!audit.pagination.total ? (
        <EmptyState
          title="No document audit events"
          description="Document activity will appear here as the knowledge base changes."
        />
      ) : (
        <>
          <div className="border-t-2 border-ink">
            <Table>
              <thead>
                <tr>
                  <Th className="pr-6">Time</Th>
                  <Th className="pr-6">Event</Th>
                  <Th>Actor and subject</Th>
                </tr>
              </thead>
              <tbody>
                {audit.audit_logs.map((row: any) => (
                  <tr key={row.id}>
                    <Td className="pr-6 whitespace-nowrap tabular-nums">
                      {new Date(row.occurred_at).toLocaleString()}
                    </Td>
                    <Td className="pr-6">{row.event}</Td>
                    <Td>
                      <div className="flex items-center gap-2">
                        <Avatar
                          user={{
                            name: row.actor_name || 'System',
                            avatar_url: row.actor_avatar_url,
                          }}
                          size="sm"
                        />
                        <span>
                          {row.actor_name || 'System'} · {row.subject_label || 'Document activity'}
                        </span>
                      </div>
                    </Td>
                  </tr>
                ))}
              </tbody>
            </Table>
          </div>
          <PaginationBar
            label="Document audit events"
            page={audit.pagination.current_page}
            perPage={audit.pagination.per_page}
            total={audit.pagination.total}
            onPageChange={onPage}
            onPerPageChange={onPerPage}
          />
        </>
      )}
    </section>
  );
}
