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

import Modal from '../Modal';
import { CopyValueButton } from '../Primitives';
import CredentialForm from './CredentialForm';
import WebhookForm from './WebhookForm';

export default function DocumentIntegrationModal({
  modal,
  setModal,
  busy,
  options,
  organizationId,
  onCredential,
  onWebhook,
}: any) {
  if (!modal) {
    return null;
  }

  if (modal.type === 'token') {
    return (
      <Modal open onClose={() => setModal(null)} title="API credential created">
        <div className="grid gap-6">
          <p className="text-sm text-ink-muted">
            Copy this bearer token now. The secret is not shown again.
          </p>
          <div className="flex items-center gap-2 border-t border-line pt-4">
            <code className="min-w-0 break-all text-sm">{modal.token}</code>
            <CopyValueButton value={modal.token} label="Copy API token" />
          </div>
        </div>
      </Modal>
    );
  }

  if (modal.type === 'secret') {
    return (
      <Modal open onClose={() => setModal(null)} title="Webhook created">
        <div className="grid gap-6">
          <p className="text-sm text-ink-muted">
            Use this secret to verify Okatana HMAC signatures.
          </p>
          <div className="flex items-center gap-2 border-t border-line pt-4">
            <code className="min-w-0 break-all text-sm">{modal.secret}</code>
            <CopyValueButton value={modal.secret} label="Copy webhook secret" />
          </div>
        </div>
      </Modal>
    );
  }

  if (modal.type === 'credential') {
    return <CredentialForm busy={busy} onCancel={() => setModal(null)} onSubmit={onCredential} />;
  }

  if (modal.type === 'webhook') {
    return (
      <WebhookForm
        busy={busy}
        onCancel={() => setModal(null)}
        onSubmit={onWebhook}
        projects={(options?.projects || []).filter(
          (project: any) => project.organization_id === organizationId,
        )}
      />
    );
  }

  return null;
}
