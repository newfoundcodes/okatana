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

import { Avatar } from '../Primitives';
import Priority from './Priority';

export default function TicketCard({ ticket, projectKey, onOpen, onDragStart }: any) {
  return (
    <article
      draggable
      tabIndex={0}
      className="cursor-grab border-b border-line py-4 active:cursor-grabbing"
      onDragStart={onDragStart}
      onClick={onOpen}
      onKeyDown={(event) =>
        event.target === event.currentTarget &&
        (event.key === 'Enter' || event.key === ' ') &&
        onOpen()
      }
    >
      <div className="flex items-start justify-between gap-4">
        <span className="text-xs text-ink-muted tabular-nums">
          {projectKey}-{ticket.number}
        </span>
        <Priority value={ticket.priority} />
      </div>
      <h3 className="mt-2 text-base font-semibold leading-normal text-ink">{ticket.title}</h3>
      <div className="mt-4 flex flex-wrap items-center gap-2 text-xs text-ink-muted">
        <Avatar user={ticket.api_credential || ticket.creator || { name: 'Unknown user' }} size="sm" />
        <span className="truncate">
          Created by{' '}
          <span className="font-medium text-ink">{ticket.api_credential?.name || ticket.creator?.name || 'Unknown user'}</span>
        </span>
      </div>
      {ticket.labels.length > 0 && (
        <div className="mt-4 flex flex-wrap gap-x-4 gap-y-2">
          {ticket.labels.map((label: any) => (
            <span key={label.id} className="border-b border-line text-xs text-ink-muted">
              {label.name}
            </span>
          ))}
        </div>
      )}
      {(ticket.tags || []).length > 0 && (
        <div className="mt-4 flex flex-wrap gap-x-4 gap-y-2" aria-label="Ticket tags">
          {ticket.tags.map((tag: any) => (
            <span key={tag.id} className="text-xs font-medium text-ink-muted">
              #{tag.name}
            </span>
          ))}
        </div>
      )}
      <div className="mt-4 flex min-h-6 items-center justify-between gap-4 text-xs text-ink-muted">
        <span className="tabular-nums">
          {ticket.due_at ? new Date(ticket.due_at).toLocaleDateString() : 'No due date'}
        </span>
        <div
          className="flex"
          aria-label={
            ticket.assignees.map((assignee: any) => assignee.name).join(', ') || 'No assignees'
          }
        >
          {ticket.assignees.slice(0, 3).map((assignee: any, index: number) => (
            <Avatar key={assignee.id} user={assignee} size="sm" className={index ? '-ml-2' : ''} />
          ))}
        </div>
      </div>
    </article>
  );
}
