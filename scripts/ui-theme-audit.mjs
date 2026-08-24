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

import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const sourceRoots = ['resources/js', 'resources/css', 'resources/views'];
const isFrontendSource = (file) =>
  ['.js', '.jsx', '.ts', '.tsx', '.css'].includes(path.extname(file)) ||
  file.endsWith('.blade.php');

function walk(directory) {
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const full = path.join(directory, entry.name);
    return entry.isDirectory() ? walk(full) : [full];
  });
}

const files = sourceRoots
  .flatMap((directory) => walk(path.join(root, directory)))
  .filter(isFrontendSource);
const relative = (file) => path.relative(root, file).replaceAll(path.sep, '/');
const errors = [];

function report(file, line, message) {
  errors.push(`${relative(file)}:${line}: ${message}`);
}

const prohibited = [
  [
    /(?:^|\s)rounded-(?:md|lg|xl|2xl|3xl)(?:\s|$)/g,
    'panel/control radius exceeds the 0–2px system',
  ],
  [
    /(?:^|\s)shadow-(?:sm|md|lg|xl|2xl|inner)(?:\s|$)/g,
    'blurred/elevated shadow utility is prohibited',
  ],
  [/(?:^|\s)(?:bg-gradient\S*|from-\S+|via-\S+|to-\S+)(?:\s|$)/g, 'gradient utility is prohibited'],
  [/(?:^|\s)text-(?:center|justify)(?:\s|$)/g, 'body/table content must stay left-aligned'],
  [
    /(?:^|\s)font-(?:bold|extrabold|black|light|thin)(?:\s|$)/g,
    'font weight must remain 400, 500, or 600',
  ],
  [
    /(?:^|\s)(?:m[trblxy]?|p[trblxy]?|gap[xy]?)-\[[^\]]+\](?:\s|$)/g,
    'arbitrary spacing utility bypasses the 8px rhythm',
  ],
  [
    /(?:^|\s)(?:bg|text|border)-(?:blue|indigo|purple|pink|cyan|teal|lime|emerald|orange|amber|rose)-\d+(?:\s|$)/g,
    'non-token product color utility is prohibited',
  ],
  [/\b(?:alert|confirm|prompt)\s*\(/g, 'browser alert/confirm/prompt is prohibited'],
];

const spacingPattern =
  /(?<![\w-])(?:-?)(?:m[trblxy]?|p[trblxy]?|gap[xy]?|space-[xy])-([0-9]+(?:\.5)?)(?![\w-])/g;

for (const file of files) {
  const lines = fs.readFileSync(file, 'utf8').split(/\r?\n/);
  lines.forEach((line, index) => {
    for (const [pattern, message] of prohibited) {
      pattern.lastIndex = 0;
      if (pattern.test(line)) {
        report(file, index + 1, message);
      }
    }

    spacingPattern.lastIndex = 0;
    for (const match of line.matchAll(spacingPattern)) {
      const step = Number(match[1]);
      if ((step * 4) % 8 !== 0) {
        report(
          file,
          index + 1,
          `spacing step ${match[0].trim()} resolves outside the 8px baseline`,
        );
      }
    }

    if (/rounded-full/.test(line)) {
      const allowed =
        relative(file) === 'resources/js/components/Primitives.tsx' ||
        relative(file) === 'resources/js/components/NotificationsMenu.tsx';
      if (!allowed) {
        report(
          file,
          index + 1,
          'rounded-full is reserved for genuinely circular avatar/status elements',
        );
      }
    }
  });
}

const appCss = fs.readFileSync(path.join(root, 'resources/css/app.css'), 'utf8');
const tailwind = fs.readFileSync(path.join(root, 'tailwind.config.js'), 'utf8');
const app = fs.readFileSync(path.join(root, 'resources/js/app.tsx'), 'utf8');
const scalar = fs.readFileSync(path.join(root, 'config/scalar.php'), 'utf8');
const docsCss = fs.readFileSync(path.join(root, 'docs/assets/stylesheets/okatana.css'), 'utf8');
const mkdocs = fs.readFileSync(path.join(root, 'mkdocs.yml'), 'utf8');

const requiredTokens = [
  "ink: '#111111'",
  "'ink-muted': '#595959'",
  "paper: '#FFFFFF'",
  "surface: '#F4F4F4'",
  "line: '#DEDEDE'",
  "'line-strong': '#B0B0B0'",
  "accent: '#D7263D'",
  "success: '#2E7D32'",
  "warning: '#B26A00'",
  "error: '#C62828'",
];

for (const token of requiredTokens) {
  if (!tailwind.includes(token)) {
    errors.push(`tailwind.config.js: missing canonical token ${token}`);
  }
}

const requiredThemeFragments = [
  "normal: '400'",
  "medium: '500'",
  "semibold: '600'",
  `sans: ['"Suisse Intl"', 'Inter', '-apple-system', '"Helvetica Neue"', 'Arial', 'sans-serif']`,
  "xs: ['0.6875rem'",
  "sm: ['0.75rem'",
  "base: ['0.875rem'",
  "md: ['1rem'",
  "lg: ['1.25rem'",
  "xl: ['1.5rem'",
  "'2xl': ['2rem'",
  "'3xl': ['3rem'",
  "'4xl': ['4rem'",
  "DEFAULT: '2px'",
  "sm: '2px'",
  "DEFAULT: '1px 1px 0 rgba(17,17,17,0.22)'",
  "display: '-0.02em'",
];

for (const fragment of requiredThemeFragments) {
  if (!tailwind.includes(fragment)) {
    errors.push(`tailwind.config.js: missing required theme fragment ${fragment}`);
  }
}

if (!appCss.includes('@media (prefers-reduced-motion: reduce)')) {
  errors.push('resources/css/app.css: missing prefers-reduced-motion handling');
}

if (!appCss.includes('font-variant-numeric: tabular-nums')) {
  errors.push('resources/css/app.css: missing global tabular numeral treatment');
}

if (!appCss.includes('.okatana-grid-guides {\n  opacity: 0.02;')) {
  errors.push('resources/css/app.css: visible grid guide opacity must remain 2%');
}

if (!app.includes('<GridGuides />')) {
  errors.push('resources/js/app.tsx: visible grid signature device is missing');
}

if (!app.includes('<ToastViewport />')) {
  errors.push('resources/js/app.tsx: global toast viewport is missing');
}

if (!app.includes('aria-label="Workspace navigation"')) {
  errors.push('resources/js/app.tsx: workspace sidebar navigation landmark is missing');
}

if (!app.includes('aria-label="Account navigation"')) {
  errors.push('resources/js/app.tsx: account sidebar navigation landmark is missing');
}

if (!app.includes('md:mt-auto')) {
  errors.push(
    'resources/js/app.tsx: desktop account navigation must remain aligned to the bottom of the sidebar',
  );
}

if (!app.includes('href="/app/projects"')) {
  errors.push('resources/js/app.tsx: Projects sidebar destination is missing');
}

if (!app.includes('href="/app/documents"')) {
  errors.push('resources/js/app.tsx: Documents sidebar destination is missing');
}

const dropdown = fs.readFileSync(
  path.join(root, 'resources/js/components/DropdownMenu.tsx'),
  'utf8',
);

const primitives = fs.readFileSync(
  path.join(root, 'resources/js/components/Primitives.tsx'),
  'utf8',
);

const accountSecuritySetup = fs.readFileSync(
  path.join(root, 'resources/js/components/AccountSecuritySetup.tsx'),
  'utf8',
);

const richTextEditor = fs.readFileSync(
  path.join(root, 'resources/js/components/RichTextEditor.tsx'),
  'utf8',
);

const tableEditor = fs.readFileSync(path.join(root, 'resources/js/lib/tableEditor.ts'), 'utf8');
if (/\b01\b|\b02\b/.test(accountSecuritySetup)) {
  errors.push(
    'resources/js/components/AccountSecuritySetup.tsx: numbered signup security progress labels are prohibited',
  );
}

if (/>\s*(?:Complete|Required)\s*</.test(accountSecuritySetup)) {
  errors.push(
    'resources/js/components/AccountSecuritySetup.tsx: signup security progress status labels are prohibited',
  );
}

if (!dropdown.includes('label ?? <OverflowIcon />')) {
  errors.push(
    'resources/js/components/DropdownMenu.tsx: overflow menus must use the shared icon trigger',
  );
}

if (!dropdown.includes('zIndex: 120')) {
  errors.push(
    'resources/js/components/DropdownMenu.tsx: dropdown portals must render above ticket viewers and dialogs',
  );
}

if (
  /label=[\"']\.\.\.[\"']|>\.\.\.<\//.test(
    app + files.map((file) => fs.readFileSync(file, 'utf8')).join('\n'),
  )
) {
  errors.push('frontend: visible ASCII ellipsis action trigger remains');
}

if (!primitives.includes('toast.success') || !primitives.includes('toast.error')) {
  errors.push('resources/js/components/Primitives.tsx: copy feedback must use global toasts');
}

if (!richTextEditor.includes('label="Insert table"')) {
  errors.push(
    'resources/js/components/RichTextEditor.tsx: WYSIWYG table insertion control is missing',
  );
}

if (!richTextEditor.includes('aria-label="Table editing"')) {
  errors.push(
    'resources/js/components/RichTextEditor.tsx: contextual table editing toolbar is missing',
  );
}

if (
  !richTextEditor.includes('Merge with cell on the right') ||
  !richTextEditor.includes('Merge with cell below')
) {
  errors.push(
    'resources/js/components/RichTextEditor.tsx: horizontal/vertical table merge controls are missing',
  );
}

if (!richTextEditor.includes('Split merged cell')) {
  errors.push('resources/js/components/RichTextEditor.tsx: merged-cell split control is missing');
}

if (richTextEditor.includes('<form')) {
  errors.push(
    'resources/js/components/RichTextEditor.tsx: inline WYSIWYG tools must not create nested HTML forms',
  );
}

if (!richTextEditor.includes('aria-label="Mention a user"')) {
  errors.push('resources/js/components/RichTextEditor.tsx: shared mention autocomplete is missing');
}

if (
  !tableEditor.includes('insertTableRow') ||
  !tableEditor.includes('insertTableColumn') ||
  !tableEditor.includes('removeTableRow') ||
  !tableEditor.includes('removeTableColumn')
) {
  errors.push('resources/js/lib/tableEditor.ts: table row/column mutation helpers are incomplete');
}

if (
  !appCss.includes('.wysiwyg-content table') ||
  !appCss.includes('.wysiwyg-content th,') ||
  !appCss.includes('.wysiwyg-content td')
) {
  errors.push('resources/css/app.css: WYSIWYG table presentation rules are missing');
}

if (!scalar.includes("'theme' => 'none'")) {
  errors.push('config/scalar.php: Scalar must use the neutral base theme for Okatana overrides');
}

if (!scalar.includes("'forceDarkModeState' => 'light'")) {
  errors.push('config/scalar.php: Scalar reference must stay in the product light color system');
}

if (!scalar.includes("'hideDarkModeToggle' => true")) {
  errors.push('config/scalar.php: Scalar dark-mode toggle must remain hidden');
}

if (!scalar.includes("'withDefaultFonts' => false")) {
  errors.push('config/scalar.php: Scalar must not load a competing default font stack');
}

if (!scalar.includes('box-shadow: none !important')) {
  errors.push('config/scalar.php: Scalar component shadows must remain disabled');
}

const requiredDocsTokens = [
  '--okatana-ink: #111111',
  '--okatana-muted: #595959',
  '--okatana-paper: #ffffff',
  '--okatana-surface: #f4f4f4',
  '--okatana-line: #dedede',
  '--okatana-line-strong: #b0b0b0',
  '--okatana-accent: #d7263d',
  '--okatana-success: #2e7d32',
  '--okatana-warning: #b26a00',
  '--okatana-error: #c62828',
];

for (const token of requiredDocsTokens) {
  if (!docsCss.includes(token)) {
    errors.push(`docs/assets/stylesheets/okatana.css: missing canonical token ${token}`);
  }
}

const requiredDocsFragments = [
  "--md-text-font: 'Suisse Intl', Inter, -apple-system, 'Helvetica Neue', Arial, sans-serif",
  'font-variant-numeric: tabular-nums',
  'border-radius: 2px',
  'box-shadow: none',
  'grid-template-columns: repeat(12, minmax(0, 1fr))',
  'grid-template-columns: repeat(8, minmax(0, 1fr))',
  'grid-template-columns: repeat(4, minmax(0, 1fr))',
  'opacity: 0.02',
  '@media (prefers-reduced-motion: reduce)',
];

for (const fragment of requiredDocsFragments) {
  if (!docsCss.includes(fragment)) {
    errors.push(`docs/assets/stylesheets/okatana.css: missing required theme fragment ${fragment}`);
  }
}

if (!mkdocs.includes('site_dir: public/docs')) {
  errors.push('mkdocs.yml: generated manual must remain in public/docs');
}

if (/site_dir:\s*public\/docs\/api/.test(mkdocs)) {
  errors.push('mkdocs.yml: documentation build must not claim the Laravel /docs/api route');
}

if (!/exclude_docs:\s*\|\s*\n\s+openapi\.yaml/m.test(mkdocs)) {
  errors.push('mkdocs.yml: OpenAPI source must be excluded from public static output');
}

if (!mkdocs.includes('font: false')) {
  errors.push('mkdocs.yml: documentation must use the product font stack without remote fonts');
}

const docsScript = fs.readFileSync(path.join(root, 'docs/assets/javascripts/okatana.js'), 'utf8');

if (!docsScript.includes("guides.className = 'okatana-grid-guides'")) {
  errors.push('docs/assets/javascripts/okatana.js: visible grid signature device is missing');
}

const documentEditor = fs.readFileSync(
  path.join(root, 'resources/js/pages/DocumentEditorPage.tsx'),
  'utf8',
);

const documentView = fs.readFileSync(
  path.join(root, 'resources/js/pages/DocumentViewPage.tsx'),
  'utf8',
);

const documentsPage = fs.readFileSync(
  path.join(root, 'resources/js/pages/DocumentsPage.tsx'),
  'utf8',
);

if (!documentEditor.includes('mentionUsers={mentionUsers}')) {
  errors.push(
    'resources/js/pages/DocumentEditorPage.tsx: article editor must pass document-scope mention candidates to the WYSIWYG editor',
  );
}

if (!documentView.includes('mentionUsers={document.mention_users || []}')) {
  errors.push(
    'resources/js/pages/DocumentViewPage.tsx: article comment editors must receive document-scope mention candidates',
  );
}

if (!documentsPage.includes('Create an organization and project before creating an article.')) {
  errors.push(
    'resources/js/pages/DocumentsPage.tsx: Create article must guard empty workspaces with a toast',
  );
}

if (errors.length) {
  console.error(
    `Swiss UI audit failed with ${errors.length} issue${errors.length === 1 ? '' : 's'}:\n`,
  );
  errors.forEach((error) => console.error(`- ${error}`));
  process.exit(1);
}

console.log(`Swiss UI audit passed across ${files.length} frontend source files.`);
