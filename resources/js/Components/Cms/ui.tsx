import { cn } from '@/lib/utils';
import type { ButtonHTMLAttributes, InputHTMLAttributes, ReactNode, TextareaHTMLAttributes } from 'react';

/** Small, consistent CMS form controls (#047857 primary, softly rounded, auto width). */
export function PrimaryButton({ className, children, ...rest }: ButtonHTMLAttributes<HTMLButtonElement>) {
  return (
    <button
      {...rest}
      className={cn(
        'inline-flex w-auto items-center gap-1.5 rounded-lg bg-[#047857] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#065f46] focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300 disabled:opacity-60',
        className
      )}
    >
      {children}
    </button>
  );
}

export function GhostButton({ className, children, ...rest }: ButtonHTMLAttributes<HTMLButtonElement>) {
  return (
    <button
      type="button"
      {...rest}
      className={cn(
        'inline-flex w-auto items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300 disabled:opacity-40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800',
        className
      )}
    >
      {children}
    </button>
  );
}

export function IconButton({ label, className, children, ...rest }: ButtonHTMLAttributes<HTMLButtonElement> & { label: string }) {
  return (
    <button
      type="button"
      aria-label={label}
      title={label}
      {...rest}
      className={cn(
        'inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300 disabled:opacity-30 dark:hover:bg-slate-800 dark:hover:text-slate-100',
        className
      )}
    >
      {children}
    </button>
  );
}

export function Field({ label, hint, children, htmlFor }: { label: string; hint?: string; children: ReactNode; htmlFor?: string }) {
  return (
    <div className="min-w-0">
      <label htmlFor={htmlFor} className="mb-1 flex items-center gap-1 text-xs font-semibold text-slate-600 dark:text-slate-300">
        {label}
        {hint && (
          <span title={hint} aria-label={hint} className="cursor-help text-slate-400">
            ⓘ
          </span>
        )}
      </label>
      {children}
    </div>
  );
}

const inputCls =
  'w-full min-w-0 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[#047857] focus:outline-none focus:ring-2 focus:ring-[#047857]/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100';

export function TextInput(props: InputHTMLAttributes<HTMLInputElement>) {
  return <input type="text" {...props} className={cn(inputCls, props.className)} />;
}

export function TextArea(props: TextareaHTMLAttributes<HTMLTextAreaElement>) {
  return <textarea rows={3} {...props} className={cn(inputCls, 'resize-y', props.className)} />;
}

export function Toggle({ checked, onChange, label }: { checked: boolean; onChange: (v: boolean) => void; label: string }) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      aria-label={label}
      title={label}
      onClick={() => onChange(!checked)}
      className={cn(
        'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300',
        checked ? 'bg-[#047857]' : 'bg-slate-300 dark:bg-slate-600'
      )}
    >
      <span className={cn('inline-block h-5 w-5 rounded-full bg-white shadow transition', checked ? 'translate-x-5' : 'translate-x-0.5')} />
    </button>
  );
}

export function Panel({ title, children, actions }: { title?: ReactNode; children: ReactNode; actions?: ReactNode }) {
  return (
    <section className="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900 sm:p-5">
      {(title || actions) && (
        <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
          {title && <h2 className="text-sm font-semibold text-slate-800 dark:text-slate-100">{title}</h2>}
          {actions}
        </div>
      )}
      {children}
    </section>
  );
}
