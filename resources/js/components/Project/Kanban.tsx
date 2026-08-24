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
import { post } from '../../lib/api';
import PaginationBar from '../Pagination/PaginationBar';
import { EmptyState } from '../Primitives';
import TicketCard from './TicketCard';

export default function Kanban({
  info,
  tickets,
  openTicket,
  reload,
  setError,
  setNotice,
  filters,
}: any) {
  const dragged = useRef<any>(null);
  const scrollRef = useRef<any>(null);

  const [dragOver, setDragOver] = useState<any>(null);
  const [boardPages, setBoardPages] = useState<any>({});
  const [boardPerPages, setBoardPerPages] = useState<any>({});

  const autoScroll = (event: any) => {
    const container = scrollRef.current;
    if (!container || !dragged.current || container.scrollWidth <= container.clientWidth) {
      return;
    }

    const rect = container.getBoundingClientRect();
    const threshold = Math.min(80, rect.width * 0.2);
    const pointer = event.clientX;

    let delta = 0;
    if (pointer < rect.left + threshold) {
      const strength = Math.max(0, Math.min(1, (rect.left + threshold - pointer) / threshold));
      delta = -Math.max(8, Math.round(32 * strength));
    } else if (pointer > rect.right - threshold) {
      const strength = Math.max(0, Math.min(1, (pointer - (rect.right - threshold)) / threshold));
      delta = Math.max(8, Math.round(32 * strength));
    }

    if (delta) {
      container.scrollLeft += delta;
    }
  };

  const drop = async (board: any, event: any) => {
    event.preventDefault();

    const ticketId = dragged.current;
    setDragOver(null);

    if (!ticketId) {
      return;
    }

    try {
      await post(`/app-api/tickets/${ticketId}/move`, { board_id: board.id });
      setNotice(`Ticket moved to ${board.name}.`);

      await reload();
    } catch (requestError: any) {
      setError(requestError.message);
    } finally {
      dragged.current = null;
    }
  };

  const boards = info.boards.filter(
    (board: any) => !board.is_hidden && !filters.hiddenBoards.includes(board.id),
  );

  if (!boards.length) {
    return (
      <EmptyState
        title="No phases are visible"
        description="Enable at least one phase in the ticket filters."
      />
    );
  }

  return (
    <div
      ref={scrollRef}
      className="overflow-x-auto pb-8"
      onDragOver={(event) => {
        event.preventDefault();
        autoScroll(event);
      }}
    >
      <div className="flex min-w-max items-start gap-6">
        {boards.map((board: any, boardIndex: number) => {
          const rows = tickets.filter((ticket: any) => ticket.board_id === board.id);
          const perPage = boardPerPages[board.id] || 10;
          const lastPage = Math.max(1, Math.ceil(rows.length / perPage));
          const page = Math.min(boardPages[board.id] || 1, lastPage);
          const pageRows = rows.slice((page - 1) * perPage, page * perPage);
          const atLimit = board.wip_limit && rows.length >= board.wip_limit;
          return (
            <section
              className={`w-80 shrink-0 border-t-2 bg-paper ${dragOver === board.id ? 'border-accent' : 'border-ink'}`}
              key={board.id}
              onDragEnter={() => setDragOver(board.id)}
              onDragLeave={(event: any) =>
                !event.currentTarget.contains(event.relatedTarget) && setDragOver(null)
              }
              onDragOver={(event) => event.preventDefault()}
              onDrop={(event) => drop(board, event)}
              aria-label={`${board.name} phase`}
            >
              <header className="grid grid-cols-4 gap-2 border-b border-line py-4">
                <div className="col-span-3">
                  <div className="text-xs font-medium uppercase tracking-widest text-ink-muted">
                    Phase {String(boardIndex + 1).padStart(2, '0')}
                  </div>
                  <h2 className="mt-2 text-md font-semibold text-ink">{board.name}</h2>
                </div>
                <div
                  className={`col-span-1 text-right text-sm tabular-nums ${atLimit ? 'text-warning' : 'text-ink-muted'}`}
                >
                  {rows.length}
                  {board.wip_limit ? ` / ${board.wip_limit}` : ''}
                </div>
              </header>

              <div>
                {pageRows.map((ticket: any) => (
                  <TicketCard
                    key={ticket.id}
                    ticket={ticket}
                    projectKey={info.project.key}
                    onOpen={() => openTicket(ticket)}
                    onDragStart={() => {
                      dragged.current = ticket.id;
                    }}
                  />
                ))}
                {!rows.length && (
                  <div className="border-b border-line py-8 text-sm text-ink-muted">
                    Drop a ticket into this phase.
                  </div>
                )}
                <PaginationBar
                  compact
                  label={`${board.name} tickets`}
                  page={page}
                  perPage={perPage}
                  total={rows.length}
                  onPageChange={(nextPage) =>
                    setBoardPages((current: any) => ({
                      ...current,
                      [board.id]: nextPage,
                    }))
                  }
                  onPerPageChange={(nextPerPage) => {
                    setBoardPerPages((current: any) => ({
                      ...current,
                      [board.id]: nextPerPage,
                    }));
                    setBoardPages((current: any) => ({ ...current, [board.id]: 1 }));
                  }}
                />
              </div>
            </section>
          );
        })}
      </div>
    </div>
  );
}
