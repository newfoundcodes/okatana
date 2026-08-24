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

import { useEffect, useRef, useState } from 'react';
import { put } from '../../lib/api';
import { Panel, SectionHeading } from '../Primitives';
import BoardRow from './BoardRow';

export default function BoardsSettings({ info, reload, setError, setNotice }: any) {
  const [order, setOrder] = useState(info.boards);
  const [dragOverId, setDragOverId] = useState<string | null>(null);
  const draggedId = useRef<string | null>(null);

  const saveOrder = async (next: any[]) => {
    setOrder(next);

    try {
      await put(`/app-api/projects/${info.project.id}/boards/reorder`, {
        board_ids: next.map((board) => board.id),
      });

      setNotice('Phase order saved.');
      reload();
    } catch (requestError: any) {
      setOrder(info.boards);
      setError(requestError.message);
    }
  };

  const reorder = async (sourceId: string, targetId: string) => {
    if (!sourceId || !targetId || sourceId === targetId) {
      return;
    }

    const next = [...order];
    const sourceIndex = next.findIndex((board) => board.id === sourceId);
    const targetIndex = next.findIndex((board) => board.id === targetId);

    if (sourceIndex < 0 || targetIndex < 0) {
      return;
    }

    const [moved] = next.splice(sourceIndex, 1);
    next.splice(targetIndex, 0, moved);

    await saveOrder(next);
  };

  const moveByKeyboard = async (boardId: string, delta: number) => {
    const sourceIndex = order.findIndex((board: any) => board.id === boardId);
    const targetIndex = sourceIndex + delta;

    if (sourceIndex < 0 || targetIndex < 0 || targetIndex >= order.length) {
      return;
    }

    const next = [...order];
    [next[sourceIndex], next[targetIndex]] = [next[targetIndex], next[sourceIndex]];

    await saveOrder(next);
  };

  useEffect(() => {
    setOrder(info.boards);
  }, [info.boards]);

  return (
    <Panel>
      <SectionHeading
        title="Phases"
        description="Drag phases to reorder them. The drag control also supports the Up and Down arrow keys."
      />
      <div className="border-t-2 border-ink">
        {order.map((board: any) => (
          <BoardRow
            key={board.id}
            board={board}
            boards={order}
            dragOver={dragOverId === board.id}
            onDragStart={(event: any) => {
              draggedId.current = board.id;
              event.dataTransfer.effectAllowed = 'move';
              event.dataTransfer.setData('text/plain', board.id);
            }}
            onDragOver={(event: any) => {
              event.preventDefault();
              event.dataTransfer.dropEffect = 'move';
              setDragOverId(board.id);
            }}
            onDragLeave={() => setDragOverId((value) => (value === board.id ? null : value))}
            onDrop={async (event: any) => {
              event.preventDefault();
              const sourceId = draggedId.current || event.dataTransfer.getData('text/plain');
              draggedId.current = null;
              setDragOverId(null);
              await reorder(sourceId, board.id);
            }}
            onDragEnd={() => {
              draggedId.current = null;
              setDragOverId(null);
            }}
            moveByKeyboard={(delta: number) => moveByKeyboard(board.id, delta)}
            reload={reload}
            setError={setError}
            setNotice={setNotice}
          />
        ))}
      </div>
    </Panel>
  );
}
