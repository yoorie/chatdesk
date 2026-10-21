import BrandLogo from '@/Components/Branding/BrandLogo';
import { cn } from '@/lib/utils';
import { Link, usePage } from '@inertiajs/react';
import {
  Activity,
  BarChart3,
  Bell,
  Bot,
  Building2,
  Calendar,
  CalendarRange,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  ChevronsUpDown,
  ClipboardList,
  Code2,
  CreditCard,
  ExternalLink,
  FileText,
  FolderOpen,
  GitBranch,
  HelpCircle,
  History,
  Image,
  Inbox,
  Layers,
  Layout,
  LayoutDashboard,
  LifeBuoy,
  Link2,
  List,
  LucideIcon,
  Megaphone,
  MessageCircle,
  PhoneCall,
  Plug,
  Puzzle,
  Radio,
  RefreshCw,
  Settings,
  Shield,
  ShoppingBag,
  Sparkles,
  Store,
  Tag,
  Target,
  Users,
  Workflow,
  Wrench,
  X,
  Zap,
} from 'lucide-react';
import { useEffect, useState } from 'react';

const iconMap: Record<string, LucideIcon> = {
  LayoutDashboard,
  Puzzle,
  MessageCircle,
  FileText,
  Inbox,
  Bot,
  Sparkles,
  Zap,
  BarChart3,
  CreditCard,
  LifeBuoy,
  Settings,
  Users,
  Activity,
  Megaphone,
  Building2,
  List,
  Tag,
  FolderOpen,
  Layers,
  Workflow,
  Wrench,
  Layout,
  Link2,
  CalendarRange,
  Plug,
  HelpCircle,
  Shield,
  Code2,
  History,
  ShoppingBag,
  Target,
  Calendar,
  ClipboardList,
  ExternalLink,
  Image,
  Radio,
  RefreshCw,
  Bell,
  Store,
  PhoneCall,
  GitBranch,
  ChevronsUpDown,
};

interface NavItem {
  label: string;
  href: string;
  icon: string;
  group?: string;
}

interface SidebarProps {
  navigation: NavItem[];
  currentRoute: string;
  account: {
    slug?: string;
    name?: string;
    plan?: { name?: string } | null;
    subscription?: { plan?: { name?: string } | null } | null;
    workspace_type_label?: string | null;
    workspace_type?: string | null;
  } | null;
  isOpen?: boolean;
  onClose?: () => void;
  onCollapseChange?: (collapsed: boolean) => void;
}

interface NavSection {
  label: string;
  items: Array<NavItem & { order: number; locked?: boolean; requiredPlan?: string | null }>;
}

/**
 * Customer sidebar groups. "Main" renders without a heading.
 * Keep labels short and in plain language; the same page always has the same name.
 */
const sectionOrder = ['Main', 'Audience', 'Messages', 'Automation', 'Settings', 'Platform'];

/** Groups the user can fold away. "Main" always stays open. */
const collapsibleSections = new Set(['Audience', 'Messages', 'Automation', 'Settings', 'Platform']);
const COLLAPSED_SECTIONS_KEY = 'app.sidebar.collapsed-sections';

const navPlacement: Record<
  string,
  { section: string; order: number; icon?: string; label?: string }
> = {
  'app.dashboard': { section: 'Main', order: 10, icon: 'LayoutDashboard', label: 'Dashboard' },
  'app.whatsapp.conversations.index': {
    section: 'Main',
    order: 20,
    icon: 'MessageCircle',
    label: 'Inbox',
  },

  'app.contacts.index': { section: 'Main', order: 30, icon: 'Users', label: 'Contacts' },
  'app.contacts.segments.index': {
    section: 'Audience',
    order: 20,
    icon: 'Layers',
    label: 'Segments',
  },
  'app.contacts.tags.index': { section: 'Audience', order: 30, icon: 'Tag', label: 'Tags' },

  'app.broadcasts.index': { section: 'Main', order: 40, icon: 'Megaphone', label: 'Campaigns' },

  'app.whatsapp.templates.index': {
    section: 'Messages',
    order: 10,
    icon: 'FileText',
    label: 'Templates',
  },
  'app.quick-replies.index': {
    section: 'Messages',
    order: 20,
    icon: 'Zap',
    label: 'Quick replies',
  },
  'app.whatsapp.lists.index': {
    section: 'Messages',
    order: 30,
    icon: 'List',
    label: 'List messages',
  },
  'app.whatsapp.flows.index': {
    section: 'Messages',
    order: 40,
    icon: 'GitBranch',
    label: 'WhatsApp forms',
  },

  'app.chatbots.index': { section: 'Automation', order: 10, icon: 'Workflow', label: 'Chatbots' },

  'app.whatsapp.connections.index': {
    section: 'Settings',
    order: 10,
    icon: 'Link2',
    label: 'WhatsApp account',
  },
  'app.team.index': { section: 'Settings', order: 30, icon: 'Users', label: 'Team' },
  'app.settings': { section: 'Settings', order: 50, icon: 'Settings', label: 'Settings' },
};

const hiddenUntilRealImplementation = new Set([
  'app.catalog.index',
  'app.ecommerce.index',
  'app.surveys.index',
]);

/**
 * Pages that stay reachable but do not need their own sidebar row:
 * Settings sub-navigation covers workspaces, activity and the developer API,
 * the top bar bell covers notifications, and the footer links to support.
 */
const reachableElsewhere = new Set([
  'app.activity-logs',
  'app.developer.index',
  'app.notifications.index',
]);

interface LockedNavItem extends NavItem {
  required_plan?: string | null;
}

export function Sidebar({
  navigation,
  currentRoute,
  account,
  isOpen = false,
  onClose,
  onCollapseChange,
}: SidebarProps) {
  const { auth, inbox_summary, locked_navigation } = usePage().props as any;
  const lockedNavigation: LockedNavItem[] = Array.isArray(locked_navigation)
    ? locked_navigation
    : [];
  const user = auth?.user;
  const inboxUnread = Number(inbox_summary?.unread || 0);
  const [collapsed, setCollapsed] = useState(false);
  const [foldedSections, setFoldedSections] = useState<string[]>([]);

  useEffect(() => {
    try {
      const saved = JSON.parse(window.localStorage.getItem(COLLAPSED_SECTIONS_KEY) || '[]');
      if (Array.isArray(saved)) {
        setFoldedSections(saved.filter((value) => typeof value === 'string'));
      }
    } catch {
      // Ignore unreadable saved state.
    }
  }, []);

  const toggleSection = (label: string) => {
    setFoldedSections((current) => {
      const next = current.includes(label)
        ? current.filter((value) => value !== label)
        : [...current, label];
      try {
        window.localStorage.setItem(COLLAPSED_SECTIONS_KEY, JSON.stringify(next));
      } catch {
        // Storage can be unavailable (private mode); folding still works for this visit.
      }
      return next;
    });
  };

  const tryRouteHref = (routeName: string): string | null => {
    try {
      return route(routeName, {});
    } catch (error) {
      return null;
    }
  };

  const resolveRouteHref = (routeName: string): string | null => {
    const direct = tryRouteHref(routeName);
    if (direct) {
      return direct;
    }

    // Backward/forward compatible route-name fallback: app.foo <-> app.foo.index
    if (routeName.endsWith('.index')) {
      return tryRouteHref(routeName.slice(0, -'.index'.length));
    }

    return tryRouteHref(`${routeName}.index`);
  };

  const supportHref = 'https://github.com/yoorie/chatdesk/issues';
  const profileHref = tryRouteHref('app.settings')
    ? `${tryRouteHref('app.settings')}?tab=profile`
    : '#';

  const accountDescriptor =
    account?.subscription?.plan?.name ||
    account?.plan?.name ||
    account?.workspace_type_label ||
    account?.workspace_type ||
    'Workspace';

  const safeNavigation = navigation.reduce<NavItem[]>((items, item) => {
    if (hiddenUntilRealImplementation.has(item.href) || reachableElsewhere.has(item.href)) {
      return items;
    }

    if (!item.href || items.some((existing) => existing.href === item.href)) {
      return items;
    }

    items.push(item);
    return items;
  }, []);

  // Features the current plan does not include: show them, locked, with an upgrade hint.
  const lockedItems = lockedNavigation.filter(
    (item) =>
      item.href &&
      navPlacement[item.href] &&
      !hiddenUntilRealImplementation.has(item.href) &&
      !safeNavigation.some((existing) => existing.href === item.href)
  );

  type PlacedItem = NavItem & { order: number; locked?: boolean; requiredPlan?: string | null };
  const sections = [
    ...safeNavigation,
    ...lockedItems.map((item) => ({ ...item, locked: true })),
  ].reduce<Record<string, PlacedItem[]>>(
    (acc, item: NavItem & { locked?: boolean; required_plan?: string | null }) => {
      const placement = navPlacement[item.href] ?? {
        section: item.group || 'Workspace',
        order: 100,
      };

      if (!acc[placement.section]) {
        acc[placement.section] = [];
      }

      acc[placement.section].push({
        ...item,
        label: placement.label ?? item.label,
        icon: placement.icon ?? item.icon,
        order: placement.order,
        locked: Boolean(item.locked),
        requiredPlan: item.required_plan ?? null,
      });

      return acc;
    },
    {}
  );

  const orderedSections: NavSection[] = sectionOrder
    .map((label) => ({
      label,
      items: (sections[label] || []).sort((a, b) => a.order - b.order),
    }))
    .filter((section) => section.items.length > 0);

  const isCurrentPath = (href: string): boolean => {
    try {
      const pathname = new URL(href, 'http://localhost').pathname;
      return currentRoute === pathname || currentRoute.startsWith(`${pathname}/`);
    } catch (error) {
      return false;
    }
  };

  const renderNavItem = (item: PlacedItem, index: number) => {
    const Icon = iconMap[item.icon] || LayoutDashboard;

    const resolved = resolveRouteHref(item.href);
    const unreadCount = item.href === 'app.whatsapp.conversations.index' ? inboxUnread : 0;

    if (!resolved) {
      return null;
    }

    const isActive = isCurrentPath(resolved);

    return (
      <Link
        key={`${item.href}-${item.label}-${index}`}
        href={resolved}
        prefetch="hover"
        cacheFor="10s"
        aria-current={isActive ? 'page' : undefined}
        className={cn(
          'group relative flex h-8 items-center gap-2.5 rounded-md text-[13px] transition-colors',
          collapsed ? 'px-2.5 lg:justify-center lg:px-0' : 'px-2.5',
          isActive
            ? 'bg-white/10 font-semibold text-white'
            : 'font-medium text-white/65 hover:bg-white/5 hover:text-white'
        )}
        title={collapsed ? item.label : undefined}
      >
        {isActive && (
          <span className="absolute -left-2 top-1.5 bottom-1.5 w-[3px] rounded-r-full bg-ui-green" />
        )}
        <Icon
          className={cn(
            'h-4 w-4 shrink-0',
            isActive ? 'text-ui-green' : 'text-white/50 group-hover:text-white/80'
          )}
        />
        <span className={cn('flex-1 truncate', collapsed ? 'lg:hidden' : '')}>{item.label}</span>
        {unreadCount > 0 && (
          <span
            className={cn(
              'flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-ui-green px-1 text-[10px] font-bold leading-none text-white',
              collapsed
                ? 'lg:absolute lg:right-0.5 lg:top-0.5 lg:h-3.5 lg:min-w-3.5 lg:text-[8px]'
                : ''
            )}
          >
            {unreadCount > 99 ? '99+' : unreadCount}
          </span>
        )}
      </Link>
    );
  };

  const sidebarContent = (
    <nav
      aria-label="Main"
      className={cn(
        'ui-scrollbar min-h-0 flex-1 space-y-3 overflow-y-auto py-3',
        collapsed ? 'px-2 lg:px-2' : 'px-2'
      )}
    >
      {orderedSections
        .map((section) => {
          // A folded group still opens when you are on one of its pages.
          const hasActive = section.items.some((item) => {
            if (item.locked) return false;
            const href = resolveRouteHref(item.href);
            return href ? isCurrentPath(href) : false;
          });
          const canFold = collapsibleSections.has(section.label);
          const folded = canFold && !hasActive && foldedSections.includes(section.label);
          const validItems = section.items.map(renderNavItem).filter((item) => item !== null);

          if (validItems.length === 0) {
            return null;
          }

          return (
            <div key={section.label}>
              {section.label !== 'Main' && (
                <button
                  type="button"
                  onClick={() => canFold && toggleSection(section.label)}
                  aria-expanded={!folded}
                  className={cn(
                    'group/heading mb-0.5 flex h-6 w-full items-center gap-1 rounded px-2.5 text-[10.5px] font-semibold uppercase tracking-wider text-white/35 transition-colors hover:text-white/60',
                    collapsed ? 'lg:hidden' : ''
                  )}
                >
                  <span className="flex-1 text-left">{section.label}</span>
                  {canFold && (
                    <ChevronDown
                      className={cn(
                        'h-3 w-3 opacity-0 transition group-hover/heading:opacity-100',
                        folded ? '-rotate-90 opacity-100' : ''
                      )}
                    />
                  )}
                </button>
              )}
              {collapsed && section.label !== 'Main' && (
                <div className="mx-2 mb-1.5 hidden border-t border-white/10 lg:block" />
              )}
              <div
                className={cn(
                  'space-y-px',
                  folded ? 'hidden' : '',
                  folded && collapsed ? 'lg:block' : ''
                )}
              >
                {validItems}
              </div>
            </div>
          );
        })
        .filter(Boolean)}
      <a
        href="https://chatdesk.yoorie.com"
        target="_blank"
        rel="noreferrer"
        title="Want to run it as a SaaS, or need more? Get a paid plan or use Zyptos."
        className={cn(
          'mt-2 flex items-center gap-2 rounded-lg bg-white/10 py-2 text-xs font-semibold text-white transition hover:bg-white/20',
          collapsed ? 'px-2.5 lg:justify-center lg:px-0' : 'px-2.5'
        )}
      >
        <span aria-hidden="true">★</span>
        <span className={cn('flex-1 truncate', collapsed ? 'lg:hidden' : '')}>Upgrade</span>
      </a>
    </nav>
  );

  const userInitials = (user?.name || 'U')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part: string) => part.charAt(0).toUpperCase())
    .join('');

  const sidebarFooter = (
    <div
      className={cn(
        'flex flex-shrink-0 items-center gap-1 border-t border-white/5 p-2',
        collapsed ? 'lg:flex-col' : ''
      )}
    >
      {user && (
        <Link
          href={profileHref}
          className={cn(
            'flex min-w-0 flex-1 items-center gap-2.5 rounded-md p-1.5 transition-colors hover:bg-white/5',
            collapsed ? 'lg:flex-none lg:justify-center' : ''
          )}
          title={collapsed ? user.name : 'Your profile'}
        >
          <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-ui-green text-[11px] font-bold text-white">
            {userInitials}
          </span>
          <span className={cn('min-w-0 flex-1 text-left', collapsed ? 'lg:hidden' : '')}>
            <span className="block truncate text-[13px] font-medium leading-tight text-white">
              {user.name}
            </span>
            <span className="block truncate text-[11px] leading-tight text-white/45">
              {accountDescriptor}
            </span>
          </span>
        </Link>
      )}
      {!auth?.user?.is_super_admin && (
        <a
          href={supportHref}
          target="_blank"
          rel="noopener noreferrer"
          className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-white/50 transition-colors hover:bg-white/5 hover:text-white"
          title="Help & support"
          aria-label="Help & support"
        >
          <LifeBuoy className="h-4 w-4" />
        </a>
      )}
    </div>
  );

  const workspaceSwitcher = null;

  return (
    <>
      {/* Mobile sidebar overlay */}
      {isOpen && (
        <div
          className="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm transition-opacity lg:hidden"
          onClick={onClose}
        />
      )}

      {/* Mobile sidebar */}
      <aside
        className={cn(
          'fixed inset-y-0 left-0 z-50 flex w-64 transform flex-col bg-ui-sidebar text-white shadow-2xl transition-transform duration-300 ease-in-out lg:hidden',
          isOpen ? 'translate-x-0' : '-translate-x-full'
        )}
      >
        <div className="flex h-14 flex-shrink-0 items-center justify-between px-4">
          <BrandLogo variant="dark" fallbackTextClassName="text-white" />
          <button
            type="button"
            onClick={onClose}
            className="rounded-md p-1.5 transition-colors hover:bg-white/10"
            aria-label="Close sidebar"
          >
            <X className="h-4 w-4 text-white/70" aria-hidden />
          </button>
        </div>
        {workspaceSwitcher}
        {sidebarContent}
        {sidebarFooter}
      </aside>

      {/* Desktop sidebar */}
      <aside
        className={cn(
          'hidden min-h-0 bg-ui-sidebar text-white transition-all duration-300 lg:fixed lg:inset-y-0 lg:z-30 lg:flex lg:flex-col',
          collapsed ? 'lg:w-14' : 'lg:w-56'
        )}
      >
        <div
          className={cn(
            'flex h-14 flex-shrink-0 items-center',
            collapsed ? 'justify-center px-2' : 'px-4'
          )}
        >
          <BrandLogo variant="dark" compact={collapsed} fallbackTextClassName="text-white" />
        </div>
        <button
          type="button"
          onClick={() => {
            const nextCollapsed = !collapsed;
            setCollapsed(nextCollapsed);
            onCollapseChange?.(nextCollapsed);
          }}
          aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
          title={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
          className="absolute -right-2.5 top-[18px] z-10 hidden h-5 w-5 items-center justify-center rounded-full bg-white text-ui-text shadow-pop ring-1 ring-gray-200 hover:bg-gray-50 lg:flex"
        >
          {collapsed ? <ChevronRight className="h-3 w-3" /> : <ChevronLeft className="h-3 w-3" />}
        </button>
        {workspaceSwitcher}
        {sidebarContent}
        {sidebarFooter}
      </aside>
    </>
  );
}
