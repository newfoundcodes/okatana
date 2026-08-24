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

import { useEffect, useState } from 'react';
import { get, navigate } from '../lib/api';
import { toast } from '../lib/toast';
import PaginationBar from '../components/Pagination/PaginationBar';
import TableSearch from '../components/Pagination/TableSearch';
import {
  CopyValueButton,
  EmptyState,
  LoadingState,
  Page,
  PageHeader,
  Table,
  Td,
  Th,
} from '../components/Primitives';

export default function ProjectsPage() {
  const [result, setResult] = useState(null);
  const [query, setQuery] = useState('');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);
  const [loadFailed, setLoadFailed] = useState(false);

  const changeQuery = (value) => {
    setQuery(value);
    setPage(1);
  };

  const changePerPage = (value) => {
    setPerPage(Number(value));
    setPage(1);
  };

  useEffect(() => {
    const timer = window.setTimeout(() => {
      const params = new URLSearchParams({ page: String(page), per_page: String(perPage) });
      if (query.trim()) {
        params.set('q', query.trim());
      }

      get(`/app-api/projects?${params.toString()}`)
        .then((data) => {
          setResult(data);
          setLoadFailed(false);

          if (data.pagination?.current_page && data.pagination.current_page !== page) {
            setPage(data.pagination.current_page);
          }
        })
        .catch((requestError) => {
          setLoadFailed(true);
          toast.error(requestError.message);
        });
    }, 200);

    return () => window.clearTimeout(timer);
  }, [query, page, perPage]);

  return (
    <Page>
      <PageHeader
        eyebrow="Workspace"
        title="Projects"
        description="All projects you can currently access across your organizations."
      />

      {loadFailed && !result ? (
        <EmptyState
          title="Projects unavailable"
          description="Reload the page to try loading your projects again."
        />
      ) : !result ? (
        <LoadingState label="Loading projects…" />
      ) : (
        <section aria-label="Projects">
          <TableSearch
            value={query}
            onChange={changeQuery}
            label="Search projects"
            placeholder="Project, organization, key, project ID, or description"
          />

          {result.pagination.total ? (
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
                    {result.projects.map((project) => (
                      <tr
                        key={project.id}
                        className="group cursor-pointer"
                        onClick={() => navigate(`/app/projects/${project.id}`)}
                      >
                        <Td className="pr-6">
                          <button
                            type="button"
                            className="text-left font-semibold text-ink group-hover:text-accent"
                          >
                            {project.name}
                          </button>
                          <div className="mt-2 text-xs text-ink-muted">
                            {project.organization?.name || 'Organization'}
                          </div>
                        </Td>
                        <Td className="pr-6 font-medium tabular-nums">{project.key}</Td>
                        <Td className="hidden pr-6 text-ink-muted md:table-cell">
                          {project.description || 'No description'}
                        </Td>
                        <Td className="pr-6">
                          <div className="flex items-center gap-2">
                            <span className="whitespace-nowrap text-sm tabular-nums">
                              {project.id}
                            </span>
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
                page={result.pagination.current_page}
                perPage={result.pagination.per_page}
                total={result.pagination.total}
                onPageChange={setPage}
                onPerPageChange={changePerPage}
              />
            </>
          ) : query.trim() ? (
            <EmptyState
              title="No projects match your search"
              description="Change the search text to see other projects."
            />
          ) : (
            <EmptyState
              title="No projects available"
              description="Projects will appear here after you are given project access."
            />
          )}
        </section>
      )}
    </Page>
  );
}
