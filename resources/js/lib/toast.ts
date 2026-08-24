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

export const OKATANA_TOAST_EVENT = 'okatana:toast';

function dispatch(tone: 'success' | 'error', message: string, options: ToastOptions = {}): void {
  if (!message || typeof window === 'undefined') {
    return;
  }

  window.dispatchEvent(
    new CustomEvent(OKATANA_TOAST_EVENT, {
      detail: {
        tone,
        message: String(message),
        duration: options.duration ?? (tone === 'error' ? 6000 : 4000),
      },
    }),
  );
}

export const toast = {
  success(message: string, options?: ToastOptions): void {
    dispatch('success', message, options || {});
  },
  error(message: string, options?: ToastOptions): void {
    dispatch('error', message, options || {});
  },
};
