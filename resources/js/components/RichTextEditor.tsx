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

import React, { useEffect, useMemo, useRef, useState } from 'react';
import { post } from '../lib/api';
import { Button, Field, TextInput } from './Primitives';
import {
  cellFromSelection,
  createEditorTable,
  insertTableColumn,
  insertTableRow,
  mergeTableCell,
  removeTableColumn,
  removeTableRow,
  splitTableCell,
  tableContextForCell,
} from '../lib/tableEditor';
import { cx } from '../lib/uiPrimitives';

const sizeClasses: Record<string, string> = {
  compact: 'min-h-24',
  default: 'min-h-36',
  large: 'min-h-48',
};

function normalizeLink(value: string) {
  const input = value.trim();
  if (!input) {
    return '';
  }
  if (/^(https?:\/\/|mailto:|\/|#)/i.test(input)) {
    return input;
  }
  return `https://${input}`;
}

function normalizeImageUrl(value: string) {
  const input = value.trim();
  if (!input) {
    return '';
  }
  if (/^(https?:\/\/|\/)/i.test(input)) {
    return input;
  }
  return `https://${input}`;
}

function ToolIcon({ name }: { name: string }) {
  const common = {
    width: 16,
    height: 16,
    viewBox: '0 0 24 24',
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 1.7,
    strokeLinecap: 'round' as const,
    strokeLinejoin: 'round' as const,
    'aria-hidden': true,
  };

  if (name === 'bold') {
    return (
      <svg {...common}>
        <path d="M7 4h6a4 4 0 0 1 0 8H7z" />
        <path d="M7 12h7a4 4 0 0 1 0 8H7z" />
      </svg>
    );
  }

  if (name === 'italic') {
    return (
      <svg {...common}>
        <path d="M10 5h7M7 19h7M14 5l-4 14" />
      </svg>
    );
  }

  if (name === 'underline') {
    return (
      <svg {...common}>
        <path d="M7 5v6a5 5 0 0 0 10 0V5M5 20h14" />
      </svg>
    );
  }

  if (name === 'heading') {
    return (
      <svg {...common}>
        <path d="M6 5v14M18 5v14M6 12h12" />
      </svg>
    );
  }

  if (name === 'quote') {
    return (
      <svg {...common}>
        <path d="M6 9h5v5H6zM13 9h5v5h-5z" />
        <path d="M9 14c0 3-1 4-3 5M16 14c0 3-1 4-3 5" />
      </svg>
    );
  }

  if (name === 'bullets') {
    return (
      <svg {...common}>
        <path d="M9 6h11M9 12h11M9 18h11" />
        <circle cx="4.5" cy="6" r="1" />
        <circle cx="4.5" cy="12" r="1" />
        <circle cx="4.5" cy="18" r="1" />
      </svg>
    );
  }

  if (name === 'numbers') {
    return (
      <svg {...common}>
        <path d="M10 6h10M10 12h10M10 18h10M4 5h2v3M4 12c0-1 2-2 2-3s-2-1-2 0M4 16h2l-2 3h2" />
      </svg>
    );
  }

  if (name === 'link') {
    return (
      <svg {...common}>
        <path d="M10 13a5 5 0 0 0 7.1.1l2-2a5 5 0 0 0-7.1-7.1l-1.1 1.1" />
        <path d="M14 11a5 5 0 0 0-7.1-.1l-2 2A5 5 0 0 0 12 20l1.1-1.1" />
      </svg>
    );
  }

  if (name === 'image') {
    return (
      <svg {...common}>
        <rect x="4" y="5" width="16" height="14" rx="1" />
        <circle cx="9" cy="10" r="1.5" />
        <path d="m6 17 4-4 3 3 2-2 3 3" />
      </svg>
    );
  }

  if (name === 'table') {
    return (
      <svg {...common}>
        <rect x="4" y="5" width="16" height="14" />
        <path d="M4 10h16M4 15h16M10 5v14M15 5v14" />
      </svg>
    );
  }

  if (name === 'row-above') {
    return (
      <svg {...common}>
        <rect x="4" y="10" width="16" height="8" />
        <path d="M4 14h16M12 8V3M9.5 5.5 12 3l2.5 2.5" />
      </svg>
    );
  }

  if (name === 'row-below') {
    return (
      <svg {...common}>
        <rect x="4" y="6" width="16" height="8" />
        <path d="M4 10h16M12 16v5M9.5 18.5 12 21l2.5-2.5" />
      </svg>
    );
  }

  if (name === 'row-remove') {
    return (
      <svg {...common}>
        <rect x="4" y="8" width="16" height="8" />
        <path d="M4 12h16M9 4h6M12 3v3" />
        <path d="M9 20h6" />
      </svg>
    );
  }

  if (name === 'column-left') {
    return (
      <svg {...common}>
        <rect x="10" y="4" width="8" height="16" />
        <path d="M14 4v16M8 12H3M5.5 9.5 3 12l2.5 2.5" />
      </svg>
    );
  }

  if (name === 'column-right') {
    return (
      <svg {...common}>
        <rect x="6" y="4" width="8" height="16" />
        <path d="M10 4v16M16 12h5M18.5 9.5 21 12l-2.5 2.5" />
      </svg>
    );
  }

  if (name === 'column-remove') {
    return (
      <svg {...common}>
        <rect x="8" y="4" width="8" height="16" />
        <path d="M12 4v16M4 9v6M3 12h3M20 9v6M18 12h3" />
      </svg>
    );
  }

  if (name === 'merge-right') {
    return (
      <svg {...common}>
        <rect x="4" y="6" width="7" height="12" />
        <rect x="13" y="6" width="7" height="12" />
        <path d="M8 12h8M13.5 9.5 16 12l-2.5 2.5" />
      </svg>
    );
  }

  if (name === 'merge-down') {
    return (
      <svg {...common}>
        <rect x="6" y="4" width="12" height="7" />
        <rect x="6" y="13" width="12" height="7" />
        <path d="M12 8v8M9.5 13.5 12 16l2.5-2.5" />
      </svg>
    );
  }

  if (name === 'split-cell') {
    return (
      <svg {...common}>
        <rect x="4" y="5" width="16" height="14" />
        <path d="M12 5v14M8 12h8M7 9l-3 3 3 3M17 9l3 3-3 3" />
      </svg>
    );
  }

  return (
    <svg {...common}>
      <path d="m5 6 13 13M14 5h5v5M5 19l5-5" />
    </svg>
  );
}

function handleToolbarNavigation(event: React.KeyboardEvent<HTMLElement>) {
  if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
    return;
  }

  const buttons = Array.from(
    event.currentTarget.querySelectorAll<HTMLButtonElement>('button:not([disabled])'),
  );
  const currentIndex = buttons.indexOf(document.activeElement as HTMLButtonElement);

  if (currentIndex < 0 || !buttons.length) {
    return;
  }

  event.preventDefault();
  if (event.key === 'Home') {
    buttons[0].focus();
  } else if (event.key === 'End') {
    buttons[buttons.length - 1].focus();
  } else {
    const offset = event.key === 'ArrowRight' ? 1 : -1;
    buttons[(currentIndex + offset + buttons.length) % buttons.length].focus();
  }
}

function ToolbarButton({ label, icon, onMouseDown, ...props }: any) {
  return (
    <button
      type="button"
      className="inline-grid h-8 w-8 place-items-center rounded-sm border border-line bg-paper text-ink transition-colors duration-150 ease-out hover:border-line-strong hover:bg-surface motion-reduce:transition-none"
      aria-label={label}
      title={label}
      onMouseDown={(event) => {
        event.preventDefault();
        onMouseDown?.(event);
      }}
      {...props}
    >
      <ToolIcon name={icon} />
    </button>
  );
}

export default function RichTextEditor({
  value = '',
  onChange,
  placeholder = 'Write content…',
  size = 'default',
  mentionUsers = [],
}: RichTextEditorProps) {
  const ref = useRef<HTMLDivElement>(null);
  const selectionRef = useRef<Range | null>(null);
  const mentionRangeRef = useRef<Range | null>(null);
  const [insertTool, setInsertTool] = useState<string | null>(null);
  const [link, setLink] = useState({ url: '', text: '' });
  const [image, setImage] = useState<{ url: string; alt: string; file: File | null }>({
    url: '',
    alt: '',
    file: null,
  });
  const [tableSize, setTableSize] = useState({ rows: '3', columns: '3' });
  const [tableContext, setTableContext] = useState<any | null>(null);
  const [tableError, setTableError] = useState('');
  const [toolError, setToolError] = useState('');
  const [uploading, setUploading] = useState(false);
  const [mentionQuery, setMentionQuery] = useState<string | null>(null);
  const [mentionIndex, setMentionIndex] = useState(0);
  const tableCellRef = useRef<HTMLTableCellElement | null>(null);

  const mentionResults = useMemo(() => {
    if (mentionQuery === null) {
      return [];
    }

    const query = mentionQuery.trim().toLowerCase();
    return mentionUsers
      .filter(
        (user) => !query || `${user.name || ''} ${user.email || ''}`.toLowerCase().includes(query),
      )
      .slice(0, 8);
  }, [mentionQuery, mentionUsers]);

  const emitChange = () => onChange?.(ref.current?.innerHTML || '');
  const updateTableContext = (range) => {
    const cell = cellFromSelection(range, ref.current);
    tableCellRef.current = cell;

    setTableContext(cell ? tableContextForCell(cell) : null);
    if (!cell) {
      setTableError('');
    }
  };

  const captureSelection = () => {
    const selection = window.getSelection();
    if (!selection?.rangeCount || !ref.current) {
      return;
    }

    const range = selection.getRangeAt(0);
    if (ref.current.contains(range.commonAncestorContainer)) {
      selectionRef.current = range.cloneRange();
      updateTableContext(range);
    }
  };

  const restoreSelection = () => {
    const editor = ref.current;
    if (!editor) {
      return null;
    }

    editor.focus();

    let range = selectionRef.current?.cloneRange() || null;
    if (!range || !editor.contains(range.commonAncestorContainer)) {
      range = document.createRange();
      range.selectNodeContents(editor);
      range.collapse(false);
    }

    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);

    updateTableContext(range);
    return range;
  };

  const closeMention = () => {
    setMentionQuery(null);
    setMentionIndex(0);

    mentionRangeRef.current = null;
  };

  const detectMention = () => {
    if (!mentionUsers.length || !ref.current) {
      closeMention();
      return;
    }

    const selection = window.getSelection();
    if (!selection?.rangeCount) {
      closeMention();
      return;
    }

    const range = selection.getRangeAt(0);
    if (
      !range.collapsed ||
      !ref.current.contains(range.startContainer) ||
      range.startContainer.nodeType !== Node.TEXT_NODE
    ) {
      closeMention();
      return;
    }

    const node = range.startContainer;
    const offset = range.startOffset;
    const before = node.textContent.slice(0, offset);
    const at = before.lastIndexOf('@');

    if (at < 0 || (at > 0 && !/\s/.test(before[at - 1]))) {
      closeMention();
      return;
    }

    const query = before.slice(at + 1);
    if (query.length > 60 || /[\n\r.,!?;:()[\]{}<>]/.test(query)) {
      closeMention();
      return;
    }

    const mentionRange = document.createRange();
    mentionRange.setStart(node, at);
    mentionRange.setEnd(node, offset);
    mentionRangeRef.current = mentionRange.cloneRange();

    setMentionQuery(query);
  };

  const command = (name, argument = null) => {
    closeMention();
    restoreSelection();

    document.execCommand(name, false, argument);

    captureSelection();
    emitChange();
  };

  const insertNode = (node) => {
    const range = restoreSelection();
    if (!range) {
      return;
    }

    range.deleteContents();
    range.insertNode(node);
    range.setStartAfter(node);
    range.collapse(true);

    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
    selectionRef.current = range.cloneRange();

    emitChange();
  };

  const insertMentionUser = (user) => {
    const editor = ref.current;
    const range = mentionRangeRef.current?.cloneRange();

    if (!editor || !range || !editor.contains(range.commonAncestorContainer)) {
      return;
    }

    const element = document.createElement('span');
    element.setAttribute('data-mention-user-id', user.id);
    element.setAttribute('contenteditable', 'false');
    element.textContent = `@${user.name}`;

    const spacer = document.createTextNode('\u00a0');
    range.deleteContents();
    range.insertNode(element);
    element.after(spacer);

    const next = document.createRange();
    next.setStartAfter(spacer);
    next.collapse(true);

    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(next);
    selectionRef.current = next.cloneRange();

    closeMention();
    editor.focus();
    emitChange();
  };

  const openTool = (tool) => {
    closeMention();
    captureSelection();

    setToolError('');
    setInsertTool(tool);
  };

  const closeTool = () => {
    setInsertTool(null);
    setToolError('');

    requestAnimationFrame(() => restoreSelection());
  };

  const insertLink = (event) => {
    event.preventDefault();

    const href = normalizeLink(link.url);
    if (!href) {
      setToolError('Enter a link URL.');
      return;
    }

    const range = restoreSelection();
    if (!range) {
      return;
    }

    const anchor = document.createElement('a');
    anchor.setAttribute('href', href);
    anchor.setAttribute('rel', 'noopener noreferrer');

    if (!href.startsWith('#') && !href.startsWith('/')) {
      anchor.setAttribute('target', '_blank');
    }

    if (link.text.trim()) {
      anchor.textContent = link.text.trim();
      range.deleteContents();
    } else if (!range.collapsed) {
      anchor.appendChild(range.extractContents());
    } else {
      anchor.textContent = href;
    }

    range.insertNode(anchor);
    range.setStartAfter(anchor);
    range.collapse(true);

    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
    selectionRef.current = range.cloneRange();

    emitChange();
    setLink({ url: '', text: '' });
    setInsertTool(null);
  };

  const insertImage = async (event) => {
    event.preventDefault();
    setToolError('');

    let src = normalizeImageUrl(image.url);
    try {
      if (image.file) {
        setUploading(true);

        const formData = new FormData();
        formData.append('image', image.file);

        const result = await post('/app-api/editor/images', formData);
        src = result.url;
      }

      if (!src) {
        setToolError('Choose an image file or enter an image URL.');
        return;
      }

      const element = document.createElement('img');
      element.setAttribute('src', src);

      if (image.alt.trim()) {
        element.setAttribute('alt', image.alt.trim());
      }

      insertNode(element);
      setImage({ url: '', alt: '', file: null });
      setInsertTool(null);
    } catch (requestError) {
      setToolError(requestError.message);
    } finally {
      setUploading(false);
    }
  };

  const focusTableCell = (cell) => {
    const editor = ref.current;
    if (!cell || !editor?.contains(cell)) {
      tableCellRef.current = null;
      setTableContext(null);

      return;
    }

    editor.focus();

    const range = document.createRange();
    range.selectNodeContents(cell);
    range.collapse(false);

    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
    selectionRef.current = range.cloneRange();

    tableCellRef.current = cell;
    setTableContext(tableContextForCell(cell));
  };

  const insertTable = (event) => {
    event.preventDefault();
    setToolError('');

    const rows = Number.parseInt(tableSize.rows, 10);
    const columns = Number.parseInt(tableSize.columns, 10);
    if (
      !Number.isInteger(rows) ||
      rows < 1 ||
      rows > 20 ||
      !Number.isInteger(columns) ||
      columns < 1 ||
      columns > 20
    ) {
      setToolError('Choose between 1 and 20 rows and columns.');
      return;
    }

    const range = restoreSelection();
    if (!range) {
      return;
    }

    const table = createEditorTable(rows, columns);
    range.deleteContents();

    const startElement = (
      range.startContainer.nodeType === Node.ELEMENT_NODE
        ? range.startContainer
        : range.startContainer.parentElement
    ) as Element | null;

    const block = startElement?.closest?.('p,h1,h2,h3,h4,blockquote,pre') || null;
    if (block && ref.current?.contains(block)) {
      const trailingRange = document.createRange();
      trailingRange.setStart(range.startContainer, range.startOffset);
      trailingRange.setEnd(block, block.childNodes.length);

      const trailingContent = trailingRange.extractContents();
      const trailingBlock = block.cloneNode(false);

      trailingBlock.appendChild(trailingContent);
      if (!block.childNodes.length) {
        block.appendChild(document.createElement('br'));
      }

      if (!trailingBlock.childNodes.length) {
        trailingBlock.appendChild(document.createElement('br'));
      }

      block.after(table, trailingBlock);
    } else {
      const paragraph = document.createElement('p');
      paragraph.appendChild(document.createElement('br'));

      range.insertNode(table);
      table.after(paragraph);
    }

    setInsertTool(null);
    setTableError('');
    emitChange();

    requestAnimationFrame(() => focusTableCell(table.rows[0]?.cells[0] || null));
  };

  const runTableAction = (action) => {
    const cell = tableCellRef.current;
    const table = cell?.closest?.('table');

    if (!cell || !table || !ref.current?.contains(table)) {
      setTableError('Place the cursor inside a table cell first.');
      return;
    }

    setTableError('');

    const result = action(table, cell);
    if (result?.ok === false) {
      setTableError(result.message || 'That table action is not available for the selected cell.');
      return;
    }

    if (result?.removeTable) {
      const paragraph = document.createElement('p');
      paragraph.appendChild(document.createElement('br'));

      table.replaceWith(paragraph);
      tableCellRef.current = null;

      setTableContext(null);
      emitChange();

      requestAnimationFrame(() => {
        ref.current?.focus();

        const range = document.createRange();
        range.selectNodeContents(paragraph);
        range.collapse(true);

        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
        selectionRef.current = range.cloneRange();
      });

      return;
    }

    emitChange();
    requestAnimationFrame(() => focusTableCell(result?.focusCell || cell));
  };

  const handleEditorKeyDown = (event) => {
    if (mentionQuery === null) {
      return;
    }

    if (event.key === 'Escape') {
      event.preventDefault();
      closeMention();
      return;
    }

    if (!mentionResults.length) {
      return;
    }

    if (event.key === 'ArrowDown') {
      event.preventDefault();
      setMentionIndex((index) => (index + 1) % mentionResults.length);
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      setMentionIndex((index) => (index - 1 + mentionResults.length) % mentionResults.length);
    } else if (event.key === 'Enter') {
      event.preventDefault();
      insertMentionUser(mentionResults[mentionIndex] || mentionResults[0]);
    }
  };

  const handleInput = (event) => {
    onChange?.(event.currentTarget.innerHTML);

    captureSelection();
    detectMention();
  };

  useEffect(() => {
    if (ref.current && ref.current.innerHTML !== (value || '')) {
      ref.current.innerHTML = value || '';
      tableCellRef.current = null;

      setTableContext(null);
      setTableError('');
    }
  }, [value]);

  useEffect(() => setMentionIndex(0), [mentionQuery]);

  return (
    <div className="rounded-sm border border-line bg-paper focus-within:border-line-strong">
      <div
        className="flex flex-wrap gap-2 border-b border-line bg-surface p-2"
        role="toolbar"
        aria-label="Text formatting"
        onKeyDown={handleToolbarNavigation}
      >
        <ToolbarButton
          label="Bold"
          icon="bold"
          onMouseDown={captureSelection}
          onClick={() => command('bold')}
        />
        <ToolbarButton
          label="Italic"
          icon="italic"
          onMouseDown={captureSelection}
          onClick={() => command('italic')}
        />
        <ToolbarButton
          label="Underline"
          icon="underline"
          onMouseDown={captureSelection}
          onClick={() => command('underline')}
        />
        <ToolbarButton
          label="Heading"
          icon="heading"
          onMouseDown={captureSelection}
          onClick={() => command('formatBlock', 'h2')}
        />
        <ToolbarButton
          label="Quote"
          icon="quote"
          onMouseDown={captureSelection}
          onClick={() => command('formatBlock', 'blockquote')}
        />
        <ToolbarButton
          label="Bulleted list"
          icon="bullets"
          onMouseDown={captureSelection}
          onClick={() => command('insertUnorderedList')}
        />
        <ToolbarButton
          label="Numbered list"
          icon="numbers"
          onMouseDown={captureSelection}
          onClick={() => command('insertOrderedList')}
        />
        <ToolbarButton
          label="Insert link"
          icon="link"
          aria-expanded={insertTool === 'link'}
          onMouseDown={captureSelection}
          onClick={() => openTool('link')}
        />
        <ToolbarButton
          label="Insert image"
          icon="image"
          aria-expanded={insertTool === 'image'}
          onMouseDown={captureSelection}
          onClick={() => openTool('image')}
        />
        <ToolbarButton
          label="Insert table"
          icon="table"
          aria-expanded={insertTool === 'table'}
          onMouseDown={captureSelection}
          onClick={() => openTool('table')}
        />
        <ToolbarButton
          label="Clear formatting"
          icon="clear"
          onMouseDown={captureSelection}
          onClick={() => command('removeFormat')}
        />
      </div>

      {tableContext && (
        <div className="grid gap-2 border-b border-line bg-paper p-2">
          <div
            className="flex flex-wrap items-center gap-2"
            role="toolbar"
            aria-label="Table editing"
            onKeyDown={handleToolbarNavigation}
          >
            <span className="mr-2 text-xs text-ink-muted">
              Table {tableContext.rows} × {tableContext.columns} · Row {tableContext.row + 1},
              column {tableContext.column + 1}
            </span>
            <ToolbarButton
              label="Insert row above"
              icon="row-above"
              onClick={() => runTableAction((table, cell) => insertTableRow(table, cell, 'above'))}
            />
            <ToolbarButton
              label="Insert row below"
              icon="row-below"
              onClick={() => runTableAction((table, cell) => insertTableRow(table, cell, 'below'))}
            />
            <ToolbarButton
              label="Remove row"
              icon="row-remove"
              onClick={() => runTableAction(removeTableRow)}
            />
            <span className="h-6 border-l border-line" aria-hidden="true" />
            <ToolbarButton
              label="Insert column left"
              icon="column-left"
              onClick={() =>
                runTableAction((table, cell) => insertTableColumn(table, cell, 'left'))
              }
            />
            <ToolbarButton
              label="Insert column right"
              icon="column-right"
              onClick={() =>
                runTableAction((table, cell) => insertTableColumn(table, cell, 'right'))
              }
            />
            <ToolbarButton
              label="Remove column"
              icon="column-remove"
              onClick={() => runTableAction(removeTableColumn)}
            />
            <span className="h-6 border-l border-line" aria-hidden="true" />
            <ToolbarButton
              label="Merge with cell on the right"
              icon="merge-right"
              disabled={!tableContext.canMergeRight}
              onClick={() => runTableAction((table, cell) => mergeTableCell(table, cell, 'right'))}
            />
            <ToolbarButton
              label="Merge with cell below"
              icon="merge-down"
              disabled={!tableContext.canMergeDown}
              onClick={() => runTableAction((table, cell) => mergeTableCell(table, cell, 'down'))}
            />
            <ToolbarButton
              label="Split merged cell"
              icon="split-cell"
              disabled={!tableContext.canSplit}
              onClick={() => runTableAction(splitTableCell)}
            />
          </div>
          {tableError && (
            <div className="text-xs text-error" role="alert">
              {tableError}
            </div>
          )}
        </div>
      )}

      {insertTool === 'link' && (
        <div
          className="grid gap-4 border-b border-line bg-paper p-4"
          role="dialog"
          aria-label="Insert link"
          onKeyDown={(event) => event.key === 'Escape' && closeTool()}
        >
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <Field label="Link URL">
              <TextInput
                autoFocus
                type="text"
                inputMode="url"
                placeholder="https://example.com"
                value={link.url}
                onChange={(event) => setLink({ ...link, url: event.target.value })}
              />
            </Field>
            <Field label="Link text" hint="Optional. Selected text is used when this is empty.">
              <TextInput
                value={link.text}
                onChange={(event) => setLink({ ...link, text: event.target.value })}
              />
            </Field>
          </div>
          {toolError && (
            <div className="text-sm text-error" role="alert">
              {toolError}
            </div>
          )}
          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="primary" onClick={insertLink}>
              Insert link
            </Button>
            <Button type="button" onClick={closeTool}>
              Cancel
            </Button>
          </div>
        </div>
      )}

      {insertTool === 'image' && (
        <div
          className="grid gap-4 border-b border-line bg-paper p-4"
          role="dialog"
          aria-label="Insert image"
          onKeyDown={(event) => event.key === 'Escape' && closeTool()}
        >
          <Field label="Upload image" hint="JPEG, PNG, GIF, or WebP. Maximum 5 MB.">
            <TextInput
              autoFocus
              type="file"
              accept="image/jpeg,image/png,image/gif,image/webp"
              onChange={(event) => setImage({ ...image, file: event.target.files?.[0] || null })}
            />
          </Field>
          <div className="text-xs text-ink-muted">or</div>
          <Field label="Image URL">
            <TextInput
              type="text"
              inputMode="url"
              placeholder="https://example.com/image.png"
              value={image.url}
              onChange={(event) => setImage({ ...image, url: event.target.value })}
            />
          </Field>
          <Field label="Alternative text" hint="Describe the image for users who cannot see it.">
            <TextInput
              value={image.alt}
              onChange={(event) => setImage({ ...image, alt: event.target.value })}
            />
          </Field>
          {toolError && (
            <div className="text-sm text-error" role="alert">
              {toolError}
            </div>
          )}
          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="primary" disabled={uploading} onClick={insertImage}>
              {uploading ? 'Uploading…' : 'Insert image'}
            </Button>
            <Button type="button" disabled={uploading} onClick={closeTool}>
              Cancel
            </Button>
          </div>
        </div>
      )}

      {insertTool === 'table' && (
        <div
          className="grid gap-4 border-b border-line bg-paper p-4"
          role="dialog"
          aria-label="Insert table"
          onKeyDown={(event) => event.key === 'Escape' && closeTool()}
        >
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <Field label="Rows" hint="1–20 rows">
              <TextInput
                autoFocus
                type="number"
                min="1"
                max="20"
                inputMode="numeric"
                value={tableSize.rows}
                onChange={(event) => setTableSize({ ...tableSize, rows: event.target.value })}
              />
            </Field>
            <Field label="Columns" hint="1–20 columns">
              <TextInput
                type="number"
                min="1"
                max="20"
                inputMode="numeric"
                value={tableSize.columns}
                onChange={(event) => setTableSize({ ...tableSize, columns: event.target.value })}
              />
            </Field>
          </div>
          {toolError && (
            <div className="text-sm text-error" role="alert">
              {toolError}
            </div>
          )}
          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="primary" onClick={insertTable}>
              Insert table
            </Button>
            <Button type="button" onClick={closeTool}>
              Cancel
            </Button>
          </div>
        </div>
      )}

      <div
        ref={ref}
        className={cx(
          'editor-content wysiwyg-content p-4 outline-none',
          sizeClasses[size] || sizeClasses.default,
        )}
        contentEditable
        suppressContentEditableWarning
        data-placeholder={placeholder}
        onInput={handleInput}
        onKeyDown={handleEditorKeyDown}
        onKeyUp={captureSelection}
        onMouseUp={captureSelection}
        onFocus={captureSelection}
      />

      {mentionQuery !== null && mentionUsers.length > 0 && (
        <div className="border-t border-line bg-paper" role="listbox" aria-label="Mention a user">
          <div className="border-b border-line px-4 py-2 text-xs text-ink-muted">
            Mention a user
          </div>
          {mentionResults.length ? (
            mentionResults.map((user, index) => (
              <button
                key={user.id}
                type="button"
                role="option"
                aria-selected={index === mentionIndex}
                className={cx(
                  'flex w-full items-center justify-between gap-4 border-b border-line px-4 py-2 text-left text-sm',
                  index === mentionIndex
                    ? 'border-l-2 border-l-accent bg-surface text-ink'
                    : 'text-ink hover:bg-surface',
                )}
                onMouseDown={(event) => {
                  event.preventDefault();
                  insertMentionUser(user);
                }}
              >
                <span className="font-medium">@{user.name}</span>
                <span className="text-xs text-ink-muted">{user.email}</span>
              </button>
            ))
          ) : (
            <div className="px-4 py-4 text-sm text-ink-muted">
              No user matches “{mentionQuery}”.
            </div>
          )}
        </div>
      )}
    </div>
  );
}
