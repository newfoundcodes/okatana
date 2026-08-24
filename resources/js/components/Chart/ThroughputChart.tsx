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

export default function ThroughputChart({ data = [] }: ThroughputChartProps) {
  const width = 600;
  const height = 192;
  const padX = 24;
  const padY = 24;
  const max = Math.max(
    1,
    ...data.flatMap((day) => [Number(day.created) || 0, Number(day.completed) || 0]),
  );

  const x = (index: number) =>
    data.length <= 1 ? padX : padX + (index / (data.length - 1)) * (width - padX * 2);
  const y = (value: number | string | undefined) =>
    height - padY - (Number(value || 0) / max) * (height - padY * 2);

  const points = (key: string) => data.map((day, index) => `${x(index)},${y(day[key])}`).join(' ');
  const last = data[data.length - 1];
  const lastIndex = Math.max(0, data.length - 1);

  return (
    <div>
      <svg
        className="h-48 w-full overflow-visible"
        viewBox={`0 0 ${width} ${height}`}
        role="img"
        aria-label="Tickets created and completed during the last 30 days"
      >
        {[0.25, 0.5, 0.75].map((position) => (
          <line
            key={position}
            x1={padX}
            x2={width - padX}
            y1={height * position}
            y2={height * position}
            className="stroke-line"
          />
        ))}
        <polyline points={points('created')} className="fill-none stroke-ink stroke-2" />
        <polyline points={points('completed')} className="fill-none stroke-accent stroke-2" />
        {last && (
          <>
            <text
              x={x(lastIndex) - 8}
              y={Math.max(12, y(last.created) - 8)}
              textAnchor="end"
              className="fill-ink text-xs"
            >
              Created {last.created}
            </text>
            <text
              x={x(lastIndex) - 8}
              y={Math.min(height - 4, y(last.completed) + 16)}
              textAnchor="end"
              className="fill-accent text-xs"
            >
              Completed {last.completed}
            </text>
          </>
        )}
      </svg>
      <div className="mt-2 flex justify-between text-xs text-ink-muted tabular-nums">
        <span>{data[0]?.date || ''}</span>
        <span>max {max}</span>
        <span>{last?.date || ''}</span>
      </div>
    </div>
  );
}
