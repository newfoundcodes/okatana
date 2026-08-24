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

import { forwardRef, ReactNode, useEffect, useRef, useState } from 'react';
import { toast } from '../lib/toast';
import { buttonVariants, cx } from '../lib/uiPrimitives';

export function Avatar({ user, size = 'md', className = '' }: AvatarProps) {
  const name = user?.name || 'User';
  const [failed, setFailed] = useState(false);

  const initials =
    name
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part[0])
      .join('')
      .toUpperCase() || '?';
  const sizeClass =
    size === 'sm' ? 'h-6 w-6 text-xs' : size === 'lg' ? 'h-16 w-16 text-lg' : 'h-8 w-8 text-xs';

  useEffect(() => setFailed(false), [user?.avatar_url]);

  return (
    <span
      className={cx(
        'inline-grid shrink-0 place-items-center overflow-hidden rounded-full border border-line bg-surface font-medium text-ink',
        sizeClass,
        className,
      )}
      title={name}
      aria-label={name}
    >
      {user?.avatar_url && !failed ? (
        <img
          src={user.avatar_url}
          alt=""
          className="h-full w-full object-cover"
          onError={() => setFailed(true)}
        />
      ) : (
        initials
      )}
    </span>
  );
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>((props, ref) => {
  const {
    children,
    onClick,
    disabled,
    variant = 'secondary',
    compact = false,
    className = '',
    type = 'button',
    ...rest
  } = props;

  return (
    <button
      ref={ref}
      type={type}
      onClick={onClick}
      disabled={disabled}
      className={cx(
        'inline-flex items-center justify-start rounded-sm border font-medium transition-colors duration-150 ease-out motion-reduce:transition-none',
        compact ? 'min-h-8 px-2 py-2 text-xs' : 'min-h-10 px-4 py-2 text-sm',
        buttonVariants[variant] || buttonVariants.secondary,
        className,
      )}
      {...rest}
    >
      {children}
    </button>
  );
});
Button.displayName = 'Button';

export function ActionLink({
  href,
  children,
  variant = 'secondary',
  compact = false,
  className = '',
  ...props
}: ActionLinkProps) {
  return (
    <a
      href={href}
      className={cx(
        'inline-flex items-center justify-start rounded-sm border font-medium transition-colors duration-150 ease-out motion-reduce:transition-none',
        compact ? 'min-h-8 px-2 py-2 text-xs' : 'min-h-10 px-4 py-2 text-sm',
        buttonVariants[variant] || buttonVariants.secondary,
        className,
      )}
      {...props}
    >
      {children}
    </a>
  );
}

export function InlineAction({
  as: Tag = 'button',
  children,
  className = '',
  ...props
}: InlineActionProps) {
  const tagProps = Tag === 'button' ? { type: 'button', ...props } : props;
  return (
    <Tag
      className={cx(
        'border-b border-line-strong bg-transparent p-0 text-left text-sm font-medium text-ink transition-colors duration-150 ease-out hover:border-accent hover:text-accent motion-reduce:transition-none',
        className,
      )}
      {...tagProps}
    >
      {children}
    </Tag>
  );
}

export function OverflowIcon({ className = 'h-4 w-4' }: { className?: string }) {
  return (
    <svg aria-hidden="true" viewBox="0 0 16 16" className={cx('fill-current', className)}>
      <circle cx="3" cy="8" r="1.25" />
      <circle cx="8" cy="8" r="1.25" />
      <circle cx="13" cy="8" r="1.25" />
    </svg>
  );
}

function CopyIcon() {
  return (
    <svg
      aria-hidden="true"
      viewBox="0 0 16 16"
      className="h-4 w-4 fill-none stroke-current"
      strokeWidth="1.5"
    >
      <rect x="5.5" y="5.5" width="7" height="7" rx="1" />
      <path d="M3.5 10.5h-1a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1h7a1 1 0 0 1 1 1v1" />
    </svg>
  );
}

export function CopyValueButton({
  value,
  label = 'Copy value',
  className = '',
}: CopyValueButtonProps) {
  const [status, setStatus] = useState('idle');
  const resetRef = useRef<number | null>(null);

  useEffect(
    () => () => {
      if (resetRef.current) {
        window.clearTimeout(resetRef.current);
      }
    },
    [],
  );

  const copy = async (event: React.MouseEvent<HTMLButtonElement>) => {
    event.stopPropagation();
    try {
      if (!navigator.clipboard?.writeText) {
        throw new Error('Clipboard API unavailable.');
      }
      await navigator.clipboard.writeText(String(value ?? ''));
      setStatus('copied');
      const subject = label.replace(/^Copy\s+/i, '').trim();
      toast.success(`${subject ? subject[0].toUpperCase() + subject.slice(1) : 'Value'} copied.`);
    } catch {
      setStatus('failed');
      toast.error('Could not copy to clipboard.');
    }
    if (resetRef.current) {
      window.clearTimeout(resetRef.current);
    }
    resetRef.current = window.setTimeout(() => setStatus('idle'), 1600);
  };

  const title = status === 'copied' ? 'Copied' : status === 'failed' ? 'Copy failed' : label;

  return (
    <button
      type="button"
      onClick={copy}
      className={cx(
        'inline-grid h-8 w-8 shrink-0 place-items-center rounded-sm border border-line bg-paper text-ink transition-colors duration-150 ease-out hover:border-line-strong hover:bg-surface focus:outline focus:outline-2 focus:outline-offset-2 focus:outline-accent motion-reduce:transition-none',
        className,
      )}
      aria-label={title}
      title={title}
    >
      <CopyIcon />
      <span className="sr-only" aria-live="polite">
        {status === 'copied'
          ? 'Copied to clipboard.'
          : status === 'failed'
            ? 'Could not copy to clipboard.'
            : ''}
      </span>
    </button>
  );
}

export function Panel({ as: Tag = 'section', className = '', children, ...props }: PanelProps) {
  return (
    <Tag className={cx('rounded-sm border border-line bg-paper p-6', className)} {...props}>
      {children}
    </Tag>
  );
}

export function GridLayout({
  as: Tag = 'div',
  className = '',
  children,
  ...props
}: GridLayoutProps) {
  return (
    <Tag
      className={cx('grid grid-cols-4 gap-6 md:grid-cols-8 xl:grid-cols-12', className)}
      {...props}
    >
      {children}
    </Tag>
  );
}

export function Page({ children, className = '' }: { children: ReactNode; className?: string }) {
  return (
    <div className={cx('relative z-10 px-8 py-8 md:px-12 md:py-12', className)}>{children}</div>
  );
}

export function PageHeader({
  eyebrow,
  title,
  description,
  actions,
  compact = false,
}: PageHeaderProps) {
  return (
    <GridLayout className={compact ? 'mb-8' : 'mb-12'}>
      <div className="col-span-4 md:col-span-5 xl:col-span-8">
        {eyebrow && (
          <div className="mb-2 text-xs font-medium uppercase tracking-widest text-ink-muted">
            {eyebrow}
          </div>
        )}
        <h1 className="text-2xl font-semibold tracking-display text-ink md:text-3xl">{title}</h1>
        {description && <p className="mt-4 max-w-3xl text-base text-ink-muted">{description}</p>}
      </div>
      {actions && (
        <div className="col-span-4 flex flex-wrap items-start gap-2 md:col-span-3 md:justify-end xl:col-span-4">
          {actions}
        </div>
      )}
    </GridLayout>
  );
}

export function Breadcrumbs({ children }: { children: ReactNode }) {
  return (
    <nav
      aria-label="Breadcrumb"
      className="mb-8 flex flex-wrap items-center gap-2 text-sm text-ink-muted"
    >
      {children}
    </nav>
  );
}

export function BreadcrumbButton({
  children,
  onClick,
}: {
  children: ReactNode;
  onClick?: () => void;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="border-0 bg-transparent p-0 text-ink-muted hover:text-accent"
    >
      {children}
    </button>
  );
}

export function Tabs({ value, onChange, items, actions }: TabsProps) {
  return (
    <div className="mb-8 flex min-w-0 items-end justify-between gap-4 border-b border-line">
      <nav aria-label="Section navigation" className="flex min-w-0 overflow-x-auto">
        {items.map((item) => (
          <button
            type="button"
            key={item.value}
            onClick={() => onChange(item.value)}
            className={cx(
              'min-h-10 shrink-0 border-b-2 px-4 py-2 text-sm transition-colors duration-150 ease-out motion-reduce:transition-none',
              value === item.value
                ? 'border-accent font-semibold text-ink'
                : 'border-transparent font-normal text-ink-muted hover:border-line-strong hover:text-ink',
            )}
          >
            {item.label}
          </button>
        ))}
      </nav>
      {actions && <div className="shrink-0 pb-2">{actions}</div>}
    </div>
  );
}

export function Alert({
  children,
  tone = 'error',
}: {
  children: ReactNode;
  tone?: 'success' | 'warning' | 'error';
}) {
  const toneClass =
    tone === 'success'
      ? 'border-success text-success'
      : tone === 'warning'
        ? 'border-warning text-warning'
        : 'border-error text-error';
  return (
    <div
      role={tone === 'error' ? 'alert' : 'status'}
      className={cx('mb-8 border-l-2 bg-paper px-4 py-2 text-sm', toneClass)}
    >
      {children}
    </div>
  );
}

export function Field({ label, hint = '', children, className = '' }: FieldProps) {
  return (
    <label className={cx('grid gap-2 text-sm text-ink-muted', className)}>
      <span>{label}</span>
      {children}
      {hint ? <span className="text-xs font-normal text-ink-muted">{hint}</span> : null}
    </label>
  );
}

export const inputClass =
  'min-h-10 w-full rounded-sm border border-line bg-paper px-4 py-2 text-base text-ink outline-none placeholder:text-ink-muted hover:border-line-strong focus:border-line-strong';
export const selectClass = inputClass;
export const textareaClass =
  'min-h-24 w-full resize-y rounded-sm border border-line bg-paper px-4 py-2 text-base text-ink outline-none placeholder:text-ink-muted hover:border-line-strong focus:border-line-strong';

export const TextInput = forwardRef<HTMLInputElement, TextInputProps>(
  ({ className = '', ...props }, ref) => {
    return <input ref={ref} className={cx(inputClass, className)} {...props} />;
  },
);
TextInput.displayName = 'TextInput';

function pickerColor(value?: string) {
  if (/^#[0-9a-f]{6}$/i.test(value || '')) {
    return value;
  }
  const short = (value || '').match(/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i);
  return short ? `#${short[1]}${short[1]}${short[2]}${short[2]}${short[3]}${short[3]}` : '#595959';
}

export function ColorValueInput({
  value = '',
  onChange,
  ariaLabel = 'Choose stored color',
  className = '',
}: ColorValueInputProps) {
  return (
    <div className={cx('flex items-stretch gap-2', className)}>
      <input
        type="color"
        aria-label={ariaLabel}
        title="Choose color"
        value={pickerColor(value)}
        onChange={(event) => onChange?.(event.target.value)}
        className="h-10 w-10 shrink-0 cursor-pointer rounded-sm border border-line bg-paper p-2"
      />
      <TextInput
        value={value}
        onChange={(event) => onChange?.(event.target.value)}
        spellCheck="false"
        aria-label={`${ariaLabel} hex value`}
      />
    </div>
  );
}

export const Select = forwardRef<HTMLSelectElement, SelectProps>(
  ({ className = '', children, ...props }, ref) => {
    return (
      <select ref={ref} className={cx(selectClass, className)} {...props}>
        {children}
      </select>
    );
  },
);
Select.displayName = 'Select';

export const TextArea = forwardRef<HTMLTextAreaElement, TextAreaProps>(
  ({ className = '', ...props }, ref) => {
    return <textarea ref={ref} className={cx(textareaClass, className)} {...props} />;
  },
);
TextArea.displayName = 'TextArea';

export const Check = forwardRef<HTMLInputElement, CheckProps>(
  ({ checked, onChange, children, className = '', disabled = false }, ref) => {
    return (
      <label className={cx('flex min-h-8 items-center gap-2 text-sm text-ink', className)}>
        <input
          ref={ref}
          type="checkbox"
          checked={checked}
          onChange={onChange}
          disabled={disabled}
          className="h-4 w-4 rounded-none accent-accent"
        />
        <span>{children}</span>
      </label>
    );
  },
);
Check.displayName = 'Check';

export function SectionHeading({ title, description, actions }: SectionHeadingProps) {
  return (
    <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
      <div>
        <h2 className="text-lg font-semibold text-ink">{title}</h2>
        {description && <p className="mt-2 max-w-3xl text-sm text-ink-muted">{description}</p>}
      </div>
      {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
    </div>
  );
}

export function EmptyState({ title, description, action }: EmptyStateProps) {
  return (
    <div className="border-y border-line py-12">
      <h3 className="text-lg font-semibold text-ink">{title}</h3>
      {description && <p className="mt-2 max-w-2xl text-sm text-ink-muted">{description}</p>}
      {action && <div className="mt-6">{action}</div>}
    </div>
  );
}

export function Stat({ label, value, note, hero = false, tone = 'ink' }: StatProps) {
  const toneClass =
    tone === 'accent' ? 'text-accent' : tone === 'error' ? 'text-error' : 'text-ink';
  return (
    <div className="border-t border-line pt-4">
      <div className="text-sm font-normal text-ink-muted">{label}</div>
      <div
        className={cx(
          'mt-2 font-medium tracking-display tabular-nums',
          hero ? 'text-4xl' : 'text-2xl',
          toneClass,
        )}
      >
        {value}
      </div>
      {note && <div className="mt-2 text-sm text-ink-muted">{note}</div>}
    </div>
  );
}

export function Table({ children, className = '' }: { children: ReactNode; className?: string }) {
  return (
    <div className="overflow-x-auto">
      <table className={cx('w-full min-w-full border-collapse text-left text-base', className)}>
        {children}
      </table>
    </div>
  );
}

export function Th({ children, numeric = false, className = '', ...props }: ThProps) {
  return (
    <th
      className={cx(
        'border-b-2 border-line-strong px-0 py-2 text-sm font-semibold text-ink',
        numeric && 'text-right tabular-nums',
        className,
      )}
      {...props}
    >
      {children}
    </th>
  );
}

export function Td({ children, numeric = false, className = '', ...props }: TdProps) {
  return (
    <td
      className={cx(
        'border-b border-line px-0 py-4 align-top text-base text-ink',
        numeric && 'text-right tabular-nums',
        className,
      )}
      {...props}
    >
      {children}
    </td>
  );
}

export function GridGuides() {
  const columns = (count: number) =>
    Array.from({ length: count }, (_, index) => (
      <span key={index} className="border-l border-ink" />
    ));
  return (
    <div aria-hidden="true" className="pointer-events-none fixed inset-0 z-0 px-8 md:px-12">
      <div className="okatana-grid-guides grid h-full grid-cols-4 gap-6 md:hidden">
        {columns(4)}
      </div>
      <div className="okatana-grid-guides hidden h-full grid-cols-8 gap-6 md:grid xl:hidden">
        {columns(8)}
      </div>
      <div className="okatana-grid-guides hidden h-full grid-cols-12 gap-6 xl:grid">
        {columns(12)}
      </div>
    </div>
  );
}

export function LoadingState({ label = 'Loading…' }: { label?: string }) {
  return (
    <div className="border-l-2 border-line-strong px-4 py-2 text-sm text-ink-muted" role="status">
      {label}
    </div>
  );
}

export function Meta({ children }: { children: ReactNode }) {
  return <span className="text-sm text-ink-muted">{children}</span>;
}
