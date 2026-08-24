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
import { get } from '../../lib/api';
import { toast } from '../../lib/toast';
import Modal from '../Modal';
import { Avatar, EmptyState, GridLayout, LoadingState } from '../Primitives';
import Priority from './Priority';
import TicketProperty from './TicketProperty';

export default function RevisionHistoryDialog({ target, onClose }: any) {
  const [data, setData] = useState<any>(null);
  const [loadError, setLoadError] = useState('');

  useEffect(() => {
    if (!target) {
      setData(null);
      setLoadError('');
      return;
    }

    setData(null);
    setLoadError('');

    const endpoint =
      target.type === 'comment'
        ? `/app-api/comments/${target.id}/revisions`
        : `/app-api/tickets/${target.id}/revisions`;

    get(endpoint)
      .then(setData)
      .catch((error: any) => {
        setLoadError(error.message);
        toast.error(error.message);
      });
  }, [target?.type, target?.id]);

  return (
    <Modal
      open={Boolean(target)}
      onClose={onClose}
      title={`${target?.label || 'Item'} revision history`}
      wide
    >
      {loadError ? (
        <EmptyState
          title="Revision history unavailable"
          description="Close this dialog and try opening the revision history again."
        />
      ) : !data ? (
        <LoadingState label="Loading revisions…" />
      ) : (
        <div className="grid gap-8">
          <p className="text-sm text-ink-muted">
            Revisions are immutable saved versions. The newest version appears first.
          </p>
          {!data.revisions.length ? (
            <EmptyState
              title="No revisions recorded"
              description="This item does not have a recorded edit history."
            />
          ) : (
            data.revisions.map((revision: any) => (
              <article key={revision.id} className="border-t-2 border-ink pt-4">
                <header className="flex flex-wrap items-start justify-between gap-4">
                  <div>
                    <div className="text-xs font-medium uppercase tracking-widest text-ink-muted">
                      Revision {revision.version}
                    </div>
                    <div className="mt-2 flex items-center gap-2 text-sm text-ink-muted">
                      <Avatar user={revision.user || { name: 'Unknown editor' }} size="sm" />
                      <span>
                        Saved by{' '}
                        <span className="font-medium text-ink">
                          {revision.user?.name || 'Unknown editor'}
                        </span>
                      </span>
                    </div>
                  </div>
                  <time className="text-xs text-ink-muted tabular-nums">
                    {new Date(revision.created_at).toLocaleString()}
                  </time>
                </header>

                {target?.type === 'ticket' ? (
                  <div className="mt-6 grid gap-6">
                    <GridLayout>
                      <TicketProperty
                        label="Title"
                        value={revision.snapshot?.title || 'Untitled'}
                        wide
                      />
                      <TicketProperty
                        label="Priority"
                        value={<Priority value={revision.snapshot?.priority || 'normal'} />}
                      />
                      <TicketProperty
                        label="Due"
                        value={
                          revision.snapshot?.due_at
                            ? new Date(revision.snapshot.due_at).toLocaleString()
                            : 'No due date'
                        }
                      />
                      <TicketProperty
                        label="Assignees"
                        value={
                          (revision.snapshot?.assignees || [])
                            .map((row: any) => row.name)
                            .join(', ') || 'No assignees'
                        }
                        wide
                      />
                      <TicketProperty
                        label="Labels"
                        value={
                          (revision.snapshot?.labels || [])
                            .map((row: any) => row.name)
                            .join(', ') || 'No labels'
                        }
                        wide
                      />
                      <TicketProperty
                        label="Tags"
                        value={
                          (revision.snapshot?.tags || [])
                            .map((row: any) => `#${row.name}`)
                            .join(', ') || 'No tags'
                        }
                        wide
                      />
                    </GridLayout>
                    <section>
                      <h3 className="mb-4 text-sm font-semibold text-ink">Description</h3>
                      <div
                        className="wysiwyg-content"
                        dangerouslySetInnerHTML={{
                          __html: revision.snapshot?.description_html || '<p>No description.</p>',
                        }}
                      />
                    </section>
                  </div>
                ) : (
                  <div
                    className="wysiwyg-content mt-6"
                    dangerouslySetInnerHTML={{
                      __html: revision.snapshot?.body_html || '<p>No content.</p>',
                    }}
                  />
                )}
              </article>
            ))
          )}
        </div>
      )}
    </Modal>
  );
}
