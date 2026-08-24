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

const csrf = (): string =>
  document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';

export class ApiError extends Error {
  public status: number;
  public errors: any;

  constructor(message: string, status: number, errors: any = null) {
    super(message);
    this.status = status;
    this.errors = errors;
  }
}

export async function api<T = any>(path: string, options: ApiOptions = {}): Promise<T | null> {
  const method = (options.method || 'GET').toUpperCase();
  const headers: Record<string, string> = {
    Accept: 'application/json',
    ...((options.headers as Record<string, string>) || {}),
  };

  let body: BodyInit | null | undefined = options.body as BodyInit | null | undefined;
  if (options.body && !(options.body instanceof FormData) && typeof options.body !== 'string') {
    headers['Content-Type'] = 'application/json';
    body = JSON.stringify(options.body);
  }

  if (!['GET', 'HEAD'].includes(method)) {
    headers['X-CSRF-TOKEN'] = csrf();
  }

  const response = await fetch(path, {
    credentials: 'same-origin',
    ...options,
    method,
    headers,
    body,
  } as RequestInit);

  if (response.status === 204) {
    return null;
  }

  const type = response.headers.get('content-type') || '';
  const data = type.includes('application/json') ? await response.json() : await response.text();

  if (!response.ok) {
    throw new ApiError(
      data?.message || `Request failed with HTTP ${response.status}`,
      response.status,
      data?.errors || null,
    );
  }

  return data;
}

export const get = <T = any>(p: string): Promise<T | null> => api<T>(p);
export const post = <T = any>(p: string, body?: any): Promise<T | null> =>
  api<T>(p, { method: 'POST', body });
export const patch = <T = any>(p: string, body?: any): Promise<T | null> =>
  api<T>(p, { method: 'PATCH', body });
export const put = <T = any>(p: string, body?: any): Promise<T | null> =>
  api<T>(p, { method: 'PUT', body });
export const del = <T = any>(p: string, body?: any): Promise<T | null> =>
  api<T>(p, { method: 'DELETE', body });

export async function downloadJson(path: string): Promise<void> {
  const response = await fetch(path, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  });

  if (!response.ok) {
    throw new ApiError(`Export failed with HTTP ${response.status}`, response.status);
  }

  const blob = await response.blob();
  const disposition = response.headers.get('content-disposition') || '';
  const filename = disposition.match(/filename="([^"]+)"/)?.[1] || 'okatana-export.json';
  const url = URL.createObjectURL(blob);

  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();

  URL.revokeObjectURL(url);
}

export function navigate(path: string): void {
  if (location.pathname !== path) {
    history.pushState({}, '', path);
  }

  window.dispatchEvent(new PopStateEvent('popstate'));
}
