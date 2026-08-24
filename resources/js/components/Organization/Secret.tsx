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

import { CopyValueButton } from '../Primitives';

export default function Secret({ title, value }: any) {
  return (
    <div className="mt-8 border-l-2 border-warning bg-surface p-4">
      <div className="text-sm font-semibold text-ink">{title} — store this value now</div>
      <code className="mt-2 block overflow-x-auto whitespace-nowrap text-xs text-ink">{value}</code>
      <div className="mt-4">
        <CopyValueButton value={value} label={`Copy ${title}`} />
      </div>
    </div>
  );
}
