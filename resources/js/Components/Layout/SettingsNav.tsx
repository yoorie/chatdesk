import { cn } from '@/lib/utils';
import { Link, usePage } from '@inertiajs/react';
import { Code2, History, Link2, LucideIcon, Settings, Users } from 'lucide-react';

export interface SettingsNavLink {
  key: string;
  label: string;
  description: string;
  icon: LucideIcon;
  route: string;
  params?: Record<string, unknown>;
}

/** Every settings area in one place, in the order customers usually need them. */
const SETTINGS_LINKS: SettingsNavLink[] = [
  {
    key: 'general',
    label: 'General',
    description: 'Workspace, profile & security',
    icon: Settings,
    route: 'app.settings',
  },
  {
    key: 'whatsapp',
    label: 'WhatsApp account',
    description: 'Business number & profile',
    icon: Link2,
    route: 'app.whatsapp.connections.index',
  },
  {
    key: 'team',
    label: 'Team',
    description: 'Members & roles',
    icon: Users,
    route: 'app.team.index',
  },
  {
    key: 'developer',
    label: 'Developer API',
    description: 'API keys & webhooks',
    icon: Code2,
    route: 'app.developer.index',
  },
  {
    key: 'activity',
    label: 'Activity log',
    description: 'Who changed what',
    icon: History,
    route: 'app.activity-logs',
  },
];

function safeRoute(name: string, params: Record<string, unknown> = {}): string | null {
  try {
    return route(name, params as any);
  } catch {
    return null;
  }
}

function settingsLinks(): Array<SettingsNavLink & { href: string }> {
  return SETTINGS_LINKS.map((link) => ({
    ...link,
    href: safeRoute(link.route, link.params),
  })).filter((link): link is SettingsNavLink & { href: string } => Boolean(link.href));
}

/**
 * Horizontal settings sub-navigation shown at the top of every settings page,
 * so customers can move between settings areas without going back to the sidebar.
 */
export default function SettingsNav({ active, className }: { active: string; className?: string }) {
  const { account_role } = usePage().props as any;

  // Chat agents only use the inbox; they never see settings navigation.
  if (account_role === 'member') {
    return null;
  }

  const links = settingsLinks();

  return (
    <nav
      aria-label="Settings"
      className={cn(
        'scrollbar-none -mx-1 mb-5 flex gap-1 overflow-x-auto border-b border-gray-200 px-1 dark:border-ui-dark-border',
        className
      )}
    >
      {links.map((link) => {
        const Icon = link.icon;
        const isActive = link.key === active;

        return (
          <Link
            key={link.key}
            href={link.href}
            prefetch="hover"
            aria-current={isActive ? 'page' : undefined}
            className={cn(
              '-mb-px inline-flex shrink-0 items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium transition-colors',
              isActive
                ? 'border-ui-green text-ui-green-dark dark:text-emerald-300'
                : 'border-transparent text-ui-text-muted hover:border-gray-300 hover:text-ui-text dark:text-ui-dark-text-muted dark:hover:text-ui-dark-text'
            )}
          >
            <Icon className="h-4 w-4" aria-hidden />
            {link.label}
          </Link>
        );
      })}
    </nav>
  );
}
