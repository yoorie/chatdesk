import { cn } from '@/lib/utils';
import { HTMLAttributes } from 'react';

interface BadgeProps extends HTMLAttributes<HTMLSpanElement> {
  variant?: 'default' | 'success' | 'warning' | 'danger' | 'info' | 'secondary' | 'outline';
}

export function Badge({ className, variant = 'default', children, ...props }: BadgeProps) {
  const variants = {
    default:
      'bg-gray-100 text-gray-800 ring-1 ring-inset ring-gray-200 dark:bg-slate-700/70 dark:text-slate-100 dark:ring-slate-500/30',
    success:
      'bg-emerald-100 text-emerald-800 ring-1 ring-inset ring-emerald-200 dark:bg-emerald-500/20 dark:text-emerald-100 dark:ring-emerald-400/25',
    warning:
      'bg-amber-100 text-amber-800 ring-1 ring-inset ring-amber-200 dark:bg-amber-500/20 dark:text-amber-100 dark:ring-amber-400/25',
    danger:
      'bg-red-100 text-red-800 ring-1 ring-inset ring-red-200 dark:bg-red-500/20 dark:text-red-100 dark:ring-red-400/25',
    info: 'bg-sky-100 text-sky-800 ring-1 ring-inset ring-sky-200 dark:bg-sky-500/20 dark:text-sky-100 dark:ring-sky-400/25',
    secondary:
      'bg-white text-gray-700 ring-1 ring-inset ring-gray-200 dark:bg-ui-dark-surface-2 dark:text-ui-dark-text dark:ring-ui-dark-border',
    outline:
      'bg-transparent text-gray-700 ring-1 ring-inset ring-gray-300 dark:text-ui-dark-text dark:ring-ui-dark-border',
  };

  return (
    <span
      className={cn(
        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
        variants[variant],
        className
      )}
      {...props}
    >
      {children}
    </span>
  );
}
