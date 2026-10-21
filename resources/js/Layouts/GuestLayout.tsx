import DemoBanner from '@/Components/DemoBanner';
import { brandName } from '../lib/brand';
import { BrandingWrapper } from '@/Components/Branding/BrandingWrapper';
import BrandLogo from '@/Components/Branding/BrandLogo';
import { ThemeToggle } from '@/Components/UI/ThemeToggle';
import { Link } from '@inertiajs/react';
import { Bot, MessageSquare, Users, type LucideIcon } from 'lucide-react';
import { PropsWithChildren } from 'react';

interface GuestProps extends PropsWithChildren {
  headerLabel?: string;
  headerLinkText?: string;
  headerLinkHref?: string;
  compactCard?: boolean;
}

export default function Guest({
  children,
  headerLabel,
  headerLinkText,
  headerLinkHref,
}: GuestProps) {
  const setupItems: Array<[LucideIcon, string]> = [
    [MessageSquare, 'Connect your WhatsApp number'],
    [Users, 'Bring your team together'],
    [Bot, 'Automate everyday conversations'],
  ];

  return (
    <BrandingWrapper>
      <div className="min-h-screen bg-white text-ui-text antialiased dark:bg-ui-dark-bg dark:text-ui-dark-text">
        <div className="min-h-screen flex">
          <div className="flex-1 flex flex-col">
            <header className="h-16 px-6 sm:px-10 flex items-center justify-between flex-shrink-0">
              <Link href={route('landing')} className="flex items-center gap-2">
                <BrandLogo variant="auto" />
              </Link>
              <div className="flex items-center gap-3">
                <ThemeToggle />
                {headerLinkText && headerLinkHref ? (
                  <div className="hidden text-sm text-ui-text-muted dark:text-ui-dark-text-muted sm:block">
                    {headerLabel}{' '}
                    <Link
                      href={headerLinkHref}
                      className="font-semibold text-ui-green-dark hover:underline dark:text-emerald-300"
                    >
                      {headerLinkText}
                    </Link>
                  </div>
                ) : (
                  <Link
                    href={route('landing')}
                    className="hidden sm:inline-flex text-sm font-semibold text-ui-green-dark hover:underline dark:text-emerald-200"
                  >
                    Back to website
                  </Link>
                )}
              </div>
            </header>

            <div className="flex-1 flex items-center justify-center px-6 sm:px-10 py-8">
              <div className="w-full max-w-md">
                {children}
                <DemoBanner />
              </div>
            </div>
          </div>

          <aside className="relative hidden w-[400px] flex-shrink-0 items-center overflow-hidden bg-ui-ink px-8 py-12 lg:flex xl:w-[460px] xl:px-12">
            <div className="relative w-full text-white">
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-300">
                {brandName()} workspace
              </p>
              <h2 className="mt-4 text-3xl font-bold leading-tight">
                One workspace.
                <br />
                Every conversation.
              </h2>
              <p className="mt-4 text-sm leading-6 text-white/65">
                Your team, customers, and automations, connected on WhatsApp.
              </p>
              <ul className="mt-8 space-y-5">
                {setupItems.map(([Icon, label]) => (
                  <li key={label} className="flex items-center gap-3 text-sm text-white/80">
                    <Icon aria-hidden="true" className="h-5 w-5 flex-shrink-0 text-emerald-300" />
                    <span>{label}</span>
                  </li>
                ))}
              </ul>
            </div>
          </aside>
        </div>
      </div>
    </BrandingWrapper>
  );
}
