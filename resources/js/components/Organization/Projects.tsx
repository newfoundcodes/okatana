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

import { navigate } from '../../lib/api';
import PaginationBar from '../Pagination/PaginationBar';
import TableSearch from '../Pagination/TableSearch';
import { useClientPagination } from '../../lib/pagination';
import { Button, CopyValueButton, EmptyState, Table, Td, Th } from '../Primitives';
import { projectMatches } from '../../lib/organization';

export default function Projects({ projects, admin, onCreate }: any) {
  const pager = useClientPagination(projects, projectMatches);

  if (!projects.length) {
    return (
      <EmptyState
        title="No projects yet"
        description={
          admin
            ? 'Create the first project for this organization.'
            : 'You do not have access to a project yet.'
        }
        action={
          admin ? (
            <Button variant="primary" onClick={onCreate}>
              Create project
            </Button>
          ) : null
        }
      />
    );
  }

  return (
    <section>
      <TableSearch
        value={pager.query}
        onChange={pager.setQuery}
        label="Search projects"
        placeholder="Name, key, project ID, or description"
      />
      {!pager.total ? (
        <EmptyState
          title="No projects match your search"
          description="Change the search text to see other projects."
        />
      ) : (
        <>
          <div className="border-t-2 border-ink">
            <Table>
              <thead>
                <tr>
                  <Th className="pr-6">Project</Th>
                  <Th className="pr-6">Key</Th>
                  <Th className="hidden pr-6 md:table-cell">Description</Th>
                  <Th className="pr-6">Project ID</Th>
                  <Th numeric>Tickets</Th>
                </tr>
              </thead>
              <tbody>
                {pager.rows.map((project: any) => (
                  <tr
                    key={project.id}
                    className="group cursor-pointer"
                    onClick={() => navigate(`/app/projects/${project.id}`)}
                  >
                    <Td className="pr-6">
                      <button
                        type="button"
                        className="text-left font-semibold group-hover:text-accent"
                      >
                        {project.name}
                      </button>
                    </Td>
                    <Td className="pr-6 font-medium tabular-nums">{project.key}</Td>
                    <Td className="hidden pr-6 text-ink-muted md:table-cell">
                      {project.description || 'No description'}
                    </Td>
                    <Td className="pr-6">
                      <div className="flex items-center gap-2">
                        <span className="whitespace-nowrap text-sm tabular-nums">{project.id}</span>
                        <CopyValueButton
                          value={project.id}
                          label={`Copy ${project.name} project ID`}
                        />
                      </div>
                    </Td>
                    <Td numeric>{project.tickets_count}</Td>
                  </tr>
                ))}
              </tbody>
            </Table>
          </div>
          <PaginationBar
            label="Projects"
            page={pager.page}
            perPage={pager.perPage}
            total={pager.total}
            onPageChange={pager.setPage}
            onPerPageChange={pager.setPerPage}
          />
        </>
      )}
    </section>
  );
}
