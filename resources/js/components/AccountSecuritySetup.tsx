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

import React, { useEffect, useState } from 'react';
import { get, post } from '../lib/api';
import { Alert, Button, CopyValueButton, Field, InlineAction, TextInput } from './Primitives';
import { formatEmailCode, normalizeOtp } from '../lib/securitySetup';

export default function AccountSecuritySetup({
  initialSecurity,
  email,
  onComplete,
  heading = 'Secure your account',
}: AccountSecuritySetupProps) {
  const [security, setSecurity] = useState<SecurityStatus>(
    initialSecurity || {
      email_required: true,
      two_factor_required: true,
      email_verified: false,
      two_factor_confirmed: false,
      complete: false,
    },
  );

  const [emailCode, setEmailCode] = useState('');
  const [otp, setOtp] = useState('');
  const [setup, setSetup] = useState<TwoFactorSetup | null>(null);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [busy, setBusy] = useState(false);

  const emailPending = Boolean(security.email_required) && !security.email_verified;
  const twoFactorPending = Boolean(security.two_factor_required) && !security.two_factor_confirmed;

  const loadTwoFactorSetup = async () => {
    setBusy(true);
    setError('');

    try {
      const data = await get('/app-api/auth/two-factor/setup');
      setSetup(data.setup);
    } catch (requestError: any) {
      setError(requestError.message);
    } finally {
      setBusy(false);
    }
  };

  const verifyEmail = async (event: React.FormEvent) => {
    event.preventDefault();
    setBusy(true);

    setError('');
    setMessage('');

    try {
      const data = await post('/app-api/auth/email/verify-code', { code: emailCode });
      setSecurity(data.security);

      if (data.security.complete) {
        onComplete?.({ ...data, status: 'authenticated' });
      } else {
        setMessage(
          data.security.two_factor_required
            ? 'Email address confirmed. Set up your authenticator app next.'
            : 'Email address confirmed.',
        );
      }
    } catch (requestError: any) {
      setError(
        requestError.errors
          ? Object.values(requestError.errors).flat().join(' ')
          : requestError.message,
      );
    } finally {
      setBusy(false);
    }
  };

  const resend = async () => {
    setBusy(true);
    setError('');
    setMessage('');

    try {
      const data = await post('/app-api/auth/email/resend-code', {});
      setMessage(data.message || 'Confirmation code sent.');
    } catch (requestError: any) {
      setError(requestError.message);
    } finally {
      setBusy(false);
    }
  };

  const confirmTwoFactor = async (event: React.FormEvent) => {
    event.preventDefault();

    setBusy(true);
    setError('');
    setMessage('');

    try {
      const data = await post('/app-api/auth/two-factor/confirm', { code: otp });

      setSecurity(data.security);
      onComplete?.(data);
    } catch (requestError: any) {
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
    if (!emailPending && twoFactorPending && !setup) {
      loadTwoFactorSetup();
    }
  }, [emailPending, twoFactorPending, setup]);

  useEffect(() => {
    if (!emailPending) {
      return;
    }

    post('/app-api/auth/email/resend-code', {}).catch(() => {});
  }, []);

  return (
    <section aria-label="Account security setup">
      <div className="border-l-2 border-ink pl-4">
        <div className="text-xs font-medium uppercase tracking-widest text-ink-muted">
          Account security
        </div>
        <h2 className="mt-4 text-xl font-semibold tracking-display text-ink">{heading}</h2>
        <p className="mt-4 max-w-2xl text-sm text-ink-muted">
          {security.two_factor_required
            ? 'Complete email confirmation and authenticator setup before Okatana opens the workspace.'
            : 'Confirm your email address before Okatana opens the workspace. Authenticator enforcement is disabled for this deployment.'}
        </p>
      </div>

      {error && (
        <div className="mt-8">
          <Alert>{error}</Alert>
        </div>
      )}
      {message && (
        <div className="mt-8">
          <Alert tone="success">{message}</Alert>
        </div>
      )}

      {emailPending ? (
        <form onSubmit={verifyEmail} className="mt-8 grid gap-4">
          <div>
            <h3 className="text-lg font-semibold text-ink">Confirm your email address</h3>
            <p className="mt-2 max-w-2xl text-sm text-ink-muted">
              Enter the eight-character code sent to {email || 'your email address'}. The code is
              formatted as XXXX-XXXX.
            </p>
          </div>
          <Field label="Confirmation code" hint="Uppercase letters and digits only.">
            <TextInput
              autoFocus
              autoComplete="one-time-code"
              inputMode="text"
              value={emailCode}
              onChange={(event) => setEmailCode(formatEmailCode(event.target.value))}
              placeholder="XXXX-XXXX"
              minLength={9}
              maxLength={9}
              required
              className="font-medium tracking-widest"
            />
          </Field>
          <div className="flex flex-wrap items-center gap-4">
            <Button type="submit" variant="primary" disabled={busy || emailCode.length !== 9}>
              {busy ? 'Checking…' : 'Confirm email'}
            </Button>
            <InlineAction type="button" onClick={resend} disabled={busy}>
              Send another code
            </InlineAction>
          </div>
        </form>
      ) : twoFactorPending ? (
        <form onSubmit={confirmTwoFactor} className="mt-8 grid gap-6">
          <div>
            <h3 className="text-lg font-semibold text-ink">Set up an authenticator app</h3>
            <p className="mt-2 max-w-2xl text-sm text-ink-muted">
              Scan the QR code with Google Authenticator, Microsoft Authenticator, 1Password, or
              another TOTP-compatible app.
            </p>
          </div>

          {setup ? (
            <div className="grid grid-cols-4 gap-6 md:grid-cols-8">
              <div className="col-span-4 md:col-span-4">
                <div className="w-fit max-w-full border border-line bg-paper p-4">
                  <img
                    src={setup.qr_data_url}
                    alt={`Authenticator QR code for ${setup.account}`}
                    width="320"
                    height="320"
                    className="authenticator-qr block h-auto max-w-full"
                  />
                </div>
              </div>
              <div className="col-span-4 md:col-span-4">
                <div className="border-y border-line py-4">
                  <div className="text-xs font-medium uppercase tracking-widest text-ink-muted">
                    Manual key
                  </div>
                  <div className="mt-2 flex items-start gap-2">
                    <code className="min-w-0 break-all text-sm font-medium text-ink">
                      {setup.manual_key}
                    </code>
                    <CopyValueButton value={setup.manual_key} label="Copy authenticator key" />
                  </div>
                </div>
                <dl className="mt-4 grid gap-2 text-sm">
                  <div>
                    <dt className="text-ink-muted">Issuer</dt>
                    <dd className="font-medium text-ink">{setup.issuer}</dd>
                  </div>
                  <div>
                    <dt className="text-ink-muted">Account</dt>
                    <dd className="break-all font-medium text-ink">{setup.account}</dd>
                  </div>
                </dl>
              </div>
            </div>
          ) : (
            <div
              className="border-l-2 border-line-strong px-4 py-2 text-sm text-ink-muted"
              role="status"
            >
              Preparing authenticator setup…
            </div>
          )}

          <Field label="Authenticator code">
            <TextInput
              autoComplete="one-time-code"
              inputMode="numeric"
              pattern="[0-9]{6}"
              value={otp}
              onChange={(event) => setOtp(normalizeOtp(event.target.value))}
              placeholder="000000"
              minLength={6}
              maxLength={6}
              required
              className="font-medium tracking-widest tabular-nums"
            />
          </Field>
          <div>
            <Button type="submit" variant="primary" disabled={busy || !setup || otp.length !== 6}>
              {busy ? 'Checking…' : 'Confirm authenticator'}
            </Button>
          </div>
        </form>
      ) : (
        <div
          className="mt-8 border-l-2 border-success px-4 py-2 text-sm text-success"
          role="status"
        >
          Account security setup complete.
        </div>
      )}
    </section>
  );
}
