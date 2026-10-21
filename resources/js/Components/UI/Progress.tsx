import { cn } from '@/lib/utils';

interface ProgressProps {
  value: number;
  className?: string;
  max?: number;
  variant?: 'default' | 'success' | 'warning' | 'danger';
}

export function Progress({ value, className, max = 100, variant = 'default' }: ProgressProps) {
  const percentage = Math.min(Math.max((value / max) * 100, 0), 100);

  const colorClasses = {
    default: 'bg-ui-green dark:bg-ui-green',
    success: 'bg-green-600 dark:bg-green-500',
    warning: 'bg-yellow-600 dark:bg-yellow-500',
    danger: 'bg-red-600 dark:bg-red-500',
  };

  return (
    <div
      className={cn(
        'h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-ui-dark-surface-2',
        className
      )}
    >
      <div
        className={cn('h-full rounded-full transition-all duration-300', colorClasses[variant])}
        style={{ width: `${percentage}%` }}
        role="progressbar"
        aria-valuenow={value}
        aria-valuemin={0}
        aria-valuemax={max}
      />
    </div>
  );
}
