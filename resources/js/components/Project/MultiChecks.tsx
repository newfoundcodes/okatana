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

import { Check } from '../Primitives';

export default function MultiChecks({ title, rows, selected, setSelected }: any) {
  const toggle = (id: string) =>
    setSelected(
      selected.includes(id) ? selected.filter((value: string) => value !== id) : [...selected, id],
    );

  return (
    <fieldset className="border-t border-line pt-4">
      <legend className="mb-2 text-sm text-ink-muted">{title}</legend>
      {!rows.length ? (
        <div className="text-sm text-ink-muted">No options available.</div>
      ) : (
        <div className="flex flex-wrap gap-x-6 gap-y-2">
          {rows.map((row: any) => (
            <Check key={row.id} checked={selected.includes(row.id)} onChange={() => toggle(row.id)}>
              {row.name}
            </Check>
          ))}
        </div>
      )}
    </fieldset>
  );
}
