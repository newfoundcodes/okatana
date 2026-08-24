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

import { useEffect, useMemo, useState } from 'react';

export const PAGE_SIZE_OPTIONS = [10, 20, 50, 100];

export function useClientPagination<T>(
  rows: T[],
  matcher: (row: T, normalizedQuery: string) => boolean,
) {
  const [query, setQueryState] = useState('');
  const [page, setPage] = useState(1);
  const [perPage, setPerPageState] = useState(10);

  const filteredRows = useMemo(() => {
    const normalized = query.trim().toLowerCase();
    if (!normalized) {
      return rows;
    }

    return rows.filter((row) => matcher(row, normalized));
  }, [rows, query, matcher]);

  const total = filteredRows.length;
  const lastPage = Math.max(1, Math.ceil(total / perPage));
  const currentPage = Math.min(page, lastPage);
  const pageRows = useMemo(
    () => filteredRows.slice((currentPage - 1) * perPage, currentPage * perPage),
    [filteredRows, currentPage, perPage],
  );

  const setQuery = (value: string) => {
    setQueryState(value);
    setPage(1);
  };

  const setPerPage = (value: number | string) => {
    setPerPageState(Number(value));
    setPage(1);
  };

  useEffect(() => {
    if (page !== currentPage) {
      setPage(currentPage);
    }
  }, [page, currentPage]);

  return {
    query,
    setQuery,
    page: currentPage,
    setPage,
    perPage,
    setPerPage,
    total,
    lastPage,
    rows: pageRows,
  };
}
