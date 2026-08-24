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
import { downloadJson, get, navigate } from '../lib/api';
import { toast } from '../lib/toast';
import {
  BreadcrumbButton,
  Breadcrumbs,
  Button,
  EmptyState,
  LoadingState,
  Page,
  PageHeader,
  Tabs,
} from '../components/Primitives';
import { activeFilterCount, emptyAnalyticsFilters, filterTicketRows } from '../lib/project';
import Modal from '../components/Modal';
import GanttChart from '../components/Chart/GanttChart';
import Analytics from '../components/Project/Analytics';
import Audit from '../components/Project/Audit';
import BoardCreate from '../components/Project/BoardCreate';
import Filters from '../components/Project/Filters';
import Kanban from '../components/Project/Kanban';
import ProjectSettings from '../components/Project/ProjectSettings';
import RevisionHistoryDialog from '../components/Project/RevisionHistoryDialog';
import TicketCreate from '../components/Project/TicketCreate';
import TicketDrawer from '../components/Project/TicketDrawer';

export default function ProjectPage({ id, initialTicketNumber = null }) {
  const [info, setInfo] = useState(null);
  const [tickets, setTickets] = useState([]);
  const [tab, setTab] = useState('board');
  const [loadFailed, setLoadFailed] = useState(false);
  const [ticketId, setTicketId] = useState(null);
  const [modal, setModal] = useState(null);
  const [analytics, setAnalytics] = useState(null);
  const [analyticsFilters, setAnalyticsFilters] = useState(emptyAnalyticsFilters);
  const [ganttTickets, setGanttTickets] = useState(null);
  const [audit, setAudit] = useState(null);
  const [auditSearch, setAuditSearch] = useState('');
  const [auditPage, setAuditPage] = useState(1);
  const [auditPerPage, setAuditPerPage] = useState(10);
  const [orgMembers, setOrgMembers] = useState(null);
  const [filters, setFilters] = useState({
    q: '',
    priority: '',
    assignee: '',
    label: '',
    tags: [],
    hiddenBoards: [],
    overdue: false,
    archived: false,
  });
  const [filterOpen, setFilterOpen] = useState(false);
  const [revisionTarget, setRevisionTarget] = useState(null);
  const setError = (message) => {
    if (message) {
      toast.error(message);
    }
  };

  const setNotice = (message) => {
    if (message) {
      toast.success(message);
    }
  };

  const loadInfo = () => get(`/app-api/projects/${id}`).then(setInfo);
  const loadTickets = () =>
    get(`/app-api/projects/${id}/tickets?limit=1000${filters.archived ? '&archived=1' : ''}`).then(
      (data) => setTickets(data.tickets),
    );

  const load = () =>
    Promise.all([loadInfo(), loadTickets()])
      .then(() => setLoadFailed(false))
      .catch((requestError) => {
        setLoadFailed(true);
        setError(requestError.message);
      });

  const admin = ['owner', 'admin'].includes(info?.role);

  const changeAuditSearch = (value) => {
    setAuditSearch(value);
    setAuditPage(1);
  };

  const changeAuditPerPage = (value) => {
    setAuditPerPage(value);
    setAuditPage(1);
  };

  const visibleTickets = useMemo(
    () => filterTicketRows(tickets, filters, info),
    [tickets, filters, info],
  );

  const visibleGanttTickets = useMemo(
    () => filterTicketRows(ganttTickets || [], filters, info),
    [ganttTickets, filters, info],
  );

  useEffect(() => {
    setAnalyticsFilters(emptyAnalyticsFilters());
    load();
  }, [id]);

  useEffect(() => {
    if (!initialTicketNumber) {
      setTicketId(null);
      return;
    }

    setTab('board');
    get(`/app-api/projects/${id}/tickets/by-number/${initialTicketNumber}`)
      .then((data) => setTicketId(data.ticket.id))
      .catch((requestError) => setError(requestError.message));
  }, [id, initialTicketNumber]);

  useEffect(() => {
    if (!info) {
      return;
    }

    loadTickets().catch((requestError) => setError(requestError.message));
  }, [filters.archived]);

  useEffect(() => {
    if (tab === 'analytics') {
      setAnalytics(null);

      const params = new URLSearchParams();
      if (analyticsFilters.member_id) {
        params.set('member_id', analyticsFilters.member_id);
      }

      if (analyticsFilters.priority) {
        params.set('priority', analyticsFilters.priority);
      }

      if (analyticsFilters.board_id) {
        params.set('board_id', analyticsFilters.board_id);
      }

      if (analyticsFilters.label_id) {
        params.set('label_id', analyticsFilters.label_id);
      }

      if (analyticsFilters.tags.length) {
        params.set('tag_ids', analyticsFilters.tags.join(','));
      }

      if (analyticsFilters.overdue) {
        params.set('overdue', '1');
      }

      if (analyticsFilters.archived) {
        params.set('archived', '1');
      }

      const query = params.toString();
      get(`/app-api/projects/${id}/analytics${query ? `?${query}` : ''}`)
        .then(setAnalytics)
        .catch((requestError) => setError(requestError.message));
    }

    if (tab === 'gantt') {
      setGanttTickets(null);

      const archived = filters.archived ? '&archived=1' : '';
      get(`/app-api/projects/${id}/tickets?limit=1000&sort=created_at${archived}`)
        .then((data) => setGanttTickets(data.tickets))
        .catch((requestError) => setError(requestError.message));
    }

    if (tab === 'settings' && info && admin) {
      get(`/app-api/organizations/${info.project.organization_id}/members`)
        .then(setOrgMembers)
        .catch((requestError) => setError(requestError.message));
    }
  }, [
    tab,
    info?.project?.id,
    analyticsFilters.member_id,
    analyticsFilters.priority,
    analyticsFilters.board_id,
    analyticsFilters.label_id,
    analyticsFilters.tags.join(','),
    analyticsFilters.overdue,
    analyticsFilters.archived,
    filters.archived,
  ]);

  useEffect(() => {
    if (tab !== 'audit') {
      return undefined;
    }

    const timer = window.setTimeout(() => {
      const params = new URLSearchParams({
        page: String(auditPage),
        per_page: String(auditPerPage),
      });

      if (auditSearch.trim()) {
        params.set('q', auditSearch.trim());
      }

      get(`/app-api/projects/${id}/audit-logs?${params.toString()}`)
        .then((data) => setAudit(data.audit_logs))
        .catch((requestError) => setError(requestError.message));
    }, 200);

    return () => window.clearTimeout(timer);
  }, [tab, id, auditSearch, auditPage, auditPerPage]);

  if (!info) {
    return (
      <Page>
        {loadFailed ? (
          <EmptyState
            title="Project unavailable"
            description="Reload the page or return to Organizations and try again."
          />
        ) : (
          <LoadingState label="Loading project…" />
        )}
      </Page>
    );
  }

  const tabs = [
    { value: 'board', label: 'Board' },
    { value: 'analytics', label: 'Analytics' },
    { value: 'gantt', label: 'Gantt' },
    { value: 'audit', label: 'Audit log' },
    ...(admin ? [{ value: 'settings', label: 'Project settings' }] : []),
  ];

  return (
    <Page>
      <Breadcrumbs>
        <BreadcrumbButton onClick={() => navigate('/app')}>Organizations</BreadcrumbButton>
        <span>/</span>
        <BreadcrumbButton
          onClick={() => navigate(`/app/organizations/${info.project.organization_id}`)}
        >
          {info.project.organization?.name || 'Organization'}
        </BreadcrumbButton>
        <span>/</span>
        <span className="font-medium text-ink">{info.project.name}</span>
      </Breadcrumbs>

      <PageHeader
        eyebrow={`${info.project.key} · ${info.role}`}
        title={info.project.name}
        description={info.project.description || 'No project description.'}
        compact
        actions={
          <>
            <Button onClick={() => downloadJson(`/app-api/projects/${id}/export`)}>
              Export project
            </Button>
            <Button onClick={() => downloadJson(`/app-api/projects/${id}/timeline/export`)}>
              Export audit log
            </Button>
            <Button variant="primary" onClick={() => setModal('ticket')}>
              Create ticket
            </Button>
            {admin && (
              <Button variant="ink" onClick={() => setModal('board')}>
                Create phase
              </Button>
            )}
          </>
        }
      />

      <Tabs
        value={tab}
        onChange={(value) => setTab(value)}
        items={tabs}
        actions={
          ['board', 'gantt'].includes(tab) ? (
            <Button
              compact
              aria-expanded={filterOpen}
              onClick={() => setFilterOpen((value) => !value)}
            >
              {filterOpen
                ? 'Hide filters'
                : `Filter tickets${activeFilterCount(filters) ? ` (${activeFilterCount(filters)})` : ''}`}
            </Button>
          ) : null
        }
      />

      {tab === 'board' && (
        <>
          <Filters
            open={filterOpen}
            filters={filters}
            setFilters={setFilters}
            info={info}
            context="Board"
          />
          <Kanban
            info={info}
            tickets={visibleTickets}
            openTicket={(ticket) => navigate(`/app/projects/${id}/${ticket.number}`)}
            reload={load}
            setError={setError}
            setNotice={setNotice}
            filters={filters}
          />
        </>
      )}
      {tab === 'analytics' && (
        <Analytics
          value={analytics}
          info={info}
          filters={analyticsFilters}
          setFilters={setAnalyticsFilters}
        />
      )}
      {tab === 'gantt' && (
        <>
          <Filters
            open={filterOpen}
            filters={filters}
            setFilters={setFilters}
            info={info}
            context="Gantt"
          />
          {ganttTickets ? (
            <GanttChart
              tickets={visibleGanttTickets}
              projectKey={info.project.key}
              emptyMessage={
                activeFilterCount(filters)
                  ? 'No tickets match the current Gantt filters.'
                  : 'No active tickets yet — create tickets to build the project timeline.'
              }
            />
          ) : (
            <LoadingState label="Loading Gantt chart…" />
          )}
        </>
      )}
      {tab === 'audit' && (
        <Audit
          value={audit}
          search={auditSearch}
          onSearch={changeAuditSearch}
          page={auditPage}
          perPage={auditPerPage}
          onPage={setAuditPage}
          onPerPage={changeAuditPerPage}
        />
      )}
      {tab === 'settings' && admin && (
        <ProjectSettings
          info={info}
          orgMembers={orgMembers}
          reload={load}
          setError={setError}
          setNotice={setNotice}
        />
      )}

      <Modal open={modal === 'ticket'} onClose={() => setModal(null)} title="Create ticket" wide>
        <TicketCreate
          info={info}
          onCreated={() => {
            setModal(null);
            load();
            setNotice('Ticket created.');
          }}
          setError={setError}
        />
      </Modal>
      <Modal open={modal === 'board'} onClose={() => setModal(null)} title="Create phase">
        <BoardCreate
          projectId={id}
          onCreated={() => {
            setModal(null);
            loadInfo();
            setNotice('Phase created.');
          }}
          setError={setError}
        />
      </Modal>
      <TicketDrawer
        ticketId={ticketId}
        close={() => navigate(`/app/projects/${id}`)}
        info={info}
        reload={load}
        setError={setError}
        setNotice={setNotice}
        onRevision={setRevisionTarget}
      />
      <RevisionHistoryDialog target={revisionTarget} onClose={() => setRevisionTarget(null)} />
    </Page>
  );
}
