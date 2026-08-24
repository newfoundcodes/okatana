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

import { useEffect, useId, useRef } from 'react';
import { createPortal } from 'react-dom';
import { Button } from './Primitives';
import { focusableSelector } from '../lib/modal';
import { cx } from '../lib/uiPrimitives';

export default function Modal({ open, onClose, title, children, wide = false }: ModalProps) {
  const dialogRef = useRef<HTMLElement>(null);
  const closeRef = useRef<HTMLButtonElement>(null);
  const onCloseRef = useRef(onClose);
  const titleId = useId();

  useEffect(() => {
    onCloseRef.current = onClose;
  }, [onClose]);

  useEffect(() => {
    if (!open) {
      return undefined;
    }

    const previous = document.activeElement as HTMLElement | null;
    const dialog = dialogRef.current;
    const frame = requestAnimationFrame(() => {
      const preferred = dialog?.querySelector<HTMLElement>(focusableSelector);
      (preferred || closeRef.current)?.focus();
    });

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        onCloseRef.current?.();
        return;
      }

      if (event.key !== 'Tab' || !dialog) {
        return;
      }

      const focusable = [
        ...dialog.querySelectorAll<HTMLElement>(
          'button:not([disabled]), a[href], input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), [contenteditable="true"], [tabindex]:not([tabindex="-1"])',
        ),
      ];

      if (!focusable.length) {
        return;
      }

      const first = focusable[0];
      const last = focusable[focusable.length - 1];

      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };

    window.addEventListener('keydown', onKeyDown);
    return () => {
      cancelAnimationFrame(frame);
      window.removeEventListener('keydown', onKeyDown);
      previous?.focus?.();
    };
  }, [open]);

  if (!open) {
    return null;
  }

  return createPortal(
    <div
      className="fixed inset-0 z-50 overflow-y-auto bg-ink/40 px-8 py-8 md:px-12 md:py-12"
      style={{ zIndex: 110 }}
      onMouseDown={(event) => event.target === event.currentTarget && onCloseRef.current?.()}
    >
      <div
        className="grid min-h-full grid-cols-4 items-start gap-6 md:grid-cols-8 xl:grid-cols-12"
        onMouseDown={(event) => event.target === event.currentTarget && onCloseRef.current?.()}
      >
        <section
          ref={dialogRef}
          role="dialog"
          aria-modal="true"
          aria-labelledby={titleId}
          className={cx(
            'col-span-4 max-h-full w-full overflow-auto rounded-sm border border-line-strong bg-paper shadow outline-none',
            wide
              ? 'md:col-span-8 xl:col-span-10 xl:col-start-2'
              : 'md:col-span-6 md:col-start-2 xl:col-span-6 xl:col-start-4',
          )}
        >
          <header className="grid grid-cols-4 gap-4 border-b border-line p-6 md:grid-cols-8 xl:grid-cols-12">
            <h2
              id={titleId}
              className="col-span-3 text-xl font-semibold tracking-display md:col-span-6 xl:col-span-10"
            >
              {title}
            </h2>
            <div className="col-span-1 flex justify-end md:col-span-2 xl:col-span-2">
              <Button
                ref={closeRef}
                data-dialog-close
                compact
                onClick={() => onCloseRef.current?.()}
              >
                Close
              </Button>
            </div>
          </header>
          <div className="p-6">{children}</div>
        </section>
      </div>
    </div>,
    document.body,
  );
}
