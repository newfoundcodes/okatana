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
import { get, post } from '../lib/api';
import AccountSecuritySetup from '../components/AccountSecuritySetup';
import {
  ActionLink,
  Alert,
  Button,
  Field,
  GridGuides,
  GridLayout,
  InlineAction,
  Page,
  Tabs,
  TextInput,
} from '../components/Primitives';

export default function AuthPage({ onAuthenticated }) {
  const [mode, setMode] = useState('login');
  const [config, setConfig] = useState({ oauth: [], sso: [], two_factor_required: true });
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [form, setForm] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
  });
  const [securityFlow, setSecurityFlow] = useState(null);
  const [challenge, setChallenge] = useState(null);
  const [challengeCode, setChallengeCode] = useState('');

  const submit = async (event) => {
    event.preventDefault();

    setBusy(true);
    setError('');

    try {
      const data = await post(`/app-api/auth/${mode}`, form);
      if (data.status === 'two_factor_challenge_required') {
        setChallenge(data.account);
        setChallengeCode('');
      } else if (data.security && !data.security.complete) {
        setMode(mode === 'register' ? 'register' : 'login');
        setSecurityFlow({ security: data.security, email: data.user?.email || form.email });
      } else {
        onAuthenticated(data.user);
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

  const confirmLoginTwoFactor = async (event) => {
    event.preventDefault();

    setBusy(true);
    setError('');

    try {
      const data = await post('/app-api/auth/two-factor/challenge', { code: challengeCode });
      onAuthenticated(data.user);
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

  const providers = [
    ...(config.oauth || []).map((provider) => ({ type: 'oauth', provider })),
    ...(config.sso || []).map((provider) => ({ type: 'sso', provider })),
  ];

  useEffect(() => {
    get('/app-api/auth/config')
      .then(setConfig)
      .catch(() => {});
    get('/app-api/auth/two-factor/pending')
      .then((data) => {
        if (data.pending && data.account) {
          setChallenge(data.account);
        }
      })
      .catch(() => {});
  }, []);

  return (
    <main className="relative min-h-screen bg-paper flex flex-col">
      <div className="relative z-20 w-full h-[3.5vh] bg-accent shrink-0" />
      <GridGuides />
      <Page className="flex-1 flex flex-col">
        <GridLayout className="items-start xl:mt-auto w-full">
          <section className="col-span-4 self-end lg:border-l-2 lg:border-line-strong lg:pl-4 md:col-span-12 xl:col-span-7">
            <img src="/logo.png" alt="Okatana" className="mt-4 h-24 md:h-32 lg:h-48 w-auto" />
            <h1 className="mt-4 text-4xl font-semibold tracking-display text-ink">Okatana</h1>
            <div className="text-xs font-medium uppercase tracking-widest text-ink-muted">
              Project management
            </div>
            <p className="mt-6 max-w-2xl text-lg text-ink-muted">
              Organize project work, decisions, access, audit history, and external integrations in
              one workspace.
            </p>
            <div className="mt-8 flex flex-wrap gap-4">
              <ActionLink href="/docs" target="_blank" rel="noopener noreferrer">
                Help Center
              </ActionLink>
              <ActionLink href="/docs/api" target="_blank" rel="noopener noreferrer">
                API Documentations
              </ActionLink>
            </div>
          </section>

          <section className="col-span-4 mt-12 border-t-2 border-ink pt-6 md:col-span-12 md:mt-0 xl:col-span-5">
            {securityFlow ? (
              <AccountSecuritySetup
                initialSecurity={securityFlow.security}
                email={securityFlow.email}
                heading={
                  mode === 'register'
                    ? 'Finish creating your account'
                    : 'Finish securing your account'
                }
                onComplete={(data) => onAuthenticated(data.user)}
              />
            ) : challenge ? (
              <section aria-label="Two-factor login challenge">
                <div className="text-xs font-medium uppercase tracking-widest text-ink-muted">
                  Two-factor authentication
                </div>
                <h2 className="mt-4 text-xl font-semibold tracking-display text-ink">
                  Confirm this login
                </h2>
                <p className="mt-4 text-sm text-ink-muted">
                  Enter the current six-digit code from the authenticator app configured for{' '}
                  {challenge.email}.
                </p>
                {error && (
                  <div className="mt-8">
                    <Alert>{error}</Alert>
                  </div>
                )}
                <form onSubmit={confirmLoginTwoFactor} className="mt-8 grid gap-4">
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
                  <div className="flex flex-wrap items-center gap-4">
                    <Button
                      type="submit"
                      variant="primary"
                      disabled={busy || challengeCode.length !== 6}
                    >
                      {busy ? 'Checking…' : 'Confirm login'}
                    </Button>
                    <InlineAction
                      type="button"
                      onClick={() => {
                        setChallenge(null);
                        setChallengeCode('');
                        setError('');
                      }}
                    >
                      Use different credentials
                    </InlineAction>
                  </div>
                </form>
              </section>
            ) : (
              <>
                <Tabs
                  value={mode}
                  onChange={(value) => {
                    setMode(value);
                    setError('');
                    setForm({
                      name: '',
                      email: '',
                      password: '',
                      password_confirmation: '',
                    });
                  }}
                  items={[
                    { value: 'login', label: 'Log in' },
                    { value: 'register', label: 'Sign up' },
                  ]}
                />

                <h2 className="text-xl font-semibold tracking-display text-ink">
                  {mode === 'login' ? 'Access your workspace' : 'Create your account'}
                </h2>
                <p className="mt-2 text-sm text-ink-muted">
                  {mode === 'login'
                    ? config.two_factor_required
                      ? 'Use your password, then confirm the login with your authenticator app.'
                      : 'Use your email address and password to access the workspace.'
                    : config.two_factor_required
                      ? 'After signup, confirm your email address and set up an authenticator app before entering the workspace.'
                      : 'After signup, confirm your email address before entering the workspace.'}
                </p>

                {error && (
                  <div className="mt-8">
                    <Alert>{error}</Alert>
                  </div>
                )}

                <form onSubmit={submit} className="mt-8 grid gap-4">
                  {mode === 'register' && (
                    <Field label="Name">
                      <TextInput
                        value={form.name}
                        onChange={(event) => setForm({ ...form, name: event.target.value })}
                        required
                      />
                    </Field>
                  )}
                  <Field label="Email">
                    <TextInput
                      type="email"
                      value={form.email}
                      onChange={(event) => setForm({ ...form, email: event.target.value })}
                      required
                    />
                  </Field>
                  <Field
                    label="Password"
                    hint={
                      mode === 'register'
                        ? 'Use at least 14 characters with uppercase and lowercase letters, a number, and a symbol.'
                        : null
                    }
                  >
                    <TextInput
                      type="password"
                      value={form.password}
                      onChange={(event) => setForm({ ...form, password: event.target.value })}
                      required
                    />
                  </Field>
                  {mode === 'register' && (
                    <Field label="Confirm password">
                      <TextInput
                        type="password"
                        value={form.password_confirmation}
                        onChange={(event) =>
                          setForm({
                            ...form,
                            password_confirmation: event.target.value,
                          })
                        }
                        required
                      />
                    </Field>
                  )}
                  <div className="mt-4">
                    <Button type="submit" variant="primary" disabled={busy}>
                      {busy ? 'Submitting…' : mode === 'login' ? 'Continue' : 'Create account'}
                    </Button>
                  </div>
                </form>

                {providers.length > 0 && (
                  <section className="mt-12 border-t border-line pt-6" aria-label="Single sign-on">
                    <h3 className="text-md font-semibold text-ink">Other sign-in methods</h3>
                    <div className="mt-4 flex flex-wrap gap-2">
                      {providers.map(({ type, provider }) => (
                        <ActionLink key={`${type}-${provider}`} href={`/auth/${type}/${provider}`}>
                          Continue with {provider}
                        </ActionLink>
                      ))}
                    </div>
                  </section>
                )}
              </>
            )}
          </section>
        </GridLayout>
      </Page>
    </main>
  );
}
