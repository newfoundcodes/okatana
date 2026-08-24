# Okatana UI design system

Okatana uses the Swiss International Style as a functional interface system. The implementation is intentionally information-dense, grid-led, typographic, and neutral. The React application does not use generic SaaS card styling, gradients, soft shadows, pastel status chips, or decorative illustration.

## Stack

- React 19 client rendered by Laravel/Vite
- Tailwind CSS 4.3.3 through `@tailwindcss/vite`
- `tailwind.config.js` as the explicit design-token source
- `resources/css/app.css` for global base behavior and rich-text rules
- `resources/js/components/Primitives.jsx` for reusable UI primitives

Tailwind v4 normally favors CSS-first configuration. This application explicitly loads `tailwind.config.js` with `@config` because the Okatana UI specification requires the JavaScript configuration file to be the central token declaration.

## Signature device

The visible grid is the single product-wide signature device.

- Mobile: 4 columns
- Medium screens: 8 columns
- Desktop: 12 columns
- Gutters: 24px
- Outer margin: 32px on small screens and 48px from the medium breakpoint upward
- The guide lines use 2% opacity and remain behind application content

The guides are implemented by `GridGuides` as actual grid columns, not as a CSS gradient.

## Design tokens

The canonical tokens are in `tailwind.config.js`:

- Ink: `#111111`
- Muted ink: `#595959`
- Paper: `#FFFFFF`
- Surface: `#F4F4F4`
- Line: `#DEDEDE`
- Strong line: `#B0B0B0`
- Accent: `#D7263D`
- Success: `#2E7D32`
- Warning: `#B26A00`
- Error: `#C62828`

Typography uses the stack `Suisse Intl`, `Inter`, system sans-serif fallbacks. No font files are bundled. The application uses weights 400, 500, and 600 only.

## Primitives

`resources/js/components/Primitives.jsx` defines:

- `Button`
- `Panel`
- `GridLayout`
- `Page`
- `PageHeader`
- `Tabs`
- `Alert`
- `Field`
- `TextInput`
- `Select`
- `TextArea`
- `Check`
- `SectionHeading`
- `EmptyState`
- `Stat`
- `Table`, `Th`, and `Td`
- `GridGuides`
- `LoadingState`

These primitives own the visual rules so screens do not invent separate radii, shadows, spacing, typography, or button behavior.

## Navigation

Navigation is text-first. Active tabs use a 2px accent rule and a weight change. Okatana does not use filled navigation pills or icon-only primary navigation. Compact contextual controls such as overflow, copy, editor-toolbar, and notification-status icons remain permitted when they have an accessible name and do not replace primary navigation labels.

## Tables

Tables use horizontal rules only. Header rows use a stronger bottom rule and font weight. They do not use zebra striping or colored header bands. Numeric cells are right-aligned and use tabular numerals.

## Forms

Labels remain visible above every input. Inputs use a 1px border, 2px radius, no inset shadow, and a visible accent focus outline. Placeholder text never replaces the label.

## Authentication and account security

Signup, email confirmation, authenticator enrollment, invitation security, and login challenges use the same grid, field, button, focus, spacing, and typography primitives as the workspace. Security setup is progressive: only the current required action is shown. Okatana does not render a decorative numbered stepper or status strip such as `01 / Confirm email / Complete` and `02 / Authenticator app / Required`. After email confirmation, the form is replaced by authenticator enrollment only when the deployment policy requires it.

Security copy is configuration-aware. When `OKATANA_REQUIRE_TWO_FACTOR=false`, the authentication interface does not describe TOTP as mandatory. Form labels stay visible, format guidance remains adjacent to the relevant field, and changing stages does not introduce a second visual system.

## Kanban board

Kanban columns use structural rules rather than colored panels. Ticket rows use border separation and no elevation. Dragging a ticket over a phase changes only that phase's top rule to the accent color.

Board and label color values remain stored because they are part of Okatana's data model and transfer format. They are edited as text metadata. The UI does not render arbitrary stored colors as badges, board backgrounds, or chart series because that would violate the single-accent rule.

## Charts

Analytics use:

- Neutral bars for active phases
- Accent bars only for completed phases
- A two-series line chart where created tickets use ink and completed tickets use the accent
- Direct labels on the throughput chart
- Tabular values and minimal gridlines

The previous donut chart was removed. Completion is presented as a precise percentage instead.

## Motion and accessibility

- UI transitions are 150ms ease-out
- `prefers-reduced-motion` removes transitions and animations
- All interactive elements keep a visible 2px accent focus outline
- Modals move focus to the visible Close control, trap keyboard focus while open, close with Escape, and restore focus to the invoking control
- Primary navigation uses text labels; compact contextual and editor-toolbar icon controls retain explicit accessible names
- Ticket rows can be opened with Enter or Space as well as pointer input

## Pre-ship audit

The repository validation checks for the main prohibited patterns:

- no `rounded-xl`, `rounded-2xl`, or `rounded-3xl`
- no gradients
- no blurred shadow utility classes
- no centered or justified body/table text
- stored project/phase color metadata may use a compact functional swatch/color input, but arbitrary stored colors do not become product chrome, badges, or chart series
- no donut chart component
- no arrow-only move controls
- no odd Tailwind spacing steps that resolve to 4px/12px/20px/etc.

The only circular UI elements are assignee initials, which function as avatars.

## Workspace sidebar shell

Authenticated application routes use a shared sidebar shell. At medium and larger breakpoints the sidebar occupies two columns of the responsive application grid, uses a single hairline separator from the main workspace, and remains sticky for the viewport height. The sidebar does not use a floating card, shadow, filled navigation pills, or decorative icon rail.

Primary workspace navigation is text-first: **Organizations**, **Projects**, and **Documents**. The active destination uses the product accent only as a thin left rule plus a 600-weight label. Account actions are separated by a horizontal hairline and align to the bottom of the desktop sidebar: signed-in user identity, **Profile**, **Settings**, and **Log-out**. On smaller screens the same groups reflow above the content with the same typography and rules rather than switching to a visually unrelated mobile navigation pattern.

The workspace top bar is deliberately quiet and retains the Notifications control. Page titles remain inside the main content area so hierarchy comes from page typography rather than duplicating large labels in application chrome.

## WYSIWYG table editing

Ticket descriptions and ticket comments use the same table-capable WYSIWYG primitive. The primary formatting toolbar keeps one compact **Insert table** icon rather than exposing all table operations at all times. Activating it opens an inline structured form for the initial row and column counts. The current implementation accepts 1–20 rows and 1–20 columns per insertion to prevent accidental creation of an excessively large editable DOM.

When the caret is inside a table cell, a contextual **Table editing** toolbar appears. Its icon controls can insert a row above or below, remove the current row, insert a column to the left or right, remove the current column, merge with the adjacent cell to the right, merge with the adjacent cell below, and split the current merged cell. Horizontal and vertical merges can be repeated to create larger regions. The table mutation model accounts for existing `rowspan` and `colspan` values when rows or columns are inserted or removed.

The table tools follow the same Swiss UI rules as the rest of the editor: 2px corners, hairline borders, neutral surfaces, no decorative color, and 8px spacing. Icons are always paired with an accessible name and tooltip. Left/Right arrow keys move between enabled controls in either editor toolbar, with Home/End moving to the first/last control.

Rendered rich text uses semantic HTML table elements and the normal ink/line token system. Tables can overflow horizontally inside the rich-text region instead of forcing the ticket viewer or comment layout wider than its container. Cell content remains left-aligned, and no table color theme is introduced.

## Documents

The Documents workspace uses the same page shell, visible grid, tabs, search field, table, pagination, toast, modal, and button primitives as Organizations and Projects. It does not introduce a card-based knowledge-base theme.

Articles and Favorites are data tables with horizontal rules only. The Create document action is aligned with the page header. Draft/published/archive state is communicated through text instead of decorative multicolor badges. The document editor preserves the fixed field order: Title, Organization, Project, Caption, Contents, Editors. Editor search results and selected editors use table structure.

The article view treats typography and content as the primary visual hierarchy. Article HTML is constrained to a readable text width while metadata remains aligned to the application grid. Comments reuse the existing rich-text and overflow-action patterns. Integration tables and audit logs use the same search/pagination controls as other workspace tables.

### Document tag input and label directory

Document tags use the same tokenized input grammar as ticket tags: tokens sit inside the field, Enter/comma commits, and Backspace removes the nearest token when no text remains. Project labels are displayed in the same semantic table/search/pagination system as other directories. Stored label colors remain functional metadata: the table exposes a small native color square beside the hex value rather than using color as decorative UI chrome.

## WYSIWYG collaboration behavior

The shared WYSIWYG follows the product's compact toolbar pattern on tickets, ticket comments, article bodies, and article comments. Mention autocomplete is invoked by typing `@`; it does not add another permanent toolbar action. Link, image, and table insertion use contextual inline dialog regions with explicit non-submit actions, so the editor remains valid when composed inside a larger form. The contextual surfaces retain the existing hairline border, 2px radius, 8px spacing rhythm, left alignment, focus treatment, and single functional accent.

## Documentation surfaces

The MkDocs Material manual at `/docs/` and the Scalar API reference at `/docs/api` are part of the same product interface. They do not adopt the default visual personality of either documentation framework.

The MkDocs implementation is configured in `mkdocs.yml`; its Okatana layer lives in `docs/assets/stylesheets/okatana.css` and `docs/assets/javascripts/okatana.js`. The layer carries the canonical ink, muted, paper, surface, line, accent, and semantic tokens into Material. It also enforces the product type stack, tabular numerals, square controls, hairline tables, typographic navigation, visible focus, reduced motion, and the 4 / 8 / 12 responsive guide. Web fonts are disabled so the documentation does not introduce an external font request or a competing type system.

Documentation content prioritizes task-oriented language and progressive disclosure. Top-level navigation separates end-user, administrator, operator, integration, developer, and reference material. Tables are reserved for exact mappings and matrices; warnings identify destructive or security-sensitive operations; code examples are copyable and remain adjacent to the behavior they demonstrate.

The MkDocs output is generated into `public/docs`. That directory is a build artifact and must not be edited directly. No generated `public/docs/api` path is created, and `docs/openapi.yaml` is excluded from static output, so Laravel continues to own both the Scalar and protected specification routes. The source-level UI audit verifies the canonical documentation tokens and guards this route separation in addition to checking the React and Scalar implementations.
