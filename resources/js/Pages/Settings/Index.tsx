import SettingsNav from '@/Components/Layout/SettingsNav';
import { Alert } from '@/Components/UI/Alert';
import { Card } from '@/Components/UI/Card';
import { Input } from '@/Components/UI/Input';
import AppShell from '@/Layouts/AppShell';
import { Head, useForm } from '@inertiajs/react';
import { Bell, Building2, ImagePlus, Inbox, Save, ShieldCheck, User } from 'lucide-react';
import { FormEvent, ReactNode, useEffect, useState } from 'react';
import InboxTab from './Tabs/InboxTab';
import NotificationsTab from './Tabs/NotificationsTab';
import ProfileTab from './Tabs/ProfileTab';
import SecurityTab from './Tabs/SecurityTab';

interface Workspace {
  id: number;
  name: string;
  slug: string;
  workspace_type: string;
  workspace_type_label: string;
  industry: string | null;
  timezone: string;
  logo_url: string | null;
  status: string;
}

interface SettingsIndexProps {
  workspace?: Workspace | null;
  workspaceTypes?: Record<string, string>;
  timezones?: string[];
  initialTab?: string;
}

function PageHeader({ title, subtitle }: { title: string; subtitle: string }) {
  return (
    <div className="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 className="text-xl font-semibold tracking-tight text-ui-text dark:text-ui-dark-text">
          {title}
        </h1>
        <p className="mt-0.5 text-sm text-ui-text-muted dark:text-ui-dark-text-muted">{subtitle}</p>
      </div>
    </div>
  );
}

function SettingsSection({
  title,
  description,
  children,
}: {
  title?: string;
  description?: string;
  children?: ReactNode;
}) {
  return (
    <section>
      {(title || description) && (
        <div className="mb-4">
          {title && (
            <h3 className="text-base font-semibold text-ui-text dark:text-ui-dark-text">{title}</h3>
          )}
          {description && (
            <p className="mt-0.5 text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
              {description}
            </p>
          )}
        </div>
      )}
      {children}
    </section>
  );
}

function Avatar({ name, src, size = 88 }: { name: string; src?: string | null; size?: number }) {
  const palette = [
    '#00A548',
    '#3B82F6',
    '#8B5CF6',
    '#EC4899',
    '#F59E0B',
    '#14B8A6',
    '#EF4444',
    '#6366F1',
  ];
  const initial = (name || '?')
    .trim()
    .split(/\s+/)
    .map((word) => word[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();
  let hash = 0;
  for (let index = 0; index < name.length; index += 1) {
    hash = name.charCodeAt(index) + ((hash << 5) - hash);
  }

  return (
    <div
      className="inline-flex flex-shrink-0 select-none items-center justify-center rounded-full font-semibold text-white"
      style={{
        width: size,
        height: size,
        background: palette[Math.abs(hash) % palette.length],
        fontSize: size * 0.38,
      }}
    >
      {src ? (
        <img src={src} alt={name || 'Workspace'} className="h-full w-full object-cover" />
      ) : (
        initial
      )}
    </div>
  );
}

function WorkspaceTab({ workspace, workspaceTypes = {}, timezones = [] }: SettingsIndexProps) {
  const [logoPreview, setLogoPreview] = useState<string | null>(workspace?.logo_url || null);
  const form = useForm({
    name: workspace?.name || '',
    workspace_type: workspace?.workspace_type || 'business',
    industry: workspace?.industry || '',
    timezone: workspace?.timezone || 'UTC',
    logo: null as File | null,
  });

  const submit = (event: FormEvent) => {
    event.preventDefault();
    form.transform((data) => ({ ...data, _method: 'patch' }));
    form.post(route('app.settings.workspace.upload') as string, {
      preserveScroll: true,
      forceFormData: true,
      onSuccess: () => form.setData('logo', null),
    });
  };

  if (!workspace) {
    return (
      <Alert variant="info" title="No workspace selected">
        Pick a workspace from the top bar, or create one, to change these settings.
      </Alert>
    );
  }

  return (
    <form onSubmit={submit} className="space-y-6">
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-[220px_1fr]">
        <Card className="flex flex-col items-center p-5 text-center lg:items-start lg:text-left">
          <Avatar name={form.data.name || workspace.name} src={logoPreview} />
          <div className="mt-3 font-semibold text-ui-text dark:text-ui-dark-text">
            {form.data.name || workspace.name}
          </div>
          <div className="mt-0.5 max-w-full truncate text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
            {workspace.slug}
          </div>
          <label className="mt-4 inline-flex h-9 cursor-pointer items-center justify-center gap-2 rounded-btn border border-gray-200 bg-white px-3 text-sm font-medium text-ui-text transition hover:bg-gray-50 dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text dark:hover:bg-ui-dark-surface-2">
            <ImagePlus className="h-4 w-4" />
            Upload image
            <input
              type="file"
              accept="image/jpeg,image/png,image/webp,image/gif"
              className="sr-only"
              onChange={(event) => {
                const file = event.target.files?.[0] || null;
                form.setData('logo', file);
                setLogoPreview(file ? URL.createObjectURL(file) : workspace.logo_url);
              }}
            />
          </label>
          <p className="mt-2 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
            PNG, JPG, WebP or GIF up to 2MB.
          </p>
          {form.errors.logo && <p className="mt-2 text-xs text-red-600">{form.errors.logo}</p>}
        </Card>

        <Card className="p-5">
          <SettingsSection>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div className="sm:col-span-2">
                <label className="mb-1 block text-xs font-medium text-ui-text dark:text-ui-dark-text">
                  Workspace name
                </label>
                <Input
                  value={form.data.name}
                  onChange={(event) => form.setData('name', event.target.value)}
                />
                {form.errors.name && (
                  <p className="mt-2 text-xs text-red-600">{form.errors.name}</p>
                )}
              </div>
              <div>
                <label className="mb-1 block text-xs font-medium text-ui-text dark:text-ui-dark-text">
                  Workspace type
                </label>
                <select
                  value={form.data.workspace_type}
                  onChange={(event) => form.setData('workspace_type', event.target.value)}
                  className="h-9 w-full rounded-btn border border-gray-200 bg-white px-3 text-sm text-ui-text outline-none transition focus:border-ui-green focus:ring-2 focus:ring-ui-green/15 dark:border-slate-600 dark:bg-slate-900 dark:text-ui-dark-text"
                >
                  {Object.entries(workspaceTypes).map(([key, label]) => (
                    <option key={key} value={key}>
                      {label}
                    </option>
                  ))}
                </select>
                {form.errors.workspace_type && (
                  <p className="mt-2 text-xs text-red-600">{form.errors.workspace_type}</p>
                )}
              </div>
              <div>
                <label className="mb-1 block text-xs font-medium text-ui-text dark:text-ui-dark-text">
                  Industry
                </label>
                <Input
                  value={form.data.industry}
                  onChange={(event) => form.setData('industry', event.target.value)}
                  placeholder="Retail, healthcare, education"
                />
                {form.errors.industry && (
                  <p className="mt-2 text-xs text-red-600">{form.errors.industry}</p>
                )}
              </div>
              <div className="sm:col-span-2">
                <label className="mb-1 block text-xs font-medium text-ui-text dark:text-ui-dark-text">
                  Timezone
                </label>
                <select
                  value={form.data.timezone}
                  onChange={(event) => form.setData('timezone', event.target.value)}
                  className="h-9 w-full rounded-btn border border-gray-200 bg-white px-3 text-sm text-ui-text outline-none transition focus:border-ui-green focus:ring-2 focus:ring-ui-green/15 dark:border-slate-600 dark:bg-slate-900 dark:text-ui-dark-text"
                >
                  {timezones.map((timezone) => (
                    <option key={timezone} value={timezone}>
                      {timezone}
                    </option>
                  ))}
                </select>
                {form.errors.timezone && (
                  <p className="mt-2 text-xs text-red-600">{form.errors.timezone}</p>
                )}
              </div>
            </div>
          </SettingsSection>
        </Card>
      </div>

      <div className="flex justify-end gap-2 border-t border-gray-100 pt-2 dark:border-slate-700">
        <button
          type="submit"
          disabled={form.processing || !form.data.name.trim()}
          className="inline-flex h-9 items-center justify-center gap-2 rounded-btn bg-ui-green px-3.5 text-sm font-medium text-white shadow-sm transition-all hover:bg-ui-green-dark disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Save className="h-4 w-4" />
          {form.processing ? 'Saving...' : 'Save changes'}
        </button>
      </div>
    </form>
  );
}

export default function SettingsIndex({
  workspace,
  workspaceTypes = {},
  timezones = [],
  initialTab = 'workspace',
}: SettingsIndexProps) {
  const [activeTab, setActiveTabState] = useState(initialTab);
  // Keep the open section in the URL so it can be bookmarked, shared and restored on reload.
  const setActiveTab = (tab: string) => {
    setActiveTabState(tab);
    if (typeof window !== 'undefined') {
      const url = new URL(window.location.href);
      url.searchParams.set('tab', tab);
      window.history.replaceState(window.history.state, '', url.toString());
    }
  };
  useEffect(() => {
    setActiveTabState(initialTab);
  }, [initialTab]);
  const tabs = [
    {
      id: 'workspace',
      label: 'Workspace',
      icon: Building2,
      desc: 'Name, logo and timezone',
      component: (
        <WorkspaceTab workspace={workspace} workspaceTypes={workspaceTypes} timezones={timezones} />
      ),
    },
    {
      id: 'profile',
      label: 'Profile',
      icon: User,
      desc: 'Your name, email & photo',
      component: <ProfileTab />,
    },
    {
      id: 'security',
      label: 'Security',
      icon: ShieldCheck,
      desc: 'Password, two-step login & devices',
      component: <SecurityTab />,
    },
    {
      id: 'notifications',
      label: 'Notifications',
      icon: Bell,
      desc: 'What we tell you about, and how',
      component: <NotificationsTab />,
    },
    {
      id: 'inbox',
      label: 'Inbox',
      icon: Inbox,
      desc: 'How new chats are assigned',
      component: <InboxTab />,
    },
  ];
  const active = tabs.find((tab) => tab.id === activeTab) || tabs[0];
  const visibleTabs = tabs;

  return (
    <AppShell>
      <Head title="Settings" />
      <div className="mx-auto w-full max-w-[1400px]">
        <PageHeader title="Settings" subtitle="Your workspace, profile and preferences." />

        <SettingsNav active="general" className="mt-4" />

        <div className="flex flex-col items-start gap-4 lg:flex-row">
          <nav className="w-full flex-shrink-0 lg:w-[220px]">
            <Card className="p-1.5 lg:sticky lg:top-4 lg:max-h-[calc(100vh-7rem)] lg:self-start lg:overflow-y-auto">
              <div className="scrollbar-none flex gap-1 overflow-x-auto lg:flex-col lg:overflow-visible">
                {visibleTabs.map((tab) => {
                  const Icon = tab.icon;
                  const isActive = activeTab === tab.id;

                  return (
                    <button
                      key={tab.id}
                      type="button"
                      onClick={() => setActiveTab(tab.id)}
                      className={`flex flex-shrink-0 items-center gap-2.5 rounded-btn px-2.5 py-2 text-left transition lg:w-full ${
                        isActive
                          ? 'bg-ui-green-soft text-ui-green-dark dark:bg-emerald-950/40 dark:text-emerald-400'
                          : 'text-ui-text-muted hover:bg-gray-50 hover:text-ui-text dark:text-ui-dark-text-muted dark:hover:bg-slate-700/50 dark:hover:text-ui-dark-text'
                      }`}
                    >
                      <span
                        className={`flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-md ${isActive ? 'bg-white/80 dark:bg-slate-800' : 'bg-gray-100 dark:bg-slate-700'}`}
                      >
                        <Icon className="h-4 w-4" />
                      </span>
                      <span className="min-w-0">
                        <span className="block truncate text-sm font-medium">{tab.label}</span>
                        <span className="hidden truncate text-[11px] opacity-80 lg:block">
                          {tab.desc}
                        </span>
                      </span>
                    </button>
                  );
                })}
              </div>
            </Card>
          </nav>

          <div className="min-w-0 flex-1 w-full">
            <Card className="overflow-hidden p-0">
              <div className="border-b border-gray-100 px-5 py-3 dark:border-slate-700">
                <h2 className="text-base font-semibold text-ui-text dark:text-ui-dark-text">
                  {active.label}
                </h2>
                <p className="mt-0.5 text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
                  {active.desc}
                </p>
              </div>
              <div className="p-5">{active.component}</div>
            </Card>
          </div>
        </div>
      </div>
    </AppShell>
  );
}
