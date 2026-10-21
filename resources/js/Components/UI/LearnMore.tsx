import { cn } from '@/lib/utils';
import { ReactNode } from 'react';

/**
 * Small "Learn more" toggle for optional detail, so pages can stay short.
 */
export function LearnMore({
  children,
  label = 'Learn more',
  className,
}: {
  children: ReactNode;
  label?: string;
  className?: string;
}) {
  return (
    <details
      className={cn(
        'group text-xs text-ui-text-muted dark:text-ui-dark-text-muted',
        className
      )}
    >
      <summary className="inline-flex cursor-pointer list-none items-center gap-1 font-medium text-ui-green-dark hover:underline dark:text-emerald-300 [&::-webkit-details-marker]:hidden">
        {label}
        <span aria-hidden className="transition group-open:rotate-90">
          ›
        </span>
      </summary>
      <div className="mt-2 space-y-1 leading-5">{children}</div>
    </details>
  );
}

export default LearnMore;
