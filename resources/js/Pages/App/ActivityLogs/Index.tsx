import { friendlyStatus } from '@/lib/friendly';
import SettingsNav from '@/Components/Layout/SettingsNav';
import TextInput from '@/Components/TextInput';
import Button from '@/Components/UI/Button';
import { Card, CardContent } from '@/Components/UI/Card';
import { PageHeader, ThemedIconTile } from '@/Components/UI/Elements';
import { EmptyState } from '@/Components/UI/EmptyState';
import { useToast } from '@/hooks/useToast';
import AppShell from '@/Layouts/AppShell';
import { Head } from '@inertiajs/react';
import {
  Activity,
  AlertCircle,
  Bot,
  CheckCircle2,
  Clock,
  Download,
  FileText,
  Megaphone,
  MessageSquare,
  Search,
  Tag,
  Users,
  Wallet,
  XCircle,
} from 'lucide-react';
import { useMemo, useState } from 'react';

interface ActivityLog {
  id: string;
  type: string;
  description: string;
  metadata: Record<string, any>;
  created_at: string;
}

const filters = [
  { id: 'all', label: 'All' },
  { id: 'campaign', label: 'Campaigns' },
  { id: 'contact', label: 'Contacts' },
  { id: 'inbox', label: 'Inbox' },
  { id: 'template', label: 'Templates' },
  { id: 'automation', label: 'Automation' },
  { id: 'billing', label: 'Billing' },
];

function classify(log: ActivityLog) {
  const type = log.type.toLowerCase();
  const text = `${log.description} ${JSON.stringify(log.metadata || {})}`.toLowerCase();

  if (type.includes('message') || text.includes('conversation')) return 'inbox';
  if (type.includes('connection')) return 'inbox';
  if (text.includes('campaign') || text.includes('broadcast')) return 'campaign';
  if (text.includes('contact')) return 'contact';
  if (text.includes('template')) return 'template';
  if (text.includes('bot') || text.includes('automation')) return 'automation';
  if (text.includes('billing') || text.includes('payment') || text.includes('wallet'))
    return 'billing';
  return 'all';
}

function iconFor(category: string, type: string) {
  if (type.includes('error') || type.includes('failed'))
    return { icon: XCircle, tone: 'red' as const };
  if (type.includes('success')) return { icon: CheckCircle2, tone: 'green' as const };
  if (category === 'campaign') return { icon: Megaphone, tone: 'purple' as const };
  if (category === 'contact') return { icon: Users, tone: 'blue' as const };
  if (category === 'template') return { icon: FileText, tone: 'amber' as const };
  if (category === 'automation') return { icon: Bot, tone: 'purple' as const };
  if (category === 'billing') return { icon: Wallet, tone: 'green' as const };
  if (category === 'inbox') return { icon: MessageSquare, tone: 'blue' as const };
  return { icon: Activity, tone: 'gray' as const };
}

function formatDate(value: string) {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('en-IN', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export default function ActivityLogsIndex({ logs }: { account: any; logs: ActivityLog[] }) {
  const { toast } = useToast();
  const [filter, setFilter] = useState('all');
  const [range, setRange] = useState('7d');
  const [search, setSearch] = useState('');

  const filtered = useMemo(
    () =>
      logs.filter((log) => {
        const category = classify(log);
        if (filter !== 'all' && category !== filter) return false;

        const createdAt = new Date(log.created_at).getTime();
        if (!Number.isNaN(createdAt)) {
          const days = range === '24h' ? 1 : range === '30d' ? 30 : 7;
          if (createdAt < Date.now() - days * 24 * 60 * 60 * 1000) return false;
        }

        if (search.trim()) {
          const haystack =
            `${log.description} ${log.type} ${JSON.stringify(log.metadata || {})}`.toLowerCase();
          if (!haystack.includes(search.toLowerCase())) return false;
        }

        return true;
      }),
    [filter, logs, range, search]
  );

  const counts = useMemo(
    () => ({
      total: logs.length,
      errors: logs.filter((log) => log.type.includes('error') || log.type.includes('failed'))
        .length,
      inbox: logs.filter((log) => classify(log) === 'inbox').length,
      billing: logs.filter((log) => classify(log) === 'billing').length,
    }),
    [logs]
  );

  return (
    <AppShell>
      <Head title="Activity log" />
      <div className="module-page max-w-[1100px]">
        <SettingsNav active="activity" className="mb-0" />
        <PageHeader title="Activity log" description="Who did what in your workspace, and when." />

        <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
          {[
            { label: 'Total events', value: counts.total, icon: Activity, tone: 'blue' as const },
            {
              label: 'Inbox events',
              value: counts.inbox,
              icon: MessageSquare,
              tone: 'green' as const,
            },
            {
              label: 'Billing events',
              value: counts.billing,
              icon: Wallet,
              tone: 'amber' as const,
            },
            {
              label: 'Errors',
              value: counts.errors,
              icon: AlertCircle,
              tone: counts.errors ? ('red' as const) : ('gray' as const),
            },
          ].map((item) => {
            const Icon = item.icon;
            return (
              <Card key={item.label} className="border-transparent dark:border-slate-700/80">
                <CardContent className="flex items-center gap-3 p-4">
                  <ThemedIconTile tone={item.tone}>
                    <Icon className="h-5 w-5" />
                  </ThemedIconTile>
                  <div>
                    <p className="text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                      {item.label}
                    </p>
                    <p className="text-xl font-bold text-ui-text dark:text-ui-dark-text">
                      {item.value.toLocaleString('en-IN')}
                    </p>
                  </div>
                </CardContent>
              </Card>
            );
          })}
        </div>

        <div className="flex flex-wrap items-center gap-3">
          <div className="flex rounded-btn bg-gray-100 p-1 dark:bg-ui-dark-surface-2">
            {[
              ['24h', '24h'],
              ['7d', '7 days'],
              ['30d', '30 days'],
            ].map(([id, label]) => (
              <button
                key={id}
                type="button"
                onClick={() => setRange(id)}
                className={`rounded-md px-3 py-1.5 text-xs font-semibold ${range === id ? 'bg-white text-ui-text shadow-sm dark:bg-ui-dark-surface dark:text-ui-dark-text' : 'text-ui-text-muted dark:text-ui-dark-text-muted'}`}
              >
                {label}
              </button>
            ))}
          </div>
          <div className="relative min-w-[220px] flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ui-text-muted" />
            <TextInput
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search activity..."
              className="pl-10"
            />
          </div>
        </div>

        <Card className="overflow-hidden border-transparent dark:border-slate-700/80">
          <div className="border-b border-gray-100 px-4 pt-3 dark:border-ui-dark-border">
            <div className="-mx-1 flex gap-1 overflow-x-auto px-1 pb-3">
              {filters.map((item) => (
                <button
                  key={item.id}
                  type="button"
                  onClick={() => setFilter(item.id)}
                  className={`shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold transition ${filter === item.id ? 'bg-ui-text text-white dark:bg-ui-dark-text dark:text-ui-dark-bg' : 'text-ui-text-muted hover:bg-gray-100 hover:text-ui-text dark:text-ui-dark-text-muted dark:hover:bg-ui-dark-surface-2 dark:hover:text-ui-dark-text'}`}
                >
                  {item.label}
                </button>
              ))}
            </div>
          </div>
          <CardContent className="p-5">
            {filtered.length === 0 ? (
              <EmptyState
                icon={Activity}
                title="No activity"
                description="Try a different filter or date range."
              />
            ) : (
              <div>
                {filtered.map((log, index) => {
                  const category = classify(log);
                  const meta = iconFor(category, log.type);
                  const Icon = meta.icon;
                  return (
                    <div key={log.id} className="relative flex gap-4 pb-6 last:pb-0">
                      {index < filtered.length - 1 && (
                        <div className="absolute left-5 top-11 h-[calc(100%-44px)] w-px bg-gray-200 dark:bg-ui-dark-border" />
                      )}
                      <ThemedIconTile tone={meta.tone} className="relative z-10">
                        <Icon className="h-5 w-5" />
                      </ThemedIconTile>
                      <div className="min-w-0 flex-1 rounded-card border border-gray-100 bg-white p-4 dark:border-ui-dark-border dark:bg-ui-dark-surface">
                        <div className="flex flex-wrap items-center gap-2">
                          <span className="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold capitalize text-ui-text-muted dark:bg-ui-dark-surface-2 dark:text-ui-dark-text-muted">
                            {category === 'all' ? friendlyStatus(log.type) : category}
                          </span>
                          <p className="font-semibold text-ui-text dark:text-ui-dark-text">
                            {log.description}
                          </p>
                        </div>
                        <div className="mt-2 flex items-center gap-2 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                          <Clock className="h-3.5 w-3.5" />
                          {formatDate(log.created_at)}
                        </div>
                        {Object.keys(log.metadata || {}).length > 0 && (
                          <details className="mt-3">
                            <summary className="inline-flex cursor-pointer items-center gap-1 text-xs font-semibold text-ui-green-dark hover:underline dark:text-emerald-300">
                              <Tag className="h-3.5 w-3.5" />
                              Details
                            </summary>
                            <pre className="mt-3 max-h-48 overflow-auto rounded-btn bg-slate-950 p-3 text-xs text-slate-100">
                              {JSON.stringify(log.metadata, null, 2)}
                            </pre>
                          </details>
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </AppShell>
  );
}
