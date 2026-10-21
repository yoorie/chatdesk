import { cn } from '@/lib/utils';
import * as React from 'react';

export interface SwitchProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'type'> {
  checked?: boolean;
  onCheckedChange?: (checked: boolean) => void;
}

const Switch = React.forwardRef<HTMLInputElement, SwitchProps>(
  ({ className, checked, onCheckedChange, ...props }, ref) => {
    return (
      <label className="relative inline-flex items-center cursor-pointer">
        <input
          type="checkbox"
          className="sr-only peer"
          checked={checked}
          onChange={(e) => onCheckedChange?.(e.target.checked)}
          ref={ref}
          {...props}
        />
        <div
          className={cn(
            "relative h-6 w-11 rounded-full bg-gray-200 peer peer-checked:bg-ui-green peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-ui-green/20 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white dark:border-ui-dark-border dark:bg-ui-dark-surface-2",
            className
          )}
        />
      </label>
    );
  }
);

Switch.displayName = 'Switch';

export { Switch };
