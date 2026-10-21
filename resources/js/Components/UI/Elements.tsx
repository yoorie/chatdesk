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

function DropdownMenu({
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
