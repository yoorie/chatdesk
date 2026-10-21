import { cn } from '@/lib/utils';
import * as React from 'react';

export interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {}

const Input = React.forwardRef<HTMLInputElement, InputProps>(
  ({ className, type, ...props }, ref) => {
    return (
      <input
        type={type}
        className={cn(
          'flex h-10 w-full rounded-btn border border-gray-200 bg-white px-3 py-2 text-sm text-ui-text shadow-sm',
          'ring-offset-white file:border-0 file:bg-transparent file:text-sm file:font-medium',
          'placeholder:text-gray-400',
          'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ui-green/20 focus-visible:border-ui-green focus-visible:ring-offset-0',
          'disabled:cursor-not-allowed disabled:opacity-50',
          'dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text dark:ring-offset-ui-dark-bg',
          'dark:placeholder:text-ui-dark-text-muted dark:focus-visible:ring-ui-green/30',
          className
        )}
        ref={ref}
        {...props}
      />
    );
  }
);

Input.displayName = 'Input';

export { Input };
