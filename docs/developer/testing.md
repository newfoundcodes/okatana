# Testing and quality

The repository combines PHPUnit feature/unit tests, production builds, Prettier, Laravel Pint, a custom UI-theme audit, and a strict MkDocs build. Run checks proportionate to the change and the full suite before release.

## Standard commands

```bash
composer run test
npm run build
npm run lint
npm run ui:audit
vendor/bin/pint --test
mkdocs build --strict
```

`composer run test` clears Laravel configuration and invokes Artisan tests. Use `vendor/bin/pint` without `--test` to apply PHP formatting; use `npm run format` to apply Prettier formatting.

## PHPUnit environment

`phpunit.xml` uses:

- in-memory SQLite;
- array session/cache;
- synchronous queue;
- array mailer;
- low bcrypt rounds;
- known test `APP_KEY`;
- TOTP enforcement disabled by default.

Tests that exercise TOTP override configuration explicitly. Synchronous queues make mail/webhook assertions deterministic, but production queue behavior still needs deployment/integration testing.

## Coverage map

Feature tests cover:

- role authorization and explicit project access;
- workspace project visibility/search/pagination;
- account onboarding with TOTP enabled/disabled and policy changes;
- invitation password strength and non-demotion;
- avatar and user data export/preferences;
- ticket archive, tags, deep links, movement history, revisions;
- attachment authorization and editor-image streaming;
- collaboration notifications, self-mention exclusion, notification pagination;
- analytics member and multi-dimension filters;
- documents visibility, CRUD, comments, tags, mentions/notification privacy;
- document external API;
- external ticket API credential scope/revocation;
- external notifications;
- webhook signing;
- audit presentation/pagination and model/database immutability;
- Scalar/OpenAPI access policy.

Unit tests target the HTML sanitizer including dangerous wrappers, links/images, mention tokens, and table spans.

## Run focused tests

```bash
php artisan test tests/Feature/DocumentsTest.php
php artisan test --filter=project_analytics
php artisan test tests/Unit/HtmlSanitizerTest.php
```

Use a focused run while iterating, then the full suite. A passing browser-path test does not cover the external API path if validation/actor behavior is duplicated; update both relevant tests.

## Write a feature test

Use `Tests\TestCase` and factories/manual model creation patterns already present. Tests should assert outcome and denial:

1. create users, organization roles, and explicit memberships;
2. make the request as the relevant actor/credential;
3. assert HTTP status/response shape;
4. assert database/domain relations and side effects;
5. assert audit actor/event/metadata;
6. assert notifications/jobs/files where applicable;
7. add cross-organization or unauthorized case;
8. cover soft-delete/archived state when relevant.

For immutable rows, do not clean up by deleting them inside the test; the test database is disposed after execution.

## Cross-database testing

The default suite exercises SQLite, including SQLite audit triggers. Changes to migrations, JSON queries, case-insensitive search, locks, date queries, or trigger SQL should also be exercised against MySQL and PostgreSQL using isolated CI databases/Compose overlays.

Key checks:

- all migrations up/down in intended release procedure;
- audit triggers exist and reject update/delete;
- ticket number concurrency;
- import transactions;
- `LOWER(... ) LIKE` behavior;
- JSON casts and datetime precision;
- foreign-key/soft-delete behavior.

## Frontend build and format

`npm run build` compiles the actual React/Tailwind entrypoints and catches TS/Vite/module errors. Prettier uses PHP and curly plugins with 100-column width, two spaces, and single quotes where supported.

For type-focused work also run:

```bash
npx tsc --noEmit
```

No browser E2E runner is currently configured. For interaction changes to drag/drop, focus trapping, dropdown keyboard navigation, WYSIWYG/table selection, and responsive layouts, perform manual browser/assistive checks or add a test harness as part of the feature.

## UI audit

`npm run ui:audit` statically scans React, CSS, and Blade for system violations and verifies canonical tokens. Among other rules, it rejects:

- radius greater than 2 px (except approved avatars);
- blurred/elevated shadows and gradients;
- centered/justified body/table text;
- weights outside 400/500/600;
- arbitrary/off-baseline spacing;
- non-token decorative colors;
- native browser alert/confirm/prompt;
- missing grid, toast, navigation, WYSIWYG table/mention behaviors;
- missing Scalar theme alignment.

It is intentionally source-pattern based. Passing it does not prove visual quality or accessibility; review the rendered interface at small/medium/large breakpoints.

## Documentation tests

Install `requirements-docs.txt`, then:

```bash
mkdocs build --strict
```

Strict mode fails on missing files, bad navigation, unresolved relative links, and warnings. Inspect the generated `public/docs` at `/docs/`, verify search and responsive navigation, and ensure `/docs/api` and `/docs/openapi.yaml` remain available.

When a feature changes behavior, update the relevant journey page plus reference/architecture/OpenAPI, not only a changelog paragraph.

## Pre-merge matrix

| Change                   | Minimum focused checks                                           |
| ------------------------ | ---------------------------------------------------------------- |
| Controller/service/model | relevant PHPUnit tests, Pint, full suite                         |
| Migration/query          | full suite + MySQL/PostgreSQL acceptance where affected          |
| React component/page     | Vite build, Prettier, UI audit, manual keyboard/responsive check |
| Rich text/table          | sanitizer unit tests + all four editor contexts                  |
| API                      | feature tests, OpenAPI/endpoint docs, Scalar smoke test          |
| Webhook                  | signing/retry/SSRF tests and receiver contract docs              |
| Documentation            | strict MkDocs build and link/navigation review                   |
| Docker/config            | image build, health, migrations, web/queue/docs smoke tests      |

## Release gate

A release is not ready merely because the application compiles. Require passing tests, formatting, UI audit, strict docs build, migrations on supported engines, built container stages, backup/rollback plan, and a smoke test of queued work and encrypted secrets.
