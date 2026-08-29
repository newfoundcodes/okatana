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
import { get, navigate, post } from '../lib/api';
import AccountSecuritySetup from '../components/AccountSecuritySetup';
import {
  ActionLink,
  Alert,
  Button,
  Field,
  GridGuides,
  GridLayout,
  LoadingState,
  Page,
  TextInput,
} from '../components/Primitives';

export default function InvitePage({ token, onAuthenticated }) {
  const [invite, setInvite] = useState(null);
  const [form, setForm] = useState({
    name: '',
    current_password: '',
    password: '',
    password_confirmation: '',
  });
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [securityFlow, setSecurityFlow] = useState(null);
  const [destination, setDestination] = useState(null);
  const [challenge, setChallenge] = useState(false);
  const [challengeCode, setChallengeCode] = useState('');

  const finish = (data, target = destination) => {
    onAuthenticated(data.user);
    navigate(
      target?.project_id
        ? `/app/projects/${target.project_id}`
        : `/app/organizations/${target?.organization_id}`,
    );
  };

  const submit = async (event) => {
    event.preventDefault();

    setBusy(true);
    setError('');

    try {
      const data = await post(`/app-api/invitations/${token}/accept`, form);
      const target = { organization_id: data.organization_id, project_id: data.project_id };

      setDestination(target);
      if (data.status === 'two_factor_challenge_required') {
        setChallenge(true);
      } else if (data.security && !data.security.complete) {
        setSecurityFlow({ security: data.security, email: data.user.email });
      } else {
        finish(data, target);
      }
    } catch (requestError) {
      setError(
        requestError.errors
          ? Object.values(requestError.errors).flat().join(' ')
          : requestError.message,
      );
    } finally {
      setBusy(false);
    }
  };

  const confirmChallenge = async (event) => {
    event.preventDefault();

    setBusy(true);
    setError('');

    try {
      const data = await post('/app-api/auth/two-factor/challenge', { code: challengeCode });
      finish(data);
    } catch (requestError) {
      setError(
        requestError.errors
          ? Object.values(requestError.errors).flat().join(' ')
          : requestError.message,
      );
    } finally {
      setBusy(false);
    }
  };

  useEffect(() => {
    get(`/app-api/invitations/${token}`)
      .then((data) => {
        setInvite(data.invitation);

        const target = {
          organization_id: data.invitation.organization.id,
          project_id: data.invitation.project?.id || null,
        };

        if (data.invitation.security_pending) {
          setSecurityFlow({
            security: data.invitation.security,
            email: data.invitation.email,
          });
          setDestination(target);
        } else if (data.invitation.two_factor_challenge_pending) {
          setChallenge(true);
          setDestination(target);
        }
      })
      .catch((requestError) => setError(requestError.message));
  }, [token]);

  return (
    <main className="relative min-h-screen bg-paper">
      <GridGuides />
      <Page>
        <GridLayout>
          <section className="col-span-4 border-l-2 border-line-strong pl-4 md:col-span-3 xl:col-span-5">
            <div className="text-xs font-medium uppercase tracking-widest text-ink-muted">
              Invitation
            </div>
            <h1 className="mt-4 text-3xl font-semibold tracking-display text-ink">Join Okatana</h1>
            <p className="mt-4 max-w-xl text-base text-ink-muted">
              {invite?.two_factor_required
                ? 'Your account must use a strong password and a configured authenticator app before you enter the workspace.'
                : 'Your account must use a strong password before you enter the workspace.'}
            </p>
          </section>

          <section className="col-span-4 mt-12 border-t-2 border-ink pt-6 md:col-span-5 md:mt-0 xl:col-span-7">
            {securityFlow ? (
              <AccountSecuritySetup
                initialSecurity={securityFlow.security}
                email={securityFlow.email}
                heading="Set up two-factor authentication"
                onComplete={(data) => finish(data)}
              />
            ) : challenge ? (
              <section aria-label="Invitation two-factor challenge">
                <div className="text-xs font-medium uppercase tracking-widest text-ink-muted">
                  Two-factor authentication
                </div>
                <h2 className="mt-4 text-xl font-semibold tracking-display text-ink">
                  Confirm your authenticator code
                </h2>
                <p className="mt-4 text-sm text-ink-muted">
                  Your account already has an authenticator app. Enter its current six-digit code to
                  finish joining.
                </p>
                {error && (
                  <div className="mt-8">
                    <Alert>{error}</Alert>
                  </div>
                )}
                <form onSubmit={confirmChallenge} className="mt-8 grid gap-4">
                  <Field label="Authenticator code">
                    <TextInput
                      autoFocus
                      autoComplete="one-time-code"
                      inputMode="numeric"
                      pattern="[0-9]{6}"
                      minLength={6}
                      maxLength={6}
                      value={challengeCode}
                      onChange={(event) =>
                        setChallengeCode(event.target.value.replace(/\D/g, '').slice(0, 6))
                      }
                      placeholder="000000"
                      className="font-medium tracking-widest tabular-nums"
                      required
                    />
                  </Field>
                  <div>
                    <Button
                      type="submit"
                      variant="primary"
                      disabled={busy || challengeCode.length !== 6}
                    >
                      {busy ? 'Checking…' : 'Finish joining'}
                    </Button>
                  </div>
                </form>
              </section>
            ) : (
              <>
                <h2 className="text-xl font-semibold tracking-display">Accept invitation</h2>
                {error && (
                  <div className="mt-8">
                    <Alert>{error}</Alert>
                  </div>
                )}

                {!invite ? (
                  <div className="mt-8">
                    <LoadingState label="Loading invitation…" />
                  </div>
                ) : invite.account_exists ? (
                  <div className="mt-8 grid gap-6">
                    <dl className="grid grid-cols-4 gap-6 border-y border-line py-6 md:grid-cols-8">
                      <div className="col-span-4 md:col-span-4">
                        <dt className="text-sm text-ink-muted">Organization</dt>
                        <dd className="mt-2 text-base font-medium text-ink">
                          {invite.organization.name}
                        </dd>
                      </div>
                      <div className="col-span-2 md:col-span-2">
                        <dt className="text-sm text-ink-muted">Role</dt>
                        <dd className="mt-2 text-base font-medium text-ink">{invite.role}</dd>
                      </div>
                      {invite.project && (
                        <div className="col-span-2 md:col-span-2">
                          <dt className="text-sm text-ink-muted">Project</dt>
                          <dd className="mt-2 text-base font-medium text-ink">
                            {invite.project.name}
                          </dd>
                        </div>
                      )}
                    </dl>

                    <div className="border-l-2 border-line-strong pl-4">
                      <p className="text-sm text-ink-muted">
                        You already have an Okatana account using{' '}
                        <span className="font-medium text-ink">{invite.email}</span>. Your
                        invitation is waiting in your notification panel — sign in and click the{' '}
                        <span className="font-medium text-ink">Notifications</span> bell to accept
                        or decline.
                      </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                      <ActionLink href="/app" variant="primary">
                        Open Okatana
                      </ActionLink>
                    </div>
                  </div>
                ) : (
                  <>
                    <dl className="mt-8 grid grid-cols-4 gap-6 border-y border-line py-6 md:grid-cols-8">
                      <div className="col-span-4 md:col-span-4">
                        <dt className="text-sm text-ink-muted">Organization</dt>
                        <dd className="mt-2 text-md font-medium">{invite.organization.name}</dd>
                      </div>
                      <div className="col-span-2 md:col-span-2">
                        <dt className="text-sm text-ink-muted">Role</dt>
                        <dd className="mt-2 text-md font-medium">{invite.role}</dd>
                      </div>
                      <div className="col-span-2 md:col-span-2">
                        <dt className="text-sm text-ink-muted">Project</dt>
                        <dd className="mt-2 text-md font-medium">
                          {invite.project?.name || 'Organization only'}
                        </dd>
                      </div>
                      <div className="col-span-4 md:col-span-8">
                        <dt className="text-sm text-ink-muted">Invited email</dt>
                        <dd className="mt-2 break-all text-md font-medium">{invite.email}</dd>
                      </div>
                    </dl>

                    <form onSubmit={submit} className="mt-8 grid gap-4">
                      <Field label="Your name">
                        <TextInput
                          required
                          value={form.name}
                          onChange={(event) =>
                            setForm({
                              ...form,
                              name: event.target.value,
                            })
                          }
                        />
                      </Field>
                      {invite.requires_current_password && (
                        <Field
                          label="Current password"
                          hint="Enter your current Okatana password before this invitation can change credentials on an existing account."
                        >
                          <TextInput
                            type="password"
                            required
                            value={form.current_password}
                            onChange={(event) =>
                              setForm({
                                ...form,
                                current_password: event.target.value,
                              })
                            }
                          />
                        </Field>
                      )}
                      <Field
                        label="Strong password"
                        hint="Minimum 14 characters with mixed case, a number, and a symbol."
                      >
                        <TextInput
                          type="password"
                          required
                          value={form.password}
                          onChange={(event) =>
                            setForm({
                              ...form,
                              password: event.target.value,
                            })
                          }
                        />
                      </Field>
                      <Field label="Confirm password">
                        <TextInput
                          type="password"
                          required
                          value={form.password_confirmation}
                          onChange={(event) =>
                            setForm({
                              ...form,
                              password_confirmation: event.target.value,
                            })
                          }
                        />
                      </Field>
                      <div className="mt-4">
                        <Button type="submit" variant="primary" disabled={busy}>
                          {busy ? 'Saving…' : 'Continue'}
                        </Button>
                      </div>
                    </form>
                  </>
                )}
              </>
            )}
          </section>
        </GridLayout>
      </Page>
    </main>
  );
}
