import DemoBanner from '@/Components/DemoBanner';
import { BrandingWrapper } from '@/Components/Branding/BrandingWrapper';
import BrandLogo from '@/Components/Branding/BrandLogo';
import { Avatar } from '@/Components/UI/Elements';
import { ThemeToggle } from '@/Components/UI/ThemeToggle';
import { cn } from '@/lib/utils';
import { Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import {
  Activity,
  ArrowLeft,
  BarChart3,
  Bell,
  Building2,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  ClipboardList,
  FileText,
  History,
  Layers,
  LifeBuoy,
  Loader2,
  LogOut,
  Menu,
  Puzzle,
  Search,
  Send,
  RefreshCw,
  Settings,
  Shield,
  ShieldCheck,
  Tag,
  UserRound,
  Users,
  Wallet,
  X,
  type LucideIcon,
  Globe,
} from 'lucide-react';
import { ReactNode, useEffect, useMemo, useRef, useState } from 'react';

interface PlatformShellProps {
  children: ReactNode;
  auth?: {
    user?: {
      name: string;
      email: string;
      is_super_admin?: boolean;
    } | null;
  };
}

type AdminItem = {
  label: string;
  routeName: string;
  icon: LucideIcon;
  match?: string[];
};

type AdminSection = {
  label: string;
  items: AdminItem[];
};

const adminSections: AdminSection[] = [
  {
    label: 'Platform',
    items: [
      { label: 'Overview', routeName: 'platform.dashboard', icon: Shield },
      {
        label: 'Workspaces',
        routeName: 'platform.accounts.index',
        icon: Building2,
        match: ['platform.accounts.show'],
      },
      { label: 'Users', routeName: 'platform.users.index', icon: Users },
      { label: 'Plans', routeName: 'platform.plans.index', icon: Layers },
      { label: 'Modules', routeName: 'platform.modules.index', icon: Puzzle },
    ],
  },
  {
    label: 'Billing & growth',
    items: [
      { label: 'Subscriptions', routeName: 'platform.subscriptions.index', icon: FileText },
      { label: 'Discounts', routeName: 'platform.discounts.index', icon: Tag },
      { label: 'Email campaigns', routeName: 'platform.email-campaigns.index', icon: Send },
      { label: 'Transactions', routeName: 'platform.transactions.index', icon: Wallet },
      { label: 'Analytics', routeName: 'platform.analytics', icon: BarChart3 },
    ],
  },
  {
    label: 'Operations',
    items: [
      {
        label: 'Support',
        routeName: 'platform.support.index',
        icon: LifeBuoy,
        match: ['platform.support.show'],
      },
      { label: 'Notifications', routeName: 'platform.notifications.index', icon: Bell },
      {
        label: 'Contact requests',
        routeName: 'platform.contact-requests.index',
        icon: ClipboardList,
      },
      {
        label: 'Templates',
        routeName: 'platform.templates.index',
        icon: FileText,
        match: ['platform.templates.show'],
      },
      { label: 'Audit logs', routeName: 'platform.activity-logs', icon: History },
      { label: 'System health', routeName: 'platform.system-health', icon: ShieldCheck },
    ],
  },
  {
    label: 'System',
    items: [
      {
        label: 'Website',
        routeName: 'platform.website.index',
        icon: Globe,
        match: ['platform.website.pages.create', 'platform.website.pages.edit'],
      },
      { label: 'System update', routeName: 'platform.system-update', icon: RefreshCw },
      { label: 'Settings', routeName: 'platform.settings', icon: Settings },
    ],
  },
];

const routeMeta: Record<string, { title: string; subtitle: string }> = {
  'platform.dashboard': {
    title: 'Overview',
    subtitle: 'Platform health, growth, and operational signals',
  },
  'platform.accounts.index': {
    title: 'Workspaces',
    subtitle: 'Tenant accounts, plans, status, and access',
  },
  'platform.users.index': {
    title: 'Users',
    subtitle: 'Platform users, roles, and impersonation access',
  },
  'platform.plans.index': {
    title: 'Plans',
    subtitle: 'Manage plan pricing, features, and workspace limits.',
  },
  'platform.modules.index': {
    title: 'Modules',
    subtitle: 'Feature modules and workspace availability',
  },
  'platform.subscriptions.index': {
    title: 'Subscriptions',
    subtitle: 'Customer subscriptions and renewal status',
  },
  'platform.discounts.index': {
    title: 'Discounts',
    subtitle: 'Promo codes, offers, and Razorpay discount mapping',
  },
  'platform.email-campaigns.index': {
    title: 'Email campaigns',
    subtitle: 'Newsletter, promotion, and offer emails',
  },
  'platform.transactions.index': {
    title: 'Transactions',
    subtitle: 'Wallet ledger and billing payment records',
  },
  'platform.analytics': {
    title: 'Analytics',
    subtitle: 'System-wide usage and business reporting',
  },
  'platform.support.index': {
    title: 'Support',
    subtitle: 'Tenant support conversations and ticket operations',
  },
  'platform.notifications.index': {
    title: 'Notifications',
    subtitle: 'Platform alerts for webhooks, payments, automation, and WABA health',
  },
  'platform.contact-requests.index': {
    title: 'Contact requests',
    subtitle: 'Public contact form leads and follow-up status',
  },
  'platform.templates.index': {
    title: 'Templates',
    subtitle: 'Review Meta templates across workspaces. Manage submissions from each workspace.',
  },
  'platform.activity-logs': {
    title: 'Audit logs',
    subtitle: 'System events and operational diagnostics',
  },
  'platform.system-health': {
    title: 'System health',
    subtitle: 'Infrastructure, queue, and integration readiness',
  },
  'platform.system-update': {
    title: 'System update',
    subtitle: 'Upload a signed package',
  },
  'platform.website.index': {
    title: 'Website',
    subtitle: 'Landing page, pages, menus and SEO',
  },
  'platform.website.pages.create': { title: 'New page', subtitle: 'Create a website page' },
  'platform.website.pages.edit': { title: 'Edit page', subtitle: 'Website page content and SEO' },
  'platform.settings': {
    title: 'Settings',
    subtitle: 'Branding, payments, support, compliance, and infrastructure',
  },
};

function routeHref(routeName: string): string {
  try {
    return route(routeName);
  } catch (error) {
    return '#';
  }
}

function routePath(routeName: string): string | null {
  const href = routeHref(routeName);
  if (href === '#') return null;

  try {
    return new URL(href, 'http://localhost').pathname;
  } catch (error) {
    return href;
  }
}

function isActiveRoute(item: AdminItem, currentPath: string) {
  const paths = [item.routeName, ...(item.match || [])]
    .map(routePath)
    .filter((path): path is string => Boolean(path));

  return paths.some(
    (path) =>
      currentPath === path ||
      (item.routeName !== 'platform.dashboard' && currentPath.startsWith(`${path}/`))
  );
}

function currentMeta(currentPath: string) {
  const profilePath = routePath('profile.edit');

  if (profilePath && (currentPath === profilePath || currentPath.startsWith(`${profilePath}/`))) {
    return { title: 'Profile', subtitle: 'Manage your admin login and security' };
  }

  const matched = adminSections
    .flatMap((section) => section.items)
    .find((item) => isActiveRoute(item, currentPath));

  return matched
    ? routeMeta[matched.routeName] || { title: matched.label, subtitle: 'Platform operations' }
    : routeMeta['platform.dashboard'];
}

function AdminSidebar({
  currentPath,
  collapsed,
  mobileOpen,
  onClose,
  onToggle,
}: {
  currentPath: string;
  collapsed: boolean;
  mobileOpen: boolean;
  onClose: () => void;
  onToggle: () => void;
}) {
  const effectiveCollapsed = collapsed;
  const currentSection = adminSections.find((section) =>
    section.items.some((item) => isActiveRoute(item, currentPath))
  )?.label;

  return (
    <>
      {mobileOpen && (
        <button
          type="button"
          aria-label="Close navigation"
          className="fixed inset-0 z-40 bg-black/40 lg:hidden"
          onClick={onClose}
        />
      )}
      <aside
        className={cn(
          'fixed inset-y-0 left-0 z-50 flex h-full w-60 flex-shrink-0 flex-col bg-ui-sidebar text-white transition-transform duration-300 ease-out lg:relative lg:translate-x-0',
          effectiveCollapsed ? 'lg:w-16' : 'lg:w-60',
          mobileOpen ? 'translate-x-0' : '-translate-x-full'
        )}
      >
        <div
          className={cn(
            'flex h-16 items-center justify-between border-b border-white/5',
            effectiveCollapsed ? 'px-3 lg:justify-center' : 'px-5'
          )}
        >
          <div className="flex min-w-0 items-center gap-2">
            <BrandLogo
              variant="dark"
              compact={effectiveCollapsed}
              className={cn(effectiveCollapsed ? 'lg:justify-center' : 'max-w-[150px]')}
              imageClassName={
                effectiveCollapsed ? 'h-8 w-8 object-contain' : 'max-h-9 w-auto object-contain'
              }
              fallbackTextClassName="text-white"
            />
            <div className={cn('min-w-0', effectiveCollapsed && 'lg:hidden')}>
              <div className="truncate text-[10px] uppercase tracking-wider text-white/45">
                Platform admin
              </div>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="flex h-8 w-8 items-center justify-center rounded-md text-white/70 hover:bg-white/10 lg:hidden"
            aria-label="Close menu"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        <button
          type="button"
          onClick={onToggle}
          aria-label="Toggle sidebar"
          className="keep-light absolute -right-3 top-20 z-10 hidden h-6 w-6 items-center justify-center rounded-full bg-white text-ui-text shadow-pop ring-1 ring-gray-200 hover:bg-gray-50 lg:flex"
        >
          {effectiveCollapsed ? (
            <ChevronRight className="h-3.5 w-3.5" />
          ) : (
            <ChevronLeft className="h-3.5 w-3.5" />
          )}
        </button>

        <nav
          aria-label="Platform navigation"
          className={cn(
            'sidebar-scroll flex-1 overflow-y-auto py-4',
            effectiveCollapsed ? 'px-2' : 'px-3'
          )}
        >
          {adminSections.map((section) => (
            <details
              key={`${section.label}-${currentSection}-${collapsed}`}
              open={
                effectiveCollapsed ||
                section.label === 'Platform' ||
                section.label === 'System' ||
                section.label === currentSection
              }
              className="group/nav-section mb-2"
            >
              <summary
                className={cn(
                  'mb-1 flex cursor-pointer list-none items-center justify-between rounded-md px-3 py-2 text-[11px] font-semibold text-white/50 hover:text-white [&::-webkit-details-marker]:hidden',
                  effectiveCollapsed && 'lg:hidden'
                )}
              >
                {section.label}
                <ChevronDown
                  aria-hidden="true"
                  className="h-3 w-3 transition-transform group-open/nav-section:rotate-180"
                />
              </summary>
              <ul className="space-y-0.5">
                {section.items.map((item) => {
                  const active = isActiveRoute(item, currentPath);
                  const Icon = item.icon;

                  return (
                    <li key={item.routeName}>
                      <Link
                        href={routeHref(item.routeName)}
                        onClick={onClose}
                        title={effectiveCollapsed ? item.label : undefined}
                        aria-current={active ? 'page' : undefined}
                        className={cn(
                          'group relative flex h-10 w-full items-center rounded-btn text-sm transition-all',
                          effectiveCollapsed ? 'px-2 lg:justify-center' : 'px-3',
                          active
                            ? 'bg-ui-green/15 font-semibold text-ui-green'
                            : 'text-white/70 hover:bg-white/5 hover:text-white'
                        )}
                      >
                        {active && (
                          <span className="absolute bottom-2 left-0 top-2 w-0.5 rounded-full bg-ui-green" />
                        )}
                        <Icon
                          className={cn(
                            'h-[18px] w-[18px] flex-shrink-0',
                            effectiveCollapsed ? 'lg:mr-0 mr-3' : 'mr-3'
                          )}
                        />
                        <span
                          className={cn(
                            'min-w-0 flex-1 truncate text-left',
                            effectiveCollapsed && 'lg:hidden'
                          )}
                        >
                          {item.label}
                        </span>
                      </Link>
                    </li>
                  );
                })}
              </ul>
            </details>
          ))}
        </nav>

        <div className={cn('border-t border-white/5 py-3', effectiveCollapsed ? 'px-2' : 'px-3')}>
          <Link
            href={routeHref('landing')}
            title="View website"
            className="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-white/60 hover:bg-white/5 hover:text-white"
          >
            <ArrowLeft aria-hidden="true" className="h-4 w-4 flex-shrink-0" />
            <span className={cn(effectiveCollapsed && 'lg:hidden')}>View website</span>
          </Link>
          <a
            href="https://yoorie.com"
            target="_blank"
            rel="noreferrer"
            title="Support · support@yoorie.com"
            className="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-white/60 hover:bg-white/5 hover:text-white"
          >
            <LifeBuoy aria-hidden="true" className="h-4 w-4 flex-shrink-0" />
            <span className={cn(effectiveCollapsed && 'lg:hidden')}>Support</span>
          </a>
        </div>
      </aside>
    </>
  );
}

function AdminHeader({
  title,
  user,
  onMobileMenuClick,
}: {
  title: string;
  user: { name: string; email: string; is_super_admin?: boolean } | null;
  onMobileMenuClick: () => void;
}) {
  const { notification_summary } = usePage().props as any;
  const [notificationsOpen, setNotificationsOpen] = useState(false);
  const [userOpen, setUserOpen] = useState(false);
  const pageUrl = usePage().url;
  useEffect(() => {
    setNotificationsOpen(false);
    setUserOpen(false);
  }, [pageUrl]);
  useEffect(() => {
    const close = (event: KeyboardEvent | MouseEvent) => {
      if (
        (event instanceof KeyboardEvent && event.key === 'Escape') ||
        (event instanceof MouseEvent &&
          event.target instanceof Element &&
          !event.target.closest('[data-popover]'))
      ) {
        setNotificationsOpen(false);
        setUserOpen(false);
      }
    };
    document.addEventListener('keydown', close);
    document.addEventListener('click', close);
    return () => {
      document.removeEventListener('keydown', close);
      document.removeEventListener('click', close);
    };
  }, []);

  const notifications = [
    ...(notification_summary?.latest || []).map((item: any) => ({
      tone:
        item.severity === 'critical'
          ? 'danger'
          : item.severity === 'warning'
            ? 'warning'
            : item.severity === 'success'
              ? 'success'
              : 'info',
      title: item.title,
      body: item.body,
      time: item.created_at ? new Date(item.created_at).toLocaleString() : '',
      href: item.action_url || routeHref('platform.notifications.index'),
    })),
  ].filter(Boolean) as Array<{
    tone: 'success' | 'warning' | 'danger' | 'info';
    title: string;
    body?: string;
    time: string;
    href?: string;
  }>;
  const unreadNotifications = Number(notification_summary?.unread || 0);

  const signOutHref = routeHref('logout');

  return (
    <header className="app-header z-30 flex h-16 flex-shrink-0 items-center gap-2 border-b border-ui-border bg-white px-4 dark:border-slate-700 dark:bg-slate-900 sm:gap-4 sm:px-6">
      <button
        type="button"
        onClick={onMobileMenuClick}
        aria-label="Open menu"
        className="flex h-9 w-9 items-center justify-center rounded-md text-ui-text hover:bg-gray-100 dark:text-ui-dark-text dark:hover:bg-slate-800 lg:hidden"
      >
        <Menu className="h-5 w-5" />
      </button>

      <span className="min-w-0 flex-1 truncate text-sm font-semibold lg:flex-none">{title}</span>

      <div className="mx-auto hidden max-w-xl flex-1 md:block">
        <PlatformGlobalSearch />
      </div>

      <div className="flex-1 md:hidden" />

      <div className="flex flex-shrink-0 items-center gap-1 sm:gap-2">
        <div className="relative" data-popover>
          <button
            type="button"
            onClick={() => {
              setNotificationsOpen((value) => !value);
              setUserOpen(false);
            }}
            className="relative flex h-10 w-10 items-center justify-center rounded-btn text-gray-600 hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-slate-800"
            aria-label="Notifications"
            aria-expanded={notificationsOpen}
            aria-controls="admin-notifications"
          >
            <Bell className="h-[18px] w-[18px]" />
            {unreadNotifications > 0 && (
              <span className="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-none text-white ring-2 ring-white dark:ring-slate-900">
                {unreadNotifications > 99 ? '99+' : unreadNotifications}
              </span>
            )}
          </button>
          {notificationsOpen && (
            <div
              id="admin-notifications"
              className="absolute right-0 top-12 z-50 w-[min(20rem,calc(100vw-2rem))] overflow-hidden rounded-card bg-white shadow-pop ring-1 ring-gray-100 dark:bg-slate-900 dark:ring-slate-700"
            >
              <div className="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-slate-700">
                <div className="text-sm font-semibold text-ui-text dark:text-ui-dark-text">
                  Notifications
                </div>
                <Link
                  href={routeHref('platform.notifications.index')}
                  className="text-xs font-semibold text-ui-green-dark hover:underline dark:text-emerald-300"
                >
                  View all
                </Link>
              </div>
              <div className="max-h-72 overflow-y-auto">
                {notifications.length === 0 ? (
                  <div className="px-4 py-6 text-center text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
                    No new alerts.
                  </div>
                ) : (
                  notifications.map((notification, index) => (
                    <Link
                      href={notification.href || routeHref('platform.notifications.index')}
                      key={`${notification.title}-${index}`}
                      className="flex gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-slate-800"
                    >
                      <Activity className="h-[18px] w-[18px] flex-shrink-0 text-ui-green" />
                      <div className="min-w-0">
                        <p className="text-sm text-ui-text dark:text-ui-dark-text">
                          {notification.title}
                        </p>
                        {notification.body && (
                          <p className="mt-0.5 line-clamp-2 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                            {notification.body}
                          </p>
                        )}
                        <p className="text-[11px] text-ui-text-muted dark:text-ui-dark-text-muted">
                          {notification.time}
                        </p>
                      </div>
                    </Link>
                  ))
                )}
              </div>
            </div>
          )}
        </div>

        <ThemeToggle />

        <div className="relative" data-popover>
          <button
            type="button"
            onClick={() => {
              setUserOpen((value) => !value);
              setNotificationsOpen(false);
            }}
            aria-label="Account menu"
            aria-expanded={userOpen}
            aria-controls="admin-account-menu"
            className="flex items-center gap-2 rounded-btn p-1 hover:bg-gray-100 dark:hover:bg-slate-800 sm:pr-2"
          >
            <Avatar name={user?.name || 'Ops Admin'} size="sm" />
            <ChevronDown className="hidden h-3.5 w-3.5 text-gray-400 sm:block" />
          </button>
          {userOpen && (
            <div
              id="admin-account-menu"
              className="absolute right-0 top-12 z-50 w-64 overflow-hidden rounded-card bg-white shadow-pop ring-1 ring-gray-100 dark:bg-slate-900 dark:ring-slate-700"
            >
              <div className="border-b border-gray-100 px-4 py-3 dark:border-slate-700">
                <div className="text-sm font-semibold text-ui-text dark:text-ui-dark-text">
                  {user?.name || 'Ops Admin'}
                </div>
                <div className="text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                  {user?.email || 'Platform admin'}
                </div>
              </div>
              <div className="py-1">
                <Link
                  href={routeHref('profile.edit')}
                  className="flex w-full items-center gap-3 px-4 py-2 text-sm text-ui-text hover:bg-gray-50 dark:text-ui-dark-text dark:hover:bg-slate-800"
                >
                  <UserRound className="h-4 w-4" /> Profile & password
                </Link>
              </div>
              {signOutHref !== '#' && (
                <div className="border-t border-gray-100 py-1 dark:border-slate-700">
                  <Link
                    href={signOutHref}
                    method="post"
                    as="button"
                    className="flex w-full items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/30"
                  >
                    <LogOut className="h-4 w-4" /> Sign out
                  </Link>
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </header>
  );
}

type PlatformSearchResult = {
  id: string;
  type: string;
  label: string;
  description?: string;
  href: string;
};

function PlatformGlobalSearch() {
  const inputRef = useRef<HTMLInputElement>(null);
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<PlatformSearchResult[]>([]);
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const handleKeyDown = (event: KeyboardEvent) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        inputRef.current?.focus();
        setOpen(true);
      }

      if (event.key === 'Escape') {
        setOpen(false);
        inputRef.current?.blur();
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, []);

  useEffect(() => {
    if (!open) {
      return;
    }

    const controller = new AbortController();
    const timer = window.setTimeout(
      async () => {
        setLoading(true);
        try {
          const response = await axios.get(route('platform.search'), {
            params: { q: query, limit: 8 },
            signal: controller.signal,
            headers: { Accept: 'application/json' },
          });
          setResults(response.data?.results || []);
        } catch (error: any) {
          if (error?.code !== 'ERR_CANCELED' && error?.name !== 'CanceledError') {
            setResults([]);
          }
        } finally {
          setLoading(false);
        }
      },
      query.trim().length >= 2 ? 180 : 0
    );

    return () => {
      window.clearTimeout(timer);
      controller.abort();
    };
  }, [query, open]);

  const visit = (href: string) => {
    setOpen(false);
    router.visit(href);
  };

  return (
    <div className="relative transition-all focus-within:scale-[1.01]">
      <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
      <input
        ref={inputRef}
        value={query}
        placeholder="Search workspaces, users, audit logs..."
        onClick={() => setOpen(true)}
        onChange={(event) => {
          setQuery(event.target.value);
          setOpen(true);
        }}
        className="h-10 w-full rounded-btn border border-transparent bg-gray-50 pl-10 pr-16 text-sm outline-none transition placeholder:text-gray-400 focus:border-ui-green focus:bg-white focus:ring-2 focus:ring-ui-green/15 dark:bg-slate-800 dark:text-ui-dark-text dark:focus:bg-slate-900"
      />
      <kbd className="absolute right-3 top-1/2 hidden -translate-y-1/2 items-center rounded border border-gray-200 bg-white px-1.5 py-0.5 text-[10px] font-medium text-gray-400 dark:border-slate-700 dark:bg-slate-900 sm:flex">
        Ctrl K
      </kbd>

      {open && (
        <>
          <div className="fixed inset-0 z-40" onClick={() => setOpen(false)} />
          <div className="absolute left-0 right-0 top-12 z-50 overflow-hidden rounded-card border border-gray-100 bg-white shadow-pop dark:border-slate-700 dark:bg-slate-900">
            <div className="flex items-center justify-between border-b border-gray-100 px-4 py-2 dark:border-slate-700">
              <span className="text-[11px] font-semibold uppercase tracking-wider text-ui-text-muted dark:text-ui-dark-text-muted">
                Platform search
              </span>
              {loading && <Loader2 className="h-3.5 w-3.5 animate-spin text-ui-green" />}
            </div>
            <div className="max-h-[60vh] overflow-y-auto py-1">
              {results.length > 0 ? (
                results.map((result) => {
                  const Icon = platformResultIcon(result.type);
                  return (
                    <button
                      key={`${result.type}-${result.id}`}
                      type="button"
                      onMouseDown={(event) => event.preventDefault()}
                      onClick={() => visit(result.href)}
                      className="flex w-full items-start gap-3 px-4 py-3 text-left transition hover:bg-gray-50 dark:hover:bg-slate-800"
                    >
                      <span className="mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-ui-green-soft text-ui-green-dark dark:bg-ui-green/10 dark:text-ui-green">
                        <Icon className="h-4 w-4" />
                      </span>
                      <span className="min-w-0 flex-1">
                        <span className="block truncate text-sm font-semibold text-ui-text dark:text-ui-dark-text">
                          {result.label}
                        </span>
                        {result.description && (
                          <span className="mt-0.5 block truncate text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                            {result.description}
                          </span>
                        )}
                        <span className="mt-1 block text-[10px] font-medium uppercase tracking-wider text-ui-green-dark dark:text-emerald-300">
                          {result.type.replace('_', ' ')}
                        </span>
                      </span>
                    </button>
                  );
                })
              ) : (
                <div className="px-4 py-8 text-center">
                  <div className="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-ui-green-soft text-ui-green-dark dark:bg-ui-green/10 dark:text-ui-green">
                    <Search className="h-4 w-4" />
                  </div>
                  <p className="mt-3 text-sm font-medium text-ui-text dark:text-ui-dark-text">
                    {query.trim().length >= 2 ? 'No matching results' : 'Search platform data'}
                  </p>
                  <p className="mt-1 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                    {query.trim().length >= 2
                      ? 'Try a workspace, user email, plan, subscription, or ticket.'
                      : 'Type at least two characters, or choose a common page.'}
                  </p>
                </div>
              )}
            </div>
          </div>
        </>
      )}
    </div>
  );
}

function platformResultIcon(type: string) {
  switch (type) {
    case 'workspace':
      return Building2;
    case 'user':
      return Users;
    case 'plan':
      return Layers;
    case 'subscription':
      return FileText;
    case 'transaction':
      return Wallet;
    case 'template':
      return FileText;
    case 'support':
      return LifeBuoy;
    case 'module':
      return Puzzle;
    default:
      return Search;
  }
}

export default function PlatformShell({ children, auth: authProp }: PlatformShellProps) {
  const { auth, ziggy } = usePage().props as any;
  const user = authProp?.user || auth?.user || null;
  const currentPath = usePage().url.split('?')[0];
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);

  useEffect(() => {
    setSidebarOpen(false);
  }, [currentPath]);

  useEffect(() => {
    if (ziggy) {
      (window as any).Ziggy = ziggy;
    }
  }, [ziggy]);

  useEffect(() => {
    const handleResize = () => {
      if (window.innerWidth >= 1024) {
        setSidebarOpen(false);
      }
    };

    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setSidebarOpen(false);
    };
    window.addEventListener('resize', handleResize);
    window.addEventListener('keydown', onKey);
    return () => {
      window.removeEventListener('resize', handleResize);
      window.removeEventListener('keydown', onKey);
    };
  }, []);

  const meta = useMemo(() => currentMeta(currentPath), [currentPath]);

  return (
    <BrandingWrapper>
      <div className="platform-admin flex h-[100dvh] overflow-hidden bg-ui-bg text-ui-text dark:bg-slate-950 dark:text-ui-dark-text">
        <AdminSidebar
          currentPath={currentPath}
          collapsed={sidebarCollapsed}
          mobileOpen={sidebarOpen}
          onClose={() => setSidebarOpen(false)}
          onToggle={() => setSidebarCollapsed((value) => !value)}
        />

        <div className="flex min-w-0 flex-1 flex-col">
          <AdminHeader
            title={meta.title}
            user={user}
            onMobileMenuClick={() => setSidebarOpen(true)}
          />
          <main
            id="admin-content"
            className="ui-scrollbar relative min-w-0 flex-1 overflow-y-auto p-4 lg:p-6"
          >
            <div className="mx-auto w-full max-w-[1440px]">
              <div className="mb-6">
                <h1 className="text-2xl font-bold tracking-tight">{meta.title}</h1>
                <p className="mt-1 text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
                  {meta.subtitle}
                </p>
              </div>
              {children}
<DemoBanner />
            </div>
          </main>
        </div>
      </div>
    </BrandingWrapper>
  );
}
