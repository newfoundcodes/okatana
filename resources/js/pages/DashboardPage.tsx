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
import { get, navigate, post } from '../lib/api';
import { toast } from '../lib/toast';
import DropdownMenu from '../components/DropdownMenu';
import Modal from '../components/Modal';
import PaginationBar from '../components/Pagination/PaginationBar';
import TableSearch from '../components/Pagination/TableSearch';
import { useClientPagination } from '../lib/pagination';
import { organizationDestination, organizationMatches } from '../lib/dashboard';
import {
  Button,
  CopyValueButton,
  EmptyState,
  Field,
  Page,
  PageHeader,
  LoadingState,
  SectionHeading,
  Table,
  Td,
  TextArea,
  TextInput,
  Th,
} from '../components/Primitives';

export default function DashboardPage({ user }) {
  const [organizations, setOrganizations] = useState<Organization[] | null>(null);
  const [recentProjects, setRecentProjects] = useState([]);
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({ name: '', slug: '', description: '' });
  const [loadFailed, setLoadFailed] = useState(false);
  const fileRef = useRef(null);
  const organizationPager = useClientPagination(organizations || [], organizationMatches);

  const load = () =>
    get('/app-api/organizations').then((data) => {
      setOrganizations(data.organizations);
      setRecentProjects(data.recent_projects || []);
      setLoadFailed(false);
    });

  useEffect(() => {
    load().catch((requestError) => {
      setLoadFailed(true);
      toast.error(requestError.message);
    });
  }, []);

  const create = async (event) => {
    event.preventDefault();
    try {
      const data = await post('/app-api/organizations', form);
      setOpen(false);
      toast.success('Organization created.');
      navigate(`/app/organizations/${data.organization.id}`);
    } catch (requestError) {
      toast.error(requestError.message);
    }
  };

  const importOrganization = async (event) => {
    const file = event.target.files?.[0];
    if (!file) {
      return;
    }
    try {
      const payload = JSON.parse(await file.text());
      const data = await post('/app-api/imports/organization', { payload });
      toast.success('Organization imported.');
      navigate(`/app/organizations/${data.organization.id}`);
    } catch (requestError) {
      toast.error(requestError.message);
    } finally {
      event.target.value = '';
    }
  };

  const organizationMenuItems = (organization) => {
    const admin = ['owner', 'admin'].includes(organization.role);
    return [
      {
        label: 'Projects',
        onSelect: () => navigate(organizationDestination(organization, 'projects')),
      },
      {
        label: 'Members',
        onSelect: () => navigate(organizationDestination(organization, 'members')),
      },
      {
        label: 'Audit log',
        onSelect: () => navigate(organizationDestination(organization, 'audit')),
      },
      {
        label: 'Integrations',
        disabled: !admin,
        disabledReason: 'Only organization owners and admins can manage integrations.',
        onSelect: () => navigate(organizationDestination(organization, 'integrations')),
      },
      {
        label: 'Import/export',
        disabled: !admin,
        disabledReason:
          'Only organization owners and admins can import or export organization data.',
        onSelect: () => navigate(organizationDestination(organization, 'data')),
      },
      {
        label: 'Settings',
        disabled: !admin,
        disabledReason: 'Only organization owners and admins can change organization settings.',
        onSelect: () => navigate(organizationDestination(organization, 'settings')),
      },
    ];
  };

  return (
    <Page>
      <PageHeader
        eyebrow="Workspace"
        title="Organizations"
        description={`Signed in as ${user.name}. Select an organization or create a new one.`}
        actions={
          <>
            <input
              ref={fileRef}
              hidden
              type="file"
              accept="application/json,.json"
              onChange={importOrganization}
            />
            <Button onClick={() => fileRef.current?.click()}>Import organization</Button>
            <Button variant="primary" onClick={() => setOpen(true)}>
              Create organization
            </Button>
          </>
        }
      />

      {loadFailed ? (
        <EmptyState
          title="Organizations unavailable"
          description="Reload the page to try loading your organizations again."
        />
      ) : organizations === null ? (
        <LoadingState label="Loading organizations…" />
      ) : organizations.length ? (
        <section aria-label="Organizations">
          <TableSearch
            value={organizationPager.query}
            onChange={organizationPager.setQuery}
            label="Search organizations"
            placeholder="Name, key, role, or description"
          />
          {organizationPager.total ? (
            <>
              <div className="border-t-2 border-ink">
                <Table>
                  <thead>
                    <tr>
                      <Th className="pr-6">Organization</Th>
                      <Th className="hidden pr-6 md:table-cell">Description</Th>
                      <Th className="pr-6">Role</Th>
                      <Th numeric className="pr-6">
                        Projects
                      </Th>
                      <Th className="pr-6">Key</Th>
                      <Th className="w-12">
                        <span className="sr-only">Actions</span>
                      </Th>
                    </tr>
                  </thead>
                  <tbody>
                    {organizationPager.rows.map((organization) => (
                      <tr
                        key={organization.id}
                        className="group cursor-pointer"
                        onClick={() => navigate(`/app/organizations/${organization.id}`)}
                      >
                        <Td className="pr-6">
                          <button
                            type="button"
                            className="text-left font-semibold text-ink group-hover:text-accent"
                          >
                            {organization.name}
                          </button>
                        </Td>
                        <Td className="hidden pr-6 text-ink-muted md:table-cell">
                          {organization.description || 'No description'}
                        </Td>
                        <Td className="pr-6 capitalize">{organization.role}</Td>
                        <Td numeric className="pr-6">
                          {organization.projects_count}
                        </Td>
                        <Td className="pr-6">
                          <div className="flex items-center gap-2">
                            <span className="whitespace-nowrap text-sm font-medium tabular-nums">
                              {organization.slug}
                            </span>
                            <CopyValueButton
                              value={organization.slug}
                              label={`Copy ${organization.name} organization key`}
                            />
                          </div>
                        </Td>
                        <Td className="text-right">
                          <DropdownMenu
                            compact
                            stopPropagation
                            ariaLabel={`Actions for ${organization.name}`}
                            items={organizationMenuItems(organization)}
                          />
                        </Td>
                      </tr>
                    ))}
                  </tbody>
                </Table>
              </div>
              <PaginationBar
                label="Organizations"
                page={organizationPager.page}
                perPage={organizationPager.perPage}
                total={organizationPager.total}
                onPageChange={organizationPager.setPage}
                onPerPageChange={organizationPager.setPerPage}
              />
            </>
          ) : (
            <EmptyState
              title="No organizations match your search"
              description="Change the search text to see other organizations."
            />
          )}
        </section>
      ) : (
        <EmptyState
          title="No organizations yet"
          description="Create your first organization. Okatana will assign you the owner role."
          action={
            <Button variant="primary" onClick={() => setOpen(true)}>
              Create organization
            </Button>
          }
        />
      )}

      <section className="mt-12" aria-label="Quick Links">
        <SectionHeading
          title="Quick Links"
          description="The five projects where you were most recently active."
        />
        {recentProjects.length ? (
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
                {recentProjects.map((project) => (
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
        ) : (
          <EmptyState
            title="No recent project activity"
            description="Projects will appear here after you work in them."
          />
        )}
      </section>

      <Modal open={open} onClose={() => setOpen(false)} title="Create organization">
        <form className="grid gap-4" onSubmit={create}>
          <Field label="Name">
            <TextInput
              required
              value={form.name}
              onChange={(event) => setForm({ ...form, name: event.target.value })}
            />
          </Field>
          <Field label="Slug" hint="Optional">
            <TextInput
              value={form.slug}
              onChange={(event) => setForm({ ...form, slug: event.target.value })}
            />
          </Field>
          <Field label="Description">
            <TextArea
              rows={4}
              value={form.description}
              onChange={(event) => setForm({ ...form, description: event.target.value })}
            />
          </Field>
          <div className="mt-4">
            <Button type="submit" variant="primary">
              Create organization
            </Button>
          </div>
        </form>
      </Modal>
    </Page>
  );
}
