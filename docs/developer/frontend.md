# React frontend

The browser client uses React 19, TypeScript/TSX, Vite 8, and Tailwind CSS 4. Laravel serves the single entry view and same-origin JSON. There is no runtime Node server, React Router dependency, global state library, or external chart package.

## Bootstrap and routing

`resources/js/app.tsx` mounts into `#app`, loads `/app-api/auth/me`, and tracks `location.pathname`. Navigation uses `history.pushState` plus a synthetic `popstate` event.

Route selection is explicit:

| Path                              | Component                                |
| --------------------------------- | ---------------------------------------- |
| `/` and unauthenticated app paths | `AuthPage`                               |
| `/join/{token}`                   | `InvitePage`                             |
| `/app`                            | `DashboardPage`                          |
| `/app/projects`                   | `ProjectsPage`                           |
| `/app/organizations/{id}`         | `OrganizationPage`                       |
| `/app/projects/{id}`              | `ProjectPage`                            |
| `/app/projects/{id}/{number}`     | `ProjectPage` + initial ticket deep link |
| `/app/documents`                  | `DocumentsPage`                          |
| `/app/documents/new`              | `DocumentEditorPage`                     |
| `/app/documents/{id}`             | `DocumentViewPage`                       |
| `/app/documents/{id}/edit`        | `DocumentEditorPage`                     |
| `/app/profile`                    | `UserProfilePage`                        |
| `/app/settings`                   | `UserSettingsPage`                       |

Invitation paths render outside the authenticated shell. Authenticated but incomplete accounts render `AccountSecuritySetup` before the workspace. Unknown authenticated paths fall back to the organization dashboard.

If route complexity grows, preserve existing deep links and browser back/forward behavior when evaluating a routing library.

## Workspace shell

`WorkspaceShell` owns the responsive 4/8/12-column layout:

- a two-column sticky desktop sidebar;
- text-first Organizations, Projects, Documents navigation;
- signed-in account, Profile, Settings, Log-out at the sidebar bottom;
- a quiet top bar with `NotificationsMenu`;
- page content below, with global `GridGuides` behind it;
- global portal-backed `ToastViewport`.

Pages use `Page`, `PageHeader`, `GridLayout`, `Tabs`, panels, tables, fields, buttons, breadcrumbs, loading/empty states, and copy actions from `Primitives.tsx`.

## API helper

`resources/js/lib/api.ts` centralizes fetch behavior:

- `Accept: application/json`;
- JSON serialization for plain bodies;
- leaves `FormData` untouched;
- adds the Blade meta `X-CSRF-TOKEN` on non-GET/HEAD;
- uses `credentials: same-origin`;
- maps `204` to null;
- parses JSON/text by content type;
- throws `ApiError(message, status, errors)`;
- provides get/post/patch/put/del helpers and JSON download.

Use this helper rather than hand-building browser fetches unless streaming/download behavior requires a focused alternative. Display failures through global toast or structured page alert, preserving validation field detail when the UI supports it.

## State and loading pattern

Pages generally own server state in `useState`, load with `useEffect`, and pass focused callbacks/values into components. The codebase uses explicit reload functions after mutation rather than a query-cache abstraction.

Represent at least three states:

- not yet loaded (`null` or `undefined`);
- loaded empty;
- load failed.

Avoid showing an empty state while a request is merely pending. Reset pagers when search/filter input changes.

## Organization and project pages

`OrganizationPage` persists its current tab in the query string and restricts available tabs by returned organization role. It coordinates project/invitation dialogs, imports/exports, and lazy section data.

`ProjectPage` owns:

- selected tab;
- shared ticket filter state;
- phase visibility;
- tickets/analytics/Gantt loading;
- ticket/phase creation modals;
- ticket drawer and number-based opening;
- admin-only project settings.

Native HTML drag-and-drop drives ticket/phase movement. Laravel remains authoritative for WIP, relationship, and permission validation; optimistic UI must reconcile server errors.

## Rich text editor

`RichTextEditor.tsx` and `lib/tableEditor.ts` implement formatting, upload/URL image insertion, mention autocomplete, and span-aware table editing without a third-party WYSIWYG package.

The editor is nested inside outer forms on document/ticket screens. Inline insertion surfaces are `role="dialog"` regions with `type="button"` actions—not nested forms—to prevent accidental outer submission.

Any editor change must preserve:

- selection/caret behavior;
- keyboard navigation and accessible labels;
- output compatible with `HtmlSanitizer`;
- mention token structure;
- table span invariants;
- horizontal overflow containment;
- ticket, comment, document, and document-comment use cases.

## Menus, modals, and toasts

`Modal` and `DropdownMenu` render through `createPortal(document.body)` to avoid clipping/stacking contexts. Modal behavior includes focus management, Escape, overlay semantics, and grid-snapped widths. Dropdown menus support Up/Down, Home/End, Escape, Tab close, and first-letter navigation, and update fixed positioning on viewport changes.

Destructive actions use `ConfirmDialog`. Browser `alert`, `confirm`, and `prompt` are prohibited. Mutation/copy feedback uses global toasts, limited to the most recent four and announced accessibly.

## Pagination

`useClientPagination` handles already-loaded collections, normalizes search text, resets to page 1 on query/page-size changes, clamps after result shrink, and slices results. `PaginationBar` presents 10/20/50/100 options.

Server-paginated views (workspace projects, notifications, audit, documents) must keep query/page/per-page synchronized with requests and reset page when search changes.

## Charts

Analytics bars/throughput and Gantt are React/SVG implementations supported by helpers in `resources/js/lib/charts.ts` and date utilities. Keep textual values accessible alongside visual encoding. Do not introduce arbitrary phase colors as application chrome or a donut chart that conflicts with the design rules.

## TypeScript boundary

The repository is migrating from loosely typed component props/data to declarations under `resources/js/types`. Current files still use `any` in several integration points. When editing:

- add named response/domain types rather than expanding `any`;
- type refs and DOM events accurately;
- preserve null/loading distinctions;
- keep API response types aligned with Laravel and OpenAPI;
- run `npx tsc --noEmit` if introducing broader typing changes, even though no dedicated npm script exists.

## Add a frontend feature

1. Reuse primitives and existing page patterns.
2. Add server authorization/validation first; UI capability checks are convenience.
3. Define loading, empty, success, validation, and permission-error states.
4. Preserve direct URL/back/forward behavior.
5. Implement keyboard/focus semantics with pointer behavior.
6. Use design tokens and 8 px spacing.
7. Add feature tests for backend contract and focused frontend helpers where present.
8. Run build, Prettier, UI audit, and relevant PHP tests.
9. Update user/reference documentation.

See [UI design system](../UI-DESIGN.md) for normative visual rules.
