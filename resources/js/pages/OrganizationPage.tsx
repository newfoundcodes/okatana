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

import { useEffect, useRef, useState } from 'react';
import { downloadJson, get, navigate, post } from '../lib/api';
import { toast } from '../lib/toast';
import Modal from '../components/Modal';
import {
  BreadcrumbButton,
  Breadcrumbs,
  Button,
  EmptyState,
  LoadingState,
  Page,
  PageHeader,
  Panel,
  SectionHeading,
  Tabs,
} from '../components/Primitives';
import { requestedOrganizationTab, organizationTabs } from '../lib/organization';
import Projects from '../components/Organization/Projects';
import ProjectForm from '../components/Organization/ProjectForm';
import InviteForm from '../components/Organization/InviteForm';
import Members from '../components/Organization/Members';
import AuditPage from '../components/Organization/AuditPage';
import Integrations from '../components/Organization/Integrations';
import OrgSettings from '../components/Organization/OrgSettings';

export default function OrganizationPage({ id }) {
  const [data, setData] = useState(null);
  const [tab, setTab] = useState(requestedOrganizationTab);
  const [loadFailed, setLoadFailed] = useState(false);
  const [modal, setModal] = useState(null);
  const [members, setMembers] = useState(null);
  const [hooks, setHooks] = useState(null);
  const [keys, setKeys] = useState(null);
  const [audit, setAudit] = useState(null);
  const [auditSearch, setAuditSearch] = useState('');
  const [auditPage, setAuditPage] = useState(1);
  const [auditPerPage, setAuditPerPage] = useState(10);

  const fileRef = useRef(null);
  const timelineRef = useRef(null);

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

  const load = () =>
    get(`/app-api/organizations/${id}`)
      .then((result) => {
        setData(result);
        setLoadFailed(false);
      })
      .catch((requestError) => {
        setLoadFailed(true);
        setError(requestError.message);
      });

  const admin = ['owner', 'admin'].includes(data?.role);
  const owner = data?.role === 'owner';

  const changeTab = (value) => {
    setTab(value);
    history.replaceState({}, '', `/app/organizations/${id}?tab=${value}`);
  };

  useEffect(() => {
    load();
  }, [id]);

  useEffect(() => {
    if (!data) {
      return;
    }

    const allowed = admin ? organizationTabs : ['projects', 'members', 'audit'];
    const requested = requestedOrganizationTab();
    const next = allowed.includes(requested) ? requested : 'projects';

    setTab(next);
  }, [data?.id, data?.role]);

  useEffect(() => {
    if (!data) {
      return;
    }

    if (tab === 'members') {
      get(`/app-api/organizations/${id}/members`)
        .then(setMembers)
        .catch((requestError) => setError(requestError.message));
    }

    if (tab === 'integrations' && admin) {
      get(`/app-api/organizations/${id}/webhooks`)
        .then(setHooks)
        .catch((requestError) => setError(requestError.message));

      get(`/app-api/organizations/${id}/api-credentials`)
        .then(setKeys)
        .catch((requestError) => setError(requestError.message));
    }
  }, [tab, id]);

  useEffect(() => {
    if (!data || tab !== 'audit') {
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

      get(`/app-api/organizations/${id}/audit-logs?${params.toString()}`)
        .then((result) => setAudit(result.audit_logs))
        .catch((requestError) => setError(requestError.message));
    }, 200);

    return () => window.clearTimeout(timer);
  }, [tab, id, auditSearch, auditPage, auditPerPage]);

  const changeAuditSearch = (value) => {
    setAuditSearch(value);
    setAuditPage(1);
  };

  const changeAuditPerPage = (value) => {
    setAuditPerPage(value);
    setAuditPage(1);
  };

  const createProject = async (form) => {
    try {
      const result = await post(`/app-api/organizations/${id}/projects`, form);

      setModal(null);
      setNotice('Project created.');

      navigate(`/app/projects/${result.project.id}`);
    } catch (requestError) {
      setError(requestError.message);
    }
  };

  const invite = async (form) => {
    try {
      await post(`/app-api/organizations/${id}/invitations`, form);

      setModal(null);
      setNotice('Invitation sent.');

      get(`/app-api/organizations/${id}/members`).then(setMembers);
    } catch (requestError) {
      setError(requestError.message);
    }
  };

  const importProject = async (event) => {
    const file = event.target.files?.[0];
    if (!file) {
      return;
    }

    try {
      const result = await post(`/app-api/organizations/${id}/imports/project`, {
        payload: JSON.parse(await file.text()),
      });

      setNotice('Project imported.');
      navigate(`/app/projects/${result.project.id}`);
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      event.target.value = '';
    }
  };

  const importTimeline = async (event) => {
    const file = event.target.files?.[0];
    if (!file) {
      return;
    }

    try {
      const result = await post(`/app-api/organizations/${id}/imports/timeline`, {
        payload: JSON.parse(await file.text()),
      });

      setNotice(`Imported ${result.imported_audit_logs} audit records.`);
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      event.target.value = '';
    }
  };

  if (!data) {
    return (
      <Page>
        {loadFailed ? (
          <EmptyState
            title="Organization unavailable"
            description="Reload the page or return to Organizations and try again."
          />
        ) : (
          <LoadingState label="Loading organization…" />
        )}
      </Page>
    );
  }

  const tabs = [
    { value: 'projects', label: 'Projects' },
    { value: 'members', label: 'Members' },
    { value: 'audit', label: 'Audit log' },
    ...(admin
      ? [
          { value: 'integrations', label: 'Integrations' },
          { value: 'data', label: 'Import/export' },
          { value: 'settings', label: 'Settings' },
        ]
      : []),
  ];

  return (
    <Page>
      <Breadcrumbs>
        <BreadcrumbButton onClick={() => navigate('/app')}>Organizations</BreadcrumbButton>
        <span>/</span>
        <span className="font-medium text-ink">{data.organization.name}</span>
      </Breadcrumbs>

      <PageHeader
        eyebrow={`Organization · ${data.role}`}
        title={data.organization.name}
        description={data.organization.description || 'No organization description.'}
        actions={
          admin ? (
            <Button variant="primary" onClick={() => setModal('project')}>
              Create project
            </Button>
          ) : null
        }
        compact
      />

      <Tabs value={tab} onChange={changeTab} items={tabs} />

      {tab === 'projects' && (
        <Projects projects={data.projects} admin={admin} onCreate={() => setModal('project')} />
      )}
      {tab === 'members' && (
        <Members
          value={members}
          admin={admin}
          orgId={id}
          onInvite={() => setModal('invite')}
          reload={() => get(`/app-api/organizations/${id}/members`).then(setMembers)}
          setError={setError}
          setNotice={setNotice}
        />
      )}
      {tab === 'audit' && (
        <AuditPage
          data={audit}
          search={auditSearch}
          onSearch={changeAuditSearch}
          page={auditPage}
          perPage={auditPerPage}
          onPage={setAuditPage}
          onPerPage={changeAuditPerPage}
        />
      )}
      {tab === 'integrations' && admin && (
        <Integrations
          orgId={id}
          projects={data.projects}
          hooks={hooks}
          keys={keys}
          reloadHooks={() => get(`/app-api/organizations/${id}/webhooks`).then(setHooks)}
          reloadKeys={() => get(`/app-api/organizations/${id}/api-credentials`).then(setKeys)}
          setError={setError}
          setNotice={setNotice}
        />
      )}
      {tab === 'data' && admin && (
        <Panel>
          <SectionHeading
            title="Import and export"
            description="Transfer organization structure, project data, WYSIWYG content, membership references, and append-only timeline records in the versioned Okatana JSON format."
          />
          <div className="flex flex-wrap gap-2">
            <Button onClick={() => downloadJson(`/app-api/organizations/${id}/export`)}>
              Export organization
            </Button>
            <Button onClick={() => downloadJson(`/app-api/organizations/${id}/timeline/export`)}>
              Export audit log
            </Button>
            <input
              ref={fileRef}
              hidden
              type="file"
              accept="application/json,.json"
              onChange={importProject}
            />
            <Button onClick={() => fileRef.current?.click()}>Import project</Button>
            <input
              ref={timelineRef}
              hidden
              type="file"
              accept="application/json,.json"
              onChange={importTimeline}
            />
            <Button onClick={() => timelineRef.current?.click()}>Import audit log</Button>
          </div>
        </Panel>
      )}
      {tab === 'settings' && admin && (
        <OrgSettings
          data={data}
          owner={owner}
          reload={load}
          setError={setError}
          setNotice={setNotice}
        />
      )}

      <Modal open={modal === 'project'} onClose={() => setModal(null)} title="Create project">
        <ProjectForm onSubmit={createProject} />
      </Modal>
      <Modal open={modal === 'invite'} onClose={() => setModal(null)} title="Invite member">
        <InviteForm projects={data.projects} onSubmit={invite} />
      </Modal>
    </Page>
  );
}
