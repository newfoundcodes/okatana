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

export default function Bars({ rows = [] }: BarsProps) {
  const max = Math.max(1, ...rows.map((row) => Number(row.count) || 0));
  return (
    <div className="grid gap-4">
      {rows.map((row) => {
        const width = `${((Number(row.count) || 0) / max) * 100}%`;
        return (
          <div
            key={row.id || row.name}
            className="grid grid-cols-4 items-center gap-6 md:grid-cols-8"
          >
            <span className="col-span-2 truncate text-sm text-ink md:col-span-3">{row.name}</span>
            <div className="col-span-1 h-2 bg-surface md:col-span-4" aria-hidden="true">
              <div
                className={row.is_done ? 'h-full bg-accent' : 'h-full bg-ink'}
                style={{ width }}
              />
            </div>
            <strong className="col-span-1 text-right text-sm font-medium tabular-nums">
              {row.count}
            </strong>
          </div>
        );
      })}
    </div>
  );
}
