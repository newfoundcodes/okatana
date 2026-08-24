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
import { del, downloadJson, get, navigate, patch, post } from '../lib/api';
import ConfirmDialog from '../components/ConfirmDialog';
import {
  Alert,
  Avatar,
  BreadcrumbButton,
  Breadcrumbs,
  Button,
  Check,
  Field,
  GridLayout,
  LoadingState,
  Page,
  PageHeader,
  Panel,
  SectionHeading,
  TextInput,
} from '../components/Primitives';

export default function UserSettingsPage({ user, onUserUpdated }) {
  const [account, setAccount] = useState(user);
  const [name, setName] = useState(user?.name || '');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [saving, setSaving] = useState(false);
  const [avatarBusy, setAvatarBusy] = useState(false);
  const [removeAvatarOpen, setRemoveAvatarOpen] = useState(false);
  const [exporting, setExporting] = useState(false);
  const avatarInput = useRef(null);

  const [emailNotifyAssigned, setEmailNotifyAssigned] = useState(
    user?.email_notify_assigned ?? true,
  );

  const [emailNotifyMentions, setEmailNotifyMentions] = useState(
    user?.email_notify_mentions ?? true,
  );

  const [notifyDocumentComments, setNotifyDocumentComments] = useState(
    user?.notify_document_comments ?? true,
  );

  useEffect(() => {
    get('/app-api/user')
      .then((data) => {
        setAccount(data.user);
        setName(data.user.name);
        setEmailNotifyAssigned(data.user.email_notify_assigned ?? true);
        setEmailNotifyMentions(data.user.email_notify_mentions ?? true);
        setNotifyDocumentComments(data.user.notify_document_comments ?? true);

        onUserUpdated?.(data.user);
      })
      .catch((requestError) => setError(requestError.message));
  }, []);

  const acceptUser = (next) => {
    setAccount(next);
    setName(next.name);
    setEmailNotifyAssigned(next.email_notify_assigned ?? true);
    setEmailNotifyMentions(next.email_notify_mentions ?? true);
    setNotifyDocumentComments(next.notify_document_comments ?? true);

    onUserUpdated?.(next);
  };

  const save = async (event) => {
    event.preventDefault();

    setError('');
    setNotice('');
    setSaving(true);

    try {
      const data = await patch('/app-api/user', {
        name,
        email_notify_assigned: emailNotifyAssigned,
        email_notify_mentions: emailNotifyMentions,
        notify_document_comments: notifyDocumentComments,
      });

      acceptUser(data.user);
      setNotice('Account settings updated.');
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setSaving(false);
    }
  };

  const uploadAvatar = async (event) => {
    const file = event.target.files?.[0];
    if (!file) {
      return;
    }

    setError('');
    setNotice('');
    setAvatarBusy(true);

    try {
      const form = new FormData();
      form.append('avatar', file);

      const data = await post('/app-api/user/avatar', form);
      acceptUser(data.user);
      setNotice('Profile picture updated.');
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setAvatarBusy(false);
      event.target.value = '';
    }
  };

  const removeAvatar = async () => {
    setAvatarBusy(true);
    setError('');

    try {
      const data = await del('/app-api/user/avatar');

      acceptUser(data.user);
      setRemoveAvatarOpen(false);
      setNotice('Profile picture removed.');
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setAvatarBusy(false);
    }
  };

  const exportData = async () => {
    setError('');
    setNotice('');
    setExporting(true);

    try {
      await downloadJson('/app-api/user/export');
      setNotice('Account data exported.');
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setExporting(false);
    }
  };

  if (!account) {
    return (
      <Page>{error ? <Alert>{error}</Alert> : <LoadingState label="Loading settings…" />}</Page>
    );
  }

  return (
    <Page>
      <Breadcrumbs>
        <BreadcrumbButton onClick={() => navigate('/app')}>Organizations</BreadcrumbButton>
        <span>/</span>
        <span className="font-medium text-ink">Settings</span>
      </Breadcrumbs>

      <PageHeader
        eyebrow="Account"
        title="Settings"
        description="Manage your personal Okatana account and export the data associated with it."
        compact
      />

      {error && <Alert>{error}</Alert>}
      {notice && <Alert tone="success">{notice}</Alert>}

      <GridLayout>
        <Panel className="col-span-4 md:col-span-5 xl:col-span-7">
          <SectionHeading
            title="Personal information"
            description="Your full name and profile picture are shown with your work and account identity. Existing immutable audit records keep their historical actor name snapshot while displaying your current profile picture when your account still exists."
          />

          <div className="mb-8 flex flex-wrap items-center gap-6 border-b border-line pb-8">
            <Avatar user={account} size="lg" />
            <div>
              <div className="text-sm font-medium text-ink">Profile picture</div>
              <p className="mt-2 max-w-lg text-sm text-ink-muted">
                JPEG, PNG, GIF, or WebP. Maximum 5 MB.
              </p>
              <div className="mt-4 flex flex-wrap gap-2">
                <input
                  ref={avatarInput}
                  hidden
                  type="file"
                  accept="image/jpeg,image/png,image/gif,image/webp"
                  onChange={uploadAvatar}
                />
                <Button onClick={() => avatarInput.current?.click()} disabled={avatarBusy}>
                  {avatarBusy ? 'Updating…' : account.avatar_url ? 'Change picture' : 'Set picture'}
                </Button>
                {account.avatar_url && (
                  <Button
                    variant="danger"
                    onClick={() => setRemoveAvatarOpen(true)}
                    disabled={avatarBusy}
                  >
                    Remove picture
                  </Button>
                )}
              </div>
            </div>
          </div>

          <form className="grid gap-6" onSubmit={save}>
            <Field label="Full name">
              <TextInput
                required
                maxLength={120}
                autoComplete="name"
                value={name}
                onChange={(event) => setName(event.target.value)}
              />
            </Field>
            <Field label="Email" hint="Email changes are not available from this screen.">
              <TextInput value={account.email} readOnly disabled />
            </Field>
            <fieldset className="border-t border-line pt-6">
              <legend className="mb-4 text-sm font-medium text-ink">Notifications</legend>
              <div className="grid gap-2">
                <Check
                  checked={emailNotifyAssigned}
                  onChange={(event) => setEmailNotifyAssigned(event.target.checked)}
                >
                  Email me when I am assigned to a ticket
                </Check>
                <Check
                  checked={emailNotifyMentions}
                  onChange={(event) => setEmailNotifyMentions(event.target.checked)}
                >
                  Email me when I am mentioned in a ticket, article, or comment
                </Check>
                <Check
                  checked={notifyDocumentComments}
                  onChange={(event) => setNotifyDocumentComments(event.target.checked)}
                >
                  Notify me when someone comments on an article I own or edit
                </Check>
              </div>
              <p className="mt-4 text-xs text-ink-muted">
                Assignment and mention controls change email delivery only. The article-comment
                control turns both in-app and email notifications for general article comment
                activity on or off; direct mentions still notify you.
              </p>
            </fieldset>
            <div>
              <Button type="submit" variant="primary" disabled={saving || !name.trim()}>
                {saving ? 'Saving…' : 'Save account settings'}
              </Button>
            </div>
          </form>
        </Panel>

        <Panel className="col-span-4 md:col-span-3 xl:col-span-5">
          <SectionHeading
            title="Export your data"
            description="Download a JSON copy of your account profile, memberships, assigned and authored work, comments, invitation history, audit activity, transfer history, and integration metadata. Passwords, API secret hashes, webhook secrets, invitation tokens, and session credentials are excluded."
          />
          <Button onClick={exportData} disabled={exporting}>
            {exporting ? 'Exporting…' : 'Export account data'}
          </Button>
        </Panel>
      </GridLayout>

      <ConfirmDialog
        open={removeAvatarOpen}
        title="Remove profile picture"
        description="Remove your current profile picture? Okatana will use your initials anywhere an avatar is shown."
        confirmLabel="Remove picture"
        onCancel={() => setRemoveAvatarOpen(false)}
        onConfirm={removeAvatar}
        busy={avatarBusy}
      />
    </Page>
  );
}
