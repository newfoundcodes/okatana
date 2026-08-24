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

import { useState } from 'react';
import { del, post } from '../../lib/api';
import { credentialMatches, webhookMatches } from '../../lib/organization';
import PaginationBar from '../Pagination/PaginationBar';
import TableSearch from '../Pagination/TableSearch';
import { useClientPagination } from '../../lib/pagination';
import {
  ActionLink,
  Button,
  Check,
  EmptyState,
  Field,
  GridLayout,
  Panel,
  SectionHeading,
  Select,
  Table,
  Td,
  TextInput,
  Th,
} from '../Primitives';
import Secret from './Secret';

export default function Integrations({
  orgId,
  projects,
  hooks,
  keys,
  reloadHooks,
  reloadKeys,
  setError,
  setNotice,
}: any) {
  const [secret, setSecret] = useState<string | null>(null);
  const [token, setToken] = useState<string | null>(null);

  const [hookForm, setHookForm] = useState({
    name: 'CI notification',
    url: '',
    project_id: '',
    events: ['ticket.created', 'ticket.moved'],
  });

  const [keyForm, setKeyForm] = useState({
    name: 'Automation',
    scopes: ['tickets:read', 'tickets:write', 'comments:write', 'notifications:write'],
    expires_at: '',
  });

  const hookPager = useClientPagination(hooks?.webhooks || [], webhookMatches);
  const keyPager = useClientPagination(keys?.credentials || [], credentialMatches);

  const toggle = (rows: any[], value: any, setter: any) =>
    setter(rows.includes(value) ? rows.filter((item) => item !== value) : [...rows, value]);

  const createHook = async (event: any) => {
    event.preventDefault();

    try {
      const result = await post(`/app-api/organizations/${orgId}/webhooks`, {
        ...hookForm,
        project_id: hookForm.project_id || null,
      });

      setSecret(result.secret);
      setNotice('Notification endpoint created.');
      reloadHooks();
    } catch (requestError: any) {
      setError(requestError.message);
    }
  };

  const createKey = async (event: any) => {
    event.preventDefault();

    try {
      const result = await post(`/app-api/organizations/${orgId}/api-credentials`, {
        ...keyForm,
        expires_at: keyForm.expires_at || null,
      });

      setToken(result.token);
      setNotice('API credential created.');
      reloadKeys();
    } catch (requestError: any) {
      setError(requestError.message);
    }
  };

  return (
    <GridLayout>
      <Panel className="col-span-4 md:col-span-8 xl:col-span-6">
        <SectionHeading
          title="Notifications"
          description="Send signed HTTP requests when selected organization or project events occur. Each request uses HMAC-SHA256 over timestamp.body."
        />
        <form className="grid gap-4" onSubmit={createHook}>
          <Field label="Name">
            <TextInput
              required
              value={hookForm.name}
              onChange={(event: any) => setHookForm({ ...hookForm, name: event.target.value })}
            />
          </Field>
          <Field label="Destination URL">
            <TextInput
              type="url"
              required
              placeholder="https://example.com/okatana"
              value={hookForm.url}
              onChange={(event: any) => setHookForm({ ...hookForm, url: event.target.value })}
            />
          </Field>
          <Field label="Project scope">
            <Select
              value={hookForm.project_id}
              onChange={(event: any) =>
                setHookForm({ ...hookForm, project_id: event.target.value })
              }
            >
              <option value="">All organization events</option>
              {projects.map((project: any) => (
                <option value={project.id} key={project.id}>
                  {project.name}
                </option>
              ))}
            </Select>
          </Field>
          <fieldset className="border-t border-line pt-4">
            <legend className="mb-2 text-sm text-ink-muted">Events</legend>
            <div className="grid grid-cols-1 gap-2 md:grid-cols-2">
              {(hooks?.events || []).map((eventName: string) => (
                <Check
                  key={eventName}
                  checked={hookForm.events.includes(eventName)}
                  onChange={() =>
                    toggle(hookForm.events, eventName, (events: string[]) =>
                      setHookForm({ ...hookForm, events }),
                    )
                  }
                >
                  <span className="font-mono text-xs">{eventName}</span>
                </Check>
              ))}
            </div>
          </fieldset>
          <div className="mt-4">
            <Button type="submit" variant="primary" disabled={!hookForm.events.length}>
              Create notification endpoint
            </Button>
          </div>
        </form>

        {secret && <Secret title="Webhook secret" value={secret} />}

        <section className="mt-12">
          <SectionHeading title="Configured endpoints" />
          {!hooks?.webhooks?.length ? (
            <EmptyState
              title="No notification endpoints yet"
              description="Create an endpoint to send signed HTTP notifications."
            />
          ) : (
            <>
              <TableSearch
                value={hookPager.query}
                onChange={hookPager.setQuery}
                label="Search endpoints"
                placeholder="Name, URL, project, event, or status"
              />
              {!hookPager.total ? (
                <EmptyState
                  title="No endpoints match your search"
                  description="Change the search text to see other configured endpoints."
                />
              ) : (
                <Table>
                  <thead>
                    <tr>
                      <Th className="pr-6">Endpoint</Th>
                      <Th className="pr-6">Status</Th>
                      <Th>Actions</Th>
                    </tr>
                  </thead>
                  <tbody>
                    {hookPager.rows.map((hook: any) => (
                      <tr key={hook.id}>
                        <Td className="pr-6">
                          <div className="font-semibold">{hook.name}</div>
                          <div className="mt-2 break-all text-sm text-ink-muted">
                            {hook.project?.name || 'Organization-wide'} · {hook.url}
                          </div>
                          <div className="mt-2 font-mono text-xs text-ink-muted">
                            {hook.events.join(', ')}
                          </div>
                        </Td>
                        <Td className="pr-6 tabular-nums">
                          {hook.is_active ? 'Active' : 'Revoked'} · {hook.deliveries_count}{' '}
                          deliveries
                        </Td>
                        <Td>
                          <div className="flex flex-wrap gap-2">
                            <Button
                              compact
                              onClick={async () => {
                                const result = await post(
                                  `/app-api/webhooks/${hook.id}/rotate`,
                                  {},
                                );
                                setSecret(result.secret);
                                setNotice('Webhook secret rotated.');
                                reloadHooks();
                              }}
                            >
                              Rotate secret
                            </Button>
                            {hook.is_active && (
                              <Button
                                compact
                                variant="danger"
                                onClick={async () => {
                                  await del(`/app-api/webhooks/${hook.id}`);
                                  setNotice('Notification endpoint revoked.');
                                  reloadHooks();
                                }}
                              >
                                Revoke
                              </Button>
                            )}
                          </div>
                        </Td>
                      </tr>
                    ))}
                  </tbody>
                </Table>
              )}
              <PaginationBar
                label="Configured endpoints"
                page={hookPager.page}
                perPage={hookPager.perPage}
                total={hookPager.total}
                onPageChange={hookPager.setPage}
                onPerPageChange={hookPager.setPerPage}
              />
            </>
          )}
        </section>
      </Panel>

      <Panel className="col-span-4 md:col-span-8 xl:col-span-6">
        <SectionHeading
          title="API access"
          description="Create organization-scoped bearer credentials with only the permissions required by an external integration."
          actions={
            <ActionLink compact href="/docs" target="_blank" rel="noreferrer">
              API documentation
            </ActionLink>
          }
        />
        <form className="grid gap-4" onSubmit={createKey}>
          <Field label="Name">
            <TextInput
              required
              value={keyForm.name}
              onChange={(event: any) => setKeyForm({ ...keyForm, name: event.target.value })}
            />
          </Field>
          <Field label="Expires at" hint="Optional">
            <TextInput
              type="datetime-local"
              value={keyForm.expires_at}
              onChange={(event: any) => setKeyForm({ ...keyForm, expires_at: event.target.value })}
            />
          </Field>
          <fieldset className="border-t border-line pt-4">
            <legend className="mb-2 text-sm text-ink-muted">Scopes</legend>
            <div className="grid grid-cols-1 gap-2 md:grid-cols-2">
              {(keys?.available_scopes || []).map((scope: string) => (
                <Check
                  key={scope}
                  checked={keyForm.scopes.includes(scope)}
                  onChange={() =>
                    toggle(keyForm.scopes, scope, (scopes: string[]) =>
                      setKeyForm({ ...keyForm, scopes }),
                    )
                  }
                >
                  <span className="font-mono text-xs">{scope}</span>
                </Check>
              ))}
            </div>
          </fieldset>
          <div className="mt-4">
            <Button type="submit" variant="primary" disabled={!keyForm.scopes.length}>
              Create API credential
            </Button>
          </div>
        </form>

        {token && <Secret title="API token" value={token} />}

        <section className="mt-12">
          <SectionHeading title="Credentials" />
          {!keys?.credentials?.length ? (
            <EmptyState
              title="No API credentials yet"
              description="Create a credential for an external client or automation."
            />
          ) : (
            <>
              <TableSearch
                value={keyPager.query}
                onChange={keyPager.setQuery}
                label="Search credentials"
                placeholder="Name, public ID, scope, or status"
              />
              {!keyPager.total ? (
                <EmptyState
                  title="No credentials match your search"
                  description="Change the search text to see other API credentials."
                />
              ) : (
                <Table>
                  <thead>
                    <tr>
                      <Th className="pr-6">Credential</Th>
                      <Th className="pr-6">Status</Th>
                      <Th>Actions</Th>
                    </tr>
                  </thead>
                  <tbody>
                    {keyPager.rows.map((key: any) => {
                      const status = key.revoked_at
                        ? 'Revoked'
                        : key.expires_at && new Date(key.expires_at) < new Date()
                          ? 'Expired'
                          : 'Active';
                      return (
                        <tr key={key.id}>
                          <Td className="pr-6">
                            <div className="font-semibold">{key.name}</div>
                            <div className="mt-2 font-mono text-xs text-ink-muted">
                              {key.public_id}
                            </div>
                            <div className="mt-2 font-mono text-xs text-ink-muted">
                              {key.scopes.join(', ')}
                            </div>
                          </Td>
                          <Td className="pr-6">{status}</Td>
                          <Td>
                            {!key.revoked_at && (
                              <Button
                                compact
                                variant="danger"
                                onClick={async () => {
                                  await del(`/app-api/api-credentials/${key.id}`);
                                  setNotice('API credential revoked.');
                                  reloadKeys();
                                }}
                              >
                                Revoke
                              </Button>
                            )}
                          </Td>
                        </tr>
                      );
                    })}
                  </tbody>
                </Table>
              )}
              <PaginationBar
                label="API credentials"
                page={keyPager.page}
                perPage={keyPager.perPage}
                total={keyPager.total}
                onPageChange={keyPager.setPage}
                onPerPageChange={keyPager.setPerPage}
              />
            </>
          )}
        </section>
      </Panel>
    </GridLayout>
  );
}
