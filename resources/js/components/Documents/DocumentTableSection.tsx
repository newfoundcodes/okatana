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
import { MouseEvent } from 'react';
import { Avatar, Button, EmptyState, LoadingState, Table, Td, Th } from '../Primitives';

export default function DocumentTableSection({
  result,
  query,
  onQuery,
  onPage,
  onPerPage,
  onFavorite,
  onArchive,
  onDelete,
  favorites,
}: any) {
  if (!result) {
    return <LoadingState label={`Loading ${favorites ? 'favorites' : 'articles'}…`} />;
  }

  return (
    <section aria-label={favorites ? 'Favorite articles' : 'Articles'}>
      <TableSearch
        value={query}
        onChange={onQuery}
        label={favorites ? 'Search favorites' : 'Search articles'}
        placeholder="Title, caption, tag, author, organization, project, or status"
      />
      {!result.pagination.total ? (
        <EmptyState
          title={
            query
              ? 'No articles match your search'
              : favorites
                ? 'No saved articles'
                : 'No articles yet'
          }
          description={
            favorites
              ? 'Use Save on an article to keep it in this list.'
              : 'Create a document to start the knowledge base.'
          }
        />
      ) : (
        <>
          <div className="border-t-2 border-ink">
            <Table>
              <thead>
                <tr>
                  <Th className="pr-6">Title</Th>
                  <Th className="hidden pr-6 md:table-cell">Short description</Th>
                  <Th className="pr-6">Author</Th>
                  <Th className="pr-6">Date of publication</Th>
                  <Th className="w-80">Actions</Th>
                </tr>
              </thead>
              <tbody>
                {result.documents.map((document: any) => (
                  <tr
                    key={document.id}
                    className="group cursor-pointer"
                    onClick={() => navigate(`/app/documents/${document.id}`)}
                  >
                    <Td className="pr-6">
                      <button
                        type="button"
                        className="text-left font-semibold text-ink group-hover:text-accent"
                      >
                        {document.title}
                      </button>
                      <div className="mt-2 text-xs text-ink-muted">
                        {document.archived_at
                          ? 'Archived'
                          : document.status === 'draft'
                            ? 'Draft'
                            : document.project?.name || document.organization?.name}
                      </div>
                      {document.tags?.length ? (
                        <div
                          className="mt-2 flex flex-wrap gap-x-4 gap-y-2"
                          aria-label="Article tags"
                        >
                          {document.tags.map((tag: any) => (
                            <span key={tag.id} className="text-xs font-medium text-ink-muted">
                              #{tag.name}
                            </span>
                          ))}
                        </div>
                      ) : null}
                    </Td>
                    <Td className="hidden pr-6 text-ink-muted md:table-cell">
                      {document.caption || 'No short description'}
                    </Td>
                    <Td className="pr-6">
                      <div className="flex items-center gap-2">
                        <Avatar
                          user={
                            document.api_credential ||
                            document.author || {
                              name: 'Unknown author',
                            }
                          }
                          size="sm"
                        />
                        <span>{document.api_credential?.name || document.author?.name || 'Unknown author'}</span>
                      </div>
                    </Td>
                    <Td className="pr-6 tabular-nums">
                      {document.published_at
                        ? new Date(document.published_at).toLocaleString()
                        : 'Draft'}
                    </Td>
                    <Td onClick={(event: MouseEvent) => event.stopPropagation()}>
                      <div className="flex flex-wrap gap-2">
                        {document.can_edit && (
                          <Button
                            compact
                            onClick={() => navigate(`/app/documents/${document.id}/edit`)}
                          >
                            Edit
                          </Button>
                        )}
                        {document.can_delete && (
                          <Button compact variant="danger" onClick={() => onDelete(document)}>
                            Delete
                          </Button>
                        )}
                        {document.can_edit && (
                          <Button compact onClick={() => onArchive(document)}>
                            {document.archived_at ? 'Unarchive' : 'Archive'}
                          </Button>
                        )}
                        <Button compact onClick={() => onFavorite(document)}>
                          {document.is_favorite ? 'Unsave' : 'Save'}
                        </Button>
                      </div>
                    </Td>
                  </tr>
                ))}
              </tbody>
            </Table>
          </div>
          <PaginationBar
            label={favorites ? 'Favorite articles' : 'Articles'}
            page={result.pagination.current_page}
            perPage={result.pagination.per_page}
            total={result.pagination.total}
            onPageChange={onPage}
            onPerPageChange={onPerPage}
          />
        </>
      )}
    </section>
  );
}
