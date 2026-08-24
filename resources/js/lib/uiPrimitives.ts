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

export const buttonVariants = {
  primary: 'border-accent bg-accent text-paper hover:bg-ink hover:border-ink',
  ink: 'border-ink bg-ink text-paper hover:bg-accent hover:border-accent',
  secondary: 'border-line bg-transparent text-ink hover:border-line-strong hover:bg-surface',
  danger: 'border-error bg-transparent text-error hover:bg-error hover:text-paper',
  text: 'border-transparent bg-transparent text-ink hover:border-line hover:bg-surface',
};

export function cx(...parts: any[]) {
  return parts.filter(Boolean).join(' ');
}
