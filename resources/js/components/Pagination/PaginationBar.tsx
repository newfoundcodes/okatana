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

import { Button, Field, Select } from '../Primitives';
import { PAGE_SIZE_OPTIONS } from '../../lib/pagination';

export default function PaginationBar({
  page,
  perPage,
  total,
  onPageChange,
  onPerPageChange,
  label = 'Entries',
  compact = false,
}: PaginationBarProps) {
  if (total <= 10) {
    return null;
  }

  const lastPage = Math.max(1, Math.ceil(total / perPage));
  const currentPage = Math.min(Math.max(1, page), lastPage);
  const from = total ? (currentPage - 1) * perPage + 1 : 0;
  const to = Math.min(currentPage * perPage, total);

  return (
    <nav
      aria-label={`${label} pagination`}
      className={compact ? 'mt-4 border-t border-line pt-4' : 'mt-6 border-t border-line pt-4'}
    >
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div className="text-sm tabular-nums text-ink-muted">
          Showing {from}–{to} of {total}
        </div>
        <div className="flex flex-wrap items-end gap-4">
          <Field label="Rows per page" className="w-32">
            <Select
              value={perPage}
              onChange={(event) => onPerPageChange(Number(event.target.value))}
            >
              {PAGE_SIZE_OPTIONS.map((size) => (
                <option key={size} value={size}>
                  {size}
                </option>
              ))}
            </Select>
          </Field>
          <div className="flex min-h-10 items-center gap-2">
            <Button
              compact
              disabled={currentPage <= 1}
              onClick={() => onPageChange(currentPage - 1)}
            >
              Previous
            </Button>
            <span className="px-2 text-sm tabular-nums text-ink-muted">
              Page {currentPage} of {lastPage}
            </span>
            <Button
              compact
              disabled={currentPage >= lastPage}
              onClick={() => onPageChange(currentPage + 1)}
            >
              Next
            </Button>
          </div>
        </div>
      </div>
    </nav>
  );
}
