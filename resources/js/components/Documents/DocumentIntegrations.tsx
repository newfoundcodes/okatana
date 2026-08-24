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
import {
  ActionLink,
  Button,
  CopyValueButton,
  EmptyState,
  Field,
  GridLayout,
  LoadingState,
  SectionHeading,
  Select,
  Table,
  Td,
  Th,
} from '../Primitives';

export default function DocumentIntegrations({
  options,
  organizationId,
  setOrganizationId,
  hooks,
  credentials,
  hookPager,
  credentialPager,
  onCreateCredential,
  onCreateWebhook,
  onRevokeCredential,
  onRevokeWebhook,
}: any) {
  const adminOrganizations =
    options?.organizations?.filter((row: any) => ['owner', 'admin'].includes(row.role)) || [];

  if (!options) {
    return <LoadingState label="Loading document integrations…" />;
  }

  if (!adminOrganizations.length) {
    return (
      <EmptyState
        title="No integration administration access"
        description="You must be an organization owner or admin to create document API credentials or webhooks."
      />
    );
  }

  return (
    <div className="grid gap-12">
      <GridLayout>
        <Field label="Organization" className="col-span-4 md:col-span-4 xl:col-span-4">
          <Select
            value={organizationId}
            onChange={(event: any) => setOrganizationId(event.target.value)}
          >
            {adminOrganizations.map((org: any) => (
              <option key={org.id} value={org.id}>
                {org.name}
              </option>
            ))}
          </Select>
        </Field>
      </GridLayout>
      <section>
        <SectionHeading
          title="Document API access"
          description="Organization-scoped API credentials can be granted document read, write, and comment scopes."
          actions={
            <>
              <ActionLink href="/docs/api" target="_blank" rel="noopener noreferrer">
                API documentation
              </ActionLink>
              <Button variant="primary" onClick={onCreateCredential}>
                Create API credential
              </Button>
            </>
          }
        />
        {!credentials ? (
          <LoadingState label="Loading API credentials…" />
        ) : (
          <>
            <TableSearch
              value={credentialPager.query}
              onChange={credentialPager.setQuery}
              label="Search API credentials"
              placeholder="Name, public ID, scope, or status"
            />
            {credentialPager.total ? (
              <>
                <div className="border-t-2 border-ink">
                  <Table>
                    <thead>
                      <tr>
                        <Th className="pr-6">Name</Th>
                        <Th className="pr-6">Public ID</Th>
                        <Th className="pr-6">Scopes</Th>
                        <Th className="pr-6">Status</Th>
                        <Th>Actions</Th>
                      </tr>
                    </thead>
                    <tbody>
                      {credentialPager.rows.map((key: any) => (
                        <tr key={key.id}>
                          <Td className="pr-6 font-semibold">{key.name}</Td>
                          <Td className="pr-6">
                            <div className="flex items-center gap-2">
                              <span className="tabular-nums">{key.public_id}</span>
                              <CopyValueButton
                                value={key.public_id}
                                label={`Copy ${key.name} public ID`}
                              />
                            </div>
                          </Td>
                          <Td className="pr-6 text-ink-muted">{(key.scopes || []).join(', ')}</Td>
                          <Td className="pr-6">
                            {key.revoked_at
                              ? 'Revoked'
                              : key.expires_at && new Date(key.expires_at) < new Date()
                                ? 'Expired'
                                : 'Active'}
                          </Td>
                          <Td>
                            {!key.revoked_at && (
                              <Button
                                compact
                                variant="danger"
                                onClick={() => onRevokeCredential(key)}
                              >
                                Revoke
                              </Button>
                            )}
                          </Td>
                        </tr>
                      ))}
                    </tbody>
                  </Table>
                </div>
                <PaginationBar
                  label="API credentials"
                  page={credentialPager.page}
                  perPage={credentialPager.perPage}
                  total={credentialPager.total}
                  onPageChange={credentialPager.setPage}
                  onPerPageChange={credentialPager.setPerPage}
                />
              </>
            ) : (
              <EmptyState
                title="No API credentials"
                description="Create a credential to automate document workflows through the external API."
              />
            )}
          </>
        )}
      </section>
      <section>
        <SectionHeading
          title="Document webhooks"
          description="Send signed HTTP callbacks when articles or document comments change."
          actions={
            <Button variant="primary" onClick={onCreateWebhook}>
              Create webhook
            </Button>
          }
        />
        {!hooks ? (
          <LoadingState label="Loading webhooks…" />
        ) : (
          <>
            <TableSearch
              value={hookPager.query}
              onChange={hookPager.setQuery}
              label="Search webhooks"
              placeholder="Name, URL, project, event, or status"
            />
            {hookPager.total ? (
              <>
                <div className="border-t-2 border-ink">
                  <Table>
                    <thead>
                      <tr>
                        <Th className="pr-6">Name</Th>
                        <Th className="pr-6">URL</Th>
                        <Th className="pr-6">Events</Th>
                        <Th className="pr-6">Status</Th>
                        <Th>Actions</Th>
                      </tr>
                    </thead>
                    <tbody>
                      {hookPager.rows.map((hook: any) => (
                        <tr key={hook.id}>
                          <Td className="pr-6 font-semibold">{hook.name}</Td>
                          <Td className="pr-6 break-all text-ink-muted">{hook.url}</Td>
                          <Td className="pr-6 text-ink-muted">{(hook.events || []).join(', ')}</Td>
                          <Td className="pr-6">{hook.is_active ? 'Active' : 'Revoked'}</Td>
                          <Td>
                            {hook.is_active && (
                              <Button
                                compact
                                variant="danger"
                                onClick={() => onRevokeWebhook(hook)}
                              >
                                Revoke
                              </Button>
                            )}
                          </Td>
                        </tr>
                      ))}
                    </tbody>
                  </Table>
                </div>
                <PaginationBar
                  label="Document webhooks"
                  page={hookPager.page}
                  perPage={hookPager.perPage}
                  total={hookPager.total}
                  onPageChange={hookPager.setPage}
                  onPerPageChange={hookPager.setPerPage}
                />
              </>
            ) : (
              <EmptyState
                title="No document webhooks"
                description="Create an endpoint to receive signed document events."
              />
            )}
          </>
        )}
      </section>
    </div>
  );
}
