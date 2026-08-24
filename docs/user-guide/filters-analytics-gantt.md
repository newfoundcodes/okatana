# Filters, analytics, and Gantt

Okatana offers three complementary project views: Board for workflow, Analytics for aggregate measures, and Gantt for time. Filters are explicit so users can distinguish the entire project from a selected subset.

## Shared ticket filters

The **Filter tickets** action appears at the right side of the project tab bar on Board and Gantt. The panel is collapsed by default; hiding it does not reset active selections. The button reports the number of active filters.

Available dimensions include:

| Filter   | Matching behavior                                       |
| -------- | ------------------------------------------------------- |
| Search   | title contains text or number exactly matches the query |
| Phase    | ticket belongs to any selected board                    |
| Priority | ticket has any selected priority                        |
| Creator  | ticket was created by any selected user                 |
| Assignee | ticket has an assignee among the selected users         |
| Label    | ticket has a label among the selected labels            |
| Tags     | ticket has **every** selected tag                       |
| Overdue  | `due_at` is before now and current phase is not done    |
| Archived | switches from normal active tickets to archived tickets |

The tag selector lists only tags currently used by at least one project ticket. Multiple tags use match-all semantics, while the other multi-value dimensions use match-any within that dimension.

### Sorting and result bounds

The ticket endpoint accepts sort fields `created_at`, `updated_at`, `due_at`, `priority`, `number`, or `position`, with ascending default and optional descending direction. The browser normally requests a bounded working set. The server clamps `limit` between 1 and 1,000, with 500 as the default.

Each phase then paginates its own filtered rows in React. Changing one phase’s page does not move other phases.

## Analytics

The Analytics tab requests calculations from Laravel rather than deriving them from visible cards. It can be filtered by:

- one assigned member;
- one priority;
- one phase;
- one label;
- multiple tags with match-all semantics;
- overdue-only state;
- archived-only state.

All KPIs, distributions, and daily series in one response come from the same filtered query definition.

### Summary metrics

| Metric     | Definition                                                            |
| ---------- | --------------------------------------------------------------------- |
| Total      | count of tickets in the filtered set                                  |
| Done       | tickets whose current board is marked done                            |
| Open       | total minus done                                                      |
| Overdue    | due date in the past and current board is not done                    |
| Completion | `done / total × 100`, rounded to one decimal; zero when total is zero |

### Distributions and throughput

- **By phase** counts the selected tickets in each board.
- **By priority** counts each supported priority.
- **Daily created** counts matching tickets created during the last 30 calendar days.
- **Daily completed** counts matching tickets whose `completed_at` falls during the same period.

The React interface renders bar and throughput charts directly with SVG. No separate chart library or chart service is required. Stored phase colors remain metadata and do not become an uncontrolled product-wide chart palette.

### Interpret carefully

- Member filtering means **currently assigned**, not historical participation.
- Done is based on the current phase’s `is_done` marker.
- Completion history can change if a ticket returns from a done phase because `completed_at` is cleared.
- Archived-only analytics are a different population from the default active set.
- A low completion percentage may reflect newly created work, not slow throughput; compare the daily series and phase distribution.

## Gantt

The Gantt tab displays a project summary row and a row for each ticket in the current shared filter set.

Ticket date range fallbacks are:

```text
start = started_at ?? created_at
end   = completed_at ?? due_at ?? updated_at
```

A vertical **Now** line uses the browser’s current date/time and refreshes once per minute. The chart calculates one overall range that contains the project/ticket dates and the current position.

### What Gantt is—and is not

The view is a derived timeline, not a dependency planner:

- it does not store separate Gantt bars;
- it does not create predecessor/successor links;
- it does not change ticket dates when rendered;
- it reuses Board filters instead of defining another saved filter model;
- its end date is a fallback chain, so a ticket without due/completion data ends at its latest update.

For intentional planning, set due dates consistently and move tickets through phases so `started_at` and `completed_at` represent real execution.

## Example investigations

### Find overloaded work

1. Filter Board to a team member.
2. Inspect how their tickets distribute across phases.
3. Open Analytics with the same member selection.
4. Compare open/overdue counts and recent completion.
5. Use Gantt to locate overlapping date ranges.

### Review a release label

1. Select the release label.
2. Add relevant component tags; remember tags use match-all.
3. Check remaining work on Board.
4. Check completion and priority distribution in Analytics.
5. Export or deep-link specific tickets for follow-up.

### Audit overdue work

1. Enable Overdue.
2. Add priority or assignee filters.
3. Open affected tickets and verify due dates/current done marker.
4. Use the project audit log for recent moves or edits.

## Empty or surprising results

When no results appear, clear dimensions one at a time. Multiple tag filters are the most common cause because all selected tags are required. Also verify whether **Archived** is enabled and whether a phase was hidden. If analytics and Board disagree, compare filter dimensions: Analytics supports one value for several dimensions while Board can support multiple selections.
