import { useToast } from '@/hooks/useToast';
import { Link } from '@inertiajs/react';
import { AlertCircle, CheckCircle, Info, X, XCircle } from 'lucide-react';
import { useEffect } from 'react';

export function Toaster() {
  const { toasts, removeToast } = useToast();

  if (toasts.length === 0) return null;

  return (
    <div className="pointer-events-none fixed bottom-6 right-6 z-[300] flex max-w-md flex-col gap-2">
      {toasts.map((toast) => (
        <ToastItem key={toast.id} toast={toast} onClose={() => removeToast(toast.id)} />
      ))}
    </div>
  );
}

function ToastItem({ toast, onClose }: { toast: any; onClose: () => void }) {
  useEffect(() => {
    if (toast.duration && toast.duration > 0) {
      const timer = setTimeout(onClose, toast.duration);
      return () => clearTimeout(timer);
    }
  }, [toast.duration, onClose]);

  const variant = toast.variant || 'info';
  const icons: Record<string, typeof CheckCircle> = {
    success: CheckCircle,
    error: XCircle,
    warning: AlertCircle,
    info: Info,
  };

  const colors: Record<string, string> = {
    success:
      'border-l-emerald-300 text-emerald-700 dark:border-l-emerald-400 dark:text-emerald-200',
    error: 'border-l-red-300 text-red-700 dark:border-l-red-400 dark:text-red-200',
    warning: 'border-l-amber-300 text-amber-700 dark:border-l-amber-400 dark:text-amber-200',
    info: 'border-l-blue-300 text-blue-700 dark:border-l-blue-400 dark:text-blue-200',
  };

  const Icon = icons[variant] || Info;
  const colorClass = colors[variant] || colors.info;

  return (
    <div
      className={`${colorClass} pointer-events-auto flex min-w-[280px] max-w-sm items-start gap-3 rounded-card border-l-4 bg-white px-4 py-3 shadow-pop animate-in slide-in-from-bottom-2 dark:bg-ui-dark-surface dark:shadow-none`}
      role="alert"
    >
      <Icon className="h-5 w-5 flex-shrink-0 mt-0.5" />
      <div className="flex-1 min-w-0">
        <p className="text-sm font-semibold text-ui-text dark:text-ui-dark-text">
          {toast.title}
        </p>
        {toast.description && (
          <p className="mt-0.5 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
            {toast.description}
          </p>
        )}
        {toast.action?.href && (
          <Link
            href={toast.action.href}
            onClick={onClose}
            className="mt-2 inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-ui-text transition hover:bg-gray-200 dark:bg-ui-dark-surface-2 dark:text-ui-dark-text dark:hover:bg-slate-700"
          >
            {toast.action.label}
          </Link>
        )}
      </div>
      <button
        onClick={onClose}
        className="flex-shrink-0 text-current opacity-70 hover:opacity-100 transition-opacity"
        aria-label="Close"
      >
        <X className="h-4 w-4" />
      </button>
    </div>
  );
}
