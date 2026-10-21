import { LucideIcon } from 'lucide-react';
import { ReactNode } from 'react';

interface EmptyStateProps {
  icon: LucideIcon;
  title: string;
  description?: string;
  action?: ReactNode;
}

export function EmptyState({ icon: Icon, title, description, action }: EmptyStateProps) {
  return (
    <div className="flex flex-col items-center justify-center px-4 py-10 text-center">
      <div className="mb-3 rounded-2xl bg-ui-green-soft p-3 text-ui-green-dark dark:bg-ui-dark-green-soft dark:text-emerald-200">
        <Icon className="h-6 w-6" />
      </div>
      <h3 className="text-base font-semibold text-ui-text dark:text-ui-dark-text">{title}</h3>
      {description && (
        <p className="mt-1 max-w-sm text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
          {description}
        </p>
      )}
      {action && <div className="mt-4">{action}</div>}
    </div>
  );
}
