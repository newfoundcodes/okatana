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

import { navigate } from '../lib/api';
import {
  Avatar,
  BreadcrumbButton,
  Breadcrumbs,
  Button,
  GridLayout,
  Page,
  PageHeader,
  Panel,
  SectionHeading,
  Table,
  Td,
  Th,
} from '../components/Primitives';
import { formatDate } from '../lib/date';

export default function UserProfilePage({ user }) {
  return (
    <Page>
      <Breadcrumbs>
        <BreadcrumbButton onClick={() => navigate('/app')}>Organizations</BreadcrumbButton>
        <span>/</span>
        <span className="font-medium text-ink">Profile</span>
      </Breadcrumbs>

      <PageHeader
        eyebrow="Account"
        title="Profile"
        description="Your account identity in Okatana."
        actions={<Button onClick={() => navigate('/app/settings')}>Edit settings</Button>}
        compact
      />

      <GridLayout>
        <Panel className="col-span-4 md:col-span-6 xl:col-span-8">
          <SectionHeading title="Account details" />
          <div className="mb-8 flex items-center gap-4 border-b border-line pb-6">
            <Avatar user={user} size="lg" />
            <div>
              <div className="text-lg font-semibold text-ink">{user.name}</div>
              <div className="mt-2 text-sm text-ink-muted">{user.email}</div>
            </div>
          </div>
          <Table>
            <tbody>
              <tr>
                <Th scope="row" className="w-48 pr-6">
                  Full name
                </Th>
                <Td>{user.name}</Td>
              </tr>
              <tr>
                <Th scope="row" className="w-48 pr-6">
                  Email
                </Th>
                <Td>{user.email}</Td>
              </tr>
              <tr>
                <Th scope="row" className="w-48 pr-6">
                  Member since
                </Th>
                <Td>{formatDate(user.created_at)}</Td>
              </tr>
            </tbody>
          </Table>
        </Panel>
      </GridLayout>
    </Page>
  );
}
