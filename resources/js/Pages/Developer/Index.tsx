import { brandName } from '../../lib/brand';
import { friendlyStatus } from '@/lib/friendly';
import SettingsNav from '@/Components/Layout/SettingsNav';
import { Alert } from '@/Components/UI/Alert';
import { Badge } from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { Card, CardContent } from '@/Components/UI/Card';
import { Modal, PageHeader } from '@/Components/UI/Elements';
import { useConfirm } from '@/hooks/useConfirm';
import { useToast } from '@/hooks/useToast';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Check, Copy, Loader2, Plus, RefreshCw, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';

type Endpoint = {
  id: string;
  method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  path: string;
  summary: string;
  description: string;
  params: Array<{ name: string; type: string; required: boolean; desc: string }>;
  example: string;
  response: string;
};

type Props = {
  webhooks: {
    events: Array<{ id: string; label: string; desc: string }>;
    deliveries: Array<{
      id: string;
      event: string;
      url: string;
      status: number;
      attempts: number;
      duration: number;
      time: string | null;
      error?: string | null;
    }>;
    endpoints: Array<{
      id: number;
      url: string;
      events: string[];
      is_enabled: boolean;
      has_secret: boolean;
      last_tested_at: string | null;
      last_status: number | null;
      last_error: string | null;
      created_at: string | null;
    }>;
  };
  requestLogs: Array<{
    id: string;
    method: Endpoint['method'];
    path: string;
    status: number;
    duration: number;
    ip: string;
    time: string | null;
  }>;
  api: {
    base_url: string;
    rate_limit: string;
    endpoints: Array<{ id: string; label: string; endpoints: Endpoint[] }>;
  };
  keys: Array<{
    id: number;
    name: string;
    prefix: string;
    lastUsed: string | null;
    lastUsedIp?: string | null;
    created: string | null;
    revokedAt?: string | null;
    scopes: string[];
  }>;
  newApiKey?: string | null;
  availableScopes: Array<{ id: string; label: string }>;
};

const tabs = [
  { id: 'keys', label: 'API keys' },
  { id: 'webhooks', label: 'Outgoing webhooks' },
  { id: 'docs', label: 'API reference' },
  { id: 'logs', label: 'Request logs' },
  { id: 'sdks', label: 'Examples' },
];

const methodClass: Record<string, string> = {
  GET: 'bg-blue-50 text-blue-700 dark:bg-blue-500/15 dark:text-blue-200',
  POST: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-200',
  PUT: 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-200',
  PATCH: 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-200',
  DELETE: 'bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-200',
};

function formatDate(value: string | null | undefined) {
  if (!value) return 'Never';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('en-IN', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function MethodBadge({ method }: { method: string }) {
  return (
    <span
      className={`inline-flex min-w-[52px] justify-center rounded px-2 py-0.5 font-mono text-[10px] font-bold ${methodClass[method] || methodClass.GET}`}
    >
      {method}
    </span>
  );
}

function CodeBlock({ code }: { code: string }) {
  const { toast } = useToast();
  return (
    <div className="overflow-hidden rounded-btn bg-slate-950 ring-1 ring-slate-700">
      <div className="flex items-center justify-between border-b border-slate-800 bg-slate-900 px-3 py-2">
        <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">
          Example
        </span>
        <button
          type="button"
          onClick={() => {
            navigator.clipboard?.writeText(code);
            toast.success('Copied');
          }}
          className="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-400 hover:text-white"
        >
          <Copy className="h-3 w-3" />
          Copy
        </button>
      </div>
      <pre className="overflow-x-auto whitespace-pre-wrap p-4 font-mono text-xs leading-6 text-slate-100">
        {code}
      </pre>
    </div>
  );
}

function DocsTab({ api }: { api: Props['api'] }) {
  const [groupId, setGroupId] = useState(api.endpoints[0]?.id || '');
  const group = api.endpoints.find((item) => item.id === groupId) || api.endpoints[0];
  const [endpointId, setEndpointId] = useState(group?.endpoints[0]?.id || '');
  const endpoint = group?.endpoints.find((item) => item.id === endpointId) || group?.endpoints[0];

  if (!endpoint || !group) return null;

  return (
    <div className="flex min-h-[480px] flex-col gap-6 lg:flex-row">
      <aside className="lg:w-64">
        <div className="space-y-4">
          {api.endpoints.map((endpointGroup) => (
            <div key={endpointGroup.id}>
              <div className="mb-1 px-2 text-[10px] font-bold uppercase tracking-wider text-ui-text-muted dark:text-ui-dark-text-muted">
                {endpointGroup.label}
              </div>
              <div className="space-y-1">
                {endpointGroup.endpoints.map((item) => (
                  <button
                    key={item.id}
                    type="button"
                    onClick={() => {
                      setGroupId(endpointGroup.id);
                      setEndpointId(item.id);
                    }}
                    className={`flex w-full items-center gap-2 rounded-btn px-2 py-2 text-left text-xs transition ${endpointId === item.id ? 'bg-ui-green-soft text-ui-green-dark dark:bg-emerald-500/10 dark:text-emerald-200' : 'text-ui-text-muted hover:bg-gray-50 dark:text-ui-dark-text-muted dark:hover:bg-ui-dark-surface-2'}`}
                  >
                    <MethodBadge method={item.method} />
                    <span className="truncate font-mono">{item.path}</span>
                  </button>
                ))}
              </div>
            </div>
          ))}
        </div>
      </aside>
      <div className="min-w-0 flex-1 space-y-5">
        <div>
          <div className="flex flex-wrap items-center gap-3">
            <MethodBadge method={endpoint.method} />
            <code className="rounded bg-gray-100 px-2 py-1 font-mono text-sm text-ui-text dark:bg-ui-dark-surface-2 dark:text-ui-dark-text">
              {endpoint.path}
            </code>
          </div>
          <h3 className="mt-3 text-lg font-semibold text-ui-text dark:text-ui-dark-text">
            {endpoint.summary}
          </h3>
          <p className="mt-1 text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
            {endpoint.description}
          </p>
        </div>
        {endpoint.params.length > 0 && (
          <div className="overflow-hidden rounded-card border border-gray-100 dark:border-ui-dark-border">
            <div className="border-b border-gray-100 px-4 py-2 text-xs font-semibold uppercase text-ui-text-muted dark:border-ui-dark-border dark:text-ui-dark-text-muted">
              Parameters
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <tbody>
                  {endpoint.params.map((param) => (
                    <tr
                      key={param.name}
                      className="border-t border-gray-100 dark:border-ui-dark-border"
                    >
                      <td className="px-4 py-2 font-mono text-xs">{param.name}</td>
                      <td className="px-4 py-2 text-ui-text-muted dark:text-ui-dark-text-muted">
                        {param.type}
                      </td>
                      <td className="px-4 py-2">
                        {param.required ? (
                          <span className="text-xs font-semibold text-red-600">Required</span>
                        ) : (
                          <span className="text-xs text-ui-text-muted">Optional</span>
                        )}
                      </td>
                      <td className="px-4 py-2 text-ui-text-muted dark:text-ui-dark-text-muted">
                        {param.desc}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
        <CodeBlock code={endpoint.example} />
        <div className="rounded-card border border-gray-100 p-4 dark:border-ui-dark-border">
          <div className="mb-2 text-xs font-semibold uppercase text-ui-text-muted dark:text-ui-dark-text-muted">
            Response
          </div>
          <pre className="overflow-x-auto rounded-btn bg-gray-50 p-3 font-mono text-xs text-ui-text dark:bg-ui-dark-surface-2 dark:text-ui-dark-text">
            {endpoint.response}
          </pre>
        </div>
      </div>
    </div>
  );
}

function KeysTab({
  keys,
  availableScopes,
  newApiKey,
  canManageKeys,
}: {
  keys: Props['keys'];
  availableScopes: Props['availableScopes'];
  newApiKey?: string | null;
  canManageKeys: boolean;
}) {
  const { toast } = useToast();
  const confirm = useConfirm();
  const [open, setOpen] = useState(false);
  const [processing, setProcessing] = useState(false);
  const [form, setForm] = useState({
    name: 'Workspace API key',
    scopes: ['connections:read', 'templates:read', 'conversations:read', 'messages:write'],
  });

  const createKey = () => {
    if (!canManageKeys) return;
    setProcessing(true);
    router.post(route('app.developer.keys.store'), form, {
      preserveScroll: true,
      onSuccess: () => setOpen(false),
      onFinish: () => setProcessing(false),
    });
  };

  const revokeKey = async (id: number) => {
    if (!canManageKeys) return;
    const confirmed = await confirm({
      title: 'Revoke API key',
      message: 'Revoke this API key? Existing integrations using it will stop working.',
      confirmText: 'Revoke key',
      variant: 'danger',
    });
    if (!confirmed) return;
    router.delete(route('app.developer.keys.destroy', id), { preserveScroll: true });
  };

  const toggleScope = (scope: string) => {
    setForm((current) => {
      const hasScope = current.scopes.includes(scope);
      const nextScopes = hasScope
        ? current.scopes.filter((item) => item !== scope)
        : [...current.scopes, scope];
      return {
        ...current,
        scopes:
          scope === '*' && !hasScope
            ? ['*']
            : nextScopes.filter((item) => scope === '*' || item !== '*'),
      };
    });
  };

  const copyNewKey = () => {
    if (!newApiKey) return;
    navigator.clipboard?.writeText(newApiKey);
    toast.success('API key copied');
  };

  return (
    <div className="space-y-5">
      {newApiKey && (
        <Alert variant="success" title="Copy your new API key now">
          <div className="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center">
            <code className="min-w-0 flex-1 break-all rounded-btn bg-white/80 px-3 py-2 text-xs text-ui-text dark:bg-ui-dark-surface-2 dark:text-ui-dark-text">
              {newApiKey}
            </code>
            <Button type="button" size="sm" onClick={copyNewKey}>
              <Copy className="h-4 w-4" /> Copy
            </Button>
          </div>
        </Alert>
      )}
      <Alert variant="warning" title="Keep keys secret">
        Never expose live keys in client-side code or public repositories. Rotate immediately if
        compromised.
      </Alert>
      {!canManageKeys && (
        <Alert variant="info" title="Owner only">
          Only workspace owners can create, revoke, or view credential controls.
        </Alert>
      )}
      <div className="flex justify-end">
        <Button
          type="button"
          onClick={() => setOpen(true)}
          disabled={!canManageKeys}
          title={canManageKeys ? 'Create API key' : 'Requires workspace owner'}
        >
          <Plus className="h-4 w-4" /> Create API key
        </Button>
      </div>
      <div className="space-y-3">
        {keys.map((key) => (
          <Card key={key.name} className="border-transparent dark:border-slate-700/80">
            <CardContent className="p-4">
              <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <div className="flex flex-wrap items-center gap-2">
                    <p className="font-semibold text-ui-text dark:text-ui-dark-text">{key.name}</p>
                    {key.revokedAt && <Badge variant="danger">revoked</Badge>}
                  </div>
                  <p className="mt-1 font-mono text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                    {key.prefix}****************
                  </p>
                  <div className="mt-2 flex flex-wrap gap-1">
                    {key.scopes.map((scope) => (
                      <span
                        key={scope}
                        className="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] text-ui-text-muted dark:bg-ui-dark-surface-2 dark:text-ui-dark-text-muted"
                      >
                        {scope}
                      </span>
                    ))}
                  </div>
                </div>
                <div className="space-y-2 text-xs text-ui-text-muted dark:text-ui-dark-text-muted sm:text-right">
                  <div>Last used {formatDate(key.lastUsed)}</div>
                  {key.lastUsedIp && <div>IP {key.lastUsedIp}</div>}
                  <div>Created {formatDate(key.created)}</div>
                  {!key.revokedAt && (
                    <Button
                      type="button"
                      size="xs"
                      variant="danger"
                      onClick={() => revokeKey(key.id)}
                      disabled={!canManageKeys}
                      title={canManageKeys ? 'Revoke key' : 'Requires workspace owner'}
                    >
                      <Trash2 className="h-3.5 w-3.5" /> Revoke
                    </Button>
                  )}
                </div>
              </div>
            </CardContent>
          </Card>
        ))}
        {keys.length === 0 && (
          <Card>
            <CardContent className="p-8 text-center text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
              No API keys yet. Create one to start using the public API.
            </CardContent>
          </Card>
        )}
      </div>
      <Modal
        open={open}
        onClose={() => setOpen(false)}
        title="Create API key"
        description="Keys are scoped to this workspace and can be revoked any time."
        footer={
          <>
            <Button type="button" variant="secondary" onClick={() => setOpen(false)}>
              Cancel
            </Button>
            <Button
              type="button"
              onClick={createKey}
              disabled={processing || form.scopes.length === 0}
            >
              {processing ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                <Check className="h-4 w-4" />
              )}
              Create key
            </Button>
          </>
        }
      >
        <div className="space-y-4">
          <div>
            <label className="mb-1.5 block text-sm font-medium text-ui-text dark:text-ui-dark-text">
              Key name
            </label>
            <input
              value={form.name}
              onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))}
              className="h-10 w-full rounded-btn border border-gray-200 bg-white px-3 text-sm dark:border-ui-dark-border dark:bg-ui-dark-surface"
            />
          </div>
          <div>
            <div className="mb-2 text-sm font-medium text-ui-text dark:text-ui-dark-text">
              Scopes
            </div>
            <div className="grid gap-2">
              {availableScopes.map((scope) => (
                <label
                  key={scope.id}
                  className="flex items-center gap-3 rounded-btn border border-gray-100 p-3 text-sm dark:border-ui-dark-border"
                >
                  <input
                    type="checkbox"
                    checked={form.scopes.includes(scope.id)}
                    onChange={() => toggleScope(scope.id)}
                    className="h-4 w-4 rounded border-gray-300 text-ui-green focus:ring-ui-green"
                  />
                  <span>
                    <span className="block font-medium text-ui-text dark:text-ui-dark-text">
                      {scope.label}
                    </span>
                    <span className="font-mono text-[11px] text-ui-text-muted dark:text-ui-dark-text-muted">
                      {scope.id}
                    </span>
                  </span>
                </label>
              ))}
            </div>
          </div>
        </div>
      </Modal>
    </div>
  );
}

function WebhooksTab({
  webhooks,
  canManageKeys,
}: {
  webhooks: Props['webhooks'];
  canManageKeys: boolean;
}) {
  const confirm = useConfirm();
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<Props['webhooks']['endpoints'][number] | null>(null);
  const [processing, setProcessing] = useState(false);
  const [form, setForm] = useState({
    url: '',
    secret: '',
    events: ['message.received', 'message.delivered'],
    is_enabled: true,
  });

  const openEditor = (endpoint?: Props['webhooks']['endpoints'][number]) => {
    setEditing(endpoint || null);
    setForm(
      endpoint
        ? {
            url: endpoint.url,
            secret: '',
            events: endpoint.events.length ? endpoint.events : ['message.received'],
            is_enabled: endpoint.is_enabled,
          }
        : {
            url: '',
            secret: '',
            events: ['message.received', 'message.delivered'],
            is_enabled: true,
          }
    );
    setOpen(true);
  };

  const saveEndpoint = () => {
    if (!canManageKeys) return;
    setProcessing(true);
    const options = {
      preserveScroll: true,
      onSuccess: () => setOpen(false),
      onFinish: () => setProcessing(false),
    };
    editing
      ? router.patch(route('app.developer.webhooks.update', editing.id), form, options)
      : router.post(route('app.developer.webhooks.store'), form, options);
  };

  const deleteEndpoint = async (id: number) => {
    if (!canManageKeys) return;
    const confirmed = await confirm({
      title: 'Remove webhook endpoint',
      message: 'Remove this webhook endpoint? Deliveries to this URL will stop.',
      confirmText: 'Remove endpoint',
      variant: 'danger',
    });
    if (!confirmed) return;
    router.delete(route('app.developer.webhooks.destroy', id), { preserveScroll: true });
  };

  const testEndpoint = (id: number) => {
    router.post(route('app.developer.webhooks.test', id), {}, { preserveScroll: true });
  };

  const toggleEvent = (eventId: string) => {
    setForm((current) => ({
      ...current,
      events: current.events.includes(eventId)
        ? current.events.filter((item) => item !== eventId)
        : [...current.events, eventId],
    }));
  };

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between gap-3">
        <div>
          <h3 className="font-semibold text-ui-text dark:text-ui-dark-text">Outgoing webhooks</h3>
          <p className="mt-1 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
            {brandName()} → your app. Send workspace events to your server, Zapier, or Make.
          </p>
        </div>
        <Button
          type="button"
          onClick={() => openEditor()}
          disabled={!canManageKeys}
          title={canManageKeys ? 'Add endpoint' : 'Requires workspace owner'}
        >
          <Plus className="h-4 w-4" /> Add endpoint
        </Button>
      </div>
      <p className="text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
        Incoming WhatsApp messages are configured in{' '}
        <Link
          href={route('app.whatsapp.connections.index')}
          className="font-medium underline underline-offset-2"
        >
          WhatsApp connections
        </Link>
        .
      </p>
      {!canManageKeys && (
        <Alert variant="info" title="Owner only">
          Webhook endpoint changes are restricted to workspace owners.
        </Alert>
      )}
      <div className="space-y-3">
        {webhooks.endpoints.map((endpoint) => (
          <Card key={endpoint.id} className="border-transparent dark:border-slate-700/80">
            <CardContent className="p-4">
              <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div className="min-w-0">
                  <div className="flex flex-wrap items-center gap-2">
                    <p className="break-all font-mono text-xs font-semibold text-ui-text dark:text-ui-dark-text">
                      {endpoint.url}
                    </p>
                    <Badge variant={endpoint.is_enabled ? 'success' : 'secondary'}>
                      {endpoint.is_enabled ? 'enabled' : 'disabled'}
                    </Badge>
                    {endpoint.has_secret && <Badge variant="info">signed</Badge>}
                  </div>
                  <div className="mt-2 flex flex-wrap gap-1">
                    {endpoint.events.map((event) => (
                      <span
                        key={event}
                        className="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] text-ui-text-muted dark:bg-ui-dark-surface-2 dark:text-ui-dark-text-muted"
                      >
                        {event}
                      </span>
                    ))}
                  </div>
                  <p className="mt-2 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                    Last test {formatDate(endpoint.last_tested_at)}{' '}
                    {endpoint.last_status ? `· ${friendlyStatus(endpoint.last_status)}` : ''}
                  </p>
                  {endpoint.last_error && (
                    <p className="mt-1 line-clamp-2 text-xs text-red-600 dark:text-red-300">
                      {endpoint.last_error}
                    </p>
                  )}
                </div>
                <div className="flex flex-wrap gap-2">
                  <Button
                    type="button"
                    size="sm"
                    variant="secondary"
                    onClick={() => testEndpoint(endpoint.id)}
                  >
                    Test
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    variant="secondary"
                    onClick={() => openEditor(endpoint)}
                    disabled={!canManageKeys}
                  >
                    Edit
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    variant="danger"
                    onClick={() => deleteEndpoint(endpoint.id)}
                    disabled={!canManageKeys}
                  >
                    Delete
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>
        ))}
        {webhooks.endpoints.length === 0 && (
          <Card>
            <CardContent className="p-8 text-center text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
              No outbound webhook endpoints configured.
            </CardContent>
          </Card>
        )}
      </div>
      <details>
        <summary className="cursor-pointer text-sm font-medium">Available events</summary>
        <div className="mt-3 grid gap-2 sm:grid-cols-2">
          {webhooks.events.map((event) => (
            <div
              key={event.id}
              className="rounded-btn border border-gray-100 p-3 dark:border-ui-dark-border"
            >
              <p className="font-mono text-xs font-semibold text-ui-text dark:text-ui-dark-text">
                {event.label}
              </p>
              <p className="mt-1 text-[11px] text-ui-text-muted dark:text-ui-dark-text-muted">
                {event.desc}
              </p>
            </div>
          ))}
        </div>
      </details>
      <div className="overflow-hidden rounded-card border border-gray-100 dark:border-ui-dark-border">
        <div className="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-ui-dark-border">
          <span className="text-sm font-semibold text-ui-text dark:text-ui-dark-text">
            Recent deliveries
          </span>
          <RefreshCw className="h-4 w-4 text-ui-text-muted" />
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <tbody>
              {webhooks.deliveries.map((delivery) => (
                <tr
                  key={delivery.id}
                  className="border-t border-gray-100 dark:border-ui-dark-border"
                >
                  <td className="px-4 py-3 font-mono text-xs">{delivery.event}</td>
                  <td className="px-4 py-3">
                    {delivery.status === 200 ? (
                      <span className="text-xs font-semibold text-emerald-600">200</span>
                    ) : (
                      <span className="text-xs font-semibold text-red-600">Failed</span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-ui-text-muted">{delivery.attempts} attempts</td>
                  <td className="px-4 py-3 text-ui-text-muted">
                    {delivery.duration ? `${delivery.duration}ms` : '-'}
                  </td>
                  <td className="px-4 py-3 text-ui-text-muted">{formatDate(delivery.time)}</td>
                </tr>
              ))}
              {webhooks.deliveries.length === 0 && (
                <tr>
                  <td className="px-4 py-8 text-center text-sm text-ui-text-muted" colSpan={5}>
                    No webhook deliveries yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
      <Modal
        open={open}
        onClose={() => setOpen(false)}
        title={editing ? 'Edit webhook endpoint' : 'Add webhook endpoint'}
        description={`${brandName()} signs test deliveries with X-App-Signature when a secret is present.`}
        footer={
          <>
            <Button type="button" variant="secondary" onClick={() => setOpen(false)}>
              Cancel
            </Button>
            <Button
              type="button"
              onClick={saveEndpoint}
              disabled={!canManageKeys || processing || form.events.length === 0 || !form.url}
            >
              {processing ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                <Check className="h-4 w-4" />
              )}
              Save endpoint
            </Button>
          </>
        }
      >
        <div className="space-y-4">
          <div>
            <label className="mb-1.5 block text-sm font-medium text-ui-text dark:text-ui-dark-text">
              Endpoint URL
            </label>
            <input
              value={form.url}
              onChange={(event) => setForm((current) => ({ ...current, url: event.target.value }))}
              placeholder="https://example.com/webhooks/zyptos"
              className="h-10 w-full rounded-btn border border-gray-200 bg-white px-3 text-sm dark:border-ui-dark-border dark:bg-ui-dark-surface"
            />
          </div>
          <div>
            <label className="mb-1.5 block text-sm font-medium text-ui-text dark:text-ui-dark-text">
              Signing secret
            </label>
            <input
              value={form.secret}
              onChange={(event) =>
                setForm((current) => ({ ...current, secret: event.target.value }))
              }
              placeholder={editing?.has_secret ? 'Leave blank to keep current secret' : 'Optional'}
              className="h-10 w-full rounded-btn border border-gray-200 bg-white px-3 text-sm dark:border-ui-dark-border dark:bg-ui-dark-surface"
            />
          </div>
          <label className="flex items-center gap-2 text-sm text-ui-text dark:text-ui-dark-text">
            <input
              type="checkbox"
              checked={form.is_enabled}
              onChange={(event) =>
                setForm((current) => ({ ...current, is_enabled: event.target.checked }))
              }
              className="h-4 w-4 rounded border-gray-300 text-ui-green focus:ring-ui-green"
            />
            Endpoint enabled
          </label>
          <div>
            <div className="mb-2 text-sm font-medium text-ui-text dark:text-ui-dark-text">
              Events
            </div>
            <div className="grid gap-2">
              {webhooks.events.map((event) => (
                <label
                  key={event.id}
                  className="flex items-start gap-3 rounded-btn border border-gray-100 p-3 text-sm dark:border-ui-dark-border"
                >
                  <input
                    type="checkbox"
                    checked={form.events.includes(event.id)}
                    onChange={() => toggleEvent(event.id)}
                    className="mt-0.5 h-4 w-4 rounded border-gray-300 text-ui-green focus:ring-ui-green"
                  />
                  <span>
                    <span className="block font-mono text-xs font-semibold text-ui-text dark:text-ui-dark-text">
                      {event.label}
                    </span>
                    <span className="text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                      {event.desc}
                    </span>
                  </span>
                </label>
              ))}
            </div>
          </div>
        </div>
      </Modal>
    </div>
  );
}

function DataTable({ children, empty }: { children: ReactNode; empty: string }) {
  const hasChildren = Array.isArray(children) ? children.length > 0 : Boolean(children);
  return (
    <div className="overflow-hidden rounded-card border border-gray-100 dark:border-ui-dark-border">
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <tbody>
            {hasChildren ? (
              children
            ) : (
              <tr>
                <td className="px-4 py-8 text-center text-sm text-ui-text-muted">{empty}</td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function LogsTab({ logs }: { logs: Props['requestLogs'] }) {
  const [statusFilter, setStatusFilter] = useState('all');
  const filtered = logs.filter((log) => {
    if (statusFilter === '2xx') return log.status >= 200 && log.status < 300;
    if (statusFilter === '4xx') return log.status >= 400 && log.status < 500;
    if (statusFilter === '5xx') return log.status >= 500;
    return true;
  });

  return (
    <div className="space-y-4">
      <div className="flex gap-2">
        {['all', '2xx', '4xx', '5xx'].map((item) => (
          <button
            key={item}
            type="button"
            onClick={() => setStatusFilter(item)}
            className={`rounded-full px-3 py-1.5 text-xs font-semibold ${statusFilter === item ? 'bg-ui-text text-white dark:bg-ui-dark-text dark:text-ui-dark-bg' : 'bg-white text-ui-text-muted ring-1 ring-gray-200 dark:bg-ui-dark-surface dark:text-ui-dark-text-muted dark:ring-ui-dark-border'}`}
          >
            {item}
          </button>
        ))}
      </div>
      <DataTable empty="No API request logs yet.">
        {filtered.map((log) => (
          <tr
            key={log.id}
            className="border-t border-gray-100 font-mono text-xs dark:border-ui-dark-border"
          >
            <td className="px-4 py-3">
              <MethodBadge method={log.method} />
            </td>
            <td className="px-4 py-3">{log.path}</td>
            <td
              className={`px-4 py-3 font-bold ${log.status >= 400 ? 'text-red-600' : 'text-emerald-600'}`}
            >
              {friendlyStatus(log.status)}
            </td>
            <td className="px-4 py-3 text-ui-text-muted">{log.duration}ms</td>
            <td className="px-4 py-3 text-ui-text-muted">{log.ip}</td>
            <td className="px-4 py-3 text-ui-text-muted">{formatDate(log.time)}</td>
          </tr>
        ))}
      </DataTable>
    </div>
  );
}

function CodeExamplesTab({ baseUrl }: { baseUrl: string }) {
  const endpoint = JSON.stringify(`${baseUrl}/templates`);
  const examples = [
    [
      'Node.js',
      `const response = await fetch(${endpoint}, {
  headers: { Authorization: 'Bearer YOUR_API_KEY' }
});
console.log(await response.json());`,
    ],
    [
      'Python',
      `import json
from urllib.request import Request, urlopen

request = Request(${endpoint}, headers={
    "Authorization": "Bearer YOUR_API_KEY"
})
with urlopen(request) as response:
    print(json.load(response))`,
    ],
    [
      'PHP',
      `$request = curl_init(${endpoint});
curl_setopt_array($request, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer YOUR_API_KEY'],
]);
echo curl_exec($request);
curl_close($request);`,
    ],
  ];
  return (
    <div className="space-y-4">
      <p className="text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
        Use a workspace API key with templates:read scope. These examples use standard HTTP clients
        and read templates without sending messages.
      </p>
      {examples.map(([name, code]) => (
        <Card key={name}>
          <CardContent className="p-4">
            <p className="font-semibold text-ui-text dark:text-ui-dark-text">{name}</p>
            <pre className="mt-3 overflow-auto rounded-btn bg-gray-50 p-3 text-xs dark:bg-ui-dark-surface-2">
              <code>{code}</code>
            </pre>
          </CardContent>
        </Card>
      ))}
    </div>
  );
}

export default function DeveloperIndex(props: Props) {
  const { workspace_permissions } = usePage().props as any;
  const { url } = usePage();
  const requestedTab = new URLSearchParams(url.split('?')[1] || '').get('tab');
  const active = tabs.find((item) => item.id === requestedTab) || tabs[0];
  const tab = active.id;
  const setTab = (next: string) =>
    router.get(
      route('app.developer.index'),
      { tab: next },
      { preserveState: true, preserveScroll: true }
    );
  const canManageKeys = Boolean(workspace_permissions?.['api_keys.owner']);
  return (
    <AppShell>
      <Head title="Developer API" />
      <div className="module-page">
        <SettingsNav active="developer" className="mb-0" />
        <PageHeader
          title="Developer API"
          description="Connect your own software with API keys and event alerts."
        />
        <nav
          aria-label="Developer sections"
          className="flex gap-5 overflow-x-auto border-b border-gray-200 dark:border-slate-800"
        >
          {tabs.map((item) => (
            <button
              key={item.id}
              type="button"
              onClick={() => setTab(item.id)}
              aria-current={item.id === tab ? 'page' : undefined}
              className={`shrink-0 border-b-2 pb-3 text-sm font-medium ${item.id === tab ? 'border-ui-green text-ui-text dark:text-white' : 'border-transparent text-ui-text-muted hover:text-ui-text dark:text-ui-dark-text-muted dark:hover:text-white'}`}
            >
              {item.label}
            </button>
          ))}
        </nav>
        {tab === 'docs' && <DocsTab api={props.api} />}
        {tab === 'keys' && (
          <KeysTab
            keys={props.keys}
            availableScopes={props.availableScopes}
            newApiKey={props.newApiKey}
            canManageKeys={canManageKeys}
          />
        )}
        {tab === 'webhooks' && (
          <WebhooksTab webhooks={props.webhooks} canManageKeys={canManageKeys} />
        )}
        {tab === 'logs' && <LogsTab logs={props.requestLogs} />}
        {tab === 'sdks' && <CodeExamplesTab baseUrl={props.api.base_url} />}
      </div>
    </AppShell>
  );
}
