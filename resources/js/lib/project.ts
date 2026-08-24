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

export const priorities = ['lowest', 'low', 'normal', 'high', 'highest', 'critical'];

export const labelMatches = (row: any, query: string) =>
  [row.name, row.color].some((value) =>
    String(value || '')
      .toLowerCase()
      .includes(query),
  );

export const emptyAnalyticsFilters = () => ({
  member_id: '',
  priority: '',
  board_id: '',
  label_id: '',
  tags: [],
  overdue: false,
  archived: false,
});

export function activeFilterCount(filters: any) {
  return (
    [filters.q, filters.priority, filters.assignee, filters.label].filter(Boolean).length +
    filters.tags.length +
    (filters.overdue ? 1 : 0) +
    (filters.archived ? 1 : 0) +
    filters.hiddenBoards.length
  );
}

export function filterTicketRows(rows: any[], filters: any, info: any) {
  return rows.filter((ticket) => {
    const query = filters.q.trim().toLowerCase();
    if (
      query &&
      !`${info?.project?.key}-${ticket.number} ${ticket.title}`.toLowerCase().includes(query)
    ) {
      return false;
    }

    if (filters.priority && ticket.priority !== filters.priority) {
      return false;
    }

    if (
      filters.assignee &&
      !ticket.assignees.some((assignee: any) => assignee.id === filters.assignee)
    ) {
      return false;
    }

    if (filters.label && !ticket.labels.some((label: any) => label.id === filters.label)) {
      return false;
    }

    if (
      filters.tags.length &&
      !filters.tags.every((tagId: string) =>
        (ticket.tags || []).some((tag: any) => tag.id === tagId),
      )
    ) {
      return false;
    }

    if (filters.hiddenBoards.includes(ticket.board_id)) {
      return false;
    }

    if (filters.overdue) {
      const overdue =
        ticket.due_at && new Date(ticket.due_at) < new Date() && !ticket.board?.is_done;

      if (!overdue) {
        return false;
      }
    }

    return true;
  });
}

export function toLocalInput(value: string | Date | null) {
  if (!value) {
    return '';
  }

  const date = new Date(value);
  const z = (n: number) => String(n).padStart(2, '0');

  return `${date.getFullYear()}-${z(date.getMonth() + 1)}-${z(date.getDate())}T${z(date.getHours())}:${z(date.getMinutes())}`;
}
