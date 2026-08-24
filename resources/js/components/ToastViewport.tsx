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
import { createPortal } from 'react-dom';
import { OKATANA_TOAST_EVENT } from '../lib/toast';
import CloseIcon from './CloseIcon';

export default function ToastViewport() {
  const [toasts, setToasts] = useState<Toast[]>([]);
  const timers = useRef<Map<string, number>>(new Map());

  const dismiss = (id: string) => {
    const timer = timers.current.get(id);
    if (timer) {
      window.clearTimeout(timer);
    }

    timers.current.delete(id);
    setToasts((current) => current.filter((item) => item.id !== id));
  };

  useEffect(() => {
    const onToast = (event: any) => {
      const detail = event.detail || {};
      if (!detail.message) {
        return;
      }

      const id = globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`;
      const next: Toast = {
        id,
        tone: detail.tone === 'error' ? 'error' : 'success',
        message: detail.message,
      };
      setToasts((current) => [...current.slice(-3), next]);

      const timer = window.setTimeout(() => dismiss(id), Number(detail.duration) || 4000);
      timers.current.set(id, timer);
    };

    window.addEventListener(OKATANA_TOAST_EVENT, onToast);
    return () => {
      window.removeEventListener(OKATANA_TOAST_EVENT, onToast);

      timers.current.forEach((timer) => window.clearTimeout(timer));
      timers.current.clear();
    };
  }, []);

  return createPortal(
    <div
      className="fixed bottom-8 left-8 right-8 z-[100] flex flex-col gap-2 md:left-auto md:right-12 md:w-96"
      aria-label="Notifications"
      aria-live="polite"
      aria-atomic="false"
    >
      {toasts.map((item) => (
        <div
          key={item.id}
          role={item.tone === 'error' ? 'alert' : 'status'}
          className={`rounded-sm border border-line-strong border-l-2 bg-paper p-4 shadow ${item.tone === 'error' ? 'border-l-error' : 'border-l-success'}`}
        >
          <div className="flex items-start justify-between gap-4">
            <div className="min-w-0">
              <div
                className={`text-xs font-medium uppercase tracking-widest ${item.tone === 'error' ? 'text-error' : 'text-success'}`}
              >
                {item.tone === 'error' ? 'Error' : 'Success'}
              </div>
              <p className="mt-2 text-sm text-ink">{item.message}</p>
            </div>
            <button
              type="button"
              className="inline-grid h-8 w-8 shrink-0 place-items-center rounded-sm border border-transparent bg-transparent text-ink-muted transition-colors duration-150 ease-out hover:border-line hover:bg-surface hover:text-ink focus:outline focus:outline-2 focus:outline-offset-2 focus:outline-accent motion-reduce:transition-none"
              onClick={() => dismiss(item.id)}
              aria-label="Dismiss notification"
              title="Dismiss"
            >
              <CloseIcon />
            </button>
          </div>
        </div>
      ))}
    </div>,
    document.body,
  );
}
