import Button from '@/Components/UI/Button';
import { cn } from '@/lib/utils';
import {
  ArrowDown,
  ArrowUp,
  Check,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  CloudUpload,
  Copy,
  Download,
  Edit3,
  Eye,
  FileText,
  Info,
  Loader2,
  Minus,
  MoreHorizontal,
  Search,
  Trash2,
  X,
} from 'lucide-react';
import {
  Fragment,
  lazy,
  Suspense,
  useEffect,
  useState,
  type ComponentProps,
  HTMLAttributes,
  ReactNode,
  useMemo,
  useRef,
  type ButtonHTMLAttributes,
  type InputHTMLAttributes,
} from 'react';

type Tone = 'default' | 'success' | 'warning' | 'danger' | 'info' | 'muted';

const toneClasses: Record<Tone, string> = {
  default:
    'bg-gray-100 text-gray-800 ring-gray-200 dark:bg-slate-700/70 dark:text-slate-100 dark:ring-slate-500/30',
  success:
    'bg-emerald-100 text-emerald-800 ring-emerald-200 dark:bg-emerald-500/20 dark:text-emerald-100 dark:ring-emerald-400/25',
  warning:
    'bg-amber-100 text-amber-800 ring-amber-200 dark:bg-amber-500/20 dark:text-amber-100 dark:ring-amber-400/25',
  danger:
    'bg-red-100 text-red-800 ring-red-200 dark:bg-red-500/20 dark:text-red-100 dark:ring-red-400/25',
  info: 'bg-sky-100 text-sky-800 ring-sky-200 dark:bg-sky-500/20 dark:text-sky-100 dark:ring-sky-400/25',
  muted:
    'bg-gray-50 text-ui-text-muted ring-gray-200 dark:bg-ui-dark-surface-2 dark:text-ui-dark-text-muted dark:ring-ui-dark-border',
};

export function Avatar({
  name,
  src,
  size = 'md',
  status,
  className,
}: {
  name?: string | null;
  src?: string | null;
  size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl' | '2xl';
  status?: 'online' | 'away' | 'offline';
  className?: string;
}) {
  const sizes = {
    xs: 'h-6 w-6 text-[10px]',
    sm: 'h-8 w-8 text-xs',
    md: 'h-10 w-10 text-sm',
    lg: 'h-12 w-12 text-base',
    xl: 'h-16 w-16 text-xl',
    '2xl': 'h-20 w-20 text-2xl',
  };
  const initials = (name || 'U')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('');

  const statusClasses = {
    online: 'bg-emerald-500',
    away: 'bg-amber-500',
    offline: 'bg-gray-300 dark:bg-ui-dark-text-muted',
  };

  return (
    <span className="relative inline-flex shrink-0">
      <span
        className={cn(
          'inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-ui-green font-semibold text-white ring-2 ring-white dark:ring-ui-dark-bg',
          sizes[size],
          className
        )}
      >
        {src ? (
          <img src={src} alt={name || 'Avatar'} className="h-full w-full object-cover" />
        ) : (
          initials
        )}
      </span>
      {status && (
        <span
          className={cn(
            'absolute bottom-0 right-0 h-3 w-3 rounded-full ring-2 ring-white dark:ring-ui-dark-bg',
            statusClasses[status]
          )}
        />
      )}
    </span>
  );
}

export function AvatarGroup({
  users,
  max = 4,
  size = 'sm',
  className,
}: {
  users: Array<{ name?: string | null; src?: string | null }>;
  max?: number;
  size?: 'xs' | 'sm' | 'md';
  className?: string;
}) {
  const visible = users.slice(0, max);
  const remaining = Math.max(users.length - visible.length, 0);
  const sizes = {
    xs: 'h-6 w-6 text-[10px]',
    sm: 'h-9 w-9 text-xs',
    md: 'h-10 w-10 text-sm',
  };

  return (
    <div className={cn('flex -space-x-2', className)}>
      {visible.map((user, index) => (
        <Avatar
          key={`${user.name || 'user'}-${index}`}
          name={user.name}
          src={user.src}
          size={size}
        />
      ))}
      {remaining > 0 && (
        <span
          className={cn(
            'inline-flex items-center justify-center rounded-full bg-gray-100 font-semibold text-gray-600 ring-2 ring-white dark:bg-ui-dark-surface-2 dark:text-ui-dark-text-muted dark:ring-ui-dark-bg',
            sizes[size]
          )}
        >
          +{remaining}
        </span>
      )}
    </div>
  );
}

export function IconButton({
  className,
  variant = 'ghost',
  size = 'md',
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: 'ghost' | 'outline' | 'primary' | 'danger';
  size?: 'sm' | 'md' | 'lg';
}) {
  const variants = {
    ghost:
      'text-ui-text-muted hover:bg-gray-100 hover:text-ui-text dark:text-ui-dark-text-muted dark:hover:bg-ui-dark-surface-2 dark:hover:text-ui-dark-text',
    outline:
      'border border-gray-200 bg-white text-ui-text hover:bg-gray-50 dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text dark:hover:bg-ui-dark-surface-2',
    primary: 'bg-ui-green text-ui-ink hover:bg-ui-green-dark hover:text-white',
    danger: 'text-red-600 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-500/10',
  };
  const sizes = {
    sm: 'h-8 w-8',
    md: 'h-10 w-10',
    lg: 'h-11 w-11',
  };

  return (
    <button
      type="button"
      className={cn(
        'inline-flex items-center justify-center rounded-btn transition focus:outline-none focus:ring-2 focus:ring-ui-green/25 disabled:pointer-events-none disabled:opacity-50',
        variants[variant],
        sizes[size],
        className
      )}
      {...props}
    />
  );
}

export function StatusBadge({
  tone = 'default',
  dot = false,
  className,
  children,
}: HTMLAttributes<HTMLSpanElement> & { tone?: Tone; dot?: boolean }) {
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset',
        toneClasses[tone],
        className
      )}
    >
      {dot && <span className="h-1.5 w-1.5 rounded-full bg-current" />}
      {children}
    </span>
  );
}

export function CounterBadge({
  count,
  tone = 'info',
  className,
}: {
  count: number | string;
  tone?: Extract<Tone, 'success' | 'danger' | 'info' | 'warning' | 'default'>;
  className?: string;
}) {
  const colors = {
    success:
      'bg-emerald-600 text-white ring-1 ring-inset ring-emerald-500/20 dark:bg-emerald-500 dark:text-emerald-950 dark:ring-emerald-300/20',
    danger:
      'bg-red-600 text-white ring-1 ring-inset ring-red-500/20 dark:bg-red-500 dark:text-red-950 dark:ring-red-300/20',
    info: 'bg-sky-600 text-white ring-1 ring-inset ring-sky-500/20 dark:bg-sky-400 dark:text-sky-950 dark:ring-sky-200/20',
    warning:
      'bg-amber-500 text-white ring-1 ring-inset ring-amber-400/20 dark:bg-amber-400 dark:text-amber-950 dark:ring-amber-200/20',
    default:
      'bg-ui-text text-white ring-1 ring-inset ring-black/10 dark:bg-ui-dark-text dark:text-ui-dark-bg dark:ring-white/10',
  };

  return (
    <span
      className={cn(
        'inline-flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-[11px] font-bold tabular-nums',
        colors[tone],
        className
      )}
    >
      {count}
    </span>
  );
}

export function ThemedIconTile({
  tone = 'green',
  size = 'md',
  className,
  children,
}: {
  tone?: 'green' | 'blue' | 'amber' | 'purple' | 'pink' | 'red' | 'gray';
  size?: 'sm' | 'md' | 'lg';
  className?: string;
  children: ReactNode;
}) {
  const tones = {
    green:
      'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200/70 dark:bg-emerald-500/20 dark:text-emerald-100 dark:ring-emerald-400/20',
    blue: 'bg-sky-100 text-sky-700 ring-1 ring-sky-200/70 dark:bg-sky-500/20 dark:text-sky-100 dark:ring-sky-400/20',
    amber:
      'bg-amber-100 text-amber-700 ring-1 ring-amber-200/70 dark:bg-amber-500/20 dark:text-amber-100 dark:ring-amber-400/20',
    // Kept as "purple" for compatibility; rendered in teal so no page shows a hard-coded purple.
    purple:
      'bg-teal-100 text-teal-700 ring-1 ring-teal-200/70 dark:bg-teal-500/20 dark:text-teal-100 dark:ring-teal-400/20',
    pink: 'bg-pink-100 text-pink-700 ring-1 ring-pink-200/70 dark:bg-pink-500/20 dark:text-pink-100 dark:ring-pink-400/20',
    red: 'bg-red-100 text-red-700 ring-1 ring-red-200/70 dark:bg-red-500/20 dark:text-red-100 dark:ring-red-400/20',
    gray: 'bg-gray-100 text-gray-700 dark:bg-ui-dark-surface-2 dark:text-ui-dark-text',
  };
  const sizes = {
    sm: 'h-8 w-8 rounded-md',
    md: 'h-10 w-10 rounded-lg',
    lg: 'h-11 w-11 rounded-xl',
  };

  return (
    <span
      className={cn(
        'inline-flex shrink-0 items-center justify-center',
        tones[tone],
        sizes[size],
        className
      )}
    >
      {children}
    </span>
  );
}

export function TagPill({
  className,
  children,
  onRemove,
  tone = 'default',
}: HTMLAttributes<HTMLSpanElement> & { onRemove?: () => void; tone?: Tone }) {
  const colors = {
    default:
      'bg-white text-ui-text ring-gray-200 dark:bg-ui-dark-surface dark:text-ui-dark-text dark:ring-ui-dark-border',
    success:
      'bg-emerald-100 text-emerald-800 ring-emerald-200 dark:bg-emerald-500/20 dark:text-emerald-100 dark:ring-emerald-400/25',
    warning:
      'bg-amber-100 text-amber-800 ring-amber-200 dark:bg-amber-500/20 dark:text-amber-100 dark:ring-amber-400/25',
    danger:
      'bg-red-100 text-red-800 ring-red-200 dark:bg-red-500/20 dark:text-red-100 dark:ring-red-400/25',
    info: 'bg-sky-100 text-sky-800 ring-sky-200 dark:bg-sky-500/20 dark:text-sky-100 dark:ring-sky-400/25',
    muted:
      'bg-gray-50 text-ui-text-muted ring-gray-200 dark:bg-ui-dark-surface-2 dark:text-ui-dark-text-muted dark:ring-ui-dark-border',
  };

  return (
    <span
      className={cn(
        'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset',
        colors[tone],
        className
      )}
    >
      {children}
      {onRemove && (
        <button
          type="button"
          onClick={onRemove}
          className="rounded-full text-ui-text-muted hover:text-red-500"
          aria-label="Remove tag"
        >
          <X className="h-3.5 w-3.5" />
        </button>
      )}
    </span>
  );
}

export function TrendBadge({
  value,
  direction = 'up',
  className,
}: {
  value: string;
  direction?: 'up' | 'down' | 'flat';
  className?: string;
}) {
  const Icon = direction === 'up' ? ArrowUp : direction === 'down' ? ArrowDown : Minus;
  const tone = direction === 'up' ? 'success' : direction === 'down' ? 'danger' : 'muted';

  return (
    <StatusBadge tone={tone} className={cn('gap-1 px-2 py-0.5', className)}>
      <Icon className="h-3.5 w-3.5" />
      {value}
    </StatusBadge>
  );
}

export function MetricCard({
  title,
  value,
  description,
  icon,
  trend,
  className,
}: {
  title: string;
  value: ReactNode;
  description?: ReactNode;
  icon?: ReactNode;
  trend?: ReactNode;
  className?: string;
}) {
  return (
    <section className={cn('ui-surface p-5', className)}>
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-sm font-medium text-ui-text-muted dark:text-ui-dark-text-muted">
            {title}
          </p>
          <div className="mt-2 text-2xl font-bold tracking-tight text-ui-text dark:text-ui-dark-text">
            {value}
          </div>
        </div>
        {icon && (
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-btn bg-ui-green-soft text-ui-green-dark dark:bg-ui-dark-green-soft dark:text-emerald-200">
            {icon}
          </div>
        )}
      </div>
      {(description || trend) && (
        <div className="mt-4 flex items-center justify-between gap-3 text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
          <span className="min-w-0 truncate">{description}</span>
          {trend}
        </div>
      )}
    </section>
  );
}

export function GradientCard({
  title,
  description,
  icon,
  action,
  className,
}: {
  title: ReactNode;
  description?: ReactNode;
  icon?: ReactNode;
  action?: ReactNode;
  className?: string;
}) {
  return (
    <section
      className={cn(
        'relative overflow-hidden rounded-2xl bg-gradient-to-br from-ui-green to-ui-green-dark p-5 text-white shadow-card',
        className
      )}
    >
      <div className="absolute -right-10 -top-10 h-32 w-32 rounded-full bg-white/10" />
      <div className="relative">
        {icon && (
          <div className="mb-3 flex h-8 w-8 items-center justify-center rounded-md bg-white/15">
            {icon}
          </div>
        )}
        <h3 className="font-semibold">{title}</h3>
        {description && <p className="mt-1 text-sm text-white/80">{description}</p>}
        {action && <div className="mt-3">{action}</div>}
      </div>
    </section>
  );
}

export function AddCard({
  children = 'Add new',
  className,
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & { children?: ReactNode }) {
  return (
    <button
      type="button"
      className={cn(
        'flex min-h-36 flex-col items-center justify-center rounded-2xl border-2 border-dashed border-gray-200 p-5 text-ui-text-muted transition hover:border-ui-green hover:bg-ui-green-soft/30 hover:text-ui-green-dark dark:border-ui-dark-border dark:text-ui-dark-text-muted dark:hover:bg-ui-dark-green-soft dark:hover:text-emerald-200',
        className
      )}
      {...props}
    >
      <span className="mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-white shadow-card dark:bg-ui-dark-surface">
        <PlusGlyph />
      </span>
      <span className="text-sm font-semibold">{children}</span>
    </button>
  );
}

function PlusGlyph() {
  return (
    <svg
      width="16"
      height="16"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2.5"
      strokeLinecap="round"
    >
      <line x1="12" y1="5" x2="12" y2="19" />
      <line x1="5" y1="12" x2="19" y2="12" />
    </svg>
  );
}

export function PageHeader({
  title,
  description,
  eyebrow,
  actions,
  className,
}: {
  title: ReactNode;
  description?: ReactNode;
  eyebrow?: ReactNode;
  actions?: ReactNode;
  className?: string;
}) {
  return (
    <div
      className={cn(
        'flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between',
        className
      )}
    >
      <div className="min-w-0">
        {eyebrow && (
          <div className="mb-1 text-[11px] font-semibold uppercase tracking-wider text-ui-green-dark dark:text-emerald-300">
            {eyebrow}
          </div>
        )}
        <h1 className="text-xl font-semibold tracking-tight text-ui-text dark:text-ui-dark-text">
          {title}
        </h1>
        {description && (
          <p className="mt-0.5 max-w-3xl text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
            {description}
          </p>
        )}
      </div>
      {actions && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
    </div>
  );
}

export function SectionHeader({
  title,
  description,
  actions,
  className,
}: {
  title: ReactNode;
  description?: ReactNode;
  actions?: ReactNode;
  className?: string;
}) {
  return (
    <div className={cn('flex items-start justify-between gap-4', className)}>
      <div>
        <h2 className="text-base font-semibold text-ui-text dark:text-ui-dark-text">
          {title}
        </h2>
        {description && (
          <p className="mt-1 text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
            {description}
          </p>
        )}
      </div>
      {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
    </div>
  );
}

export function Toolbar({
  search,
  filters,
  actions,
  className,
}: {
  search?: { value: string; onChange: (value: string) => void; placeholder?: string };
  filters?: ReactNode;
  actions?: ReactNode;
  className?: string;
}) {
  return (
    <div
      className={cn(
        'flex flex-col gap-3 rounded-card border border-gray-100 bg-white p-3 shadow-card dark:border-ui-dark-border dark:bg-ui-dark-surface dark:shadow-none md:flex-row md:items-center md:justify-between',
        className
      )}
    >
      <div className="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
        {search && (
          <label className="relative block min-w-0 flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ui-text-muted dark:text-ui-dark-text-muted" />
            <input
              value={search.value}
              onChange={(event) => search.onChange(event.target.value)}
              placeholder={search.placeholder || 'Search'}
              className="ui-input pl-9"
            />
          </label>
        )}
        {filters && <div className="flex flex-wrap items-center gap-2">{filters}</div>}
      </div>
      {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
    </div>
  );
}

export function InputGroup({
  label,
  children,
  helper,
  error,
  success,
  className,
}: {
  label?: ReactNode;
  children: ReactNode;
  helper?: ReactNode;
  error?: ReactNode;
  success?: ReactNode;
  className?: string;
}) {
  return (
    <label className={cn('block', className)}>
      {label && (
        <span className="mb-1.5 block text-sm font-medium text-ui-text dark:text-ui-dark-text">
          {label}
        </span>
      )}
      {children}
      {error && (
        <span className="mt-1.5 block text-xs text-red-600 dark:text-red-300">{error}</span>
      )}
      {!error && success && (
        <span className="mt-1.5 flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-300">
          <Check className="h-3 w-3" />
          {success}
        </span>
      )}
      {!error && !success && helper && (
        <span className="mt-1.5 block text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
          {helper}
        </span>
      )}
    </label>
  );
}

export function TextInputShell({
  leading,
  trailing,
  prefix,
  suffix,
  children,
  state = 'default',
  className,
}: {
  leading?: ReactNode;
  trailing?: ReactNode;
  prefix?: ReactNode;
  suffix?: ReactNode;
  children: ReactNode;
  state?: 'default' | 'success' | 'error';
  className?: string;
}) {
  const stateClass = {
    default:
      'focus-within:border-ui-green focus-within:ring-ui-green/15 dark:focus-within:ring-ui-green/25',
    success:
      'border-emerald-400 ring-2 ring-emerald-100 dark:border-emerald-500/60 dark:ring-emerald-500/20',
    error: 'border-red-400 ring-2 ring-red-100 dark:border-red-500/60 dark:ring-red-500/20',
  };

  return (
    <div
      className={cn(
        'flex min-h-10 items-center overflow-hidden rounded-btn border border-gray-200 bg-white text-sm shadow-sm transition focus-within:ring-2 dark:border-ui-dark-border dark:bg-ui-dark-surface',
        stateClass[state],
        className
      )}
    >
      {prefix && (
        <span className="inline-flex h-10 items-center border-r border-gray-200 bg-gray-50 px-3 text-gray-500 dark:border-ui-dark-border dark:bg-ui-dark-surface-2 dark:text-ui-dark-text-muted">
          {prefix}
        </span>
      )}
      {leading && (
        <span className="ml-3 text-gray-400 dark:text-ui-dark-text-muted">{leading}</span>
      )}
      <div className="min-w-0 flex-1 [&_input]:h-10 [&_input]:w-full [&_input]:border-0 [&_input]:bg-transparent [&_input]:px-3 [&_input]:text-sm [&_input]:shadow-none [&_input]:outline-none [&_input]:ring-0 [&_input]:focus:ring-0">
        {children}
      </div>
      {trailing && (
        <span className="mr-3 text-gray-400 dark:text-ui-dark-text-muted">{trailing}</span>
      )}
      {suffix && (
        <span className="inline-flex h-10 items-center border-l border-gray-200 bg-gray-50 px-3 text-gray-500 dark:border-ui-dark-border dark:bg-ui-dark-surface-2 dark:text-ui-dark-text-muted">
          {suffix}
        </span>
      )}
    </div>
  );
}

export function TextareaComposer({
  value,
  onChange,
  count,
  max = 1024,
  className,
}: {
  value: string;
  onChange?: (value: string) => void;
  count?: number;
  max?: number;
  className?: string;
}) {
  const currentCount = count ?? value.length;

  return (
    <div
      className={cn(
        'overflow-hidden rounded-btn border border-gray-200 bg-white transition focus-within:border-ui-green focus-within:ring-2 focus-within:ring-ui-green/15 dark:border-ui-dark-border dark:bg-ui-dark-surface dark:focus-within:ring-ui-green/25',
        className
      )}
    >
      <textarea
        rows={4}
        value={value}
        onChange={(event) => onChange?.(event.target.value)}
        className="w-full resize-none border-0 bg-transparent p-3 text-sm text-ui-text shadow-none outline-none ring-0 focus:ring-0 dark:text-ui-dark-text"
      />
      <div className="flex items-center justify-between border-t border-gray-100 px-2.5 py-1.5 dark:border-ui-dark-border">
        <div className="flex items-center gap-0.5">
          {['B', 'I', 'S'].map((item) => (
            <button
              key={item}
              type="button"
              className="flex h-7 w-7 items-center justify-center rounded-md text-xs text-gray-500 hover:bg-gray-100 dark:text-ui-dark-text-muted dark:hover:bg-ui-dark-surface-2"
            >
              <span
                className={cn(
                  item === 'B' && 'font-bold',
                  item === 'I' && 'italic',
                  item === 'S' && 'line-through'
                )}
              >
                {item}
              </span>
            </button>
          ))}
          <div className="mx-1 h-4 w-px bg-gray-200 dark:bg-ui-dark-border" />
          <button
            type="button"
            className="flex h-7 items-center gap-1 rounded-md px-2 text-[11px] font-medium text-gray-600 hover:bg-gray-100 dark:text-ui-dark-text-muted dark:hover:bg-ui-dark-surface-2"
          >
            <PlusGlyph />
            Variable
          </button>
        </div>
        <span className="text-[10px] tabular-nums text-ui-text-muted dark:text-ui-dark-text-muted">
          {currentCount} / {max}
        </span>
      </div>
    </div>
  );
}

export function CheckboxField({
  label,
  description,
  className,
  ...props
}: InputHTMLAttributes<HTMLInputElement> & { label: ReactNode; description?: ReactNode }) {
  return (
    <label
      className={cn(
        'flex cursor-pointer items-start gap-2 text-sm text-ui-text dark:text-ui-dark-text',
        props.disabled && 'cursor-not-allowed opacity-50',
        className
      )}
    >
      <input
        type="checkbox"
        className="mt-0.5 h-4 w-4 rounded border-gray-300 text-ui-green focus:ring-ui-green/25"
        {...props}
      />
      <span>
        <span className="block font-medium">{label}</span>
        {description && (
          <span className="mt-0.5 block text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
            {description}
          </span>
        )}
      </span>
    </label>
  );
}

export function RadioField({
  label,
  description,
  className,
  ...props
}: InputHTMLAttributes<HTMLInputElement> & { label: ReactNode; description?: ReactNode }) {
  return (
    <label
      className={cn(
        'flex cursor-pointer items-start gap-2 text-sm text-ui-text dark:text-ui-dark-text',
        props.disabled && 'cursor-not-allowed opacity-50',
        className
      )}
    >
      <input
        type="radio"
        className="mt-0.5 h-4 w-4 border-gray-300 text-ui-green focus:ring-ui-green/25"
        {...props}
      />
      <span>
        <span className="block font-medium">{label}</span>
        {description && (
          <span className="mt-0.5 block text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
            {description}
          </span>
        )}
      </span>
    </label>
  );
}

export function RadioCard({
  title,
  description,
  children,
  className,
  ...props
}: InputHTMLAttributes<HTMLInputElement> & {
  title: ReactNode;
  description?: ReactNode;
  children?: ReactNode;
}) {
  return (
    <label
      className={cn(
        'block cursor-pointer rounded-btn border border-gray-200 bg-white p-3 transition has-[:checked]:border-ui-green has-[:checked]:bg-ui-green-soft/40 has-[:checked]:ring-1 has-[:checked]:ring-ui-green dark:border-ui-dark-border dark:bg-ui-dark-surface dark:has-[:checked]:bg-ui-dark-green-soft',
        className
      )}
    >
      <div className="flex items-start gap-2">
        <input
          type="radio"
          className="mt-0.5 h-4 w-4 border-gray-300 text-ui-green focus:ring-ui-green/25"
          {...props}
        />
        <span className="min-w-0">
          <span className="block text-sm font-semibold text-ui-text dark:text-ui-dark-text">
            {title}
          </span>
          {description && (
            <span className="mt-0.5 block text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
              {description}
            </span>
          )}
          {children && <span className="mt-2 block">{children}</span>}
        </span>
      </div>
    </label>
  );
}

export function Breadcrumb({
  items,
  className,
}: {
  items: Array<{ label: ReactNode; href?: string }>;
  className?: string;
}) {
  return (
    <nav
      className={cn(
        'flex items-center gap-2 text-sm text-ui-text-muted dark:text-ui-dark-text-muted',
        className
      )}
      aria-label="Breadcrumb"
    >
      {items.map((item, index) => (
        <Fragment key={index}>
          {index > 0 && <ChevronRight className="h-4 w-4" />}
          {item.href ? (
            <a
              href={item.href}
              className="font-medium hover:text-ui-green-dark dark:hover:text-emerald-300"
            >
              {item.label}
            </a>
          ) : (
            <span className="font-semibold text-ui-text dark:text-ui-dark-text">
              {item.label}
            </span>
          )}
        </Fragment>
      ))}
    </nav>
  );
}

export function Kbd({ className, ...props }: HTMLAttributes<HTMLElement>) {
  return (
    <kbd
      className={cn(
        'inline-flex min-w-5 items-center justify-center rounded border border-gray-200 bg-gray-50 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-ui-text-muted shadow-sm dark:border-ui-dark-border dark:bg-ui-dark-surface-2 dark:text-ui-dark-text-muted',
        className
      )}
      {...props}
    />
  );
}

export function Stepper({
  steps,
  current,
  className,
}: {
  steps: Array<{
    title: ReactNode;
    description?: ReactNode;
    status?: 'complete' | 'current' | 'pending' | 'error';
  }>;
  current?: number;
  className?: string;
}) {
  return (
    <ol className={cn('space-y-3', className)}>
      {steps.map((step, index) => {
        const inferred =
          index < (current || 0) ? 'complete' : index === current ? 'current' : 'pending';
        const status = step.status || inferred;
        const complete = status === 'complete';
        const active = status === 'current';
        const error = status === 'error';

        return (
          <li key={index} className="flex gap-3">
            <span
              className={cn(
                'mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full border text-xs font-bold',
                complete && 'border-ui-green bg-ui-green text-ui-ink',
                active &&
                  'border-ui-green bg-ui-green-soft text-ui-green-dark dark:bg-ui-dark-green-soft dark:text-emerald-200',
                error &&
                  'border-red-300 bg-red-50 text-red-600 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-200',
                status === 'pending' &&
                  'border-gray-200 bg-white text-ui-text-muted dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text-muted'
              )}
            >
              {complete ? <Check className="h-4 w-4" /> : index + 1}
            </span>
            <span className="min-w-0">
              <span className="block text-sm font-semibold text-ui-text dark:text-ui-dark-text">
                {step.title}
              </span>
              {step.description && (
                <span className="mt-0.5 block text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
                  {step.description}
                </span>
              )}
            </span>
          </li>
        );
      })}
    </ol>
  );
}

export function HorizontalStepper({
  steps,
  current = 0,
  className,
}: {
  steps: ReactNode[];
  current?: number;
  className?: string;
}) {
  return (
    <ol className={cn('flex items-center overflow-x-auto', className)}>
      {steps.map((step, index) => {
        const complete = index < current;
        const active = index === current;

        return (
          <li key={index} className="flex min-w-fit flex-1 items-center">
            <div className="flex shrink-0 items-center gap-2">
              <span
                className={cn(
                  'flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold',
                  complete && 'bg-ui-green text-white',
                  active && 'bg-ui-green text-white ring-4 ring-ui-green/20',
                  !complete &&
                    !active &&
                    'bg-white text-ui-text-muted ring-1 ring-gray-200 dark:bg-ui-dark-surface dark:text-ui-dark-text-muted dark:ring-ui-dark-border'
                )}
              >
                {complete ? <Check className="h-4 w-4" /> : index + 1}
              </span>
              <span
                className={cn(
                  'text-xs font-medium',
                  !complete && !active && 'text-ui-text-muted dark:text-ui-dark-text-muted'
                )}
              >
                {step}
              </span>
            </div>
            {index < steps.length - 1 && (
              <span
                className={cn(
                  'mx-3 h-0.5 min-w-8 flex-1',
                  index < current ? 'bg-ui-green' : 'bg-gray-200 dark:bg-ui-dark-border'
                )}
              />
            )}
          </li>
        );
      })}
    </ol>
  );
}

export function PaginationControl({
  page,
  pages,
  onPageChange,
  total,
  perPage,
  className,
}: {
  page: number;
  pages: number;
  onPageChange?: (page: number) => void;
  total?: number;
  perPage?: number;
  className?: string;
}) {
  const visiblePages = useMemo(() => {
    const base = Array.from(
      { length: Math.min(pages, 3) },
      (_, index) => Math.max(1, Math.min(pages - 2, page - 1)) + index
    );
    return Array.from(new Set([1, ...base, pages])).filter((item) => item >= 1 && item <= pages);
  }, [page, pages]);
  const start = total && perPage ? (page - 1) * perPage + 1 : null;
  const end = total && perPage ? Math.min(page * perPage, total) : null;

  return (
    <div
      className={cn(
        'flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between',
        className
      )}
    >
      {start && end && (
        <div className="text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
          Showing{' '}
          <span className="font-medium text-ui-text dark:text-ui-dark-text">
            {start}-{end}
          </span>{' '}
          of{' '}
          <span className="font-medium text-ui-text dark:text-ui-dark-text">{total}</span>
        </div>
      )}
      <div className="flex items-center gap-1">
        <IconButton
          size="sm"
          variant="outline"
          disabled={page <= 1}
          onClick={() => onPageChange?.(page - 1)}
          aria-label="Previous page"
        >
          <ChevronLeft className="h-4 w-4" />
        </IconButton>
        {visiblePages.map((item, index) => (
          <Fragment key={item}>
            {index > 0 && item - visiblePages[index - 1] > 1 && (
              <span className="px-1 text-gray-400">...</span>
            )}
            <button
              type="button"
              onClick={() => onPageChange?.(item)}
              className={cn(
                'h-8 min-w-8 rounded-md px-2 text-xs transition',
                item === page
                  ? 'bg-ui-text font-semibold text-white dark:bg-ui-dark-text dark:text-ui-dark-bg'
                  : 'font-medium text-ui-text ring-1 ring-inset ring-gray-200 hover:bg-gray-50 dark:text-ui-dark-text dark:ring-ui-dark-border dark:hover:bg-ui-dark-surface-2'
              )}
            >
              {item}
            </button>
          </Fragment>
        ))}
        <IconButton
          size="sm"
          variant="outline"
          disabled={page >= pages}
          onClick={() => onPageChange?.(page + 1)}
          aria-label="Next page"
        >
          <ChevronRight className="h-4 w-4" />
        </IconButton>
      </div>
    </div>
  );
}

export function CircularProgress({
  value,
  size = 96,
  label,
  className,
}: {
  value: number;
  size?: number;
  label?: ReactNode;
  className?: string;
}) {
  const clamped = Math.max(0, Math.min(value, 100));

  return (
    <div
      className={cn('relative inline-flex items-center justify-center', className)}
      style={{ width: size, height: size }}
    >
      <svg viewBox="0 0 36 36" className="-rotate-90" width={size} height={size}>
        <circle
          cx="18"
          cy="18"
          r="15.9"
          fill="none"
          stroke="currentColor"
          strokeWidth="3"
          className="text-gray-200 dark:text-ui-dark-surface-2"
        />
        <circle
          cx="18"
          cy="18"
          r="15.9"
          fill="none"
          stroke="currentColor"
          strokeWidth="3"
          strokeLinecap="round"
          strokeDasharray={`${clamped} 100`}
          className="text-ui-green"
        />
      </svg>
      <div className="absolute text-center">
        <div className="text-sm font-bold text-ui-text dark:text-ui-dark-text">
          {clamped}%
        </div>
        {label && (
          <div className="text-[10px] text-ui-text-muted dark:text-ui-dark-text-muted">
            {label}
          </div>
        )}
      </div>
    </div>
  );
}

const AsyncModal = lazy(() => import('./Overlays').then((module) => ({ default: module.Modal })));
const AsyncDrawer = lazy(() => import('./Overlays').then((module) => ({ default: module.Drawer })));

function useOverlayReady() {
  const [ready, setReady] = useState(false);
  useEffect(() => {
    setReady(true);
  }, []);
  return ready;
}

function OverlayLoading() {
  return (
    <div
      role="status"
      className="fixed inset-0 z-[250] flex items-center justify-center bg-ui-ink/45 text-sm font-medium text-white"
    >
      Opening panel…
    </div>
  );
}

export function Modal(props: ComponentProps<typeof import('./Overlays').Modal>) {
  const ready = useOverlayReady();
  if (!props.open) return null;
  return ready ? (
    <Suspense fallback={<OverlayLoading />}>
      <AsyncModal {...props} />
    </Suspense>
  ) : (
    <OverlayLoading />
  );
}

export function Drawer(props: ComponentProps<typeof import('./Overlays').Drawer>) {
  const ready = useOverlayReady();
  if (!props.open) return null;
  return ready ? (
    <Suspense fallback={<OverlayLoading />}>
      <AsyncDrawer {...props} />
    </Suspense>
  ) : (
    <OverlayLoading />
  );
}

export function FileDropzone({
  label = 'Upload file',
  description = 'Drag and drop a file here, or click to browse.',
  accept,
  onFile,
  className,
}: {
  label?: ReactNode;
  description?: ReactNode;
  accept?: string;
  onFile?: (file: File) => void;
  className?: string;
}) {
  const inputRef = useRef<HTMLInputElement | null>(null);

  return (
    <button
      type="button"
      onClick={() => inputRef.current?.click()}
      onDragOver={(event) => event.preventDefault()}
      onDrop={(event) => {
        event.preventDefault();
        const file = event.dataTransfer.files?.[0];
        if (file) {
          onFile?.(file);
        }
      }}
      className={cn(
        'flex w-full flex-col items-center justify-center rounded-card border border-dashed border-gray-300 bg-gray-50/70 px-5 py-8 text-center transition hover:border-ui-green hover:bg-ui-green-soft/60 dark:border-ui-dark-border dark:bg-ui-dark-surface-2/50 dark:hover:bg-ui-dark-green-soft',
        className
      )}
    >
      <input
        ref={inputRef}
        type="file"
        accept={accept}
        className="hidden"
        onChange={(event) => {
          const file = event.target.files?.[0];
          if (file) {
            onFile?.(file);
          }
        }}
      />
      <span className="flex h-11 w-11 items-center justify-center rounded-full bg-white text-ui-green-dark shadow-sm dark:bg-ui-dark-surface dark:text-emerald-200">
        <CloudUpload className="h-5 w-5" />
      </span>
      <span className="mt-3 text-sm font-semibold text-ui-text dark:text-ui-dark-text">
        {label}
      </span>
      <span className="mt-1 max-w-sm text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
        {description}
      </span>
    </button>
  );
}

export function UploadedFileRow({
  name,
  meta,
  progress,
  status = 'uploading',
  onRemove,
  className,
}: {
  name: ReactNode;
  meta?: ReactNode;
  progress?: number;
  status?: 'uploading' | 'done' | 'error';
  onRemove?: () => void;
  className?: string;
}) {
  const done = status === 'done';
  const error = status === 'error';

  return (
    <div
      className={cn(
        'flex items-center gap-3 rounded-btn p-3',
        done
          ? 'bg-emerald-50 ring-1 ring-emerald-100 dark:bg-emerald-500/12 dark:ring-emerald-500/20'
          : error
            ? 'bg-red-50 ring-1 ring-red-100 dark:bg-red-500/12 dark:ring-red-500/20'
            : 'bg-gray-50 dark:bg-ui-dark-surface-2',
        className
      )}
    >
      <div
        className={cn(
          'flex h-10 w-10 shrink-0 items-center justify-center rounded-md',
          done
            ? 'bg-white text-emerald-500 dark:bg-ui-dark-surface'
            : error
              ? 'bg-white text-red-500 dark:bg-ui-dark-surface'
              : 'bg-blue-50 text-blue-600 dark:bg-blue-500/12 dark:text-blue-200'
        )}
      >
        {done ? <Check className="h-5 w-5" /> : <FileText className="h-5 w-5" />}
      </div>
      <div className="min-w-0 flex-1">
        <div className="flex items-center justify-between gap-3">
          <div className="truncate text-sm font-medium text-ui-text dark:text-ui-dark-text">
            {name}
          </div>
          {onRemove && (
            <button
              type="button"
              onClick={onRemove}
              className="text-gray-400 transition hover:text-red-600 dark:hover:text-red-300"
              aria-label="Remove file"
            >
              <X className="h-4 w-4" />
            </button>
          )}
        </div>
        {typeof progress === 'number' && !done && (
          <div className="mt-1 h-1 overflow-hidden rounded-full bg-gray-200 dark:bg-ui-dark-border">
            <div
              className="h-full rounded-full bg-ui-green"
              style={{ width: `${Math.max(0, Math.min(progress, 100))}%` }}
            />
          </div>
        )}
        {meta && (
          <div className="mt-1 text-[11px] text-ui-text-muted dark:text-ui-dark-text-muted">
            {meta}
          </div>
        )}
      </div>
    </div>
  );
}

export function OtpInput({
  value,
  onChange,
  length = 6,
  className,
}: {
  value: string;
  onChange: (value: string) => void;
  length?: number;
  className?: string;
}) {
  const chars = value.padEnd(length).slice(0, length).split('');

  return (
    <div className={cn('flex gap-2', className)}>
      {chars.map((char, index) => (
        <input
          key={index}
          value={char.trim()}
          onChange={(event) => {
            const next = value.split('');
            next[index] = event.target.value.replace(/\D/g, '').slice(-1);
            onChange(next.join('').slice(0, length));
          }}
          inputMode="numeric"
          className="h-11 w-10 rounded-btn border border-gray-200 bg-white text-center text-base font-bold text-ui-text shadow-sm focus:border-ui-green focus:outline-none focus:ring-2 focus:ring-ui-green/20 dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text"
          maxLength={1}
        />
      ))}
    </div>
  );
}

export function SliderField({
  label,
  value,
  min = 0,
  max = 100,
  suffix,
  onChange,
  className,
}: {
  label: ReactNode;
  value: number;
  min?: number;
  max?: number;
  suffix?: string;
  onChange?: (value: number) => void;
  className?: string;
}) {
  return (
    <label className={cn('block space-y-2', className)}>
      <span className="flex items-center justify-between gap-3 text-sm font-medium text-ui-text dark:text-ui-dark-text">
        {label}
        <span className="text-ui-text-muted dark:text-ui-dark-text-muted">
          {value}
          {suffix}
        </span>
      </span>
      <input
        type="range"
        min={min}
        max={max}
        value={value}
        onChange={(event) => onChange?.(Number(event.target.value))}
        className="h-2 w-full cursor-pointer appearance-none rounded-full bg-gray-200 accent-ui-green dark:bg-ui-dark-surface-2"
      />
    </label>
  );
}

export function PhonePreview({
  title = 'WhatsApp',
  subtitle = 'Business account',
  children,
  className,
}: {
  title?: ReactNode;
  subtitle?: ReactNode;
  children: ReactNode;
  className?: string;
}) {
  return (
    <div
      className={cn(
        'mx-auto w-full max-w-[320px] rounded-[28px] border border-gray-200 bg-ui-ink p-2 shadow-card-lg dark:border-ui-dark-border',
        className
      )}
    >
      <div className="overflow-hidden rounded-[22px] bg-[#e6ddd4] dark:bg-[#111827]">
        <div className="flex items-center gap-3 bg-ui-green-darker px-4 py-3 text-white">
          <Avatar name={String(title)} size="sm" className="bg-white text-ui-green-darker" />
          <div className="min-w-0">
            <p className="truncate text-sm font-semibold">{title}</p>
            <p className="truncate text-xs text-white/75">{subtitle}</p>
          </div>
        </div>
        <div className="min-h-[420px] space-y-3 p-3">{children}</div>
      </div>
    </div>
  );
}

export function ChatBubble({
  children,
  inbound = false,
  time,
  status,
  className,
}: {
  children: ReactNode;
  inbound?: boolean;
  time?: ReactNode;
  status?: 'sent' | 'delivered' | 'read' | 'pending';
  className?: string;
}) {
  return (
    <div className={cn('flex', inbound ? 'justify-start' : 'justify-end')}>
      <div
        className={cn(
          'max-w-[82%] rounded-lg px-3 py-2 text-sm shadow-sm',
          inbound
            ? 'bg-white text-ui-text dark:bg-ui-dark-surface dark:text-ui-dark-text'
            : 'bg-[#dcf8c6] text-ui-text dark:bg-ui-dark-green-soft dark:text-ui-dark-text',
          className
        )}
      >
        <div>{children}</div>
        {(time || status) && (
          <div className="mt-1 flex items-center justify-end gap-1 text-[10px] text-ui-text-muted dark:text-ui-dark-text-muted">
            {time}
            {status === 'pending' && <Loader2 className="h-3 w-3 animate-spin" />}
            {status === 'sent' && <Check className="h-3 w-3" />}
            {status === 'delivered' && <CheckCircle2 className="h-3 w-3" />}
            {status === 'read' && <CheckCircle2 className="h-3 w-3 text-sky-500" />}
          </div>
        )}
      </div>
    </div>
  );
}

export function TypingBubble({ className }: { className?: string }) {
  return (
    <div className={cn('flex justify-start', className)}>
      <div className="flex items-center gap-1 rounded-2xl bg-white px-3 py-2 shadow-sm dark:bg-ui-dark-surface">
        {[0, 150, 300].map((delay) => (
          <span
            key={delay}
            className="h-1.5 w-1.5 animate-pulse rounded-full bg-gray-400"
            style={{ animationDelay: `${delay}ms` }}
          />
        ))}
      </div>
    </div>
  );
}

export function Tooltip({
  content,
  side = 'top',
  children,
  className,
}: {
  content: ReactNode;
  side?: 'top' | 'right' | 'bottom' | 'left';
  children: ReactNode;
  className?: string;
}) {
  const positions = {
    top: 'bottom-full left-1/2 mb-2 -translate-x-1/2',
    right: 'left-full top-1/2 ml-2 -translate-y-1/2',
    bottom: 'left-1/2 top-full mt-2 -translate-x-1/2',
    left: 'right-full top-1/2 mr-2 -translate-y-1/2',
  };

  return (
    <span className={cn('group relative inline-flex', className)}>
      {children}
      <span
        className={cn(
          'pointer-events-none absolute z-20 whitespace-nowrap rounded bg-ui-text px-2 py-1 text-[11px] font-medium text-white opacity-0 shadow-pop transition group-hover:opacity-100 dark:bg-ui-dark-text dark:text-ui-dark-bg',
          positions[side]
        )}
      >
        {content}
      </span>
    </span>
  );
}

export function DropdownMenu({
  trigger,
  items,
  align = 'left',
  className,
}: {
  trigger: ReactNode;
  items: Array<{
    label: ReactNode;
    icon?: ReactNode;
    danger?: boolean;
    onClick?: () => void;
    separatorBefore?: boolean;
  }>;
  align?: 'left' | 'right';
  className?: string;
}) {
  return (
    <div className={cn('group relative inline-block', className)}>
      {trigger}
      <div
        className={cn(
          'invisible absolute top-11 z-20 w-52 rounded-btn bg-white py-1 opacity-0 shadow-pop ring-1 ring-gray-100 transition group-hover:visible group-hover:opacity-100 dark:bg-ui-dark-surface dark:ring-ui-dark-border',
          align === 'right' ? 'right-0' : 'left-0'
        )}
      >
        {items.map((item, index) => (
          <Fragment key={index}>
            {item.separatorBefore && (
              <div className="my-1 h-px bg-gray-100 dark:bg-ui-dark-border" />
            )}
            <button
              type="button"
              onClick={item.onClick}
              className={cn(
                'flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition',
                item.danger
                  ? 'text-red-600 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-500/10'
                  : 'text-ui-text hover:bg-gray-50 dark:text-ui-dark-text dark:hover:bg-ui-dark-surface-2'
              )}
            >
              {item.icon && (
                <span className="text-gray-500 dark:text-ui-dark-text-muted">{item.icon}</span>
              )}
              {item.label}
            </button>
          </Fragment>
        ))}
      </div>
    </div>
  );
}

export function DefaultActionMenu({ className }: { className?: string }) {
  return (
    <DropdownMenu
      className={className}
      trigger={
        <IconButton variant="outline" size="sm" aria-label="More actions">
          <MoreHorizontal className="h-4 w-4" />
        </IconButton>
      }
      items={[
        { label: 'Edit', icon: <Edit3 className="h-3.5 w-3.5" /> },
        { label: 'Duplicate', icon: <Copy className="h-3.5 w-3.5" /> },
        { label: 'View', icon: <Eye className="h-3.5 w-3.5" /> },
        { label: 'Export', icon: <Download className="h-3.5 w-3.5" /> },
        {
          label: 'Delete',
          icon: <Trash2 className="h-3.5 w-3.5" />,
          danger: true,
          separatorBefore: true,
        },
      ]}
    />
  );
}

export function EmptyPanel({
  icon,
  title,
  description,
  action,
  className,
}: {
  icon?: ReactNode;
  title: ReactNode;
  description?: ReactNode;
  action?: ReactNode;
  className?: string;
}) {
  return (
    <div
      className={cn(
        'flex min-h-[220px] flex-col items-center justify-center rounded-card border border-dashed border-gray-200 bg-white px-6 py-10 text-center dark:border-ui-dark-border dark:bg-ui-dark-surface',
        className
      )}
    >
      <div className="flex h-12 w-12 items-center justify-center rounded-full bg-ui-green-soft text-ui-green-dark dark:bg-ui-dark-green-soft dark:text-emerald-200">
        {icon || <Info className="h-5 w-5" />}
      </div>
      <h3 className="mt-4 text-base font-semibold text-ui-text dark:text-ui-dark-text">
        {title}
      </h3>
      {description && (
        <p className="mt-1 max-w-md text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
          {description}
        </p>
      )}
      {action && <div className="mt-5">{action}</div>}
    </div>
  );
}

export function ActionBar({
  saving,
  onCancel,
  onSave,
  saveLabel = 'Save changes',
  cancelLabel = 'Cancel',
  className,
}: {
  saving?: boolean;
  onCancel?: () => void;
  onSave?: () => void;
  saveLabel?: string;
  cancelLabel?: string;
  className?: string;
}) {
  return (
    <div
      className={cn(
        'sticky bottom-0 z-10 flex items-center justify-end gap-2 border-t border-gray-100 bg-white/90 px-4 py-3 backdrop-blur dark:border-ui-dark-border dark:bg-ui-dark-surface/90',
        className
      )}
    >
      <Button type="button" variant="secondary" onClick={onCancel}>
        {cancelLabel}
      </Button>
      <Button type="button" onClick={onSave} disabled={saving}>
        {saving && <Loader2 className="h-4 w-4 animate-spin" />}
        {saveLabel}
      </Button>
    </div>
  );
}
