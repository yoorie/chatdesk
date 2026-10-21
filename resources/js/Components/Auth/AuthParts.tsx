import { Input } from '@/Components/UI/Input';
import { cn } from '@/lib/utils';
import { Eye, EyeOff, Lock } from 'lucide-react';
import { ChangeEvent, InputHTMLAttributes, ReactNode, useState } from 'react';

export function AuthDivider({ label }: { label: string }) {
  return (
    <div className="my-6 flex items-center gap-3 text-xs uppercase tracking-wide text-ui-text-muted dark:text-ui-dark-text-muted">
      <div className="h-px flex-1 bg-gray-200 dark:bg-slate-700" />
      {label}
      <div className="h-px flex-1 bg-gray-200 dark:bg-slate-700" />
    </div>
  );
}

export function AuthField({
  label,
  error,
  children,
  htmlFor,
}: {
  label: string;
  error?: string;
  children: ReactNode;
  htmlFor: string;
}) {
  return (
    <div>
      <label
        htmlFor={htmlFor}
        className="mb-1.5 block text-sm font-medium text-ui-text dark:text-ui-dark-text"
      >
        {label}
      </label>
      {children}
      {error && <p className="mt-2 text-sm text-red-600 dark:text-red-400">{error}</p>}
    </div>
  );
}

export function AuthInput({ className, ...props }: InputHTMLAttributes<HTMLInputElement>) {
  return (
    <Input
      className={cn(
        'h-11 w-full rounded-xl border border-gray-200 bg-white px-3.5 text-sm text-ui-text outline-none transition placeholder:text-gray-400 focus:border-ui-green focus:ring-2 focus:ring-ui-green/15 dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text dark:placeholder:text-ui-dark-text-muted',
        className
      )}
      {...props}
    />
  );
}

export function PasswordField({
  id,
  value,
  onChange,
  label = 'Password',
  placeholder,
  autoComplete,
  error,
  forgotHref,
  required = true,
}: {
  id: string;
  value: string;
  onChange: (event: ChangeEvent<HTMLInputElement>) => void;
  label?: string;
  placeholder?: string;
  autoComplete?: string;
  error?: string;
  forgotHref?: string;
  required?: boolean;
}) {
  const [show, setShow] = useState(false);

  return (
    <div>
      <div className="mb-1.5 flex items-center justify-between">
        <label
          htmlFor={id}
          className="block text-sm font-medium text-ui-text dark:text-ui-dark-text"
        >
          {label}
        </label>
        {forgotHref && (
          <a
            href={forgotHref}
            className="text-xs font-medium text-ui-green-dark hover:underline dark:text-emerald-300"
          >
            Forgot password?
          </a>
        )}
      </div>
      <div className="relative">
        <Lock className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
        <AuthInput
          id={id}
          type={show ? 'text' : 'password'}
          value={value}
          onChange={onChange}
          autoComplete={autoComplete}
          placeholder={placeholder}
          required={required}
          className="pl-10 pr-10"
        />
        <button
          type="button"
          onClick={() => setShow((current) => !current)}
          className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 transition hover:text-ui-text dark:hover:text-ui-dark-text"
          aria-label={show ? 'Hide password' : 'Show password'}
        >
          {show ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
        </button>
      </div>
      {error && <p className="mt-2 text-sm text-red-600 dark:text-red-400">{error}</p>}
    </div>
  );
}

export function AuthCard({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <div
      className={cn(
        'w-full max-w-md rounded-card border border-gray-100 bg-white p-7 shadow-card dark:border-ui-dark-border dark:bg-ui-dark-surface sm:p-10',
        className
      )}
    >
      {children}
    </div>
  );
}
