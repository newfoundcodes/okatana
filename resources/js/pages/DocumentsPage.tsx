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
import { del, get, navigate, post } from '../lib/api';
import { toast } from '../lib/toast';
import ConfirmDialog from '../components/ConfirmDialog';
import { useClientPagination } from '../lib/pagination';
import { Button, Page, PageHeader, Tabs } from '../components/Primitives';
import { integrationMatches, requestedTab, tabs } from '../lib/page';
import DocumentTableSection from '../components/Documents/DocumentTableSection';
import DocumentAuditSection from '../components/Documents/DocumentAuditSection';
import DocumentIntegrations from '../components/Documents/DocumentIntegrations';
import DocumentIntegrationModal from '../components/Documents/DocumentIntegrationModal';

export default function DocumentsPage() {
  const [tab, setTab] = useState(requestedTab);
  const [result, setResult] = useState(null);
  const [query, setQuery] = useState('');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);
  const [audit, setAudit] = useState(null);
  const [options, setOptions] = useState(null);
  const [integrationOrg, setIntegrationOrg] = useState('');
  const [hooks, setHooks] = useState(null);
  const [credentials, setCredentials] = useState(null);
  const [modal, setModal] = useState(null);
  const [confirm, setConfirm] = useState(null);
  const [busy, setBusy] = useState(false);

  const hookPager = useClientPagination(hooks?.webhooks || [], integrationMatches);
  const credentialPager = useClientPagination(credentials?.credentials || [], integrationMatches);

  const loadDocuments = () => {
    const params = new URLSearchParams({
      scope: tab,
      page: String(page),
      per_page: String(perPage),
    });

    if (query.trim()) {
      params.set('q', query.trim());
    }

    return get(`/app-api/documents?${params}`)
      .then(setResult)
      .catch((error) => toast.error(error.message));
  };

  const loadAudit = () => {
    const params = new URLSearchParams({ page: String(page), per_page: String(perPage) });
    if (query.trim()) {
      params.set('q', query.trim());
    }

    return get(`/app-api/documents/audit-logs?${params}`)
      .then(setAudit)
      .catch((error) => toast.error(error.message));
  };

  const loadIntegrations = async (organizationId) => {
    if (!organizationId) {
      return;
    }

    try {
      const [webhooks, keys] = await Promise.all([
        get(`/app-api/organizations/${organizationId}/webhooks`),
        get(`/app-api/organizations/${organizationId}/api-credentials`),
      ]);

      setHooks({
        ...webhooks,
        webhooks: (webhooks.webhooks || []).filter(
          (hook) =>
            (hook.events || []).includes('*') ||
            (hook.events || []).some((event) => event.startsWith('document.')),
        ),
      });

      setCredentials({
        ...keys,
        credentials: (keys.credentials || []).filter(
          (credential) =>
            (credential.scopes || []).includes('*') ||
            (credential.scopes || []).some(
              (scope) => scope.startsWith('documents:') || scope === 'document_comments:write',
            ),
        ),
      });
    } catch (error) {
      toast.error(error.message);
    }
  };

  useEffect(() => {
    get('/app-api/documents/editor-options')
      .then(setOptions)
      .catch((error) => toast.error(error.message));
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      if (tab === 'articles' || tab === 'favorites') {
        loadDocuments();
      }

      if (tab === 'audit') {
        loadAudit();
      }
    }, 180);

    return () => window.clearTimeout(timer);
  }, [tab, query, page, perPage]);

  useEffect(() => {
    if (tab !== 'integrations' || !options) {
      return;
    }

    const adminOrganizations = options.organizations.filter((row) =>
      ['owner', 'admin'].includes(row.role),
    );

    const selected = integrationOrg || adminOrganizations[0]?.id || '';
    setIntegrationOrg(selected);

    if (selected) {
      loadIntegrations(selected);
    }
  }, [tab, options]);

  const changeTab = (value) => {
    setTab(value);
    setQuery('');
    setPage(1);
    setResult(null);
    setAudit(null);

    history.replaceState({}, '', `/app/documents?tab=${value}`);
  };

  const changeQuery = (value) => {
    setQuery(value);
    setPage(1);
  };

  const changePerPage = (value) => {
    setPerPage(Number(value));
    setPage(1);
  };

  const toggleFavorite = async (document) => {
    try {
      await post(`/app-api/documents/${document.id}/favorite`, {
        saved: !document.is_favorite,
      });

      toast.success(
        document.is_favorite ? 'Article removed from favorites.' : 'Article saved to favorites.',
      );

      loadDocuments();
    } catch (error) {
      toast.error(error.message);
    }
  };

  const toggleArchive = async (document) => {
    try {
      await post(`/app-api/documents/${document.id}/archive`, {
        archived: !document.archived_at,
      });

      toast.success(document.archived_at ? 'Article unarchived.' : 'Article archived.');
      loadDocuments();
    } catch (error) {
      toast.error(error.message);
    }
  };

  const removeDocument = async () => {
    if (!confirm?.document) {
      return;
    }

    setBusy(true);
    try {
      await del(`/app-api/documents/${confirm.document.id}`);
      toast.success('Article deleted.');

      setConfirm(null);
      loadDocuments();
    } catch (error) {
      toast.error(error.message);
    } finally {
      setBusy(false);
    }
  };

  const createCredential = async (form) => {
    setBusy(true);

    try {
      const response = await post(`/app-api/organizations/${integrationOrg}/api-credentials`, form);
      setModal({ type: 'token', token: response.token });

      toast.success('Document API credential created.');
      await loadIntegrations(integrationOrg);
    } catch (error) {
      toast.error(error.message);
    } finally {
      setBusy(false);
    }
  };

  const createWebhook = async (form) => {
    setBusy(true);

    try {
      const response = await post(`/app-api/organizations/${integrationOrg}/webhooks`, form);
      setModal({ type: 'secret', secret: response.secret });

      toast.success('Document webhook created.');
      await loadIntegrations(integrationOrg);
    } catch (error) {
      toast.error(error.message);
    } finally {
      setBusy(false);
    }
  };

  const revokeCredential = async (credential) => {
    try {
      await del(`/app-api/api-credentials/${credential.id}`);

      toast.success('API credential revoked.');
      loadIntegrations(integrationOrg);
    } catch (error) {
      toast.error(error.message);
    }
  };

  const revokeWebhook = async (hook) => {
    try {
      await del(`/app-api/webhooks/${hook.id}`);

      toast.success('Webhook revoked.');
      loadIntegrations(integrationOrg);
    } catch (error) {
      toast.error(error.message);
    }
  };

  const createArticle = () => {
    if (!options) {
      toast.error('Workspace availability is still loading.');
      return;
    }

    if (!options.organizations?.length || !options.projects?.length) {
      toast.error('Create an organization and project before creating an article.');
      return;
    }

    navigate('/app/documents/new');
  };

  return (
    <Page>
      <PageHeader
        eyebrow="Workspace"
        title="Documents"
        description="Create, publish, save, and collaborate on organization and project knowledge."
        actions={
          <Button variant="primary" onClick={createArticle}>
            Create article
          </Button>
        }
      />
      <Tabs value={tab} onChange={changeTab} items={tabs} />

      {(tab === 'articles' || tab === 'favorites') && (
        <DocumentTableSection
          result={result}
          query={query}
          onQuery={changeQuery}
          page={page}
          perPage={perPage}
          onPage={setPage}
          onPerPage={changePerPage}
          onFavorite={toggleFavorite}
          onArchive={toggleArchive}
          onDelete={(document) => setConfirm({ document })}
          favorites={tab === 'favorites'}
        />
      )}

      {tab === 'audit' && (
        <DocumentAuditSection
          audit={audit}
          query={query}
          onQuery={changeQuery}
          page={page}
          perPage={perPage}
          onPage={setPage}
          onPerPage={changePerPage}
        />
      )}

      {tab === 'integrations' && (
        <DocumentIntegrations
          options={options}
          organizationId={integrationOrg}
          setOrganizationId={(value) => {
            setIntegrationOrg(value);
            setHooks(null);
            setCredentials(null);
            loadIntegrations(value);
          }}
          hooks={hooks}
          credentials={credentials}
          hookPager={hookPager}
          credentialPager={credentialPager}
          onCreateCredential={() => setModal({ type: 'credential' })}
          onCreateWebhook={() => setModal({ type: 'webhook' })}
          onRevokeCredential={revokeCredential}
          onRevokeWebhook={revokeWebhook}
        />
      )}

      <ConfirmDialog
        open={Boolean(confirm?.document)}
        title="Delete article"
        description={
          confirm?.document
            ? `Delete “${confirm.document.title}”? The article is soft-deleted and its audit history remains available.`
            : ''
        }
        confirmLabel="Delete article"
        onCancel={() => setConfirm(null)}
        onConfirm={removeDocument}
        busy={busy}
      />
      <DocumentIntegrationModal
        modal={modal}
        setModal={setModal}
        busy={busy}
        options={options}
        organizationId={integrationOrg}
        onCredential={createCredential}
        onWebhook={createWebhook}
      />
    </Page>
  );
}
