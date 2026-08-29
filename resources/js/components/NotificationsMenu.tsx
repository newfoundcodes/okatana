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

import { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { get, navigate, post } from '../lib/api';
import { toast } from '../lib/toast';
import { Avatar, Field, InlineAction, TextInput } from './Primitives';
import PaginationBar from './Pagination/PaginationBar';
import BellIcon from './BellIcon';
import InvitationRespondModal from './InvitationRespondModal';

export default function NotificationsMenu() {
  const [open, setOpen] = useState(false);
  const [rows, setRows] = useState<NotificationRow[]>([]);
  const [unread, setUnread] = useState(0);
  const [query, setQueryState] = useState('');
  const [page, setPage] = useState(1);
  const [perPage, setPerPageState] = useState(10);
  const [pagination, setPagination] = useState({
    current_page: 1,
    per_page: 10,
    total: 0,
    last_page: 1,
  });
  const [position, setPosition] = useState({ top: 0, right: 0 });
  const [respondingInvitation, setRespondingInvitation] = useState<NotificationRow | null>(null);

  const triggerRef = useRef<HTMLButtonElement>(null);
  const panelRef = useRef<HTMLElement>(null);
  const requestStateRef = useRef({ query: '', page: 1, perPage: 10 });

  useEffect(() => {
    requestStateRef.current = { query, page, perPage };
  }, [query, page, perPage]);

  const load = useCallback(() => {
    const state = requestStateRef.current;
    const params = new URLSearchParams({
      page: String(state.page),
      per_page: String(state.perPage),
    });

    if (state.query.trim()) {
      params.set('q', state.query.trim());
    }

    return get(`/app-api/notifications?${params.toString()}`)
      .then((data: any) => {
        const nextPagination = data.pagination || {
          current_page: 1,
          per_page: state.perPage,
          total: (data.notifications || []).length,
          last_page: 1,
        };

        setRows(data.notifications || []);
        setUnread(data.unread_count || 0);
        setPagination(nextPagination);

        if (nextPagination.current_page !== state.page) {
          setPage(nextPagination.current_page || 1);
        }
      })
      .catch(() => {});
  }, []);

  const updatePosition = () => {
    const rect = triggerRef.current?.getBoundingClientRect();
    if (!rect) {
      return;
    }

    setPosition({ top: rect.bottom + 8, right: Math.max(32, window.innerWidth - rect.right) });
  };

  useEffect(() => {
    load();
    const timer = setInterval(load, 30000);
    const onFocus = () => load();

    window.addEventListener('focus', onFocus);
    return () => {
      clearInterval(timer);
      window.removeEventListener('focus', onFocus);
    };
  }, [load]);

  useEffect(() => {
    if (!open) {
      return undefined;
    }

    const timer = window.setTimeout(load, query.trim() ? 250 : 0);
    return () => window.clearTimeout(timer);
  }, [open, query, page, perPage, load]);

  useEffect(() => {
    if (!open) {
      return undefined;
    }

    updatePosition();

    const onPointer = (event: PointerEvent) => {
      const target = event.target as Node;
      if (!triggerRef.current?.contains(target) && !panelRef.current?.contains(target)) {
        setOpen(false);
      }
    };

    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault();

        setOpen(false);
        requestAnimationFrame(() => triggerRef.current?.focus());
      }
    };

    const onViewport = () => updatePosition();
    document.addEventListener('pointerdown', onPointer);
    document.addEventListener('keydown', onKey);
    window.addEventListener('resize', onViewport);
    window.addEventListener('scroll', onViewport, true);

    return () => {
      document.removeEventListener('pointerdown', onPointer);
      document.removeEventListener('keydown', onKey);
      window.removeEventListener('resize', onViewport);
      window.removeEventListener('scroll', onViewport, true);
    };
  }, [open]);

  const setQuery = (value: string) => {
    setQueryState(value);
    setPage(1);
  };

  const setPerPage = (value: number | string) => {
    setPerPageState(Number(value));
    setPage(1);
  };

  const markRowRead = async (row: NotificationRow) => {
    if (!row.read_at) {
      try {
        const data = await post(`/app-api/notifications/${row.id}/read`, {});

        setUnread(data.unread_count || 0);
        setRows((current) =>
          current.map((item) =>
            item.id === row.id ? { ...item, read_at: new Date().toISOString() } : item,
          ),
        );
      } catch {}
    }
  };

  const openNotification = async (row: NotificationRow) => {
    await markRowRead(row);
    if (row.data?.kind === 'invitation.received' && row.data?.invitation_id) {
      setOpen(false);
      setRespondingInvitation(row);
      return;
    }

    setOpen(false);

    const url = row.data?.url;
    if (typeof url === 'string' && /^\/app(?:\/|$)/.test(url)) {
      navigate(url);
    }
  };

  const markAll = async () => {
    try {
      await post('/app-api/notifications/read-all', {});

      setUnread(0);
      setRows((current) =>
        current.map((item) => ({
          ...item,
          read_at: item.read_at || new Date().toISOString(),
        })),
      );
    } catch {}
  };

  const handleInvitationResponded = (
    status: 'accepted' | 'declined',
    organizationId?: string,
    projectId?: string,
  ) => {
    setRespondingInvitation(null);
    load();

    if (status === 'accepted') {
      toast.success('Invitation accepted. Welcome to the workspace!');
      if (projectId) {
        navigate(`/app/projects/${projectId}`);
      } else if (organizationId) {
        navigate(`/app/organizations/${organizationId}`);
      }
    } else {
      toast.success('Invitation declined.');
    }
  };

  const panel = open ? (
    <section
      ref={panelRef}
      aria-label="Notifications"
      style={{ top: position.top, right: position.right }}
      className="fixed z-50 w-[min(30rem,calc(100vw-4rem))] rounded-sm border border-line-strong bg-paper shadow"
    >
      <header className="border-b border-line p-4">
        <h2 className="text-md font-semibold text-ink">Notifications</h2>
        <div className="mt-2 flex flex-wrap items-center gap-2 text-xs text-ink-muted">
          <span className="tabular-nums">{unread} unread</span>
          {unread > 0 && (
            <>
              <span aria-hidden="true">·</span>
              <InlineAction onClick={markAll}>Mark all as read</InlineAction>
            </>
          )}
        </div>
        <Field label="Search notifications" className="mt-4">
          <TextInput
            type="search"
            value={query}
            placeholder="Title or message"
            onChange={(event) => setQuery(event.target.value)}
          />
        </Field>
      </header>
      <div className="max-h-[52vh] overflow-y-auto">
        {!rows.length ? (
          <div className="p-6 text-sm text-ink-muted">
            {query.trim() ? 'No notifications match your search.' : 'No notifications yet.'}
          </div>
        ) : (
          rows.map((row) => (
            <button
              key={row.id}
              type="button"
              onClick={() => openNotification(row)}
              className={`grid w-full grid-cols-[auto_1fr] gap-4 border-b border-line p-4 text-left hover:bg-surface ${row.read_at ? 'bg-paper' : 'border-l-2 border-l-accent'}`}
            >
              <Avatar user={row.data?.actor || { name: 'Okatana' }} size="sm" />
              <span className="min-w-0">
                <span className="block text-sm font-semibold text-ink">
                  {row.data?.title || 'Okatana notification'}
                </span>
                <span className="mt-2 block text-sm text-ink-muted">{row.data?.body || ''}</span>
                {row.data?.kind === 'invitation.received' && (
                  <span className="mt-2 block text-xs font-medium text-accent">
                    Click to review invitation
                  </span>
                )}
                <time className="mt-2 block text-xs text-ink-muted tabular-nums">
                  {new Date(row.created_at).toLocaleString()}
                </time>
              </span>
            </button>
          ))
        )}
      </div>
      <div className="px-4 pb-4">
        <PaginationBar
          compact
          label="Notifications"
          page={pagination.current_page || page}
          perPage={pagination.per_page || perPage}
          total={pagination.total || 0}
          onPageChange={setPage}
          onPerPageChange={setPerPage}
        />
      </div>
    </section>
  ) : null;

  return (
    <div className="relative">
      <button
        ref={triggerRef}
        type="button"
        aria-label={`Notifications${unread ? `, ${unread} unread` : ''}`}
        aria-expanded={open}
        onClick={() => setOpen((value) => !value)}
        className="inline-flex min-h-10 items-center gap-2 rounded-sm border border-transparent bg-transparent px-4 py-2 text-sm font-medium text-ink hover:border-line hover:bg-surface"
      >
        <span className="relative inline-grid h-6 w-6 place-items-center" aria-hidden="true">
          <BellIcon />
          {unread > 0 && <span className="absolute right-0 top-0 h-2 w-2 rounded-full bg-accent" />}
        </span>
        <span>Notifications</span>
      </button>
      {panel && createPortal(panel, document.body)}

      {respondingInvitation && respondingInvitation.data?.invitation_id && (
        <InvitationRespondModal
          invitationId={respondingInvitation.data.invitation_id}
          organizationName={respondingInvitation.data.invitation_organization ?? ''}
          projectName={respondingInvitation.data.invitation_project ?? null}
          role={respondingInvitation.data.invitation_role ?? ''}
          inviterName={
            respondingInvitation.data.invitation_inviter ??
            respondingInvitation.data.actor?.name ??
            'A team member'
          }
          expiresAt={respondingInvitation.data.invitation_expires_at ?? ''}
          onClose={() => setRespondingInvitation(null)}
          onResponded={handleInvitationResponded}
        />
      )}
    </div>
  );
}
