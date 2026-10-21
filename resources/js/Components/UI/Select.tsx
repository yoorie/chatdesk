import { cn } from '@/lib/utils';
import { ChevronDown } from 'lucide-react';
import * as React from 'react';

interface SelectContextValue {
  value: string;
  onValueChange: (value: string) => void;
  open: boolean;
  setOpen: (open: boolean) => void;
}

const SelectContext = React.createContext<SelectContextValue | undefined>(undefined);

interface SelectProps {
  value: string;
  onValueChange: (value: string) => void;
  children: React.ReactNode;
}

const Select = ({ value, onValueChange, children }: SelectProps) => {
  const [open, setOpen] = React.useState(false);

  return (
    <SelectContext.Provider value={{ value, onValueChange, open, setOpen }}>
      <div className="relative">{children}</div>
    </SelectContext.Provider>
  );
};

const SelectTrigger = React.forwardRef<
  HTMLButtonElement,
  React.ButtonHTMLAttributes<HTMLButtonElement> & { className?: string }
>(({ className, children, ...props }, ref) => {
  const context = React.useContext(SelectContext);
  if (!context) throw new Error('SelectTrigger must be used within Select');

  return (
    <button
      ref={ref}
      type="button"
      onClick={(e) => {
        e.preventDefault();
        e.stopPropagation();
        context.setOpen(!context.open);
      }}
      className={cn(
        'flex h-10 w-full items-center justify-between rounded-btn border border-gray-200 bg-white px-3 py-2 text-sm text-ui-text shadow-sm',
        'ring-offset-white focus:outline-none focus:ring-2 focus:ring-ui-green/20 focus:ring-offset-0 focus:border-ui-green',
        'disabled:cursor-not-allowed disabled:opacity-50',
        'dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text dark:ring-offset-ui-dark-bg dark:focus:ring-ui-green/30',
        className
      )}
      {...props}
    >
      {children}
      <ChevronDown
        className={cn(
          'h-4 w-4 text-gray-500 transition-transform duration-200',
          context.open && 'rotate-180'
        )}
      />
    </button>
  );
});
SelectTrigger.displayName = 'SelectTrigger';

interface SelectValueProps {
  placeholder?: string;
  children?: React.ReactNode;
}

const SelectValue = ({ placeholder, children }: SelectValueProps) => {
  const context = React.useContext(SelectContext);
  if (!context) throw new Error('SelectValue must be used within Select');

  // If children provided, use them (for custom display)
  if (children) {
    return <>{children}</>;
  }

  // Otherwise show value or placeholder
  return <span>{context.value || placeholder || 'Select...'}</span>;
};

const SelectContent = React.forwardRef<
  HTMLDivElement,
  React.HTMLAttributes<HTMLDivElement> & { className?: string }
>(({ className, children, ...props }, ref) => {
  const context = React.useContext(SelectContext);
  if (!context) throw new Error('SelectContent must be used within Select');

  React.useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (ref && 'current' in ref && ref.current && !ref.current.contains(event.target as Node)) {
        context.setOpen(false);
      }
    };

    if (context.open) {
      document.addEventListener('mousedown', handleClickOutside);
      return () => document.removeEventListener('mousedown', handleClickOutside);
    }
  }, [context.open, ref]);

  if (!context.open) return null;

  return (
    <div
      ref={ref}
      className={cn(
        'absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-card border border-gray-100 bg-white py-1 text-sm text-ui-text shadow-pop',
        'dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text',
        className
      )}
      {...props}
    >
      {children}
    </div>
  );
});
SelectContent.displayName = 'SelectContent';

interface SelectItemProps extends React.HTMLAttributes<HTMLDivElement> {
  value: string;
  className?: string;
  children: React.ReactNode;
}

const SelectItem = React.forwardRef<HTMLDivElement, SelectItemProps>(
  ({ className, value, children, onClick, ...props }, ref) => {
    const context = React.useContext(SelectContext);
    if (!context) throw new Error('SelectItem must be used within Select');

    const isSelected = context.value === value;

    return (
      <div
        ref={ref}
        onClick={(e) => {
          e.preventDefault();
          e.stopPropagation();
          context.onValueChange(value);
          context.setOpen(false);
          onClick?.(e);
        }}
        className={cn(
          'relative flex cursor-pointer select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none',
          'hover:bg-gray-100 focus:bg-gray-100',
          'dark:hover:bg-ui-dark-surface-2 dark:focus:bg-ui-dark-surface-2',
          isSelected &&
            'bg-ui-green-soft text-ui-green-dark dark:bg-ui-dark-green-soft dark:text-emerald-100',
          className
        )}
        {...props}
      >
        {children}
      </div>
    );
  }
);
SelectItem.displayName = 'SelectItem';

export { Select, SelectContent, SelectItem, SelectTrigger, SelectValue };
