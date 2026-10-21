import { brandName } from '../../lib/brand';
import { Badge } from '@/Components/UI/Badge';
import { Avatar } from '@/Components/UI/Elements';
import { ThemeToggle } from '@/Components/UI/ThemeToggle';
import { cn } from '@/lib/utils';
import { Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import {
  Bell,
  Bot,
  Building2,
  ChevronDown,
  CreditCard,
  HelpCircle,
  Keyboard,
  LifeBuoy,
  Loader2,
  LogOut,
  Megaphone,
  Menu,
  MessageCircle,
  MessageSquareText,
  Plus,
  Reply,
  Search,
  Shield,
  Tags,
  User,
  Users,
  Wand2,
  X,
} from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';

interface TopbarProps {
  user: {
    name: string;
    email: string;
    is_super_admin?: boolean;
  } | null;
  onMenuClick?: () => void;
}

export function Topbar({ user, onMenuClick }: TopbarProps) {
  const { flash, impersonation, notification_summary, ziggy } = usePage().props as any;
  const currentRoute = usePage().url.split('?')[0];
  const currentOrigin = new URL(ziggy?.location || 'http://localhost').origin;
  const [showUserMenu, setShowUserMenu] = useState(false);
  const [showNotifications, setShowNotifications] = useState(false);
  const [showHelpMenu, setShowHelpMenu] = useState(false);
  const [mobileSearchOpen, setMobileSearchOpen] = useState(false);

  const tryRoute = (routeName: string, params: Record<string, unknown> = {}) => {
    try {
      return route(routeName, params);
    } catch (error) {
      return null;
    }
  };

  const routeTitles = [
    {
      route: 'app.dashboard',
      title: 'Dashboard',
      subtitle: 'Your workspace at a glance',
    },
    {
      route: 'app.whatsapp.conversations.index',
      title: 'Inbox',
      subtitle: 'Reply to customers on WhatsApp',
    },
    {
      route: 'app.whatsapp.templates.index',
      title: 'Templates',
      subtitle: 'Approved messages for campaigns and follow-ups',
    },
    {
      route: 'app.whatsapp.templates.library',
      title: 'Template library',
      subtitle: 'Start from a ready-made template',
    },
    {
      route: 'app.broadcasts.index',
      title: 'Campaigns',
      subtitle: 'Send and schedule messages to many contacts',
    },
    {
      route: 'app.contacts.index',
      title: 'Contacts',
      subtitle: 'Everyone you talk to on WhatsApp',
    },
    {
      route: 'app.contacts.segments.index',
      title: 'Segments',
      subtitle: 'Groups of contacts that update automatically',
    },
    {
      route: 'app.whatsapp.connections.index',
      title: 'WhatsApp account',
      subtitle: 'Your WhatsApp number and business profile',
    },
    { route: 'app.billing.index', title: 'Billing', subtitle: 'Your plan, usage, and invoices' },
    {
      route: 'app.workspaces.index',
      title: 'Workspaces',
      subtitle: 'Separate spaces for brands or clients',
    },
    { route: 'app.settings', title: 'Settings', subtitle: 'Workspace, profile, and security' },
    { route: 'app.activity-logs', title: 'Activity log', subtitle: 'Who did what, and when' },
    {
      route: 'app.notifications.index',
      title: 'Notifications',
      subtitle: 'Alerts and things that need your attention',
    },
    {
      route: 'app.team.index',
      title: 'Team',
      subtitle: 'Invite people and choose what they can do',
    },
    {
      route: 'app.modules',
      title: 'Tools',
      subtitle: 'QR codes, chat links, and handy utilities',
    },
    {
      route: 'app.floaters',
      title: 'Website widget',
      subtitle: 'Add a WhatsApp chat button to your website',
    },
    {
      route: 'app.developer.index',
      title: 'Developer API',
      subtitle: 'API keys and event notifications for your own apps',
    },
    {
      route: 'app.whatsapp-calls.index',
      title: 'Calls',
      subtitle: 'Take WhatsApp voice calls from customers',
    },
    {
      route: 'app.quick-replies.index',
      title: 'Quick replies',
      subtitle: 'Reusable replies for inbox agents',
    },
    {
      route: 'app.whatsapp.flows.index',
      title: 'WhatsApp forms',
      subtitle: 'Forms and interactive screens inside WhatsApp',
    },
    { route: 'app.support.index', title: 'Support', subtitle: 'Get help from our team' },
    {
      route: 'app.contacts.tags.index',
      title: 'Tags',
      subtitle: 'Label contacts to find and target them',
    },
    {
      route: 'app.chatbots.index',
      title: 'Chatbots',
      subtitle: 'Automatic replies and conversation flows',
    },
    { route: 'app.ai.index', title: 'AI assistant', subtitle: 'Let AI answer common questions' },
    {
      route: 'app.analytics.index',
      title: 'Analytics',
      subtitle: 'How your messages and campaigns perform',
    },
    {
      route: 'app.integrations.index',
      title: 'Integrations',
      subtitle: 'Connect the tools you already use',
    },
    {
      route: 'app.whatsapp.lists.index',
      title: 'List messages',
      subtitle: 'Menus customers can pick from',
    },
    {
      route: 'app.media-library.index',
      title: 'Media',
      subtitle: 'Images, videos, and files for your messages',
    },
    {
      route: 'app.meta-leads.index',
      title: 'Ad leads',
      subtitle: 'Leads from your Facebook and Instagram ads',
    },
    { route: 'app.appointments.index', title: 'Appointments', subtitle: 'Bookings and reminders' },
    {
      route: 'platform.dashboard',
      title: 'Platform admin',
      subtitle: 'System-wide operations and health',
    },
    {
      route: 'platform.accounts.index',
      title: 'Tenants',
      subtitle: 'Workspace and tenant management',
    },
    { route: 'platform.users.index', title: 'Users', subtitle: 'Platform users and permissions' },
    {
      route: 'platform.plans.index',
      title: 'Plans',
      subtitle: 'Plan catalog, pricing, and limits',
    },
    {
      route: 'platform.modules.index',
      title: 'Modules',
      subtitle: 'Feature modules and availability',
    },
    {
      route: 'platform.subscriptions.index',
      title: 'Subscriptions',
      subtitle: 'Customer subscriptions and status',
    },
    {
      route: 'platform.discounts.index',
      title: 'Discounts',
      subtitle: 'Promo and billing discount rules',
    },
    {
      route: 'platform.transactions.index',
      title: 'Transactions',
      subtitle: 'Wallet, invoices, and payment records',
    },
    {
      route: 'platform.analytics',
      title: 'Platform analytics',
      subtitle: 'System usage and growth metrics',
    },
    {
      route: 'platform.templates.index',
      title: 'Template library',
      subtitle: 'Platform message template oversight',
    },
    {
      route: 'platform.support.index',
      title: 'Support inbox',
      subtitle: 'Customer tickets and assistance',
    },
    {
      route: 'platform.activity-logs',
      title: 'Audit logs',
      subtitle: 'System events and admin activity',
    },
    {
      route: 'platform.notifications.index',
      title: 'Notifications',
      subtitle: 'Platform alerts and incident signals',
    },
    {
      route: 'platform.system-health',
      title: 'System health',
      subtitle: 'Infrastructure and integration status',
    },
    {
      route: 'platform.system-update',
      title: 'System update',
      subtitle: 'Upload a signed package',
    },
    {
      route: 'platform.settings',
      title: 'Platform settings',
      subtitle: 'Branding, payments, support, and compliance',
    },
  ]
    .map((item) => ({ ...item, href: tryRoute(item.route) }))
    .filter((item) => item.href);

  const activeMeta = routeTitles
    .sort((a, b) => String(b.href).length - String(a.href).length)
    .find((item) => {
      const pathname = new URL(String(item.href), currentOrigin).pathname;
      return currentRoute === pathname || currentRoute.startsWith(`${pathname}/`);
    });

  const title = activeMeta?.title || (user?.is_super_admin ? 'Platform admin' : 'Workspace');
  const hideRouteTitle =
    activeMeta?.route === 'app.settings' || activeMeta?.route === 'app.whatsapp.connections.index';
  const notificationHref = user?.is_super_admin
    ? tryRoute('platform.notifications.index')
    : tryRoute('app.notifications.index');
  const notificationItems = [
    flash?.success ? { tone: 'success', title: flash.success, time: 'Now' } : null,
    flash?.warning ? { tone: 'warning', title: flash.warning, time: 'Now' } : null,
    flash?.error ? { tone: 'danger', title: flash.error, time: 'Now' } : null,
    flash?.info ? { tone: 'info', title: flash.info, time: 'Now' } : null,
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
      href: item.action_url || notificationHref,
    })),
  ].filter(Boolean) as Array<{
    tone: 'success' | 'warning' | 'danger' | 'info';
    title: string;
    body?: string;
    time: string;
    href?: string;
  }>;
  const unreadNotifications = Number(notification_summary?.unread || 0);

  const campaignIndexHref = tryRoute('app.broadcasts.index');
  const campaignCreateHref = campaignIndexHref ? `${campaignIndexHref}?panel=create` : null;
  const helpHref = user?.is_super_admin
    ? tryRoute('platform.support.index')
    : tryRoute('app.support.index');
  const knowledgeBaseHref =
    tryRoute('knowledgebase') || tryRoute('marketing.knowledge-base') || '/knowledge-base';
  const contactHref = tryRoute('contact') || tryRoute('marketing.contact') || '/contact';
  const publicHelpHref = tryRoute('help') || tryRoute('marketing.help') || '/help';
  const effectiveHelpHref = helpHref || publicHelpHref;

  return (
    <header className="app-header relative z-[120] h-14 flex-shrink-0 border-b border-ui-border bg-white/95 px-4 backdrop-blur dark:border-ui-dark-border dark:bg-ui-dark-surface/95 sm:px-6">
      <div className="flex h-full items-center gap-2 sm:gap-4">
        {mobileSearchOpen && (
          <div className="fixed inset-x-0 top-0 z-50 flex h-14 items-center gap-2 border-b border-ui-border bg-white px-4 dark:border-ui-dark-border dark:bg-ui-dark-surface md:hidden">
            <Search className="h-4 w-4 shrink-0 text-gray-400" />
            <GlobalSearchInput mobile autoFocus onClose={() => setMobileSearchOpen(false)} />
            <button
              type="button"
              onClick={() => setMobileSearchOpen(false)}
              className="flex h-9 w-9 items-center justify-center rounded-btn text-ui-text-muted hover:bg-gray-100 dark:hover:bg-ui-dark-surface-2"
              aria-label="Close search"
            >
              <X className="h-4 w-4" />
            </button>
          </div>
        )}

        <div className="flex min-w-0 flex-1 items-center gap-3 lg:flex-none">
          {onMenuClick && (
            <button
              onClick={onMenuClick}
              className="flex h-9 w-9 shrink-0 items-center justify-center rounded-btn text-ui-text transition-colors hover:bg-gray-100 dark:text-ui-dark-text dark:hover:bg-ui-dark-surface-2 lg:hidden"
              aria-label="Open menu"
            >
              <Menu className="h-5 w-5" aria-hidden />
            </button>
          )}

          {!hideRouteTitle && (
            <div className="min-w-0">
              <div className="flex min-w-0 items-center gap-2">
                <h1 className="truncate text-sm font-semibold leading-tight text-ui-text dark:text-ui-dark-text">
                  {title}
                </h1>
              </div>
            </div>
          )}
        </div>

        <div className="mx-auto hidden max-w-xl flex-1 md:block">
          <GlobalSearchInput />
        </div>

        <div className="flex-1 md:hidden" />

        <div className="flex shrink-0 items-center gap-1 sm:gap-2">
          <button
            type="button"
            onClick={() => setMobileSearchOpen(true)}
            className="flex h-9 w-9 items-center justify-center rounded-btn text-gray-600 transition hover:bg-gray-100 dark:text-ui-dark-text-muted dark:hover:bg-ui-dark-surface-2 md:hidden"
            aria-label="Search"
          >
            <Search className="h-4 w-4" />
          </button>

          <div className="relative">
            <button
              type="button"
              onClick={() => {
                setShowNotifications((value) => !value);
                setShowUserMenu(false);
                setShowHelpMenu(false);
              }}
              className="relative flex h-10 w-10 items-center justify-center rounded-btn text-gray-600 transition hover:bg-gray-100 dark:text-ui-dark-text-muted dark:hover:bg-ui-dark-surface-2"
              aria-label="Notifications"
            >
              <Bell className="h-[18px] w-[18px]" />
              {unreadNotifications > 0 && (
                <span className="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-none text-white ring-2 ring-white dark:ring-ui-dark-surface">
                  {unreadNotifications > 99 ? '99+' : unreadNotifications}
                </span>
              )}
            </button>
            {showNotifications && (
              <>
                <div className="fixed inset-0 z-10" onClick={() => setShowNotifications(false)} />
                <div className="absolute right-0 top-12 z-20 w-80 overflow-hidden rounded-card border border-gray-100 bg-white shadow-pop dark:border-ui-dark-border dark:bg-ui-dark-surface">
                  <div className="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-ui-dark-border">
                    <div className="text-sm font-semibold text-ui-text dark:text-ui-dark-text">
                      Notifications
                    </div>
                    {notificationHref && (
                      <Link
                        href={notificationHref}
                        className="text-xs font-medium text-ui-green-dark hover:underline dark:text-emerald-200"
                      >
                        View all
                      </Link>
                    )}
                  </div>
                  <div className="max-h-96 overflow-y-auto py-1">
                    {notificationItems.length > 0 ? (
                      notificationItems.map((notification, index) => (
                        <Link
                          href={notification.href || notificationHref || '#'}
                          key={`${notification.tone}-${index}`}
                          className="flex gap-3 px-4 py-3 text-sm hover:bg-gray-50 dark:hover:bg-ui-dark-surface-2"
                        >
                          <span
                            className={cn(
                              'mt-1 h-2 w-2 shrink-0 rounded-full',
                              notification.tone === 'success' && 'bg-emerald-500',
                              notification.tone === 'warning' && 'bg-amber-500',
                              notification.tone === 'danger' && 'bg-red-500',
                              notification.tone === 'info' && 'bg-ui-green'
                            )}
                          />
                          <div className="min-w-0 flex-1">
                            <p className="leading-snug text-ui-text dark:text-ui-dark-text">
                              {notification.title}
                            </p>
                            {notification.body && (
                              <p className="mt-0.5 line-clamp-2 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                                {notification.body}
                              </p>
                            )}
                            <p className="mt-0.5 text-[11px] text-ui-text-muted dark:text-ui-dark-text-muted">
                              {notification.time}
                            </p>
                          </div>
                        </Link>
                      ))
                    ) : (
                      <div className="px-4 py-8 text-center">
                        <div className="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-ui-green-soft text-ui-green-dark dark:bg-ui-dark-green-soft dark:text-emerald-200">
                          <Bell className="h-4 w-4" />
                        </div>
                        <p className="mt-3 text-sm font-medium text-ui-text dark:text-ui-dark-text">
                          No new notifications
                        </p>
                        <p className="mt-1 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                          Recent activity appears here after actions complete.
                        </p>
                      </div>
                    )}
                  </div>
                </div>
              </>
            )}
          </div>

          <ThemeToggle />

          <div className="relative hidden md:block">
            <button
              type="button"
              onClick={() => {
                setShowHelpMenu((value) => !value);
                setShowNotifications(false);
                setShowUserMenu(false);
              }}
              className="flex h-10 w-10 items-center justify-center rounded-btn text-gray-600 transition hover:bg-gray-100 dark:text-ui-dark-text-muted dark:hover:bg-ui-dark-surface-2"
              aria-label="Help"
              aria-haspopup="menu"
              aria-expanded={showHelpMenu}
            >
              <HelpCircle className="h-[18px] w-[18px]" />
            </button>
            {showHelpMenu && (
              <>
                <div className="fixed inset-0 z-10" onClick={() => setShowHelpMenu(false)} />
                <div className="absolute right-0 top-12 z-20 w-64 overflow-hidden rounded-card border border-gray-100 bg-white shadow-pop dark:border-ui-dark-border dark:bg-ui-dark-surface">
                  <div className="border-b border-gray-100 px-4 py-3 dark:border-ui-dark-border">
                    <div className="text-sm font-semibold text-ui-text dark:text-ui-dark-text">
                      Help
                    </div>
                    <p className="mt-0.5 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                      Get support without leaving your workspace.
                    </p>
                  </div>
                  <div className="py-1">
                    <TopbarMenuLink
                      href={effectiveHelpHref}
                      icon={<LifeBuoy className="h-4 w-4" />}
                    >
                      Support center
                    </TopbarMenuLink>
                    <TopbarMenuLink
                      href={knowledgeBaseHref}
                      icon={<MessageSquareText className="h-4 w-4" />}
                    >
                      Knowledge base
                    </TopbarMenuLink>
                    <TopbarMenuLink href={contactHref} icon={<MessageCircle className="h-4 w-4" />}>
                      Contact {brandName()}
                    </TopbarMenuLink>
                  </div>
                </div>
              </>
            )}
          </div>

          {campaignCreateHref && !user?.is_super_admin && (
            <>
              <Link
                href={campaignCreateHref}
                className="hidden h-10 items-center gap-2 rounded-btn bg-ui-green px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-ui-green-dark sm:inline-flex"
              >
                <Plus className="h-4 w-4" />
                New Campaign
              </Link>
              <Link
                href={campaignCreateHref}
                className="flex h-9 w-9 items-center justify-center rounded-btn bg-ui-green text-white shadow-sm transition hover:bg-ui-green-dark sm:hidden"
                aria-label="New campaign"
              >
                <Plus className="h-4 w-4" />
              </Link>
            </>
          )}

          {user && (
            <div className="relative sm:pl-1">
              <button
                type="button"
                onClick={() => {
                  setShowUserMenu((value) => !value);
                  setShowNotifications(false);
                  setShowHelpMenu(false);
                }}
                className="flex items-center gap-2 rounded-btn p-1 transition hover:bg-gray-100 dark:hover:bg-ui-dark-surface-2 sm:pr-2"
              >
                <Avatar name={user.name} size="sm" />
                <ChevronDown className="hidden h-3.5 w-3.5 text-gray-400 sm:block" />
              </button>
              {showUserMenu && (
                <>
                  <div className="fixed inset-0 z-10" onClick={() => setShowUserMenu(false)} />
                  <div className="absolute right-0 top-12 z-20 w-64 overflow-hidden rounded-card border border-gray-100 bg-white shadow-pop dark:border-ui-dark-border dark:bg-ui-dark-surface">
                    <div className="border-b border-gray-100 px-4 py-3 dark:border-ui-dark-border">
                      <div className="flex items-center gap-3">
                        <Avatar name={user.name} />
                        <div className="min-w-0 flex-1">
                          <p className="truncate text-sm font-semibold text-ui-text dark:text-ui-dark-text">
                            {user.name}
                          </p>
                          <p className="truncate text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                            {user.email}
                          </p>
                        </div>
                        {user.is_super_admin && (
                          <Badge
                            variant="info"
                            className="flex items-center gap-1 px-2 py-0.5 text-xs"
                          >
                            <Shield className="h-3 w-3" />
                            Admin
                          </Badge>
                        )}
                      </div>
                    </div>
                    <div className="py-1">
                      <TopbarMenuLink
                        href={`${route('app.settings')}?tab=profile`}
                        icon={<User className="h-4 w-4" />}
                      >
                        Profile
                      </TopbarMenuLink>
                      {!user.is_super_admin && (
                        <>
                          <TopbarMenuLink
                            href={route('app.team.index')}
                            icon={<Users className="h-4 w-4" />}
                          >
                            Team
                          </TopbarMenuLink>
                        </>
                      )}
                      <button className="flex w-full items-center gap-3 px-4 py-2 text-left text-sm text-ui-text transition hover:bg-gray-50 dark:text-ui-dark-text dark:hover:bg-ui-dark-surface-2">
                        <Keyboard className="h-4 w-4 text-gray-500 dark:text-ui-dark-text-muted" />
                        Keyboard shortcuts
                      </button>
                      <TopbarMenuLink
                        href={effectiveHelpHref}
                        icon={<LifeBuoy className="h-4 w-4" />}
                      >
                        Support
                      </TopbarMenuLink>
                    </div>
                    <div className="border-t border-gray-100 py-1 dark:border-ui-dark-border">
                      <Link
                        href={route('logout')}
                        method="post"
                        className="flex items-center gap-3 px-4 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                      >
                        <LogOut className="h-4 w-4" />
                        Sign out
                      </Link>
                    </div>
                  </div>
                </>
              )}
            </div>
          )}
        </div>
      </div>
    </header>
  );
}

function TopbarMenuLink({
  href,
  icon,
  children,
}: {
  href: string;
  icon: ReactNode;
  children: ReactNode;
}) {
  return (
    <Link
      href={href}
      className="flex items-center gap-3 px-4 py-2 text-sm text-ui-text transition hover:bg-gray-50 dark:text-ui-dark-text dark:hover:bg-ui-dark-surface-2"
    >
      <span className="text-gray-500 dark:text-ui-dark-text-muted">{icon}</span>
      {children}
    </Link>
  );
}

type SearchResult = {
  id: string;
  type: string;
  label: string;
  description?: string;
  href: string;
};

function GlobalSearchInput({
  mobile = false,
  autoFocus = false,
  onClose,
}: {
  mobile?: boolean;
  autoFocus?: boolean;
  onClose?: () => void;
}) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<SearchResult[]>([]);
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (autoFocus) {
      window.setTimeout(() => {
        inputRef.current?.focus();
        setOpen(true);
      }, 30);
    }
  }, [autoFocus]);

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
        onClose?.();
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [onClose]);

  useEffect(() => {
    if (!open) {
      return;
    }

    const controller = new AbortController();
    const timer = window.setTimeout(
      async () => {
        setLoading(true);
        try {
          const response = await axios.get(route('app.search'), {
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
    onClose?.();
    router.visit(href);
  };

  return (
    <div
      className={cn(
        'relative',
        mobile ? 'min-w-0 flex-1' : 'transition-all focus-within:scale-[1.01]'
      )}
    >
      {!mobile && (
        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
      )}
      <input
        ref={inputRef}
        value={query}
        onChange={(event) => {
          setQuery(event.target.value);
          setOpen(true);
        }}
        onClick={() => setOpen(true)}
        placeholder="Search campaigns, contacts, templates..."
        className={cn(
          'h-10 w-full border-0 text-sm outline-none ring-0 transition placeholder:text-gray-400 focus:ring-0',
          mobile
            ? 'bg-transparent px-0 text-ui-text dark:text-ui-dark-text'
            : 'rounded-btn border border-transparent bg-gray-50 pl-10 pr-16 focus:border-ui-green focus:bg-white focus:ring-2 focus:ring-ui-green/15 dark:bg-ui-dark-surface-2 dark:text-ui-dark-text dark:focus:bg-ui-dark-surface'
        )}
      />
      {!mobile && (
        <kbd className="absolute right-3 top-1/2 hidden -translate-y-1/2 items-center rounded border border-gray-200 bg-white px-1.5 py-0.5 text-[10px] font-medium text-gray-400 dark:border-ui-dark-border dark:bg-ui-dark-surface sm:flex">
          Ctrl K
        </kbd>
      )}

      {open && (
        <>
          <div className="fixed inset-0 z-[130]" onClick={() => setOpen(false)} />
          <div
            className={cn(
              'absolute z-[150] overflow-hidden rounded-card border border-gray-100 bg-white shadow-pop dark:border-ui-dark-border dark:bg-ui-dark-surface',
              mobile ? 'left-[-2rem] right-[-3rem] top-12' : 'left-0 right-0 top-12'
            )}
          >
            <div className="flex items-center justify-between border-b border-gray-100 px-4 py-2 dark:border-ui-dark-border">
              <span className="text-[11px] font-semibold uppercase tracking-wider text-ui-text-muted dark:text-ui-dark-text-muted">
                Global search
              </span>
              {loading && <Loader2 className="h-3.5 w-3.5 animate-spin text-ui-green" />}
            </div>
            <div className="max-h-[60vh] overflow-y-auto py-1">
              {results.length > 0 ? (
                results.map((result) => {
                  const Icon = resultIcon(result.type);
                  return (
                    <button
                      key={`${result.type}-${result.id}`}
                      type="button"
                      onMouseDown={(event) => event.preventDefault()}
                      onClick={() => visit(result.href)}
                      className="flex w-full items-start gap-3 px-4 py-3 text-left transition hover:bg-gray-50 dark:hover:bg-ui-dark-surface-2"
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
                          {resultTypeLabel(result.type)}
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
                    {query.trim().length >= 2 ? 'No matching results' : 'Search workspace data'}
                  </p>
                  <p className="mt-1 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                    {query.trim().length >= 2
                      ? 'Try a contact name, phone number, campaign, or template.'
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

function resultIcon(type: string) {
  switch (type) {
    case 'contact':
      return Users;
    case 'conversation':
      return MessageCircle;
    case 'template':
      return MessageSquareText;
    case 'campaign':
      return Megaphone;
    case 'segment':
      return Tags;
    case 'quick_reply':
      return Reply;
    case 'connection':
      return Building2;
    case 'widget':
      return Wand2;
    case 'chatbot':
      return Bot;
    default:
      return Search;
  }
}

function resultTypeLabel(type: string) {
  return type.replace('_', ' ');
}
