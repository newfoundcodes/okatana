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
import { post } from '../lib/api';
import Modal from './Modal';
import { Alert, Button } from './Primitives';

interface InvitationRespondModalProps {
  invitationId: string;
  organizationName: string;
  projectName: string | null;
  role: string;
  inviterName: string;
  expiresAt: string;
  onClose: () => void;
  onResponded: (
    status: 'accepted' | 'declined',
    organizationId?: string,
    projectId?: string,
  ) => void;
}

export default function InvitationRespondModal({
  invitationId,
  organizationName,
  projectName,
  role,
  inviterName,
  expiresAt,
  onClose,
  onResponded,
}: InvitationRespondModalProps) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  const scope = projectName ? `project ${projectName}` : `organization ${organizationName}`;
  const respond = async (action: 'accept' | 'decline') => {
    setBusy(true);
    setError('');
    try {
      const data = await post(`/app-api/invitations/${invitationId}/respond`, { action });
      onResponded(data.status, data.organization_id, data.project_id);
    } catch (requestError: any) {
      setError(
        requestError.errors
          ? Object.values(requestError.errors).flat().join(' ')
          : requestError.message,
      );
      setBusy(false);
    }
  };

  const expiryDate = new Date(expiresAt).toLocaleString();

  return (
    <Modal open onClose={busy ? undefined : onClose} title="Project invitation">
      <div className="grid gap-6">
        {error && <Alert>{error}</Alert>}

        <dl className="grid grid-cols-4 gap-6 border-y border-line py-6 md:grid-cols-8">
          <div className="col-span-4 md:col-span-4">
            <dt className="text-sm text-ink-muted">Organization</dt>
            <dd className="mt-2 text-base font-medium text-ink">{organizationName}</dd>
          </div>
          {projectName && (
            <div className="col-span-4 md:col-span-4">
              <dt className="text-sm text-ink-muted">Project</dt>
              <dd className="mt-2 text-base font-medium text-ink">{projectName}</dd>
            </div>
          )}
          <div className="col-span-2 md:col-span-4">
            <dt className="text-sm text-ink-muted">Role</dt>
            <dd className="mt-2 text-base font-medium text-ink">{role}</dd>
          </div>
          <div className="col-span-2 md:col-span-4">
            <dt className="text-sm text-ink-muted">Invited by</dt>
            <dd className="mt-2 text-base font-medium text-ink">{inviterName}</dd>
          </div>
          <div className="col-span-4 md:col-span-8">
            <dt className="text-sm text-ink-muted">Expires</dt>
            <dd className="mt-2 text-sm text-ink-muted tabular-nums">{expiryDate}</dd>
          </div>
        </dl>

        <p className="text-sm text-ink-muted">
          {inviterName} has invited you to join {scope} as {role}. Accept to gain access, or decline
          to dismiss this invitation.
        </p>

        <div className="flex flex-wrap gap-2">
          <Button variant="primary" disabled={busy} onClick={() => respond('accept')}>
            {busy ? 'Working…' : 'Accept invitation'}
          </Button>
          <Button variant="secondary" disabled={busy} onClick={() => respond('decline')}>
            Decline
          </Button>
        </div>
      </div>
    </Modal>
  );
}
