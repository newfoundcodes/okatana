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

function spanValue(cell: HTMLElement, attribute: string): number {
  const value = Number.parseInt(cell.getAttribute(attribute) || '1', 10);
  return Number.isFinite(value) && value > 0 ? value : 1;
}

export function createTableCell(tagName: string = 'td'): HTMLTableCellElement {
  const cell = document.createElement(
    tagName.toLowerCase() === 'th' ? 'th' : 'td',
  ) as HTMLTableCellElement;

  cell.appendChild(document.createElement('br'));
  return cell;
}

export function createEditorTable(
  rowCount: number | string,
  columnCount: number | string,
): HTMLTableElement {
  const rows = Math.max(1, Math.min(20, Number.parseInt(String(rowCount), 10) || 1));
  const columns = Math.max(1, Math.min(20, Number.parseInt(String(columnCount), 10) || 1));
  const table = document.createElement('table');
  const body = document.createElement('tbody');

  for (let rowIndex = 0; rowIndex < rows; rowIndex += 1) {
    const row = document.createElement('tr');
    for (let columnIndex = 0; columnIndex < columns; columnIndex += 1) {
      row.appendChild(createTableCell());
    }

    body.appendChild(row);
  }

  table.appendChild(body);
  return table;
}

export function cellFromSelection(
  range: Range | null,
  editor: HTMLElement | null,
): HTMLTableCellElement | null {
  if (!range || !editor) {
    return null;
  }

  const start =
    range.startContainer?.nodeType === Node.ELEMENT_NODE
      ? (range.startContainer as HTMLElement)
      : range.startContainer?.parentElement;
  const cell = start?.closest?.('td,th') as HTMLTableCellElement | null;

  if (!cell || !editor.contains(cell)) {
    return null;
  }

  return cell;
}

export function buildTableModel(table: HTMLTableElement): TableModel {
  const rows = Array.from(table?.rows || []);
  const grid: (TableModelBlock | null)[][] = [];
  const blocks: TableModelBlock[] = [];

  let width = 0;
  rows.forEach((row, rowIndex) => {
    if (!grid[rowIndex]) {
      grid[rowIndex] = [];
    }

    let columnIndex = 0;
    Array.from((row as HTMLTableRowElement).cells).forEach((cell) => {
      while (grid[rowIndex][columnIndex]) {
        columnIndex += 1;
      }

      const rowSpan = spanValue(cell, 'rowspan');
      const colSpan = spanValue(cell, 'colspan');
      const block: TableModelBlock = {
        cell,
        row: rowIndex,
        col: columnIndex,
        rowSpan,
        colSpan,
      };
      blocks.push(block);

      for (let r = rowIndex; r < rowIndex + rowSpan; r += 1) {
        if (!grid[r]) {
          grid[r] = [];
        }

        for (let c = columnIndex; c < columnIndex + colSpan; c += 1) {
          grid[r][c] = block;
        }
      }

      columnIndex += colSpan;
      width = Math.max(width, columnIndex);
    });
  });

  return { table, blocks, grid, width, height: Math.max(rows.length, grid.length) };
}

export function blockForCell(
  model: TableModel,
  cell: HTMLTableCellElement,
): TableModelBlock | null {
  return model.blocks.find((block) => block.cell === cell) || null;
}

export function blockAt(model: TableModel, row: number, column: number): TableModelBlock | null {
  return model.grid[row]?.[column] || null;
}

function cloneBlocks(model: TableModel): TableModelBlock[] {
  return model.blocks.map((block) => ({ ...block }));
}

function normalizeCell(cell: HTMLTableCellElement, rowSpan: number, colSpan: number): void {
  if (rowSpan > 1) {
    cell.setAttribute('rowspan', String(rowSpan));
  } else {
    cell.removeAttribute('rowspan');
  }

  if (colSpan > 1) {
    cell.setAttribute('colspan', String(colSpan));
  } else {
    cell.removeAttribute('colspan');
  }

  if (!cell.childNodes.length) {
    cell.appendChild(document.createElement('br'));
  }
}

export function renderTable(
  table: HTMLTableElement,
  blocks: TableModelBlock[],
  height: number,
): TableModel {
  const caption =
    Array.from(table.children).find((child) => child.tagName?.toLowerCase() === 'caption') || null;

  if (caption) {
    caption.remove();
  }

  table.replaceChildren();
  if (caption) {
    table.appendChild(caption);
  }

  const body = document.createElement('tbody');
  const rows = Array.from({ length: Math.max(1, height) }, () => {
    const row = document.createElement('tr');
    body.appendChild(row);

    return row;
  });

  blocks
    .filter((block) => block.row >= 0 && block.row < rows.length)
    .sort((left, right) => left.row - right.row || left.col - right.col)
    .forEach((block) => {
      normalizeCell(block.cell, block.rowSpan, block.colSpan);
      rows[block.row].appendChild(block.cell);
    });

  table.appendChild(body);
  return buildTableModel(table);
}

function newBlock(row: number, col: number, tagName: string = 'td'): TableModelBlock {
  return { cell: createTableCell(tagName), row, col, rowSpan: 1, colSpan: 1 };
}

export function insertTableRow(
  table: HTMLTableElement,
  activeCell: HTMLTableCellElement,
  direction: 'above' | 'below',
) {
  const model = buildTableModel(table);
  const selected = blockForCell(model, activeCell);

  if (!selected || !model.width) {
    return { model, focusCell: activeCell };
  }

  const boundary = direction === 'above' ? selected.row : selected.row + selected.rowSpan;
  const blocks = cloneBlocks(model);
  const coveredColumns = new Set<number>();

  blocks.forEach((block) => {
    if (block.row < boundary && block.row + block.rowSpan > boundary) {
      block.rowSpan += 1;
      for (let column = block.col; column < block.col + block.colSpan; column += 1) {
        coveredColumns.add(column);
      }
    } else if (block.row >= boundary) {
      block.row += 1;
    }
  });

  for (let column = 0; column < model.width; column += 1) {
    if (!coveredColumns.has(column)) {
      blocks.push(newBlock(boundary, column));
    }
  }

  const next = renderTable(table, blocks, model.height + 1);
  return { model: next, focusCell: blockAt(next, boundary, selected.col)?.cell || activeCell };
}

export function removeTableRow(table: HTMLTableElement, activeCell: HTMLTableCellElement) {
  const model = buildTableModel(table);
  const selected = blockForCell(model, activeCell);

  if (!selected) {
    return { model, focusCell: activeCell, removeTable: false };
  }

  if (model.height <= 1) {
    return { model, focusCell: null, removeTable: true };
  }

  const removedRow = selected.row;
  const blocks = cloneBlocks(model).flatMap((block) => {
    if (block.row > removedRow) {
      return [{ ...block, row: block.row - 1 }];
    }

    if (block.row < removedRow && block.row + block.rowSpan > removedRow) {
      return [{ ...block, rowSpan: block.rowSpan - 1 }];
    }

    if (block.row === removedRow) {
      if (block.rowSpan > 1) {
        return [{ ...block, rowSpan: block.rowSpan - 1 }];
      }

      return [];
    }

    return [block];
  });

  const next = renderTable(table, blocks, model.height - 1);
  const focusRow = Math.min(removedRow, next.height - 1);

  return {
    model: next,
    focusCell:
      blockAt(next, focusRow, Math.min(selected.col, Math.max(0, next.width - 1)))?.cell ||
      next.blocks[0]?.cell ||
      null,
    removeTable: false,
  };
}

export function insertTableColumn(
  table: HTMLTableElement,
  activeCell: HTMLTableCellElement,
  direction: 'left' | 'right',
) {
  const model = buildTableModel(table);
  const selected = blockForCell(model, activeCell);

  if (!selected || !model.height) {
    return { model, focusCell: activeCell };
  }

  const boundary = direction === 'left' ? selected.col : selected.col + selected.colSpan;
  const blocks = cloneBlocks(model);
  const coveredRows = new Set<number>();

  blocks.forEach((block) => {
    if (block.col < boundary && block.col + block.colSpan > boundary) {
      block.colSpan += 1;
      for (let row = block.row; row < block.row + block.rowSpan; row += 1) {
        coveredRows.add(row);
      }
    } else if (block.col >= boundary) {
      block.col += 1;
    }
  });

  for (let row = 0; row < model.height; row += 1) {
    if (!coveredRows.has(row)) {
      blocks.push(newBlock(row, boundary));
    }
  }

  const next = renderTable(table, blocks, model.height);
  return { model: next, focusCell: blockAt(next, selected.row, boundary)?.cell || activeCell };
}

export function removeTableColumn(table: HTMLTableElement, activeCell: HTMLTableCellElement) {
  const model = buildTableModel(table);
  const selected = blockForCell(model, activeCell);

  if (!selected) {
    return { model, focusCell: activeCell, removeTable: false };
  }

  if (model.width <= 1) {
    return { model, focusCell: null, removeTable: true };
  }

  const removedColumn = selected.col;
  const blocks = cloneBlocks(model).flatMap((block) => {
    if (block.col > removedColumn) {
      return [{ ...block, col: block.col - 1 }];
    }

    if (block.col < removedColumn && block.col + block.colSpan > removedColumn) {
      return [{ ...block, colSpan: block.colSpan - 1 }];
    }

    if (block.col === removedColumn) {
      if (block.colSpan > 1) {
        return [{ ...block, colSpan: block.colSpan - 1 }];
      }

      return [];
    }

    return [block];
  });

  const next = renderTable(table, blocks, model.height);
  const focusColumn = Math.min(removedColumn, next.width - 1);

  return {
    model: next,
    focusCell:
      blockAt(next, Math.min(selected.row, next.height - 1), focusColumn)?.cell ||
      next.blocks[0]?.cell ||
      null,
    removeTable: false,
  };
}

function hasMeaningfulContent(cell: HTMLTableCellElement): boolean {
  return Boolean(
    cell.textContent?.trim() || cell.querySelector('img,table,[data-mention-user-id]'),
  );
}

function appendMergedContent(target: HTMLTableCellElement, source: HTMLTableCellElement): void {
  const sourceHasContent = hasMeaningfulContent(source);
  if (!sourceHasContent) {
    return;
  }

  if (!hasMeaningfulContent(target)) {
    target.replaceChildren();
  } else {
    target.appendChild(document.createElement('br'));
  }

  while (source.firstChild) {
    target.appendChild(source.firstChild);
  }
}

export function mergeTableCell(
  table: HTMLTableElement,
  activeCell: HTMLTableCellElement,
  direction: 'right' | 'down',
) {
  const model = buildTableModel(table);
  const selected = blockForCell(model, activeCell);

  if (!selected) {
    return { ok: false, message: 'Select a table cell first.', model, focusCell: activeCell };
  }

  const target =
    direction === 'right'
      ? blockAt(model, selected.row, selected.col + selected.colSpan)
      : blockAt(model, selected.row + selected.rowSpan, selected.col);

  const aligned =
    direction === 'right'
      ? target &&
        target.row === selected.row &&
        target.rowSpan === selected.rowSpan &&
        target.col === selected.col + selected.colSpan
      : target &&
        target.col === selected.col &&
        target.colSpan === selected.colSpan &&
        target.row === selected.row + selected.rowSpan;

  if (!aligned) {
    return {
      ok: false,
      message:
        direction === 'right'
          ? 'The cell on the right must cover the same rows before it can be merged.'
          : 'The cell below must cover the same columns before it can be merged.',
      model,
      focusCell: activeCell,
    };
  }

  appendMergedContent(selected.cell, target!.cell);

  const blocks = cloneBlocks(model).filter((block) => block.cell !== target!.cell);
  const selectedCopy = blocks.find((block) => block.cell === selected.cell)!;

  if (direction === 'right') {
    selectedCopy.colSpan += target!.colSpan;
  } else {
    selectedCopy.rowSpan += target!.rowSpan;
  }

  const next = renderTable(table, blocks, model.height);
  return { ok: true, model: next, focusCell: selected.cell };
}

export function splitTableCell(table: HTMLTableElement, activeCell: HTMLTableCellElement) {
  const model = buildTableModel(table);
  const selected = blockForCell(model, activeCell);

  if (!selected || (selected.rowSpan === 1 && selected.colSpan === 1)) {
    return { ok: false, message: 'This cell is not merged.', model, focusCell: activeCell };
  }

  const blocks = cloneBlocks(model);
  const selectedCopy = blocks.find((block) => block.cell === selected.cell)!;
  const originalRowSpan = selectedCopy.rowSpan;
  const originalColSpan = selectedCopy.colSpan;

  selectedCopy.rowSpan = 1;
  selectedCopy.colSpan = 1;

  const tagName = selected.cell.tagName?.toLowerCase() || 'td';
  for (let row = selected.row; row < selected.row + originalRowSpan; row += 1) {
    for (let column = selected.col; column < selected.col + originalColSpan; column += 1) {
      if (row === selected.row && column === selected.col) {
        continue;
      }

      blocks.push(newBlock(row, column, tagName));
    }
  }

  const next = renderTable(table, blocks, model.height);
  return { ok: true, model: next, focusCell: selected.cell };
}

export function tableContextForCell(cell: HTMLTableCellElement | null): TableContext | null {
  const table = cell?.closest?.('table') as HTMLTableElement | null;
  if (!table) {
    return null;
  }

  const model = buildTableModel(table);
  const selected = cell ? blockForCell(model, cell) : null;

  if (!selected) {
    return null;
  }

  const right = blockAt(model, selected.row, selected.col + selected.colSpan);
  const below = blockAt(model, selected.row + selected.rowSpan, selected.col);

  return {
    table,
    cell: cell!,
    row: selected.row,
    column: selected.col,
    rowSpan: selected.rowSpan,
    colSpan: selected.colSpan,
    rows: model.height,
    columns: model.width,
    canMergeRight: Boolean(
      right &&
      right.row === selected.row &&
      right.rowSpan === selected.rowSpan &&
      right.col === selected.col + selected.colSpan,
    ),
    canMergeDown: Boolean(
      below &&
      below.col === selected.col &&
      below.colSpan === selected.colSpan &&
      below.row === selected.row + selected.rowSpan,
    ),
    canSplit: selected.rowSpan > 1 || selected.colSpan > 1,
  };
}
