import { friendlyStatus } from '@/lib/friendly';
import TextInput from '@/Components/TextInput';
import { Badge } from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { Card, CardContent } from '@/Components/UI/Card';
import { Drawer, PageHeader } from '@/Components/UI/Elements';
import { LearnMore } from '@/Components/UI/LearnMore';
import { EmptyState } from '@/Components/UI/EmptyState';
import { useToast } from '@/hooks/useToast';
import AppShell from '@/Layouts/AppShell';
import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle, Archive, GitBranch, Plus, RefreshCw, Rocket } from 'lucide-react';
import { useState } from 'react';

interface FlowRecord {
  id: number;
  meta_flow_id?: string | null;
  name: string;
  status: string;
  category?: string | null;
  data_channel_uri?: string | null;
  validation_errors?: any[];
  last_synced_at?: string | null;
  last_meta_error?: string | null;
  connection?: { id: number; name: string; slug?: string | null } | null;
}

interface ConnectionOption {
  id: number;
  name: string;
  slug?: string | null;
  waba_id?: string | null;
}

const FLOW_CATEGORIES: Array<[string, string]> = [
  ['LEAD_GENERATION', 'Get leads'],
  ['APPOINTMENT_BOOKING', 'Bookings'],
  ['SIGN_UP', 'Sign up'],
  ['SIGN_IN', 'Sign in'],
  ['CONTACT_US', 'Contact us'],
  ['CUSTOMER_SUPPORT', 'Support'],
  ['SURVEY', 'Survey'],
  ['OTHER', 'Other'],
];

const categoryLabel = (value?: string | null) =>
  FLOW_CATEGORIES.find(([key]) => key === value)?.[1] ?? value ?? '';

function statusVariant(status: string) {
  const normalized = status.toLowerCase();
  if (['published', 'approved'].includes(normalized)) return 'success';
  if (['draft'].includes(normalized)) return 'warning';
  if (['deprecated', 'blocked', 'rejected'].includes(normalized)) return 'danger';
  return 'secondary';
}

export default function WhatsAppFlowsIndex({
  flows = [],
  connections = [],
}: {
  flows: FlowRecord[];
  connections: ConnectionOption[];
}) {
  const { toast } = useToast();
  const [drawerOpen, setDrawerOpen] = useState(false);
  const form = useForm({
    whatsapp_connection_id: connections[0]?.id ?? '',
    name: '',
    category: 'OTHER',
    data_channel_uri: '',
    flow_json: '',
  });

  const openCreate = () => {
    form.reset();
    form.setData({
      whatsapp_connection_id: connections[0]?.id ?? '',
      name: '',
      category: 'OTHER',
      data_channel_uri: '',
      flow_json: '',
    });
    setDrawerOpen(true);
  };

  const submit = () => {
    form.post(route('app.whatsapp.flows.store'), {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Form created');
        setDrawerOpen(false);
      },
      onError: () => toast.error('Could not create the form'),
    });
  };

  const syncFlows = () => {
    router.post(
      route('app.whatsapp.flows.sync'),
      {},
      {
        preserveScroll: true,
        onSuccess: () => toast.success('Your forms are up to date'),
      }
    );
  };

  const action = (flow: FlowRecord, type: 'publish' | 'deprecate') => {
    router.post(
      route(`app.whatsapp.flows.${type}`, { flow: flow.id }),
      {},
      {
        preserveScroll: true,
        onSuccess: () => toast.success(type === 'publish' ? 'Form published' : 'Form retired'),
        onError: () =>
          toast.error(
            type === 'publish' ? 'Could not publish the form' : 'Could not retire the form'
          ),
      }
    );
  };

  return (
    <AppShell>
      <Head title="WhatsApp forms" />
      <div className="module-page max-w-5xl">
        <PageHeader
          title="WhatsApp forms"
          description="Short forms customers fill in without leaving WhatsApp."
          actions={
            <>
              <Button type="button" variant="ghost" onClick={syncFlows}>
                <RefreshCw className="h-4 w-4" />
                Refresh
              </Button>
              <Button type="button" onClick={openCreate}>
                <Plus className="h-4 w-4" />
                New form
              </Button>
            </>
          }
        />

        {flows.length === 0 ? (
          <Card className="border-transparent dark:border-slate-700/80">
            <CardContent className="p-0">
              <EmptyState
                icon={GitBranch}
                title="No forms yet"
                description="Collect leads, bookings or sign-ups right in the chat."
                action={
                  <Button type="button" onClick={openCreate}>
                    <Plus className="h-4 w-4" />
                    New form
                  </Button>
                }
              />
            </CardContent>
          </Card>
        ) : (
          <div className="grid gap-2">
            {flows.map((flow) => (
              <Card key={flow.id} className="border-transparent dark:border-slate-700/80">
                <CardContent className="p-4">
                  <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0">
                      <div className="flex flex-wrap items-center gap-2">
                        <h2 className="truncate text-base font-semibold text-ui-text dark:text-ui-dark-text">
                          {flow.name}
                        </h2>
                        <Badge variant={statusVariant(flow.status) as any}>
                          {friendlyStatus(flow.status)}
                        </Badge>
                        {flow.category && (
                          <Badge variant="secondary">{categoryLabel(flow.category)}</Badge>
                        )}
                      </div>
                      <p className="mt-1 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                        {flow.meta_flow_id ? 'Saved to WhatsApp' : 'Not saved to WhatsApp yet'}
                        {flow.last_synced_at
                          ? ` · updated ${new Date(flow.last_synced_at).toLocaleString()}`
                          : ''}
                      </p>
                      {flow.last_meta_error && (
                        <div className="mt-3 flex gap-2 rounded-md border border-red-100 bg-red-50 p-2 text-xs text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200">
                          <AlertTriangle className="h-4 w-4 flex-shrink-0" />
                          <span>{flow.last_meta_error}</span>
                        </div>
                      )}
                    </div>
                    <div className="flex flex-wrap gap-2">
                      <Button
                        type="button"
                        size="sm"
                        variant="secondary"
                        onClick={() => action(flow, 'publish')}
                        disabled={!flow.meta_flow_id || flow.status === 'published'}
                      >
                        <Rocket className="h-4 w-4" />
                        Publish
                      </Button>
                      <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => action(flow, 'deprecate')}
                        title="Stop using this form. It cannot be published again."
                        disabled={!flow.meta_flow_id || flow.status === 'deprecated'}
                      >
                        <Archive className="h-4 w-4" />
                        Retire
                      </Button>
                    </div>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        )}
      </div>

      <Drawer
        open={drawerOpen}
        onClose={() => setDrawerOpen(false)}
        title="New form"
        description="Give it a name. You can add screens and publish it later."
        footer={
          <div className="flex justify-end gap-2">
            <Button type="button" variant="secondary" onClick={() => setDrawerOpen(false)}>
              Cancel
            </Button>
            <Button
              type="button"
              onClick={submit}
              disabled={form.processing || !form.data.name || !form.data.whatsapp_connection_id}
            >
              {form.processing ? 'Creating...' : 'Create form'}
            </Button>
          </div>
        }
      >
        <div className="space-y-4">
          <label className={connections.length > 1 ? 'block space-y-1.5' : 'hidden'}>
            <span className="text-xs font-semibold text-ui-text dark:text-ui-dark-text">
              WhatsApp number
            </span>
            <select
              value={form.data.whatsapp_connection_id}
              onChange={(event) =>
                form.setData('whatsapp_connection_id', Number(event.target.value))
              }
              className="h-10 w-full rounded-btn border border-ui-border bg-white px-3 text-sm dark:border-ui-dark-border dark:bg-slate-800"
            >
              {connections.map((connection) => (
                <option key={connection.id} value={connection.id}>
                  {connection.name}
                </option>
              ))}
            </select>
          </label>
          <label className="block space-y-1.5">
            <span className="text-xs font-semibold text-ui-text dark:text-ui-dark-text">Name</span>
            <TextInput
              value={form.data.name}
              onChange={(event) => form.setData('name', event.target.value)}
              className="w-full"
            />
          </label>
          <label className="block space-y-1.5">
            <span className="text-xs font-semibold text-ui-text dark:text-ui-dark-text">
              What is it for?
            </span>
            <select
              value={form.data.category}
              onChange={(event) => form.setData('category', event.target.value)}
              className="h-10 w-full rounded-btn border border-ui-border bg-white px-3 text-sm dark:border-ui-dark-border dark:bg-slate-800"
            >
              {FLOW_CATEGORIES.map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
              {!FLOW_CATEGORIES.some(([value]) => value === form.data.category) &&
                form.data.category && (
                  <option value={form.data.category}>{form.data.category}</option>
                )}
            </select>
          </label>
          <LearnMore label="Advanced (for developers)">
            <div className="space-y-4 pt-1">
              <label className="block space-y-1.5">
                <span className="text-xs font-semibold text-ui-text dark:text-ui-dark-text">
                  Data link (optional)
                </span>
                <TextInput
                  value={form.data.data_channel_uri}
                  onChange={(event) => form.setData('data_channel_uri', event.target.value)}
                  className="w-full"
                  placeholder="https://example.com/whatsapp/flow-data"
                />
              </label>
              <label className="block space-y-1.5">
                <span className="text-xs font-semibold text-ui-text dark:text-ui-dark-text">
                  Form code (optional)
                </span>
                <textarea
                  value={form.data.flow_json}
                  onChange={(event) => form.setData('flow_json', event.target.value)}
                  rows={10}
                  className="w-full resize-y rounded-btn border border-ui-border bg-white px-3 py-2 font-mono text-xs dark:border-ui-dark-border dark:bg-slate-800"
                  placeholder='{"version":"7.1","screens":[]}'
                />
              </label>
            </div>
          </LearnMore>
        </div>
      </Drawer>
    </AppShell>
  );
}
