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

import type {
  ReactNode,
  ButtonHTMLAttributes,
  AnchorHTMLAttributes,
  HTMLAttributes,
  InputHTMLAttributes,
  SelectHTMLAttributes,
  TextareaHTMLAttributes,
  ChangeEvent,
  ThHTMLAttributes,
  TdHTMLAttributes,
} from 'react';

declare global {
  interface SecurityStatus {
    email_required?: boolean;
    two_factor_required?: boolean;
    email_verified?: boolean;
    two_factor_confirmed?: boolean;
    complete?: boolean;
  }

  interface TwoFactorSetup {
    qr_data_url: string;
    manual_key: string;
    issuer: string;
    account: string;
  }

  interface AccountSecuritySetupProps {
    initialSecurity?: SecurityStatus;
    email?: string;
    onComplete?: (data: any) => void;
    heading?: ReactNode;
  }

  interface Attachment {
    id: string | number;
    original_name: string;
    size_bytes: number;
    download_url: string;
  }

  interface AttachmentPickerProps {
    files?: File[];
    onChange?: (files: File[]) => void;
    disabled?: boolean;
    label?: ReactNode;
  }

  interface AttachmentListProps {
    attachments?: Attachment[];
    onRemoved?: (id: string | number) => void;
    canRemove?: boolean | ((attachment: Attachment) => boolean);
  }

  interface ConfirmDialogProps {
    open: boolean;
    title: ReactNode;
    description: ReactNode;
    confirmLabel?: ReactNode;
    onConfirm: () => void;
    onCancel?: () => void;
    busy?: boolean;
    confirmDisabled?: boolean;
  }

  interface ModalProps {
    open: boolean;
    onClose?: () => void;
    title: ReactNode;
    children?: ReactNode;
    wide?: boolean;
  }

  interface User {
    name?: string;
    avatar_url?: string;
  }

  interface AvatarProps {
    user?: User;
    size?: 'sm' | 'md' | 'lg';
    className?: string;
  }

  type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?: 'primary' | 'secondary' | 'danger' | 'ghost' | string;
    compact?: boolean;
  };

  type ActionLinkProps = AnchorHTMLAttributes<HTMLAnchorElement> & {
    variant?: string;
    compact?: boolean;
  };

  type InlineActionProps = ButtonHTMLAttributes<HTMLButtonElement> & {
    as?: any;
  };

  interface CopyValueButtonProps {
    value?: string | number | null;
    label?: string;
    className?: string;
  }

  type PanelProps = HTMLAttributes<HTMLElement> & {
    as?: any;
  };

  type GridLayoutProps = HTMLAttributes<HTMLElement> & {
    as?: any;
  };

  interface PageHeaderProps {
    eyebrow?: ReactNode;
    title: ReactNode;
    description?: ReactNode;
    actions?: ReactNode;
    compact?: boolean;
  }

  interface TabItem {
    value: string;
    label: ReactNode;
  }

  interface TabsProps {
    value: string;
    onChange: (value: string) => void;
    items: TabItem[];
    actions?: ReactNode;
  }

  interface FieldProps {
    label: ReactNode;
    hint?: ReactNode;
    children: ReactNode;
    className?: string;
  }

  type TextInputProps = InputHTMLAttributes<HTMLInputElement>;

  interface ColorValueInputProps {
    value?: string;
    onChange?: (value: string) => void;
    ariaLabel?: string;
    className?: string;
  }

  type SelectProps = SelectHTMLAttributes<HTMLSelectElement>;

  type TextAreaProps = TextareaHTMLAttributes<HTMLTextAreaElement>;

  type CheckProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'onChange'> & {
    checked?: boolean;
    onChange?: (event: ChangeEvent<HTMLInputElement>) => void;
    children?: ReactNode;
  };

  interface SectionHeadingProps {
    title: ReactNode;
    description?: ReactNode;
    actions?: ReactNode;
  }

  interface EmptyStateProps {
    title: ReactNode;
    description?: ReactNode;
    action?: ReactNode;
  }

  interface StatProps {
    label: ReactNode;
    value: ReactNode;
    note?: ReactNode;
    hero?: boolean;
    tone?: 'accent' | 'error' | 'ink';
  }

  type ThProps = ThHTMLAttributes<HTMLTableCellElement> & {
    numeric?: boolean;
  };

  type TdProps = TdHTMLAttributes<HTMLTableCellElement> & {
    numeric?: boolean;
  };

  interface BarRow {
    id?: string | number;
    name?: string;
    count?: number | string;
    is_done?: boolean;
  }

  interface BarsProps {
    rows?: BarRow[];
  }

  interface ThroughputData {
    created?: number | string;
    completed?: number | string;
    date?: string;
    [key: string]: any;
  }

  interface ThroughputChartProps {
    data?: ThroughputData[];
  }

  interface GanttTicket {
    id: string | number;
    number: number | string;
    title: string;
    started_at?: string;
    created_at?: string;
    completed_at?: string;
    due_at?: string;
    updated_at?: string;
    board?: {
      name?: string;
      is_done?: boolean;
    };
  }

  interface GanttChartProps {
    tickets?: GanttTicket[];
    projectKey?: string;
    emptyMessage?: ReactNode;
  }

  interface DropdownMenuItem {
    label: ReactNode;
    onSelect?: () => void;
    disabled?: boolean;
    disabledReason?: string;
    separatorBefore?: boolean;
    tone?: 'danger' | 'ink' | string;
  }

  interface DropdownMenuProps {
    label?: ReactNode;
    ariaLabel?: string;
    items: DropdownMenuItem[];
    align?: 'left' | 'right';
    compact?: boolean;
    stopPropagation?: boolean;
    className?: string;
  }

  interface NotificationData {
    url?: string;
    actor?: User;
    title?: string;
    body?: string;
  }

  interface NotificationRow {
    id: string | number;
    read_at?: string;
    created_at: string;
    data?: NotificationData;
  }

  interface TableSearchProps {
    value: string;
    onChange: (value: string) => void;
    label?: string;
    placeholder?: string;
  }

  interface PaginationBarProps {
    page: number;
    perPage: number;
    total: number;
    onPageChange: (page: number) => void;
    onPerPageChange: (perPage: number) => void;
    label?: string;
    compact?: boolean;
  }

  interface MentionUser {
    id: string | number;
    name: string;
    email?: string;
  }

  interface RichTextEditorProps {
    value?: string;
    onChange?: (value: string) => void;
    placeholder?: string;
    size?: 'compact' | 'default' | 'large';
    mentionUsers?: MentionUser[];
  }

  interface TagSuggestion {
    id: string | number;
    name: string;
    [key: string]: any;
  }

  interface TagInputProps {
    value?: string[];
    onChange: (value: string[]) => void;
    suggestions?: TagSuggestion[];
    label?: ReactNode;
    ariaLabel?: string;
    countKey?: string;
  }

  interface Toast {
    id: string;
    tone: 'error' | 'success';
    message: ReactNode;
  }
}
