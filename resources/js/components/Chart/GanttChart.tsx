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

import React, { useEffect, useMemo, useState } from 'react';
import { formatShortDate, validDate } from '../../lib/charts';

export default function GanttChart({
  tickets = [],
  projectKey = 'TKT',
  emptyMessage = 'No active tickets yet — create tickets to build the project timeline.',
}: GanttChartProps) {
  const [now, setNow] = useState(() => new Date());

  useEffect(() => {
    const timer = setInterval(() => setNow(new Date()), 60000);
    return () => clearInterval(timer);
  }, []);

  const model = useMemo(() => {
    const rows = tickets.map((ticket) => {
      const start = validDate(ticket.started_at) || validDate(ticket.created_at) || now;
      const preferredEnd =
        validDate(ticket.completed_at) ||
        validDate(ticket.due_at) ||
        validDate(ticket.updated_at) ||
        start;
      const end = preferredEnd.getTime() < start.getTime() ? start : preferredEnd;

      return { ticket, start, end };
    });

    if (!rows.length) {
      return {
        rows: [],
        min: now,
        max: new Date(now.getTime() + 86400000),
        ticks: [],
        projectStart: now,
        projectEnd: now,
      };
    }

    const projectStart = new Date(Math.min(...rows.map((row) => row.start.getTime())));
    const projectEnd = new Date(Math.max(...rows.map((row) => row.end.getTime())));
    const times = rows.flatMap((row) => [row.start.getTime(), row.end.getTime()]);

    times.push(now.getTime());

    let min = new Date(Math.min(...times));
    let max = new Date(Math.max(...times));
    const oneDay = 86400000;

    min = new Date(min.getTime() - oneDay);
    max = new Date(max.getTime() + oneDay);

    if (max.getTime() - min.getTime() < oneDay * 2) {
      max = new Date(min.getTime() + oneDay * 2);
    }

    const ticks = Array.from(
      { length: 5 },
      (_, index) => new Date(min.getTime() + ((max.getTime() - min.getTime()) * index) / 4),
    );

    return { rows, min, max, ticks, projectStart, projectEnd };
  }, [tickets, now]);

  if (!model.rows.length) {
    return <div className="border-y border-line py-12 text-sm text-ink-muted">{emptyMessage}</div>;
  }

  const span = Math.max(1, model.max.getTime() - model.min.getTime());
  const percent = (date: Date) =>
    Math.max(0, Math.min(100, ((date.getTime() - model.min.getTime()) / span) * 100));
  const nowLeft = percent(now);

  return (
    <div className="overflow-x-auto border-t-2 border-ink">
      <div className="gantt-chart">
        <div className="border-b border-line bg-paper p-4 text-sm font-semibold text-ink">
          Ticket
        </div>
        <div
          className="relative min-h-16 border-b border-line bg-paper"
          aria-label={`Project timeline from ${formatShortDate(model.min)} to ${formatShortDate(model.max)}`}
        >
          {model.ticks.map((tick, index) => (
            <span
              key={tick.toISOString()}
              className="absolute top-2 -translate-x-1/2 text-xs text-ink-muted tabular-nums"
              style={{ left: `${(index / 4) * 100}%` }}
            >
              {formatShortDate(tick)}
            </span>
          ))}
          <span
            className="absolute bottom-2 top-0 w-px bg-accent"
            style={{ left: `${nowLeft}%` }}
            aria-hidden="true"
          />
          <span
            className="absolute bottom-2 -translate-x-1/2 bg-paper px-2 text-xs font-medium text-accent tabular-nums"
            style={{ left: `${nowLeft}%` }}
          >
            Now · {now.toLocaleString()}
          </span>
        </div>

        <div className="border-b-2 border-line-strong p-4">
          <div className="text-sm font-semibold text-ink">{projectKey} project</div>
          <div className="mt-2 text-xs text-ink-muted">
            {formatShortDate(model.projectStart)} → {formatShortDate(model.projectEnd)}
          </div>
        </div>
        <div className="relative min-h-16 border-b-2 border-line-strong bg-paper">
          {model.ticks.map((tick, index) => (
            <span
              key={`project-${tick.toISOString()}`}
              className="absolute inset-y-0 w-px bg-line"
              style={{ left: `${(index / 4) * 100}%` }}
              aria-hidden="true"
            />
          ))}
          <span
            className="absolute inset-y-0 w-px bg-accent"
            style={{ left: `${nowLeft}%` }}
            aria-hidden="true"
          />
          <div
            className="absolute top-4 h-6 border-2 border-ink bg-surface"
            style={{
              left: `${percent(model.projectStart)}%`,
              width: `${Math.max(0.75, percent(model.projectEnd) - percent(model.projectStart))}%`,
            }}
            role="img"
            aria-label={`${projectKey} project timeline, ${model.projectStart.toLocaleString()} to ${model.projectEnd.toLocaleString()}`}
          />
        </div>

        {model.rows.map(({ ticket, start, end }) => {
          const left = percent(start);
          const right = percent(end);
          const width = Math.max(0.75, right - left);
          return (
            <React.Fragment key={ticket.id}>
              <div className="border-b border-line p-4">
                <div className="text-sm font-medium text-ink tabular-nums">
                  {projectKey}-{ticket.number}
                </div>
                <div className="mt-2 truncate text-sm text-ink">{ticket.title}</div>
                <div className="mt-2 text-xs text-ink-muted">
                  {ticket.board?.name || 'No phase'} · {formatShortDate(start)} →{' '}
                  {formatShortDate(end)}
                </div>
              </div>
              <div className="relative min-h-16 border-b border-line bg-paper">
                {model.ticks.map((tick, index) => (
                  <span
                    key={`${ticket.id}-${tick.toISOString()}`}
                    className="absolute inset-y-0 w-px bg-line"
                    style={{ left: `${(index / 4) * 100}%` }}
                    aria-hidden="true"
                  />
                ))}
                <span
                  className="absolute inset-y-0 w-px bg-accent"
                  style={{ left: `${nowLeft}%` }}
                  aria-hidden="true"
                />
                <div
                  className={
                    ticket.board?.is_done
                      ? 'absolute top-4 h-6 bg-accent'
                      : 'absolute top-4 h-6 bg-ink'
                  }
                  style={{ left: `${left}%`, width: `${width}%` }}
                  title={`${projectKey}-${ticket.number}: ${start.toLocaleString()} to ${end.toLocaleString()}`}
                  role="img"
                  aria-label={`${projectKey}-${ticket.number}, ${ticket.title}, ${start.toLocaleString()} to ${end.toLocaleString()}`}
                />
              </div>
            </React.Fragment>
          );
        })}
      </div>
    </div>
  );
}
