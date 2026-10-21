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

export function SocialAuthButtons({
  googleHref,
  googleEnabled = false,
}: {
  googleHref?: string;
  googleEnabled?: boolean;
}) {
  const className =
    'surface inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-white text-sm font-medium text-ui-text ring-1 ring-gray-200 transition hover:bg-gray-50 dark:bg-ui-dark-surface dark:text-ui-dark-text dark:ring-ui-dark-border dark:hover:bg-ui-dark-surface-2';

  if (!googleEnabled || !googleHref) {
    return (
      <button type="button" disabled className={cn(className, 'cursor-not-allowed opacity-60')}>
        <GoogleIcon />
        Continue with Google
      </button>
    );
  }

  return (
    <a href={googleHref} className={className}>
      <GoogleIcon />
      Continue with Google
    </a>
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

function GoogleIcon() {
  return (
    <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">
      <path
        fill="#FFC107"
        d="M43.611 20.083H42V20H24v8h11.303C33.654 32.657 29.223 36 24 36c-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"
      />
      <path
        fill="#FF3D00"
        d="m6.306 14.691 6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"
      />
      <path
        fill="#4CAF50"
        d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238A11.91 11.91 0 0 1 24 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"
      />
      <path
        fill="#1976D2"
        d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 0 1-4.087 5.571l6.193 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"
      />
    </svg>
  );
}
