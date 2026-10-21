import { appUrl } from '@/lib/appUrl';
import { brandName } from '../../lib/brand';
import AnalyticsScripts from '@/Components/Analytics/AnalyticsScripts';
import { BrandingWrapper } from '@/Components/Branding/BrandingWrapper';
import BrandLogo from '@/Components/Branding/BrandLogo';
import CookieConsentBanner from '@/Components/Compliance/CookieConsentBanner';
import ProviderLogo from '@/Components/Integrations/ProviderLogo';
import SeoHead from '@/Components/SEO/SeoHead';
import { Badge } from '@/Components/UI/Badge';
import BaseButton from '@/Components/UI/Button';
import { ThemeToggle } from '@/Components/UI/ThemeToggle';
import { cn } from '@/lib/utils';
import { Link, usePage } from '@inertiajs/react';
import {
  ArrowRight,
  ArrowUpRight,
  BarChart2,
  Bell,
  Check,
  ChevronDown,
  ChevronUp,
  Clock,
  CreditCard,
  FileCheck,
  FileText,
  HelpCircle,
  Key,
  LayoutDashboard,
  Link2,
  Mail,
  MapPin,
  Megaphone,
  Menu,
  MessageCircle,
  PlayCircle,
  Rocket,
  Search,
  Send,
  Server,
  Shield,
  Sparkles,
  Star,
  Target,
  TrendingUp,
  Users,
  Wand2,
  Workflow,
  X,
  Zap,
  type LucideIcon,
} from 'lucide-react';
import {
  ComponentProps,
  HTMLAttributes,
  PropsWithChildren,
  ReactNode,
  useEffect,
  useMemo,
  useState,
} from 'react';

function Card({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return (
    <div
      className={cn(
        'rounded-xl border border-slate-100 bg-white shadow-none dark:border-slate-800 dark:bg-slate-900/40',
        className
      )}
      {...props}
    />
  );
}

function Button({ className, size = 'lg', ...props }: ComponentProps<typeof BaseButton>) {
  return (
    <BaseButton
      {...props}
      size={size}
      className={cn('rounded-lg font-semibold shadow-none', className)}
    />
  );
}

type PageKey =
  | 'home'
  | 'pricing'
  | 'privacy'
  | 'terms'
  | 'refund'
  | 'acceptableUse'
  | 'gdpr'
  | 'cookies'
  | 'security'
  | 'faq'
  | 'help'
  | 'knowledgebase'
  | 'roadmap'
  | 'docs'
  | 'about'
  | 'contact'
  | 'survey';

const navSections = [
  { id: 'product', label: 'Product' },
  { id: 'solutions', label: 'Solutions' },
  { id: 'features', label: 'Features' },
  { id: 'pricing', label: 'Pricing' },
];

const resourceLinks = [
  { key: 'docs' as PageKey, label: 'Docs', href: () => route('docs') },
  { key: 'knowledgebase' as PageKey, label: 'Guides', href: () => route('knowledgebase') },
  { key: 'faq' as PageKey, label: 'FAQs', href: () => route('faqs') },
  { key: 'help' as PageKey, label: 'Help Center', href: () => route('help') },
];

const legalLinks = [
  { key: 'privacy' as PageKey, label: 'Privacy', href: () => route('privacy') },
  { key: 'terms' as PageKey, label: 'Terms', href: () => route('terms') },
  { key: 'refund' as PageKey, label: 'Refunds', href: () => route('refund.policy') },
  { key: 'acceptableUse' as PageKey, label: 'Acceptable use', href: () => route('acceptable.use') },
  { key: 'gdpr' as PageKey, label: 'GDPR', href: () => route('gdpr') },
  { key: 'cookies' as PageKey, label: 'Cookies', href: () => route('cookies') },
  { key: 'security' as PageKey, label: 'Security', href: () => route('security') },
];

export const marketingFaq = [
  {
    q: 'Do I need my own WhatsApp Business Account?',
    a: `Yes. ${brandName()} connects to your WhatsApp Business account and guides you through number setup, templates, and inviting your team.`,
  },
  {
    q: 'Can my team use one shared inbox?',
    a: 'Yes. Agents can reply from one inbox with assignments, notes, quick replies, SLA visibility, and workspace permissions.',
  },
  {
    q: 'Are Meta conversation charges included?',
    a: `No. ${brandName()} subscription fees and Meta conversation charges are separate. WhatsApp message charges depend on your country and the type of message you send.`,
  },
  {
    q: 'How many WhatsApp numbers can one workspace connect?',
    a: 'A workspace can connect as many WhatsApp numbers as its plan includes. Starter includes one number. Pro, Business, and Enterprise include more. Use another workspace when a brand or client needs its own team and billing.',
  },
  {
    q: 'Can I renew the same plan after a trial or expired period?',
    a: 'Yes. You can renew your plan, choose monthly or yearly billing, apply discounts, pay online or by bank transfer/UPI, and download GST invoices.',
  },
  {
    q: 'Can I start Enterprise directly?',
    a: 'No. Enterprise activation is admin-approved because limits, onboarding, billing, and Meta readiness must be reviewed before access is granted.',
  },
  {
    q: 'Can I connect my own payment and AI accounts?',
    a: `Yes. Workspace owners can connect their own payment and AI accounts from Integrations. Your ${brandName()} subscription is billed separately.`,
  },
  {
    q: 'What happens when a template is rejected?',
    a: 'You can review the rejection reason, edit the wording, examples, variables, or buttons, then resubmit from the template manager.',
  },
  {
    q: 'Can I import contacts and send campaigns?',
    a: 'Yes. Contacts, tags, segments, templates, broadcasts, failed-recipient retry, and campaign analytics are part of the workspace campaign workflow.',
  },
  {
    q: 'How do bank or UPI payments activate a plan?',
    a: `${brandName()} creates an invoice first. After you pay and upload proof, a platform admin reviews it and marks the payment paid to activate or renew the workspace plan.`,
  },
  {
    q: 'Can automations hand over to a human?',
    a: 'Yes. Flows can assign a chat, pause the bot, show status in the inbox, and let agents take over manually when needed.',
  },
  {
    q: 'Which integrations are available right now?',
    a: `${brandName()} currently includes WhatsApp Cloud API, Meta Leads, Google Sheets, Google Calendar, Razorpay, workspace AI providers, Shopify, WooCommerce, Zapier, Make, Slack, and Developer Webhooks.`,
  },
  {
    q: `Can ${brandName()} sync Google Sheets automatically?`,
    a: 'Yes, after Google OAuth is connected and the required Google APIs are enabled. You can pick a spreadsheet, map columns, import contacts, apply tags, and review sync logs.',
  },
  {
    q: 'Can new Meta leads alert my team?',
    a: 'Yes. Meta Leads can create contacts, apply tags, assign or route leads, show in-app/browser notifications, and start a selected automation flow.',
  },
  {
    q: `Can website visitors contact ${brandName()} on WhatsApp?`,
    a: `Yes. Public pages include a ${brandName()} WhatsApp floater that opens a chat with the support number set in admin Branding settings.`,
  },
];

export const helpArticles = [
  {
    title: 'Embedded signup checklist',
    excerpt:
      `Sign in with Facebook, pick your business and phone number, and ${brandName()} sets up the rest, including your message templates.`,
  },
  {
    title: 'Template approval process',
    excerpt:
      'Pick the right category, write clean variables, attach examples, review rejection reasons, edit rejected templates, and resubmit from the template manager.',
  },
  {
    title: 'Workspace billing and invoices',
    excerpt:
      'See tax and discounts up front, pay online or by bank transfer/UPI, and download GST invoices.',
  },
  {
    title: 'Workspace permissions',
    excerpt:
      'Use owner/admin/agent roles to restrict contacts, campaigns, billing, credentials, API keys, payment approvals, and destructive actions.',
  },
  {
    title: 'Automation safety',
    excerpt:
      'Use deterministic nodes for predictable steps, AI-agent nodes for flexible replies, and pause/handoff rules to prevent duplicate or spammy responses.',
  },
  {
    title: 'Meta Leads setup',
    excerpt:
      'Connect Facebook login, map lead forms, auto-create contacts, tag sources, alert assignees, and start a follow-up flow when a lead arrives.',
  },
  {
    title: 'WhatsApp Calling readiness',
    excerpt:
      'Turn on calls for your number, choose whether your team or AI answers, and review call history and transcripts.',
  },
  {
    title: 'Google Sheets and Calendar setup',
    excerpt:
      'Enable the required Google APIs, connect OAuth, pick sheets or calendars, map fields, and review integration logs when sync fails.',
  },
  {
    title: 'Campaign retry and delivery review',
    excerpt:
      'Retry only failed recipients, inspect failure reasons, validate template state, and watch delivery/read/reply metrics after sending.',
  },
  {
    title: 'Mobile agent workflow',
    excerpt:
      'Use the mobile app for login, inbox checks, quick replies, contact details, assignment updates, notifications, and call actions while away from desktop.',
  },
  {
    title: 'Bank transfer payment flow',
    excerpt:
      'Create an invoice from checkout, pay using the displayed bank or UPI details, upload payment proof, and wait for admin approval before activation.',
  },
];

export const knowledgeBaseGuides = [
  {
    category: 'WhatsApp setup',
    title: 'Connect a production WABA',
    summary:
      'Use Cloud API embedded signup for production workspaces. Coexistence is available for eligible businesses through Meta.',
    bullets: [
      'Verify Meta app ID, app secret, and callback URL.',
      'Confirm WABA and phone permissions before syncing templates.',
      'Check webhook subscription, phone registration, and signature diagnostics after connection.',
    ],
  },
  {
    category: 'Inbox',
    title: 'Avoid duplicate bot replies',
    summary:
      'A conversation should have one active automation path at a time. Human handoff, bot pause, and AI-agent settings decide whether the next inbound message is automated or manual.',
    bullets: [
      'Pause automation when an agent replies manually.',
      'Use AI-agent nodes only where flexible qualification is needed.',
      'Review conversation timeline statuses: automation running, AI replying, completed, skipped, or failed.',
    ],
  },
  {
    category: 'Campaigns',
    title: 'Campaign send checks',
    summary:
      'Before sending, validate recipients, opt-out status, template approval, media availability, rate limits, and workspace monthly limits.',
    bullets: [
      'Retry only failed or bounced recipients instead of resending to everyone.',
      'Use segments and source tags to keep audiences clean.',
      'Review delivery, read, failed, and reply metrics after sending.',
    ],
  },
  {
    category: 'Billing',
    title: 'Plans, trials, and invoices',
    summary:
      'Plans are per workspace. Each plan includes a set number of WhatsApp connections. Self-service trials are limited per email account; Enterprise requires platform admin approval.',
    bullets: [
      'Use checkout preview for discount, GST, and final amount.',
      'Bank/UPI invoices need proof upload and admin approval.',
      `Razorpay is one-time checkout for ${brandName()} billing, not recurring subscriptions.`,
    ],
  },
  {
    category: 'Integrations',
    title: 'Google, Meta Leads, and webhooks',
    summary:
      'Google integrations require the correct OAuth scopes and enabled Google APIs. Meta Leads uses Facebook login and form mapping, then webhook or sync jobs ingest new leads.',
    bullets: [
      'Enable Google Drive/Sheets/Calendar APIs in the Google Cloud project.',
      'Map every Meta lead field before turning on auto-ingestion.',
      'Use integration logs to diagnose failed syncs and replay eligible events.',
    ],
  },
  {
    category: 'Security',
    title: 'Credential and role safety',
    summary:
      'Workspace API keys, AI provider keys, Razorpay keys, WABA tokens, and webhook secrets should be visible/editable only to owners or admins with the right permission.',
    bullets: [
      'Never expose decrypted secrets in the UI.',
      'Use audit logs for credential changes and destructive actions.',
      'Revoke sessions after password reset or suspicious activity.',
    ],
  },
  {
    category: 'Appointments',
    title: 'Appointment and reminder flow',
    summary:
      'Use appointment slots, Google Calendar sync, WhatsApp reminders, and lead source context to keep bookings connected to conversations.',
    bullets: [
      'Create clear appointment types before sharing links.',
      'Send confirmation and reminder templates only to opted-in contacts.',
      'Review no-shows and reschedule requests from the conversation timeline.',
    ],
  },
  {
    category: 'Policy',
    title: 'WhatsApp policy basics',
    summary:
      `${brandName()} provides tools, but your business remains responsible for consent, content, template accuracy, opt-outs, and Meta commerce or messaging restrictions.`,
    bullets: [
      'Do not upload purchased or scraped lists.',
      'Honor stop/unsubscribe requests quickly.',
      'Avoid misleading templates, prohibited goods, or high-frequency cold outreach.',
    ],
  },
  {
    category: 'Website widget',
    title: `Use the ${brandName()} WhatsApp floater`,
    summary:
      'The public website can show a WhatsApp click-to-chat floater for quick sales, onboarding, support, and demo requests.',
    bullets: [
      'The public floater opens WhatsApp to the support phone set in admin Branding settings.',
      'Workspace widgets can be created from Tools and embedded on customer websites.',
      'Use a short prefilled message so visitors know what to ask.',
    ],
  },
  {
    category: 'Provider setup',
    title: 'Available provider integrations',
    summary:
      `Provider integrations are shown with real logos and dedicated setup flows so teams know exactly which services ${brandName()} supports.`,
    bullets: [
      `Payments: Razorpay payment links and ${brandName()} billing checkout.`,
      'AI: OpenAI, Anthropic, and Gemini workspace credentials.',
      'Productivity and automation: Google Sheets, Google Calendar, Zapier, Make, Slack, and Developer Webhooks.',
    ],
  },
];

export const roadmapItems = [
  {
    status: 'Available',
    quarter: 'Now',
    title: 'WhatsApp workspace operations',
    desc: 'WhatsApp numbers for the plan, shared inbox, contacts, segments, templates, broadcasts, assignments, notes, and team permissions.',
  },
  {
    status: 'Available',
    quarter: 'Now',
    title: 'Billing and payments',
    desc: 'Plan checkout, discounts, bank/UPI payment approval, Razorpay one-time payments, payment proof review, and downloadable invoice PDFs.',
  },
  {
    status: 'Available',
    quarter: 'Now',
    title: 'Automations and AI agents',
    desc: 'Visual flows with branches, waits, tags, template sends, webhooks, AI-agent handoff, bot pause controls, and run status inside the inbox.',
  },
  {
    status: 'Improving',
    quarter: 'Next',
    title: 'Lead capture and appointments',
    desc: 'Deeper Meta Lead Forms sync, lead alerts, source tracking, appointment scheduling, Google Calendar sync, reminders, and follow-up automation.',
  },
  {
    status: 'Improving',
    quarter: 'Next',
    title: 'Campaign reliability',
    desc: 'Better failed-recipient retry, delivery diagnostics, audience validation, opt-out safety, rate-limit visibility, and campaign performance insights.',
  },
  {
    status: 'Planned',
    quarter: 'Later',
    title: 'Mobile app upgrades',
    desc: 'More complete mobile inbox, push notifications, quick replies, assignment actions, profile management, and call handling for agents on the move.',
  },
];

export const integrations = [
  {
    name: 'WhatsApp Cloud API',
    providerId: 'whatsapp',
    icon: MessageCircle,
    color: '#00A548',
    desc: 'Official WABA connection, templates, webhooks, inbox, campaigns, and calling readiness.',
  },
  {
    name: 'Meta Leads',
    providerId: 'meta-leads',
    icon: Target,
    color: '#1877F2',
    desc: 'Facebook lead forms, field mapping, lead alerts, contact creation, and automation triggers.',
  },
  {
    name: 'Google Sheets',
    providerId: 'google-sheets',
    icon: Search,
    color: '#0F9D58',
    desc: 'Import contacts and lead rows with mapped columns, tags, and sync logs.',
  },
  {
    name: 'Google Calendar',
    providerId: 'google-calendar',
    icon: Clock,
    color: '#4285F4',
    desc: 'Appointment sync, upcoming event imports, reminders, and meeting links.',
  },
  {
    name: 'Razorpay',
    providerId: 'razorpay',
    icon: CreditCard,
    color: '#0B72E7',
    desc: `Workspace payment links for customer payments and ${brandName()} one-time checkout for billing.`,
  },
  {
    name: 'Workspace AI',
    providerId: 'workspace-ai',
    icon: Sparkles,
    color: '#10B981',
    desc: 'Workspace OpenAI, Anthropic, or Gemini keys for AI agents, suggestions, and automation nodes.',
  },
  {
    name: 'Shopify',
    providerId: 'shopify',
    icon: CreditCard,
    color: '#7AB55C',
    desc: 'Import products and orders for ecommerce recovery and order-aware conversations.',
  },
  {
    name: 'WooCommerce',
    providerId: 'woocommerce',
    icon: CreditCard,
    color: '#96588A',
    desc: 'Sync store products and orders through WooCommerce REST API.',
  },
  {
    name: 'Zapier',
    providerId: 'zapier',
    icon: Workflow,
    color: '#FF4A00',
    desc: `Send ${brandName()} webhook events into Zapier catch hooks.`,
  },
  {
    name: 'Make',
    providerId: 'make',
    icon: Workflow,
    color: '#6D00CC',
    desc: 'Trigger Make scenarios from real workspace webhook events.',
  },
  {
    name: 'Slack',
    providerId: 'slack',
    icon: Bell,
    color: '#4A154B',
    desc: 'Send operational notifications to Slack incoming webhooks.',
  },
  {
    name: 'Developer Webhooks',
    providerId: 'developer-webhooks',
    icon: Server,
    color: '#111827',
    desc: 'API keys, signed outbound webhooks, delivery logs, tests, and replay support.',
  },
];

export const iconMap: Record<string, LucideIcon> = {
  megaphone: Megaphone,
  workflow: Workflow,
  'message-circle': MessageCircle,
  'file-text': FileText,
  target: Target,
  'credit-card': CreditCard,
  sparkles: Sparkles,
  rocket: Rocket,
  'play-circle': PlayCircle,
  'layout-dashboard': LayoutDashboard,
  'link-2': Link2,
  users: Users,
  'file-check': FileCheck,
  send: Send,
  'wand-2': Wand2,
  check: Check,
  shield: Shield,
  key: Key,
  server: Server,
  bell: Bell,
  mail: Mail,
  'map-pin': MapPin,
  clock: Clock,
  search: Search,
  'trending-up': TrendingUp,
  'bar-chart-2': BarChart2,
  zap: Zap,
  'arrow-up-right': ArrowUpRight,
};

export function MarketingIcon({
  name,
  className,
  size = 18,
}: {
  name: string;
  className?: string;
  size?: number;
}) {
  const Icon = iconMap[name] || HelpCircle;
  return <Icon className={className} size={size} />;
}

export function marketingSectionHref(section: string) {
  return section === 'pricing' ? route('pricing') : `${route('landing')}#${section}`;
}

function namedRoute(name: string, fallback: string) {
  return route().has(name) ? route(name) : fallback;
}

function dashboardHref(auth: any, accounts: any[] | undefined) {
  if (!auth?.user) return null;
  if (auth.user.is_platform_admin || auth.user.is_super_admin) return route('platform.dashboard');
  // Let the server pick the destination: shared `accounts` props can be missing on
  // cached marketing pages, which used to send workspace members to onboarding.
  if (route().has('dashboard')) return route('dashboard');
  return Array.isArray(accounts) && accounts.length > 0
    ? route('app.dashboard')
    : route('onboarding');
}

const mobileNavSections = [
  { id: 'features', label: 'Features', icon: Zap, desc: 'Broadcasts, automation, inbox' },
  { id: 'product', label: 'Product', icon: LayoutDashboard, desc: 'Your WhatsApp workspace' },
  { id: 'solutions', label: 'Solutions', icon: Workflow, desc: 'Sales, support, and marketing' },
  { id: 'pricing', label: 'Pricing', icon: CreditCard, desc: 'Simple per-workspace plans' },
];

const mobileResourceSections = resourceLinks.map((item) => ({
  ...item,
  icon: item.key === 'docs' ? FileText : item.key === 'knowledgebase' ? FileCheck : HelpCircle,
}));


type CmsLink = { label: string; url: string; new_tab?: boolean };

export function useCms(): any {
  return ((usePage().props as any).cms ?? null) as any;
}

export function CmsAnchor({ item, className, onClick }: { item: CmsLink; className?: string; onClick?: () => void }) {
  const external = /^https?:\/\//i.test(item.url) || item.url.startsWith('mailto:') || item.url.startsWith('tel:');
  const href = external ? item.url : appUrl(item.url);
  if (external || item.new_tab || item.url.includes('#')) {
    return (
      <a
        href={href}
        className={className}
        onClick={onClick}
        {...(item.new_tab ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
      >
        {item.label}
      </a>
    );
  }
  return (
    <Link href={href} className={className} onClick={onClick}>
      {item.label}
    </Link>
  );
}

function AnnouncementBar() {
  const cms = useCms();
  const a = cms?.announcement;
  if (!a?.enabled || !a.text) return null;
  return (
    <div className="flex min-h-9 flex-wrap items-center justify-center gap-x-3 gap-y-1 bg-[#047857] px-4 py-2 text-center text-xs font-medium text-white sm:text-sm">
      <span>{a.text}</span>
      {a.link_label && a.link_url && (
        <CmsAnchor item={{ label: `${a.link_label} →`, url: a.link_url }} className="underline underline-offset-2 hover:no-underline" />
      )}
    </div>
  );
}

export function MarketingSiteNav({ currentPage = 'home' }: { currentPage?: PageKey }) {
  const { auth, accounts } = usePage().props as any;
  const [mobileOpen, setMobileOpen] = useState(false);
  const [resourcesOpen, setResourcesOpen] = useState(false);
  const dashHref = dashboardHref(auth, accounts);
  const canRegister = route().has('register');
  const canLogin = route().has('login');
  const cms = useCms();
  const headerLinks: CmsLink[] = cms?.menus?.header?.length
    ? cms.menus.header
    : navSections.map((item) => ({ label: item.label, url: marketingSectionHref(item.id) }));
  const hasAnnouncement = Boolean(cms?.announcement?.enabled && cms?.announcement?.text);

  useEffect(() => {
    if (mobileOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
    return () => {
      document.body.style.overflow = '';
    };
  }, [mobileOpen]);

  useEffect(() => {
    if (!resourcesOpen && !mobileOpen) return;
    const closeOnEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setResourcesOpen(false);
        setMobileOpen(false);
      }
    };
    window.addEventListener('keydown', closeOnEscape);
    return () => window.removeEventListener('keydown', closeOnEscape);
  }, [resourcesOpen, mobileOpen]);

  const navBtn = (active = false) =>
    cn(
      'px-3 py-2 rounded-lg transition text-sm font-medium',
      active
        ? 'bg-ui-green/10 text-ui-green-dark'
        : 'text-ui-text-muted hover:text-ui-text hover:bg-gray-50 dark:hover:bg-slate-800'
    );

  const pageLink = (key: PageKey, href: string, label: string) => (
    <Link
      key={key}
      href={href}
      onClick={() => {
        setMobileOpen(false);
        setResourcesOpen(false);
      }}
      className={cn(
        'block rounded-lg px-3 py-2 text-sm',
        currentPage === key
          ? 'bg-ui-green/10 font-medium text-ui-green-dark'
          : 'text-ui-text-muted hover:bg-gray-50 dark:hover:bg-slate-800'
      )}
    >
      {label}
    </Link>
  );

  return (
    <>
      <nav
        className="mkt-site-nav fixed inset-x-0 top-0 z-50 border-b border-slate-100/70 bg-white dark:border-slate-800 dark:bg-slate-950"
        aria-label="Main navigation"
      >
        <AnnouncementBar />
        <div className="mkt-container">
          <div className="flex h-[72px] items-center justify-between gap-4">
            <Link href={route('landing')} className="flex items-center gap-2">
              <BrandLogo variant="auto" imageClassName="h-8" />
            </Link>

            <div className="hidden items-center gap-0.5 lg:flex">
              {headerLinks.map((item) => (
                <CmsAnchor
                  key={item.label + item.url}
                  item={item}
                  className={navBtn(currentPage === 'pricing' && item.url === '/pricing')}
                />
              ))}
              <div className="relative">
                <button
                  type="button"
                  className={cn(
                    navBtn(resourceLinks.some((item) => item.key === currentPage)),
                    'inline-flex items-center gap-1'
                  )}
                  aria-expanded={resourcesOpen}
                  aria-controls="marketing-resources"
                  onClick={() => setResourcesOpen((open) => !open)}
                >
                  Resources{' '}
                  <ChevronDown
                    size={14}
                    className={cn('transition', resourcesOpen && 'rotate-180')}
                  />
                </button>
                {resourcesOpen && (
                  <>
                    <button
                      type="button"
                      className="fixed inset-0 z-40 cursor-default"
                      onClick={() => setResourcesOpen(false)}
                      aria-label="Close resources menu"
                    />
                    <div
                      id="marketing-resources"
                      className="absolute right-0 top-full z-50 mt-2 w-48 rounded-xl bg-white py-1 shadow-pop ring-1 ring-gray-100 dark:bg-slate-900 dark:ring-slate-700"
                    >
                      {resourceLinks.map((item) => pageLink(item.key, item.href(), item.label))}
                    </div>
                  </>
                )}
              </div>
            </div>

            <div className="flex items-center gap-2">
              <ThemeToggle />
              {dashHref ? (
                <Link
                  href={dashHref}
                  className="hidden h-9 items-center rounded-lg px-3 text-sm font-medium text-ui-text-muted hover:text-ui-text sm:inline-flex"
                >
                  Dashboard
                </Link>
              ) : canLogin ? (
                <Link
                  href={route('login')}
                  className="hidden h-9 items-center rounded-lg px-3 text-sm font-medium text-ui-text-muted hover:text-ui-text sm:inline-flex"
                >
                  Sign in
                </Link>
              ) : null}
              {canRegister && !dashHref && (
                <Link href={route('register')} className="hidden sm:block">
                  <span className="hidden h-9 items-center rounded-lg bg-ui-green px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-ui-green-dark sm:inline-flex">
                    Start free →
                  </span>
                </Link>
              )}
              <button
                type="button"
                className="flex h-9 w-9 items-center justify-center rounded-lg text-ui-text transition hover:bg-gray-100 dark:hover:bg-slate-800 lg:hidden"
                onClick={() => setMobileOpen((open) => !open)}
                aria-label={mobileOpen ? 'Close menu' : 'Open menu'}
              >
                {mobileOpen ? <X size={20} /> : <Menu size={20} />}
              </button>
            </div>
          </div>
        </div>
      </nav>
      {hasAnnouncement && <div aria-hidden="true" className="h-9" />}

      {/* Mobile full-screen drawer */}
      {mobileOpen && (
        <div className="fixed inset-0 z-[60] lg:hidden">
          {/* Backdrop */}
          <div
            className="absolute inset-0 bg-black/50 backdrop-blur-sm"
            onClick={() => setMobileOpen(false)}
          />
          {/* Drawer panel */}
          <div className="absolute inset-y-0 right-0 flex w-full max-w-sm flex-col bg-white shadow-2xl dark:bg-slate-900 anim-slide-right">
            {/* Drawer header */}
            <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-slate-800">
              <Link href={route('landing')} onClick={() => setMobileOpen(false)}>
                <BrandLogo variant="auto" imageClassName="h-8" />
              </Link>
              <button
                type="button"
                onClick={() => setMobileOpen(false)}
                className="flex h-9 w-9 items-center justify-center rounded-xl bg-gray-100 text-ui-text-muted transition hover:bg-gray-200 dark:bg-slate-800 dark:hover:bg-slate-700"
                aria-label="Close menu"
              >
                <X size={18} />
              </button>
            </div>

            {/* Scrollable body */}
            <div className="flex-1 overflow-y-auto px-4 py-4">
              {cms?.menus?.header?.length ? (
                <div className="mb-4 space-y-1">
                  {(cms.menus.header as CmsLink[]).map((item) => (
                    <CmsAnchor
                      key={'m' + item.label + item.url}
                      item={item}
                      onClick={() => setMobileOpen(false)}
                      className="block rounded-xl px-3 py-3 text-sm font-medium hover:bg-gray-50 dark:hover:bg-slate-800"
                    />
                  ))}
                </div>
              ) : null}
              {/* Product nav */}
              <p className="mb-2 px-1 text-[10px] font-bold uppercase tracking-widest text-ui-text-muted">
                Product
              </p>
              <div className="space-y-1">
                {mobileNavSections.map((item) => {
                  const Icon = item.icon;
                  const href = marketingSectionHref(item.id);
                  const isActive = currentPage === 'pricing' && item.id === 'pricing';
                  return (
                    <Link
                      key={item.id}
                      href={href}
                      onClick={() => setMobileOpen(false)}
                      className={cn(
                        'flex items-center gap-3 rounded-xl px-3 py-3 transition',
                        isActive
                          ? 'bg-ui-green/10 text-ui-green-dark'
                          : 'hover:bg-gray-50 dark:hover:bg-slate-800'
                      )}
                    >
                      <span
                        className={cn(
                          'flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl',
                          isActive ? 'bg-ui-green/15' : 'bg-gray-100 dark:bg-slate-800'
                        )}
                      >
                        <Icon
                          size={18}
                          className={isActive ? 'text-ui-green-dark' : 'text-ui-text-muted'}
                        />
                      </span>
                      <div className="min-w-0">
                        <div
                          className={cn(
                            'text-sm font-semibold',
                            isActive
                              ? 'text-ui-green-dark'
                              : 'text-ui-text dark:text-ui-dark-text'
                          )}
                        >
                          {item.label}
                        </div>
                        <div className="text-[11px] text-ui-text-muted">{item.desc}</div>
                      </div>
                      <ArrowUpRight
                        size={14}
                        className="ml-auto flex-shrink-0 text-ui-text-muted opacity-40"
                      />
                    </Link>
                  );
                })}
              </div>

              {/* Resources */}
              <div className="my-4 border-t border-gray-100 dark:border-slate-800" />
              <p className="mb-2 px-1 text-[10px] font-bold uppercase tracking-widest text-ui-text-muted">
                Resources
              </p>
              <div className="grid grid-cols-2 gap-1">
                {mobileResourceSections.map((item) => {
                  const Icon = item.icon;
                  return (
                    <Link
                      key={item.key}
                      href={item.href()}
                      onClick={() => setMobileOpen(false)}
                      className={cn(
                        'flex items-center gap-2 rounded-xl px-3 py-2.5 text-sm transition',
                        currentPage === item.key
                          ? 'bg-ui-green/10 font-medium text-ui-green-dark'
                          : 'text-ui-text-muted hover:bg-gray-50 dark:hover:bg-slate-800'
                      )}
                    >
                      <Icon size={15} className="flex-shrink-0" />
                      {item.label}
                    </Link>
                  );
                })}
              </div>

              <div className="mt-4 flex gap-5 border-t border-gray-100 px-3 pt-4 text-sm dark:border-slate-800">
                <Link href={route('about')} onClick={() => setMobileOpen(false)}>
                  About
                </Link>
                <Link href={route('contact')} onClick={() => setMobileOpen(false)}>
                  Contact
                </Link>
              </div>
            </div>

            {/* CTA footer */}
            <div className="border-t border-gray-100 px-4 pb-6 pt-4 dark:border-slate-800">
              {dashHref ? (
                <Link href={dashHref} onClick={() => setMobileOpen(false)}>
                  <span className="flex h-11 w-full items-center justify-center rounded-xl bg-ui-green text-sm font-semibold text-white shadow-sm">
                    <LayoutDashboard size={16} className="mr-2" /> Open dashboard
                  </span>
                </Link>
              ) : (
                <div className="space-y-2">
                  {canRegister && (
                    <Link href={route('register')} onClick={() => setMobileOpen(false)}>
                      <span className="flex h-11 w-full items-center justify-center rounded-xl bg-ui-green text-sm font-semibold text-white shadow-sm">
                        <Rocket size={16} className="mr-2" /> Start free — no credit card
                      </span>
                    </Link>
                  )}
                  {canLogin && (
                    <Link href={route('login')} onClick={() => setMobileOpen(false)}>
                      <span className="flex h-11 w-full items-center justify-center rounded-xl bg-gray-100 text-sm font-semibold text-ui-text dark:bg-slate-800 dark:text-ui-dark-text">
                        Sign in
                      </span>
                    </Link>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>
      )}
    </>
  );
}

export function MarketingClosingBanner() {
  const { features } = usePage().props as any;
  const canRegister = features?.user_registration !== false && route().has('register');
  return (
    <div className="mkt-closing-banner">
      <div>
        <p className="mkt-eyebrow text-emerald-400">Ready to grow?</p>
        <h2 className="mt-3 text-2xl font-semibold leading-tight tracking-tight sm:text-3xl">
          Start your WhatsApp journey with {brandName()}.
        </h2>
        <p className="mt-3 text-sm leading-6 text-slate-300">
          Bring your conversations, campaigns, and team together.
        </p>
      </div>
      <Link
        href={canRegister ? route('register') : route('contact')}
        className="mkt-button-primary shrink-0"
      >
        {canRegister ? 'Get started' : 'Contact sales'}
        <ArrowRight size={15} />
      </Link>
    </div>
  );
}

export function MarketingSiteFooter() {
  const { branding } = usePage().props as any;
  const cms = useCms();
  const footerLinks: CmsLink[] | null = cms?.menus?.footer?.length ? cms.menus.footer : null;
  const legal: CmsLink[] | null = cms?.menus?.footer_legal?.length ? cms.menus.footer_legal : null;
  const platformName = branding?.platform_name || `${brandName()}`;
  return (
    <footer className="border-t border-slate-100 bg-white pb-7 pt-12 text-slate-500 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-400">
      <div className="mkt-container">
        <div className="grid gap-9 sm:grid-cols-2 lg:grid-cols-[1.6fr_repeat(4,1fr)]">
          <div>
            <Link href={route('landing')} className="inline-flex">
              <BrandLogo variant="auto" imageClassName="h-9" />
            </Link>
            <p className="mt-4 max-w-xs text-sm leading-7">
              WhatsApp operations for businesses.
              <br />
              Chat, automate, broadcast, and grow — all in one place.
            </p>
          </div>
          {footerLinks ? (
            <div>
              <p className="mb-4 text-xs font-semibold text-slate-800 dark:text-slate-200">Links</p>
              <ul className="space-y-3 text-sm">
                {footerLinks.map((item) => (
                  <li key={item.label + item.url}>
                    <CmsAnchor item={item} className="transition-colors hover:text-ui-green" />
                  </li>
                ))}
              </ul>
            </div>
          ) : null}
          <FooterCol
            title="Product"
            links={[
              { label: 'Features', href: marketingSectionHref('features') },
              { label: 'Pricing', href: route('pricing') },
              { label: 'Integrations', href: marketingSectionHref('integrations') },
              { label: 'Developer API', href: namedRoute('developer.index', route('docs')) },
            ]}
          />
          <FooterCol
            title="Resources"
            links={[
              { label: 'Documentation', href: route('docs') },
              { label: 'Guides', href: route('knowledgebase') },
              { label: 'FAQs', href: route('faqs') },
              { label: 'Help Center', href: route('help') },
            ]}
          />
          <FooterCol
            title="Company"
            links={[
              { label: 'About Us', href: route('about') },
              { label: 'Contact', href: route('contact') },
              { label: 'Roadmap', href: route('roadmap') },
              { label: 'Security', href: route('security') },
            ]}
          />
        </div>
        <details className="mt-8 text-xs">
          <summary className="w-fit cursor-pointer font-medium text-slate-600 dark:text-slate-300">
            Legal & policies
          </summary>
          <div className="mt-4 flex flex-wrap gap-x-5 gap-y-3">
            {legalLinks.map((item) => (
              <Link key={item.key} href={item.href()} className="hover:text-ui-green">
                {item.label}
              </Link>
            ))}
          </div>
        </details>
        <div className="mt-10 flex flex-col justify-between gap-4 border-t border-slate-100 pt-6 text-xs dark:border-slate-800 sm:flex-row">
          <p>
            © {new Date().getFullYear()} {platformName}. All rights reserved.
          </p>
          <div className="flex flex-wrap gap-5">
            {legal ? (
              legal.map((item) => <CmsAnchor key={item.label + item.url} item={item} className="hover:text-ui-green" />)
            ) : (
              <>
                <Link href={route('privacy')}>Privacy</Link>
                <Link href={route('terms')}>Terms</Link>
                <Link href={route('security')}>Security</Link>
                <Link href={route('refund.policy')}>Refunds</Link>
              </>
            )}
          </div>
        </div>
      </div>
    </footer>
  );
}

function FooterCol({ title, links }: { title: string; links: { label: string; href: string }[] }) {
  return (
    <div>
      <p className="mb-4 text-xs font-semibold text-slate-800 dark:text-slate-200">{title}</p>
      <ul className="space-y-3 text-sm">
        {links.map((link) => (
          <li key={link.label}>
            <Link href={link.href} className="transition-colors hover:text-ui-green">
              {link.label}
            </Link>
          </li>
        ))}
      </ul>
    </div>
  );
}

const pageMeta: Record<PageKey, { title: string; subtitle: string }> = {
  home: { title: `${brandName()}`, subtitle: 'WhatsApp marketing platform.' },
  pricing: {
    title: 'Simple, transparent pricing',
    subtitle:
      'A workspace for every stage. Choose your plan, bring your team, and start the conversation.',
  },
  privacy: {
    title: 'Privacy Policy',
    subtitle: `How ${brandName()} handles account, workspace, contact, billing, Meta, and support data.`,
  },
  terms: {
    title: 'Terms of Service',
    subtitle:
      `The operating terms for using ${brandName()}, Meta APIs, workspaces, campaigns, billing, and automation.`,
  },
  refund: {
    title: 'Refund and Cancellation Policy',
    subtitle:
      'How cancellations, renewals, failed payments, proof reviews, and refund requests are handled.',
  },
  acceptableUse: {
    title: 'Acceptable Use Policy',
    subtitle:
      'Practical messaging rules for consent, campaigns, templates, automation, AI replies, and Meta policy compliance.',
  },
  faq: {
    title: 'Frequently asked questions',
    subtitle:
      'Answers about WhatsApp onboarding, billing, plans, workspace permissions, and Meta readiness.',
  },
  gdpr: {
    title: 'GDPR',
    subtitle: 'Data subject rights, lawful basis, subprocessors, and export/delete workflows.',
  },
  cookies: {
    title: 'Cookies',
    subtitle: `How ${brandName()} uses essential session cookies and optional analytics preferences.`,
  },
  security: {
    title: 'Security',
    subtitle:
      'Encryption, role-based access, audit logs, webhook verification, and incident readiness.',
  },
  help: {
    title: 'How can we help?',
    subtitle:
      'Guides for setup, Meta connections, templates, automation, billing, and diagnostics.',
  },
  knowledgebase: {
    title: 'Guides & tutorials',
    subtitle: 'Search practical setup guides for Meta, billing, workspaces, and campaigns.',
  },
  roadmap: {
    title: 'Roadmap',
    subtitle:
      'Customer-facing improvements for WhatsApp operations, billing, automations, integrations, and mobile workflows.',
  },
  docs: {
    title: 'Documentation',
    subtitle:
      'Practical setup notes for workspaces, billing, Meta readiness, integrations, and automation safety.',
  },
  about: {
    title: 'Built for better conversations.',
    subtitle: 'One focused workspace for the teams behind every WhatsApp conversation.',
  },
  contact: {
    title: 'Let’s talk.',
    subtitle: 'Questions about plans, setup, or WhatsApp workflows? Tell us what you’re building.',
  },
  survey: { title: 'Feedback form', subtitle: 'Share your response with this workspace.' },
};

export function MarketingLayout({
  page,
  children,
  wide = false,
  showHeader = true,
}: PropsWithChildren<{ page: PageKey; wide?: boolean; showHeader?: boolean }>) {
  const meta = pageMeta[page];
  const { seo } = usePage().props as any;

  useEffect(() => {
    window.scrollTo(0, 0);
  }, [page]);

  return (
    <BrandingWrapper>
      <div className="marketing-site min-h-screen bg-white text-ui-text dark:bg-slate-950 dark:text-ui-dark-text">
        <MarketingSiteNav currentPage={page} />
        <main className="min-h-screen pt-[72px]">
          {showHeader && (
            <header className="border-b border-slate-100 bg-slate-50/40 dark:border-slate-800 dark:bg-slate-900/20">
              <div className="mkt-container py-12 sm:py-16">
                <p className="mkt-eyebrow">
                  {[
                    'privacy',
                    'terms',
                    'refund',
                    'acceptableUse',
                    'gdpr',
                    'cookies',
                    'security',
                  ].includes(page)
                    ? 'Trust & transparency'
                    : ['docs', 'help', 'knowledgebase', 'faq'].includes(page)
                      ? 'Resources'
                      : 'Built for modern businesses'}
                </p>
                <h1 className="mkt-heading mt-4 max-w-3xl">{meta.title}</h1>
                <p className="mt-4 max-w-2xl text-base leading-7 text-slate-500 dark:text-slate-400">
                  {meta.subtitle}
                </p>
              </div>
            </header>
          )}
          <div className="mkt-container py-10 sm:py-12">
            <div className={wide ? undefined : 'max-w-3xl'}>{children}</div>
            <div className="mt-14">
              <MarketingClosingBanner />
            </div>
          </div>
        </main>
        <SeoHead seo={seo} />
        <MarketingSiteFooter />
        <MarketingWhatsAppFloater />
        <CookieConsentBanner />
        <AnalyticsScripts />
      </div>
    </BrandingWrapper>
  );
}

export function MarketingWhatsAppFloater() {
  const { branding } = usePage().props as any;
  const platformName = branding?.platform_name || `${brandName()}`;
  const phone = String(branding?.support_phone || '').replace(/\D/g, '');
  if (!phone) return null;
  const text = encodeURIComponent(
    `Hi ${platformName} team, I need help with WhatsApp setup, pricing, or a demo.`
  );

  return (
    <a
      href={`https://wa.me/${phone}?text=${text}`}
      target="_blank"
      rel="noreferrer"
      className="fixed bottom-5 right-5 z-50 flex items-center gap-2 rounded-full bg-ui-green px-4 py-3 text-sm font-semibold text-white shadow-2xl shadow-ui-green/30 ring-1 ring-white/20 transition hover:-translate-y-0.5 hover:bg-ui-green-dark focus:outline-none focus:ring-4 focus:ring-ui-green/25"
      aria-label={`Chat with ${platformName} on WhatsApp`}
    >
      <ProviderLogo
        id="whatsapp"
        name="WhatsApp"
        className="h-8 w-8 rounded-full border-white/30 bg-white"
        imageClassName="h-5 w-5"
      />
      <span className="hidden sm:inline">Chat with {platformName}</span>
    </a>
  );
}

export function MarketingFaqAccordion({
  items = marketingFaq,
}: {
  items?: { q: string; a: string }[];
}) {
  const [open, setOpen] = useState(0);

  return (
    <div className="space-y-2">
      {items.map((item, index) => {
        const isOpen = open === index;
        return (
          <Card
            key={item.q}
            className={cn(
              'overflow-hidden transition ring-1',
              isOpen ? 'ring-ui-green/30' : 'ring-transparent'
            )}
          >
            <button
              type="button"
              className="flex w-full items-center justify-between gap-4 p-5 text-left"
              onClick={() => setOpen(isOpen ? -1 : index)}
              aria-expanded={isOpen}
            >
              <span className="pr-2 font-medium text-ui-text dark:text-ui-dark-text">
                {item.q}
              </span>
              {isOpen ? (
                <ChevronUp size={18} className="flex-shrink-0 text-ui-text-muted" />
              ) : (
                <ChevronDown size={18} className="flex-shrink-0 text-ui-text-muted" />
              )}
            </button>
            {isOpen && (
              <div className="border-t border-gray-100 px-5 pb-5 pt-4 text-sm leading-relaxed text-ui-text-muted dark:border-slate-800 dark:text-ui-dark-text-muted">
                {item.a}
              </div>
            )}
          </Card>
        );
      })}
    </div>
  );
}

export function Avatar({ name, size = 40 }: { name: string; size?: number }) {
  const initials = name
    .split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase();
  return (
    <div
      className="flex flex-shrink-0 items-center justify-center rounded-full bg-ui-green/15 font-bold text-ui-green-dark"
      style={{ width: size, height: size, fontSize: Math.max(10, size / 3) }}
    >
      {initials}
    </div>
  );
}

export function MarketingLogoCloud() {
  const items = integrations.slice(0, 8);
  const row = [...items, ...items];

  return (
    <section className="overflow-hidden border-y border-gray-100 bg-white/50 py-10 dark:border-slate-800 dark:bg-slate-900/50">
      <p className="mb-6 text-center text-xs font-semibold uppercase tracking-widest text-ui-text-muted">
        Built around the integrations available in {brandName()}
      </p>
      <div className="relative">
        <div className="pointer-events-none absolute inset-y-0 left-0 z-10 w-16 bg-gradient-to-r from-white to-transparent dark:from-slate-900" />
        <div className="pointer-events-none absolute inset-y-0 right-0 z-10 w-16 bg-gradient-to-l from-white to-transparent dark:from-slate-900" />
        <div className="mkt-marquee flex items-center gap-12">
          {row.map((item, index) => (
            <span
              key={`${item.name}-${index}`}
              className="flex whitespace-nowrap px-4 text-lg font-bold text-gray-400 dark:text-slate-500 sm:text-xl"
            >
              <span className="flex items-center gap-3">
                <ProviderLogo
                  id={item.providerId}
                  name={item.name}
                  className="h-9 w-9 rounded-lg"
                  imageClassName="h-5 w-5"
                />
                {item.name}
              </span>
            </span>
          ))}
        </div>
      </div>
    </section>
  );
}

export function MarketingIntegrationsShowcase({ compact = false }: { compact?: boolean }) {
  const visible = compact ? integrations.slice(0, 8) : integrations;

  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      {visible.map((item) => (
        <Card key={item.name} className="mkt-card-lift p-4">
          <div className="flex items-start gap-3">
            <ProviderLogo id={item.providerId} name={item.name} className="h-11 w-11 rounded-xl" />
            <div className="min-w-0">
              <h3 className="text-sm font-semibold text-ui-text dark:text-ui-dark-text">
                {item.name}
              </h3>
              <p className="mt-1 text-xs leading-relaxed text-ui-text-muted dark:text-ui-dark-text-muted">
                {item.desc}
              </p>
            </div>
          </div>
        </Card>
      ))}
    </div>
  );
}

export function ProductPreview({ children }: { children: ReactNode }) {
  return (
    <Card className="min-h-[280px] overflow-hidden p-0 shadow-pop ring-1 ring-gray-100 dark:ring-slate-700">
      <div className="flex h-9 items-center gap-1.5 border-b border-gray-100 bg-gray-50 px-3 dark:border-slate-700 dark:bg-slate-800">
        <span className="h-2.5 w-2.5 rounded-full bg-red-400" />
        <span className="h-2.5 w-2.5 rounded-full bg-amber-400" />
        <span className="h-2.5 w-2.5 rounded-full bg-emerald-400" />
        <span className="ml-2 font-mono text-[10px] text-ui-text-muted">app.example.com</span>
      </div>
      {children}
    </Card>
  );
}

export function formatInr(value: number) {
  return new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 }).format(value);
}

export function useFilteredArticles(query: string) {
  return useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return helpArticles;
    return helpArticles.filter(
      (article) =>
        article.title.toLowerCase().includes(q) || article.excerpt.toLowerCase().includes(q)
    );
  }, [query]);
}

export { ArrowRight, Badge, Button, Card, Check, Star };
