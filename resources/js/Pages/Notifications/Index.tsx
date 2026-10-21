import Button from '@/Components/UI/Button';
import { Card } from '@/Components/UI/Card';
import { IconButton, PageHeader } from '@/Components/UI/Elements';
import { EmptyState } from '@/Components/UI/EmptyState';
import AppShell from '@/Layouts/AppShell';
import { cn } from '@/lib/utils';
import { Head, Link, router } from '@inertiajs/react';
import { Bell, CheckCircle2, ShieldAlert } from 'lucide-react';

type Notification = {
  id: number;
  type: string;
  severity: string;
  title: string;
  body?: string | null;
  action_url?: string | null;
  read_at?: string | null;
  created_at?: string | null;
};

type Paginated<T> = { data: T[]; total: number };

const STATUS_TABS: Array<[string, string]> = [
  ['all', 'All'],
  ['unread', 'Unread'],
  ['read', 'Read'],
];

export default function NotificationsIndex({
  notifications,
  filters,
  stats,
}: {
  notifications: Paginated<Notification>;
  filters: any;
  stats: { unread: number; critical: number };
}) {
  const markRead = (notification: Notification) =>
    router.post(route('app.notifications.read', notification.id), {}, { preserveScroll: true });
  const markAllRead = () =>
    router.post(route('app.notifications.read-all'), {}, { preserveScroll: true });
  const activeStatus = filters?.status || 'all';

  return (
    <AppShell>
      <Head title="Notifications" />
      <div className="module-page max-w-4xl">
        <PageHeader
          title="Notifications"
          description={stats.unread > 0 ? `${stats.unread} unread` : 'You are all caught up.'}
          actions={
            stats.unread > 0 ? (
              <Button type="button" variant="secondary" onClick={markAllRead}>
                <CheckCircle2 className="h-4 w-4" />
                Mark all read
              </Button>
            ) : undefined
          }
        />

        <div className="flex gap-1">
          {STATUS_TABS.map(([status, label]) => (
            <Link
              key={status}
              href={route('app.notifications.index', { ...filters, status })}
              preserveScroll
              className={cn(
                'rounded-full px-3 py-1 text-sm font-medium transition',
                activeStatus === status
                  ? 'bg-ui-green-soft text-ui-green-dark dark:bg-emerald-500/15 dark:text-emerald-200'
                  : 'text-ui-text-muted hover:bg-gray-100 dark:text-ui-dark-text-muted dark:hover:bg-slate-700'
              )}
            >
              {label}
            </Link>
          ))}
        </div>

        <Card className="overflow-hidden border-transparent dark:border-slate-700/80">
          {notifications.data.length === 0 ? (
            <EmptyState
              icon={Bell}
              title="No notifications"
              description="Alerts about chats, chatbots, campaigns and templates show up here."
            />
          ) : (
            <ul className="divide-y divide-gray-100 dark:divide-slate-700">
              {notifications.data.map((notification) => {
                const unread = !notification.read_at;
                const critical = notification.severity === 'critical';
                return (
                  <li key={notification.id} className="flex items-start gap-3 px-4 py-3">
                    <span
                      className={cn(
                        'mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg',
                        critical
                          ? 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300'
                          : 'bg-ui-green-soft text-ui-green-dark dark:bg-emerald-500/10 dark:text-emerald-300'
                      )}
                    >
                      {critical ? (
                        <ShieldAlert className="h-4 w-4" />
                      ) : (
                        <Bell className="h-4 w-4" />
                      )}
                    </span>
                    <div className="min-w-0 flex-1">
                      <p
                        className={cn(
                          'flex items-center gap-2 text-sm text-ui-text dark:text-ui-dark-text',
                          unread ? 'font-semibold' : 'font-medium'
                        )}
                      >
                        {unread && (
                          <span
                            className="h-2 w-2 shrink-0 rounded-full bg-ui-green"
                            aria-label="Unread"
                          />
                        )}
                        {notification.title}
                      </p>
                      {notification.body && (
                        <p className="mt-0.5 text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
                          {notification.body}
                        </p>
                      )}
                      <p className="mt-1 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                        {notification.created_at
                          ? new Date(notification.created_at).toLocaleString()
                          : ''}
                      </p>
                    </div>
                    <div className="flex shrink-0 items-center gap-0.5">
                      {notification.action_url && (
                        <a href={notification.action_url}>
                          <Button size="sm" variant="ghost">
                            Open
                          </Button>
                        </a>
                      )}
                      {unread && (
                        <IconButton
                          size="sm"
                          variant="ghost"
                          onClick={() => markRead(notification)}
                          title="Mark as read"
                          aria-label="Mark as read"
                        >
                          <CheckCircle2 className="h-4 w-4" />
                        </IconButton>
                      )}
                    </div>
                  </li>
                );
              })}
            </ul>
          )}
        </Card>
      </div>
    </AppShell>
  );
}
