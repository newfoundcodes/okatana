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

import React, { useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { OverflowIcon } from './Primitives';
import { cx } from '../lib/uiPrimitives';

export default function DropdownMenu({
  label = null,
  ariaLabel,
  items,
  align = 'right',
  compact = false,
  stopPropagation = false,
  className = '',
}: DropdownMenuProps) {
  const [open, setOpen] = useState(false);
  const [position, setPosition] = useState<{
    top: number;
    left: number | null;
    right: number | null;
  }>({ top: 0, left: null, right: 0 });
  const rootRef = useRef<HTMLDivElement>(null);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const menuRef = useRef<HTMLDivElement>(null);
  const menuId = useId();

  const enabledItems = () => [
    ...(menuRef.current?.querySelectorAll<HTMLElement>('[role="menuitem"]:not([disabled])') || []),
  ];

  const updatePosition = () => {
    const rect = triggerRef.current?.getBoundingClientRect();
    if (!rect) {
      return;
    }

    setPosition({
      top: rect.bottom + 8,
      left: align === 'left' ? rect.left : null,
      right: align === 'right' ? window.innerWidth - rect.right : null,
    });
  };

  const close = ({ restoreFocus = false } = {}) => {
    setOpen(false);
    if (restoreFocus) {
      requestAnimationFrame(() => {
        triggerRef.current?.focus();
      });
    }
  };

  const openMenu = (focus: 'first' | 'last') => {
    updatePosition();
    setOpen(true);

    requestAnimationFrame(() => {
      const elements = enabledItems();
      if (!elements.length) {
        return;
      }

      (focus === 'last' ? elements[elements.length - 1] : elements[0]).focus();
    });
  };

  useEffect(() => {
    if (!open) {
      return undefined;
    }

    const onPointerDown = (event: PointerEvent) => {
      const target = event.target as Node;
      const insideTrigger = rootRef.current?.contains(target);
      const insideMenu = menuRef.current?.contains(target);

      if (!insideTrigger && !insideMenu) {
        close();
      }
    };

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        close({ restoreFocus: true });

        return;
      }

      const elements = enabledItems();
      if (!elements.length) {
        return;
      }

      const activeIndex = elements.indexOf(document.activeElement as HTMLElement);
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        elements[(activeIndex + 1 + elements.length) % elements.length].focus();
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        elements[(activeIndex - 1 + elements.length) % elements.length].focus();
      } else if (event.key === 'Home') {
        event.preventDefault();
        elements[0].focus();
      } else if (event.key === 'End') {
        event.preventDefault();
        elements[elements.length - 1].focus();
      } else if (/^[a-z0-9]$/i.test(event.key)) {
        const start = Math.max(activeIndex, 0);
        const ordered = [...elements.slice(start + 1), ...elements.slice(0, start + 1)];
        const match = ordered.find((element) =>
          element.textContent?.trim().toLowerCase().startsWith(event.key.toLowerCase()),
        );

        if (match) {
          event.preventDefault();
          match.focus();
        }
      } else if (event.key === 'Tab') {
        close();
      }
    };

    const onViewportChange = () => updatePosition();

    document.addEventListener('pointerdown', onPointerDown);
    document.addEventListener('keydown', onKeyDown);
    window.addEventListener('resize', onViewportChange);
    window.addEventListener('scroll', onViewportChange, true);

    return () => {
      document.removeEventListener('pointerdown', onPointerDown);
      document.removeEventListener('keydown', onKeyDown);
      window.removeEventListener('resize', onViewportChange);
      window.removeEventListener('scroll', onViewportChange, true);
    };
  }, [open, align]);

  const handleRootClick = (event: React.MouseEvent) => {
    if (stopPropagation) {
      event.stopPropagation();
    }
  };

  const menu = open ? (
    <div
      ref={menuRef}
      id={menuId}
      role="menu"
      style={{
        top: position.top,
        left: position.left ?? undefined,
        right: position.right ?? undefined,
        zIndex: 120,
      }}
      className="fixed min-w-48 rounded-sm border border-ink bg-paper py-2 shadow"
    >
      {items.map((item, index) => (
        <React.Fragment key={`${item.label}-${index}`}>
          {item.separatorBefore && <div role="separator" className="my-2 border-t border-line" />}
          <button
            type="button"
            role="menuitem"
            disabled={item.disabled}
            title={item.disabled ? item.disabledReason : undefined}
            onClick={() => {
              if (item.disabled) {
                return;
              }
              close();
              item.onSelect?.();
            }}
            className={cx(
              'flex min-h-10 w-full items-center px-4 py-2 text-left text-sm font-normal transition-colors duration-150 ease-out hover:bg-surface hover:text-accent motion-reduce:transition-none',
              item.tone === 'danger' ? 'text-error' : 'text-ink',
              item.disabled &&
                'cursor-not-allowed text-ink-muted opacity-50 hover:bg-transparent hover:text-ink-muted',
            )}
          >
            {item.label}
          </button>
        </React.Fragment>
      ))}
    </div>
  ) : null;

  return (
    <div
      ref={rootRef}
      className={cx('relative inline-block text-left', className)}
      onClick={handleRootClick}
    >
      <button
        ref={triggerRef}
        type="button"
        aria-label={ariaLabel}
        aria-haspopup="menu"
        aria-expanded={open}
        aria-controls={open ? menuId : undefined}
        onClick={() => {
          if (open) {
            close();
          } else {
            openMenu('first');
          }
        }}
        onKeyDown={(event) => {
          if (event.key === 'ArrowDown') {
            event.preventDefault();
            openMenu('first');
          } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            openMenu('last');
          }
        }}
        className={cx(
          'inline-flex max-w-full items-center overflow-hidden whitespace-nowrap rounded-sm border border-transparent bg-transparent font-medium text-ink transition-colors duration-150 ease-out hover:border-line hover:bg-surface motion-reduce:transition-none',
          compact
            ? 'min-h-8 min-w-8 justify-center px-2 py-2 text-md'
            : 'min-h-10 justify-start px-4 py-2 text-sm',
        )}
      >
        {label ?? <OverflowIcon />}
      </button>
      {menu && createPortal(menu, document.body)}
    </div>
  );
}
