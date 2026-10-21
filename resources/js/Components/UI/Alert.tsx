import { cn } from '@/lib/utils';
import { AlertCircle, CheckCircle, Info, X, XCircle } from 'lucide-react';
import { ReactNode } from 'react';

interface AlertProps {
  variant?: 'success' | 'error' | 'warning' | 'info';
  title?: string;
  children: ReactNode;
  onClose?: () => void;
  className?: string;
}

export function Alert({ variant = 'info', title, children, onClose, className }: AlertProps) {
  const variants = {
    success: {
      container:
        'bg-[#ecfdf5] text-emerald-800 ring-emerald-200 dark:bg-[#052e22] dark:text-emerald-100 dark:ring-emerald-500/30',
      icon: CheckCircle,
    },
    error: {
      container:
        'bg-[#fef2f2] text-red-800 ring-red-200 dark:bg-[#3b0a0a] dark:text-red-100 dark:ring-red-500/30',
      icon: XCircle,
    },
    warning: {
      container:
        'bg-[#fffbeb] text-amber-900 ring-amber-200 dark:bg-[#2a1f0a] dark:text-amber-100 dark:ring-amber-500/35',
      icon: AlertCircle,
    },
    info: {
      container:
        'bg-[#eff6ff] text-blue-800 ring-blue-200 dark:bg-[#0b2447] dark:text-blue-100 dark:ring-blue-500/30',
      icon: Info,
    },
  };

  const config = variants[variant];
  const Icon = config.icon;

  return (
    <div
      className={cn('flex items-start gap-3 rounded-card p-4 ring-1', config.container, className)}
      role="alert"
    >
      <Icon className="h-5 w-5 flex-shrink-0 mt-0.5" />
      <div className="flex-1 min-w-0">
        {title && <p className="font-medium text-sm mb-1">{title}</p>}
        <div className="text-sm">{children}</div>
      </div>
      {onClose && (
        <button
          onClick={onClose}
          className="flex-shrink-0 text-current opacity-70 hover:opacity-100 transition-opacity"
          aria-label="Close"
        >
          <X className="h-4 w-4" />
        </button>
      )}
    </div>
  );
}
