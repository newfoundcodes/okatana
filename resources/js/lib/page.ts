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

export const tabs = [
  { value: 'articles', label: 'Articles' },
  { value: 'favorites', label: 'Favorites' },
  { value: 'audit', label: 'Audit logs' },
  { value: 'integrations', label: 'Integrations' },
];

export const documentEvents = [
  'document.created',
  'document.updated',
  'document.published',
  'document.drafted',
  'document.archived',
  'document.unarchived',
  'document.deleted',
  'document.comment.created',
  'document.comment.updated',
  'document.comment.deleted',
];

export function integrationMatches(row: IntegrationRow, query: string) {
  return [
    row.name,
    row.public_id,
    row.url,
    row.project?.name,
    ...(row.scopes || []),
    ...(row.events || []),
  ].some((value) =>
    String(value || '')
      .toLowerCase()
      .includes(query),
  );
}

export function requestedTab() {
  const value = new URLSearchParams(location.search).get('tab');
  return tabs.some((tab) => tab.value === value) ? value : 'articles';
}
