import { cn } from '@/lib/utils';
import {
  Dialog,
  DialogDescription,
  DialogPanel,
  DialogTitle,
  Transition,
  TransitionChild,
} from '@headlessui/react';
import { Fragment, type ReactNode } from 'react';
import { X } from 'lucide-react';

export function Modal({
  open,
  onClose,
  title,
  description,
  children,
  footer,
  className,
}: {
  open: boolean;
  onClose: () => void;
  title: ReactNode;
  description?: ReactNode;
  children: ReactNode;
  footer?: ReactNode;
  className?: string;
}) {
  return (
    <Transition show={open} as={Fragment}>
      <Dialog onClose={onClose} className="fixed inset-0 z-[250] overflow-y-auto">
        <TransitionChild
          as={Fragment}
          enter="ease-out duration-150 motion-reduce:duration-0"
          enterFrom="opacity-0"
          enterTo="opacity-100"
          leave="ease-in duration-100 motion-reduce:duration-0"
          leaveFrom="opacity-100"
          leaveTo="opacity-0"
        >
          <div aria-hidden="true" className="fixed inset-0 bg-ui-ink/45 dark:bg-black/70" />
        </TransitionChild>
        <div className="flex min-h-full items-center justify-center p-4">
          <TransitionChild
            as={Fragment}
            enter="ease-out duration-150 motion-reduce:duration-0"
            enterFrom="opacity-0 translate-y-3 scale-95"
            enterTo="opacity-100 translate-y-0 scale-100"
            leave="ease-in duration-100 motion-reduce:duration-0"
            leaveFrom="opacity-100 translate-y-0 scale-100"
            leaveTo="opacity-0 translate-y-3 scale-95"
          >
            <DialogPanel
              className={cn(
                'relative w-full max-w-lg overflow-hidden rounded-card border border-gray-100 bg-white shadow-pop dark:border-ui-dark-border dark:bg-ui-dark-surface',
                className
              )}
            >
              <div className="flex items-start justify-between gap-4 border-b border-gray-100 px-5 py-4 dark:border-ui-dark-border">
                <div>
                  <DialogTitle
                    as="h2"
                    className="text-base font-semibold text-ui-text dark:text-ui-dark-text"
                  >
                    {title}
                  </DialogTitle>
                  {description && (
                    <DialogDescription className="mt-1 text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
                      {description}
                    </DialogDescription>
                  )}
                </div>
                <button
                  type="button"
                  onClick={onClose}
                  aria-label="Close"
                  className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700"
                >
                  <X className="h-4 w-4" />
                </button>
              </div>
              <div className="px-5 py-4">{children}</div>
              {footer && (
                <div className="flex justify-end gap-2 border-t border-gray-100 bg-gray-50/70 px-5 py-4 dark:border-ui-dark-border dark:bg-ui-dark-surface-2/70">
                  {footer}
                </div>
              )}
            </DialogPanel>
          </TransitionChild>
        </div>
      </Dialog>
    </Transition>
  );
}

export function Drawer({
  open,
  onClose,
  title,
  description,
  children,
  footer,
  side = 'right',
  className,
}: {
  open: boolean;
  onClose: () => void;
  title: ReactNode;
  description?: ReactNode;
  children: ReactNode;
  footer?: ReactNode;
  side?: 'left' | 'right';
  className?: string;
}) {
  const defaultWidthClass = className ? '' : 'sm:max-w-md';

  return (
    <Transition show={open} as={Fragment}>
      <Dialog onClose={onClose} className="fixed inset-0 z-[250] overflow-hidden">
        <TransitionChild
          as={Fragment}
          enter="ease-out duration-150 motion-reduce:duration-0"
          enterFrom="opacity-0"
          enterTo="opacity-100"
          leave="ease-in duration-100 motion-reduce:duration-0"
          leaveFrom="opacity-100"
          leaveTo="opacity-0"
        >
          <div aria-hidden="true" className="fixed inset-0 bg-ui-ink/45 dark:bg-black/70" />
        </TransitionChild>
        <div
          className={cn(
            'fixed inset-y-0 flex max-w-full',
            side === 'right' ? 'right-0 pl-0 sm:pl-10' : 'left-0 pr-0 sm:pr-10'
          )}
        >
          <TransitionChild
            as={Fragment}
            enter="transform transition ease-out duration-150 motion-reduce:duration-0"
            enterFrom={side === 'right' ? 'translate-x-full' : '-translate-x-full'}
            enterTo="translate-x-0"
            leave="transform transition ease-in duration-100 motion-reduce:duration-0"
            leaveFrom="translate-x-0"
            leaveTo={side === 'right' ? 'translate-x-full' : '-translate-x-full'}
          >
            <DialogPanel
              className={cn(
                'flex h-full w-screen max-w-[100vw] flex-col border-gray-100 bg-white shadow-pop dark:border-ui-dark-border dark:bg-ui-dark-surface',
                defaultWidthClass,
                side === 'right' ? 'border-l' : 'border-r',
                className
              )}
            >
              <div className="flex items-start justify-between gap-4 border-b border-gray-100 px-4 py-4 dark:border-ui-dark-border sm:px-5">
                <div className="min-w-0">
                  <DialogTitle
                    as="h2"
                    className="break-words text-base font-semibold text-ui-text dark:text-ui-dark-text"
                  >
                    {title}
                  </DialogTitle>
                  {description && (
                    <DialogDescription className="mt-1 text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
                      {description}
                    </DialogDescription>
                  )}
                </div>
                <button
                  type="button"
                  onClick={onClose}
                  aria-label="Close"
                  className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700"
                >
                  <X className="h-4 w-4" />
                </button>
              </div>
              <div className="flex-1 overflow-y-auto px-4 py-4 ui-scrollbar sm:px-5">
                {children}
              </div>
              {footer && (
                <div className="border-t border-gray-100 bg-gray-50/70 px-4 py-4 dark:border-ui-dark-border dark:bg-ui-dark-surface-2/70 sm:px-5">
                  {footer}
                </div>
              )}
            </DialogPanel>
          </TransitionChild>
        </div>
      </Dialog>
    </Transition>
  );
}
