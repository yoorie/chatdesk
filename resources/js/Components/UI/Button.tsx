import { cn } from '@/lib/utils';
import { ButtonHTMLAttributes, forwardRef } from 'react';

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?:
    | 'primary'
    | 'secondary'
    | 'danger'
    | 'ghost'
    | 'success'
    | 'warning'
    | 'info'
    | 'dark'
    | 'link';
  size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl';
}

const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant = 'primary', size = 'md', ...props }, ref) => {
    const baseStyles =
      'inline-flex items-center justify-center rounded-btn font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-ui-green/25 focus:ring-offset-2 focus:ring-offset-white disabled:pointer-events-none disabled:opacity-50 dark:focus:ring-offset-ui-dark-bg';

    const variants = {
      primary: 'bg-ui-green text-white shadow-sm hover:bg-ui-green-dark',
      secondary:
        'bg-white text-ui-text hover:bg-gray-50 ring-1 ring-inset ring-gray-200 dark:bg-ui-dark-surface dark:text-ui-dark-text dark:ring-ui-dark-border dark:hover:bg-ui-dark-surface-2',
      danger: 'bg-red-600 text-white hover:bg-red-700',
      success: 'bg-ui-green text-white shadow-sm hover:bg-ui-green-dark',
      warning: 'bg-amber-500 text-white hover:bg-amber-600',
      info: 'bg-ui-green-dark text-white hover:bg-ui-green',
      dark: 'bg-ui-text text-white hover:bg-gray-800 dark:bg-ui-dark-text dark:text-ui-dark-bg dark:hover:bg-white',
      link: 'px-0 text-ui-green-dark hover:underline dark:text-emerald-300',
      ghost:
        'text-ui-text-muted hover:bg-gray-100 hover:text-ui-text dark:text-ui-dark-text-muted dark:hover:bg-ui-dark-surface-2 dark:hover:text-ui-dark-text',
    };

    const sizes = {
      xs: 'h-7 px-2.5 text-xs gap-1.5 rounded-md',
      sm: 'h-8 px-3 text-xs gap-1.5',
      md: 'h-9 px-3.5 text-sm gap-2',
      lg: 'h-11 px-5 text-sm gap-2 rounded-xl',
      xl: 'h-12 px-6 text-base gap-2 rounded-xl',
    };

    return (
      <button
        ref={ref}
        className={cn(baseStyles, variants[variant], sizes[size], className)}
        {...props}
      />
    );
  }
);

Button.displayName = 'Button';

export default Button;
