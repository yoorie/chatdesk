import { cn } from '@/lib/utils';
import { HTMLAttributes, ReactNode } from 'react';

interface CardProps extends HTMLAttributes<HTMLDivElement> {
  children: ReactNode;
}

export function Card({ className, children, ...props }: CardProps) {
  return (
    <div
      className={cn(
        'surface rounded-card border border-gray-200/80 bg-white shadow-sm dark:border-slate-700/80 dark:bg-slate-800 dark:shadow-none',
        className
      )}
      {...props}
    >
      {children}
    </div>
  );
}

export function CardHeader({ className, children, ...props }: CardProps) {
  return (
    <div
      className={cn('border-b border-gray-100 px-4 py-3 dark:border-ui-dark-border', className)}
      {...props}
    >
      {children}
    </div>
  );
}

export function CardTitle({ className, children, ...props }: CardProps) {
  return (
    <h3
      className={cn(
        'text-base font-semibold text-ui-text dark:text-ui-dark-text',
        className
      )}
      {...props}
    >
      {children}
    </h3>
  );
}

export function CardDescription({ className, children, ...props }: CardProps) {
  return (
    <p
      className={cn(
        'mt-1 text-sm text-ui-text-muted dark:text-ui-dark-text-muted',
        className
      )}
      {...props}
    >
      {children}
    </p>
  );
}

export function CardContent({ className, children, ...props }: CardProps) {
  return (
    <div className={cn('px-4 py-4', className)} {...props}>
      {children}
    </div>
  );
}
