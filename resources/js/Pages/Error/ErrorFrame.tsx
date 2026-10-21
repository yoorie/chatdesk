import BrandLogo from '@/Components/Branding/BrandLogo';
import { appUrl } from '@/lib/appUrl';
import type { ReactNode } from 'react';
import errorStyles from '../../../css/error-pages.css?raw';
import { Head } from '@inertiajs/react';
import { ArrowLeft, Home, type LucideIcon } from 'lucide-react';

type Accent = 'green' | 'amber' | 'red';

interface ErrorFrameAction {
  label: string;
  href?: string;
  onClick?: () => void;
  icon?: LucideIcon;
  primary?: boolean;
}

interface ErrorFrameProps {
  code: string;
  title: string;
  description: string;
  icon: LucideIcon;
  accent?: Accent;
  actions?: ErrorFrameAction[];
  children?: ReactNode;
}

export function appDashboardHref() {
  return typeof route !== 'undefined' ? route('dashboard') : appUrl('/dashboard');
}

export function defaultBackAction(): ErrorFrameAction {
  return {
    label: 'Back',
    icon: ArrowLeft,
    onClick: () =>
      window.history.length > 1 ? window.history.back() : window.location.assign(appUrl('/')),
  };
}

export function defaultDashboardAction(): ErrorFrameAction {
  return {
    label: 'Dashboard',
    icon: Home,
    href: appDashboardHref(),
  };
}

export default function ErrorFrame({
  code,
  title,
  description,
  icon: Icon,
  accent = 'green',
  actions = [defaultBackAction(), defaultDashboardAction()],
  children,
}: ErrorFrameProps) {
  return (
    <main className="error-page">
      <Head>
        <style>{errorStyles}</style>
      </Head>
      <section className="error-card" data-accent={accent}>
        <div className="error-header">
          <BrandLogo imageClassName="error-logo" />
          <span className="error-code">{code}</span>
        </div>
        <span className="error-icon" aria-hidden="true">
          <Icon />
        </span>
        <h1 className="error-title">{title}</h1>
        <p className="error-description">{description}</p>
        {children}
        <div className="error-actions">
          {actions.map((action) => {
            const ActionIcon = action.icon;
            const content = (
              <>
                {ActionIcon && <ActionIcon aria-hidden="true" />}
                {action.label}
              </>
            );
            const className = `error-action${action.primary ? ' error-action-primary' : ''}`;
            return action.href ? (
              <a key={action.label} href={action.href} className={className}>
                {content}
              </a>
            ) : (
              <button
                key={action.label}
                type="button"
                onClick={action.onClick}
                className={className}
              >
                {content}
              </button>
            );
          })}
        </div>
      </section>
    </main>
  );
}
