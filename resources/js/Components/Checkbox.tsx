import { InputHTMLAttributes } from 'react';

export default function Checkbox({
  className = '',
  ...props
}: InputHTMLAttributes<HTMLInputElement>) {
  return (
    <input
      {...props}
      type="checkbox"
      className={
        'rounded border-gray-300 text-ui-green shadow-sm focus:ring-ui-green/30 dark:border-ui-dark-border dark:bg-ui-dark-surface ' +
        className
      }
    />
  );
}
