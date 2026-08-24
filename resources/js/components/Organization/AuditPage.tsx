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
import { LoadingState, Panel, SectionHeading } from '../Primitives';
import AuditTable from './AuditTable';

export default function AuditPage({
  data,
  search,
  onSearch,
  page,
  perPage,
  onPage,
  onPerPage,
}: any) {
  if (!data) {
    return <LoadingState label="Loading audit log…" />;
  }

  const rows = data.data || [];
  return (
    <Panel>
      <SectionHeading
        title="Immutable audit log"
        description="Okatana exposes no update or delete action for these records. Database triggers also reject audit-log mutation."
      />
      <TableSearch
        value={search}
        onChange={onSearch}
        label="Search audit log"
        placeholder="Event, project, actor, or subject"
      />
      <AuditTable rows={rows} searching={Boolean(search.trim())} />
      <PaginationBar
        label="Audit records"
        page={page}
        perPage={perPage}
        total={Number(data.total || 0)}
        onPageChange={onPage}
        onPerPageChange={onPerPage}
      />
    </Panel>
  );
}
