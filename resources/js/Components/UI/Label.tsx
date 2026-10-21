import { cn } from '@/lib/utils';
import * as React from 'react';

export interface LabelProps extends React.LabelHTMLAttributes<HTMLLabelElement> {}

const Label = React.forwardRef<HTMLLabelElement, LabelProps>(({ className, ...props }, ref) => {
  return (
    <label
      ref={ref}
      className={cn(
        'text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70',
        'text-gray-700 dark:text-gray-300',
        className
      )}
      {...props}
    />
  );
});

Label.displayName = 'Label';

export { Label };
