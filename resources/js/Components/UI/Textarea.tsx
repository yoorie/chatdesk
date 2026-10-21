import { cn } from '@/lib/utils';
import * as React from 'react';

export interface TextareaProps extends React.TextareaHTMLAttributes<HTMLTextAreaElement> {}

const Textarea = React.forwardRef<HTMLTextAreaElement, TextareaProps>(
  ({ className, ...props }, ref) => {
    return (
      <textarea
        className={cn(
          'flex min-h-[80px] w-full rounded-btn border border-gray-200 bg-white px-3 py-2 text-sm text-ui-text shadow-sm ring-offset-white placeholder:text-gray-400 focus-visible:border-ui-green focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ui-green/20 focus-visible:ring-offset-0 disabled:cursor-not-allowed disabled:opacity-50 dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text dark:ring-offset-ui-dark-bg dark:placeholder:text-ui-dark-text-muted dark:focus-visible:ring-ui-green/30',
          className
        )}
        ref={ref}
        {...props}
      />
    );
  }
);
Textarea.displayName = 'Textarea';

export { Textarea };
