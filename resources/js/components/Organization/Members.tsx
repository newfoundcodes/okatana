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
import { del, patch } from '../../lib/api';
import { memberMatches } from '../../lib/organization';
import ConfirmDialog from '../ConfirmDialog';
import PaginationBar from '../Pagination/PaginationBar';
import TableSearch from '../Pagination/TableSearch';
import { useClientPagination } from '../../lib/pagination';
import {
  Avatar,
  Button,
  EmptyState,
  LoadingState,
  Panel,
  SectionHeading,
  Select,
  Table,
  Td,
  Th,
} from '../Primitives';

export default function Members({
  value,
  admin,
  orgId,
  onInvite,
  reload,
  setError,
  setNotice,
}: any) {
  const [memberToRemove, setMemberToRemove] = useState<any>(null);
  const [removing, setRemoving] = useState(false);
  const pager = useClientPagination(value?.members || [], memberMatches);

  if (!value) {
    return <LoadingState label="Loading members…" />;
  }

  const changeRole = async (member: any, role: string) => {
    try {
      await patch(`/app-api/organizations/${orgId}/members/${member.id}`, { role });

      setNotice(`Updated ${member.name}'s role.`);
      reload();
    } catch (requestError: any) {
      setError(requestError.message);
    }
  };

  const remove = async () => {
    if (!memberToRemove) {
      return;
    }

    setRemoving(true);
    try {
      await del(`/app-api/organizations/${orgId}/members/${memberToRemove.id}`);

      setNotice(`Removed ${memberToRemove.name}.`);
      setMemberToRemove(null);

      reload();
    } catch (requestError: any) {
      setError(requestError.message);
    } finally {
      setRemoving(false);
    }
  };

  return (
    <Panel>
      <SectionHeading
        title="Members"
        description={`${value.members.length} active members`}
        actions={
          admin ? (
            <Button variant="primary" onClick={onInvite}>
              Invite member
            </Button>
          ) : null
        }
      />
      <TableSearch
        value={pager.query}
        onChange={pager.setQuery}
        label="Search members"
        placeholder="Name, email, or role"
      />
      {!pager.total ? (
        <EmptyState
          title="No members match your search"
          description="Change the search text to see other organization members."
        />
      ) : (
        <>
          <Table>
            <thead>
              <tr>
                <Th className="pr-6">Person</Th>
                <Th className="pr-6">Role</Th>
                <Th>Actions</Th>
              </tr>
            </thead>
            <tbody>
              {pager.rows.map((member: any) => (
                <tr key={member.id}>
                  <Td className="pr-6">
                    <div className="flex items-center gap-4">
                      <Avatar user={member} />
                      <div>
                        <div className="font-semibold">{member.name}</div>
                        <div className="mt-2 text-sm text-ink-muted">{member.email}</div>
                      </div>
                    </div>
                  </Td>
                  <Td className="pr-6">
                    {admin ? (
                      <Select
                        value={member.role}
                        onChange={(event: any) => changeRole(member, event.target.value)}
                      >
                        <option value="owner">Owner</option>
                        <option value="admin">Admin</option>
                        <option value="user">User</option>
                      </Select>
                    ) : (
                      <span className="capitalize">{member.role}</span>
                    )}
                  </Td>
                  <Td>
                    {admin && (
                      <Button compact variant="danger" onClick={() => setMemberToRemove(member)}>
                        Remove
                      </Button>
                    )}
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
          <PaginationBar
            label="Members"
            page={pager.page}
            perPage={pager.perPage}
            total={pager.total}
            onPageChange={pager.setPage}
            onPerPageChange={pager.setPerPage}
          />
        </>
      )}

      {value.pending_invitations?.length > 0 && (
        <section className="mt-12">
          <SectionHeading
            title="Pending invitations"
            description="Invitations remain inactive until the recipient accepts them."
          />
          <Table>
            <thead>
              <tr>
                <Th className="pr-6">Email</Th>
                <Th className="pr-6">Role</Th>
                <Th>Expires</Th>
              </tr>
            </thead>
            <tbody>
              {value.pending_invitations.map((invitation: any) => (
                <tr key={invitation.id}>
                  <Td className="pr-6">{invitation.email}</Td>
                  <Td className="pr-6 capitalize">{invitation.role}</Td>
                  <Td>{new Date(invitation.expires_at).toLocaleString()}</Td>
                </tr>
              ))}
            </tbody>
          </Table>
        </section>
      )}
      <ConfirmDialog
        open={Boolean(memberToRemove)}
        title="Remove member"
        description={
          memberToRemove
            ? `Remove ${memberToRemove.name} from this organization? Their organization and project access through this membership will be removed.`
            : 'Remove this member?'
        }
        confirmLabel="Remove member"
        onCancel={() => setMemberToRemove(null)}
        onConfirm={remove}
        busy={removing}
      />
    </Panel>
  );
}
