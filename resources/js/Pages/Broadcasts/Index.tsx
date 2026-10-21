import { campaignAudiencePayload } from '@/lib/campaignAudience';
import { friendlyStatus } from '@/lib/friendly';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import { Badge } from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { Card, CardContent } from '@/Components/UI/Card';
import { Drawer, IconButton, PageHeader } from '@/Components/UI/Elements';
import { EmptyState } from '@/Components/UI/EmptyState';
import { Progress } from '@/Components/UI/Progress';
import { useConfirm } from '@/hooks/useConfirm';
import AppShell from '@/Layouts/AppShell';
import { usePage, Head, Link, router, useForm } from '@inertiajs/react';
import {
  BarChart3,
  Calendar,
  CheckCircle2,
  Clock,
  Copy,
  Megaphone,
  MoreVertical,
  Pause,
  Play,
  Plus,
  RotateCcw,
  Search,
  Send,
  Trash2,
  Users,
  XCircle,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface Campaign {
  id: number;
  slug: string;
  name: string;
  description: string | null;
  status: string;
  type: string;
  total_recipients: number;
  sent_count: number;
  delivered_count: number;
  read_count: number;
  failed_count: number;
  completion_percentage: number;
  scheduled_at: string | null;
  started_at: string | null;
  completed_at: string | null;
  connection: { id: number; name: string } | null;
  template: { id: number; name: string } | null;
  created_by: { id: number; name: string } | null;
  created_at: string;
}

function paginationLabel(label: unknown) {
  return String(label ?? '')
    .replace(/&laquo;\s*/g, 'Previous')
    .replace(/\s*&raquo;/g, 'Next')
    .replace(/&amp;/g, '&')
    .replace(/<[^>]*>/g, '')
    .trim();
}

interface CampaignDetail extends Campaign {
  recipient_type: string;
  message_text: string | null;
  media_url: string | null;
  media_type: string | null;
  template_params: Record<string, unknown> | unknown[];
  send_delay_seconds: number;
  respect_opt_out: boolean;
  dry_run: boolean;
  recipient_sample_size: number;
  tracking?: {
    source?: string | null;
    ctwa_ad_id?: string | null;
    ctwa_post_id?: string | null;
    utm_source?: string | null;
    utm_medium?: string | null;
    utm_campaign?: string | null;
    ab_test_enabled?: boolean;
    ab_variant?: string | null;
    retargeting_basis?: string | null;
  };
  connection: { id: number; name: string; phone_number_id?: string | null } | null;
  template: {
    id: number;
    name: string;
    language?: string | null;
    category?: string | null;
    body_text?: string | null;
  } | null;
  stats: {
    total_recipients: number;
    sent_count: number;
    delivered_count: number;
    read_count: number;
    failed_count: number;
    pending_count: number;
    completion_percentage: number;
    delivery_rate: number;
    read_rate: number;
  };
  recipients: Array<{
    id: number;
    name: string | null;
    phone_number: string | null;
    status: string;
    sent_at: string | null;
    delivered_at: string | null;
    read_at: string | null;
    failed_at: string | null;
    failure_reason: string | null;
    message_id: string | null;
    timeline?: Array<{ label: string; at: string | null; status: string }>;
  }>;
  diagnostics?: {
    preflight?: { ok?: boolean; errors?: string[]; warnings?: string[] };
    queue?: {
      pending_recipients: number;
      sending_recipients: number;
      failed_recipients: number;
      oldest_pending_at: string | null;
    };
    connection_backoff_until?: string | null;
  };
  testTargetPhone?: string | null;
}

interface CreateOptions {
  connections: Array<{ id: number; name: string; phone_number_id?: string | null }>;
  templates: Array<{
    id: number;
    name: string;
    language: string;
    category: string;
    body_text?: string | null;
    connection_id?: number | null;
  }>;
  contactsCount: number;
  contacts: Array<{
    id: number;
    name: string | null;
    wa_id: string;
    phone: string | null;
    status: string;
  }>;
  segments: Array<{ id: number; name: string; contact_count?: number | null }>;
  selectedContacts?: Array<{
    id: number;
    name: string | null;
    wa_id: string | null;
    phone: string | null;
    status?: string | null;
  }>;
}

function formatNumber(value: number | string | null | undefined) {
  const numeric = Number(value ?? 0);
  return Number.isFinite(numeric) ? new Intl.NumberFormat('en-IN').format(numeric) : '0';
}

function clampRate(value: number) {
  return Math.max(0, Math.min(100, Math.round(Number.isFinite(value) ? value : 0)));
}

function formatDate(date: string | null) {
  if (!date) return 'Not scheduled';
  const parsed = new Date(date);
  if (Number.isNaN(parsed.getTime())) return date;
  return parsed.toLocaleString('en-IN', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function campaignStatus(status: string): {
  variant: 'success' | 'warning' | 'danger' | 'default' | 'info';
  label: string;
  icon: typeof Clock;
} {
  const statusMap: Record<
    string,
    {
      variant: 'success' | 'warning' | 'danger' | 'default' | 'info';
      label: string;
      icon: typeof Clock;
    }
  > = {
    draft: { variant: 'default', label: 'Draft', icon: Clock },
    scheduled: { variant: 'info', label: 'Scheduled', icon: Clock },
    sending: { variant: 'info', label: 'Sending', icon: Play },
    paused: { variant: 'warning', label: 'Paused', icon: Pause },
    completed: { variant: 'success', label: 'Completed', icon: CheckCircle2 },
    cancelled: { variant: 'danger', label: 'Stopped', icon: XCircle },
  };

  return statusMap[status] || { variant: 'default', label: status, icon: Clock };
}

const TYPE_LABELS: Record<string, string> = {
  template: 'Template',
  text: 'Text',
  media: 'Image or file',
};

const AUDIENCE_LABELS: Record<string, string> = {
  contacts: 'All contacts',
  all: 'All contacts',
  segment: 'Segment',
  selected: 'Selected contacts',
  custom: 'Phone numbers',
};

const SOURCE_LABELS: Record<string, string> = {
  ctwa: 'Ad on Facebook or Instagram',
  retargeting: 'Follow-up campaign',
  organic: 'Regular message',
  manual: 'Uploaded list',
};

function typeLabel(type: string) {
  return TYPE_LABELS[type] ?? friendlyStatus(type);
}

function CampaignCard({ campaign }: { campaign: Campaign }) {
  const confirm = useConfirm();
  const [menuOpen, setMenuOpen] = useState(false);
  const status = campaignStatus(campaign.status);
  const StatusIcon = status.icon;
  const totalRecipients = Math.max(0, Number(campaign.total_recipients || 0));
  const sentCount = Math.max(0, Number(campaign.sent_count || 0));
  const deliveredCount = Math.min(
    totalRecipients,
    Math.max(0, Number(campaign.delivered_count || 0))
  );
  const readCount = Math.min(deliveredCount, Math.max(0, Number(campaign.read_count || 0)));
  const failedCount = Math.max(0, Number(campaign.failed_count || 0));
  const readRate = deliveredCount > 0 ? clampRate((readCount / deliveredCount) * 100) : 0;
  const deliveryRate =
    totalRecipients > 0 ? clampRate((deliveredCount / totalRecipients) * 100) : 0;
  const processed = Math.min(totalRecipients, sentCount + failedCount);
  const processedRate = totalRecipients > 0 ? clampRate((processed / totalRecipients) * 100) : 0;
  const date =
    campaign.scheduled_at || campaign.completed_at || campaign.started_at || campaign.created_at;
  const canStart =
    ['draft', 'scheduled', 'paused'].includes(campaign.status) && campaign.total_recipients > 0;
  const canPause = campaign.status === 'sending';
  const canCancel = ['draft', 'scheduled', 'sending', 'paused'].includes(campaign.status);
  const canDelete = ['draft', 'cancelled', 'completed'].includes(campaign.status);
  const canRetry =
    failedCount > 0 && ['sending', 'completed', 'paused', 'cancelled'].includes(campaign.status);

  const postAction = (routeName: string) => {
    setMenuOpen(false);
    router.post(
      route(routeName, { campaign: campaign.slug }),
      {},
      {
        preserveScroll: true,
      }
    );
  };

  const duplicate = () => postAction('app.broadcasts.duplicate');

  const deleteCampaign = async () => {
    setMenuOpen(false);
    const confirmed = await confirm({
      title: 'Delete campaign',
      message: `Delete "${campaign.name}"? You can restore it from the recovery bin for a short time.`,
      confirmText: 'Delete',
      variant: 'danger',
    });
    if (!confirmed) return;

    router.delete(route('app.broadcasts.destroy', { campaign: campaign.slug }), {
      preserveScroll: true,
    });
  };

  const cancelCampaign = async () => {
    setMenuOpen(false);
    const confirmed = await confirm({
      title: 'Stop campaign',
      message: `Stop "${campaign.name}"? Messages not sent yet will not go out.`,
      confirmText: 'Stop campaign',
      variant: 'warning',
    });
    if (!confirmed) return;

    router.post(
      route('app.broadcasts.cancel', { campaign: campaign.slug }),
      {},
      {
        preserveScroll: true,
      }
    );
  };

  const menuItemClass =
    'flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm font-medium text-ui-text transition hover:bg-gray-100 dark:text-ui-dark-text dark:hover:bg-ui-dark-surface-2 disabled:cursor-not-allowed disabled:opacity-45';
  const dangerMenuItemClass =
    'flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-500/10 disabled:cursor-not-allowed disabled:opacity-45';

  return (
    <Card className="group border-transparent transition-shadow hover:shadow-card-lg dark:border-slate-700/80">
      <CardContent className="p-4">
        <div className="mb-3 flex items-start justify-between gap-3">
          <div className="flex min-w-0 items-center gap-2">
            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-ui-green-soft text-ui-green-dark dark:bg-ui-dark-green-soft dark:text-emerald-200">
              <Megaphone className="h-4 w-4" />
            </div>
            <div className="min-w-0">
              <Link
                href={route('app.broadcasts.index', { campaign: campaign.slug })}
                className="line-clamp-2 font-semibold leading-snug text-ui-text hover:text-ui-green-dark dark:text-ui-dark-text dark:hover:text-emerald-300"
              >
                {campaign.name}
              </Link>
              <p className="text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                {typeLabel(campaign.type)}
              </p>
            </div>
          </div>
          <Badge variant={status.variant} className="shrink-0 gap-1">
            <StatusIcon className="h-3 w-3" />
            {status.label}
          </Badge>
        </div>

        {campaign.description && (
          <p className="mb-3 line-clamp-2 text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
            {campaign.description}
          </p>
        )}

        <div className="flex flex-wrap items-center gap-x-3 gap-y-2 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
          <span className="flex items-center gap-1">
            <Users className="h-3.5 w-3.5" />
            {formatNumber(campaign.total_recipients)} contacts
          </span>
          <span className="flex items-center gap-1">
            <Calendar className="h-3.5 w-3.5" />
            {formatDate(date)}
          </span>
        </div>

        {campaign.status !== 'draft' && campaign.status !== 'scheduled' ? (
          <div className="mt-4">
            <div className="mb-1.5 flex items-center justify-between text-xs">
              <span className="text-ui-text-muted dark:text-ui-dark-text-muted">
                {formatNumber(processed)} of {formatNumber(totalRecipients)} sent
              </span>
              <span className="font-semibold tabular-nums text-ui-text dark:text-ui-dark-text">
                {processedRate}%
              </span>
            </div>
            <Progress value={processedRate} variant={failedCount > 0 ? 'danger' : 'success'} />
            <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
              <span>
                Delivered{' '}
                <span className="font-semibold text-ui-text dark:text-ui-dark-text">
                  {deliveryRate}%
                </span>
              </span>
              <span>
                Read{' '}
                <span className="font-semibold text-ui-text dark:text-ui-dark-text">
                  {readRate}%
                </span>
              </span>
              {failedCount > 0 && (
                <span className="text-red-600 dark:text-red-300">
                  {formatNumber(failedCount)} failed
                  {canRetry && (
                    <>
                      {' · '}
                      <button
                        type="button"
                        onClick={() => postAction('app.broadcasts.retry-failed')}
                        className="font-semibold hover:underline"
                      >
                        Retry
                      </button>
                    </>
                  )}
                </span>
              )}
            </div>
          </div>
        ) : (
          <p className="mt-4 rounded-md bg-gray-50 px-3 py-2 text-xs text-ui-text-muted dark:bg-ui-dark-surface-2 dark:text-ui-dark-text-muted">
            {campaign.status === 'scheduled'
              ? `Sends ${formatDate(campaign.scheduled_at)}`
              : 'Not sent yet'}
          </p>
        )}

        <div className="mt-4 flex items-center gap-2 border-t border-gray-100 pt-3 dark:border-ui-dark-border">
          <Link
            href={route('app.broadcasts.index', { campaign: campaign.slug })}
            className="flex-1"
          >
            <Button variant="secondary" size="sm" className="w-full">
              <BarChart3 className="h-3.5 w-3.5" />
              View
            </Button>
          </Link>
          {canStart && (
            <Button
              type="button"
              size="sm"
              className="flex-1"
              onClick={() => postAction('app.broadcasts.start')}
            >
              <Play className="h-3.5 w-3.5" />
              Send now
            </Button>
          )}
          <div className="relative">
            <IconButton
              size="sm"
              variant="ghost"
              aria-label="More actions"
              aria-expanded={menuOpen}
              onClick={() => setMenuOpen((open) => !open)}
            >
              <MoreVertical className="h-4 w-4" />
            </IconButton>
            {menuOpen && (
              <>
                <button
                  type="button"
                  aria-label="Close menu"
                  className="fixed inset-0 z-30 cursor-default"
                  onClick={() => setMenuOpen(false)}
                />
                <div className="absolute bottom-10 right-0 z-40 w-52 rounded-lg border border-gray-200 bg-white p-1.5 shadow-dropdown dark:border-ui-dark-border dark:bg-ui-dark-surface">
                  {canPause && (
                    <button
                      type="button"
                      className={menuItemClass}
                      onClick={() => postAction('app.broadcasts.pause')}
                    >
                      <Pause className="h-4 w-4" />
                      Pause
                    </button>
                  )}
                  {canRetry && (
                    <button
                      type="button"
                      className={menuItemClass}
                      onClick={() => postAction('app.broadcasts.retry-failed')}
                    >
                      <RotateCcw className="h-4 w-4" />
                      Retry failed
                    </button>
                  )}
                  <button type="button" className={menuItemClass} onClick={duplicate}>
                    <Copy className="h-4 w-4" />
                    Duplicate
                  </button>
                  {(canCancel || canDelete) && (
                    <div className="my-1 border-t border-gray-100 dark:border-ui-dark-border" />
                  )}
                  {canCancel && (
                    <button type="button" className={dangerMenuItemClass} onClick={cancelCampaign}>
                      <XCircle className="h-4 w-4" />
                      Stop campaign
                    </button>
                  )}
                  {canDelete && (
                    <button type="button" className={dangerMenuItemClass} onClick={deleteCampaign}>
                      <Trash2 className="h-4 w-4" />
                      Delete
                    </button>
                  )}
                </div>
              </>
            )}
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

function CampaignCreateDrawer({
  open,
  onClose,
  options,
}: {
  open: boolean;
  onClose: () => void;
  options: CreateOptions;
}) {
  const workspaceConnection = options.connections[0] || null;
  const selectedContacts = options.selectedContacts ?? [];
  const selectedContactIds = selectedContacts.map((contact) => contact.id);
  const form = useForm({
    name: '',
    description: '',
    type: 'template',
    whatsapp_connection_id: workspaceConnection ? String(workspaceConnection.id) : '',
    whatsapp_template_id: '',
    message_text: '',
    media_url: '',
    media_type: 'image',
    recipient_type: selectedContacts.length > 0 ? 'selected' : 'contacts',
    recipient_filters: {} as Record<string, any>,
    custom_recipients: [] as Array<{ phone: string; name?: string }>,
    scheduled_at: '',
    send_delay_seconds: 0,
    respect_opt_out: true,
    dry_run: false,
    recipient_sample_size: 0,
    tracking: {
      source: '',
      ctwa_ad_id: '',
      ctwa_post_id: '',
      utm_source: 'facebook',
      utm_medium: 'whatsapp',
      utm_campaign: '',
      ab_test_enabled: false,
      ab_variant: '',
      retargeting_basis: '',
    },
  });
  const [customText, setCustomText] = useState('');
  const labelClass = 'mb-1.5 block text-sm font-medium text-ui-text dark:text-ui-dark-text';
  const selectClass =
    'h-10 w-full rounded-btn border border-gray-200 bg-white px-3 text-sm text-ui-text focus:border-ui-green focus:ring-ui-green/20 dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text';
  const textareaClass =
    'w-full rounded-btn border border-gray-200 bg-white px-3 py-2 text-sm text-ui-text focus:border-ui-green focus:ring-ui-green/20 dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text';
  const hintClass = 'mt-1 text-xs text-ui-text-muted dark:text-ui-dark-text-muted';

  const selectedTemplate = options.templates.find(
    (template) => String(template.id) === String(form.data.whatsapp_template_id)
  );

  const submit = (event: React.FormEvent) => {
    event.preventDefault();
    const customRecipients = customText
      .split(/\n|,/)
      .map((value) => ({ phone: value.trim() }))
      .filter((item) => item.phone.length > 0);

    form.transform((data) => ({
      ...data,
      ...campaignAudiencePayload(data.recipient_type, data.recipient_filters, selectedContactIds),
      whatsapp_template_id: data.type === 'template' ? data.whatsapp_template_id : '',
      message_text: data.type === 'text' ? data.message_text : '',
      media_url: data.type === 'media' ? data.media_url : '',
      custom_recipients: data.recipient_type === 'custom' ? customRecipients : [],
    }));
    form.post(route('app.broadcasts.store', {}), {
      preserveScroll: true,
      onSuccess: onClose,
    });
  };

  return (
    <Drawer
      open={open}
      onClose={onClose}
      title="New campaign"
      description="Send one message to many people."
      className="sm:max-w-xl"
      footer={
        <div className="flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" form="campaign-create-form" disabled={form.processing}>
            {form.processing
              ? 'Saving...'
              : form.data.scheduled_at
                ? 'Schedule campaign'
                : 'Save campaign'}
          </Button>
        </div>
      }
    >
      <form id="campaign-create-form" onSubmit={submit} className="space-y-5">
        {options.connections.length === 0 && (
          <div className="rounded-btn border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
            Connect your WhatsApp number first.{' '}
            <Link
              href={route('app.whatsapp.connections.index')}
              className="font-semibold underline"
            >
              Connect now
            </Link>
          </div>
        )}

        <div>
          <label className={labelClass}>Name</label>
          <TextInput
            value={form.data.name}
            onChange={(event) => form.setData('name', event.target.value)}
            placeholder="e.g. Diwali offer"
            className="w-full"
          />
          <InputError message={form.errors.name} className="mt-2" />
        </div>

        {options.connections.length > 1 && (
          <div>
            <label className={labelClass}>Send from</label>
            <select
              value={form.data.whatsapp_connection_id}
              onChange={(event) => form.setData('whatsapp_connection_id', event.target.value)}
              className={selectClass}
            >
              {options.connections.map((connection) => (
                <option key={connection.id} value={connection.id}>
                  {connection.name}
                </option>
              ))}
            </select>
          </div>
        )}

        <div>
          <label className={labelClass}>Who gets it</label>
          <select
            value={form.data.recipient_type}
            onChange={(event) => form.setData('recipient_type', event.target.value)}
            className={selectClass}
          >
            {selectedContacts.length > 0 && (
              <option value="selected">
                Selected contacts ({formatNumber(selectedContacts.length)})
              </option>
            )}
            <option value="contacts">All contacts ({formatNumber(options.contactsCount)})</option>
            <option value="segment">A segment</option>
            <option value="custom">Phone numbers I type in</option>
          </select>
          {form.data.recipient_type === 'selected' && (
            <div className="mt-2">
              <p className={hintClass}>
                Only these contacts get it:{' '}
                {selectedContacts
                  .slice(0, 5)
                  .map((contact) => contact.name || contact.phone || contact.wa_id || 'Unnamed')
                  .join(', ')}
                {selectedContacts.length > 5
                  ? ` and ${formatNumber(selectedContacts.length - 5)} more`
                  : ''}
                .
              </p>
              <InputError message={(form.errors as any).recipient_filters} className="mt-2" />
            </div>
          )}
          {form.data.recipient_type === 'custom' && (
            <div className="mt-2">
              <textarea
                value={customText}
                onChange={(event) => setCustomText(event.target.value)}
                rows={3}
                placeholder="+919988776655, +919876543210"
                className={textareaClass}
              />
              <p className={hintClass}>One per line or separated by commas, with country code.</p>
              <InputError message={form.errors.custom_recipients} className="mt-2" />
            </div>
          )}
          {form.data.recipient_type === 'segment' && (
            <div className="mt-2">
              <select
                value={form.data.recipient_filters.segment_ids?.[0] ?? ''}
                onChange={(event) =>
                  form.setData('recipient_filters', {
                    segment_ids: event.target.value ? [Number(event.target.value)] : [],
                  })
                }
                className={selectClass}
              >
                <option value="">Choose a segment</option>
                {options.segments.map((segment) => (
                  <option key={segment.id} value={segment.id}>
                    {segment.name}
                    {segment.contact_count != null
                      ? ` (${formatNumber(segment.contact_count)})`
                      : ''}
                  </option>
                ))}
              </select>
              {options.segments.length === 0 && (
                <p className={hintClass}>
                  No segments yet.{' '}
                  <Link
                    href={route('app.contacts.segments.index')}
                    className="font-medium text-ui-green-dark hover:underline dark:text-emerald-300"
                  >
                    Create one
                  </Link>
                </p>
              )}
            </div>
          )}
        </div>

        <div>
          <label className={labelClass}>Message</label>
          <div className="grid grid-cols-3 gap-1 rounded-btn bg-gray-100 p-1 dark:bg-ui-dark-surface-2">
            {(
              [
                ['template', 'Template'],
                ['text', 'Text'],
                ['media', 'Image or file'],
              ] as const
            ).map(([value, label]) => (
              <button
                key={value}
                type="button"
                onClick={() => form.setData('type', value)}
                className={`h-8 rounded-md text-xs font-semibold transition sm:text-sm ${
                  form.data.type === value
                    ? 'bg-white text-ui-text shadow-sm dark:bg-ui-dark-surface dark:text-ui-dark-text'
                    : 'text-ui-text-muted hover:text-ui-text dark:text-ui-dark-text-muted dark:hover:text-ui-dark-text'
                }`}
              >
                {label}
              </button>
            ))}
          </div>
          {form.data.type === 'template' && (
            <div className="mt-2">
              <select
                value={form.data.whatsapp_template_id}
                onChange={(event) => form.setData('whatsapp_template_id', event.target.value)}
                className={selectClass}
              >
                <option value="">Choose a template</option>
                {options.templates.map((template) => (
                  <option key={template.id} value={template.id}>
                    {template.name} · {template.language}
                  </option>
                ))}
              </select>
              {options.templates.length === 0 ? (
                <p className={hintClass}>
                  No approved templates yet.{' '}
                  <Link
                    href={route('app.whatsapp.templates.index')}
                    className="font-medium text-ui-green-dark hover:underline dark:text-emerald-300"
                  >
                    Create one
                  </Link>
                </p>
              ) : (
                <p className={hintClass}>Templates can reach anyone on your list.</p>
              )}
              <InputError message={form.errors.whatsapp_template_id} className="mt-2" />
            </div>
          )}
          {form.data.type === 'text' && (
            <div className="mt-2">
              <textarea
                value={form.data.message_text}
                onChange={(event) => form.setData('message_text', event.target.value)}
                rows={4}
                placeholder="Type your message"
                className={textareaClass}
              />
              <p className={hintClass}>
                Only reaches people who messaged you in the last 24 hours.
              </p>
              <InputError message={form.errors.message_text} className="mt-2" />
            </div>
          )}
          {form.data.type === 'media' && (
            <div className="mt-2 grid gap-2 sm:grid-cols-[140px,1fr]">
              <select
                value={form.data.media_type}
                onChange={(event) => form.setData('media_type', event.target.value)}
                className={selectClass}
              >
                <option value="image">Image</option>
                <option value="video">Video</option>
                <option value="document">Document</option>
                <option value="audio">Audio</option>
              </select>
              <TextInput
                value={form.data.media_url}
                onChange={(event) => form.setData('media_url', event.target.value)}
                placeholder="Link to the file, e.g. https://..."
                className="w-full"
              />
              <p className={`${hintClass} sm:col-span-2`}>
                Only reaches people who messaged you in the last 24 hours.
              </p>
            </div>
          )}
        </div>

        <div className="rounded-card border border-gray-100 bg-gray-50 p-3 text-sm dark:border-ui-dark-border dark:bg-ui-dark-surface-2">
          <p className="text-xs font-medium text-ui-text-muted dark:text-ui-dark-text-muted">
            Preview
          </p>
          <p className="mt-1 whitespace-pre-wrap text-ui-text dark:text-ui-dark-text">
            {form.data.type === 'template'
              ? selectedTemplate?.body_text || 'Pick a template to see it here.'
              : form.data.type === 'text'
                ? form.data.message_text || 'Your message shows here.'
                : form.data.media_url || 'Add a link to your file.'}
          </p>
        </div>

        <div>
          <label className={labelClass}>
            When to send <span className="font-normal text-ui-text-muted">(optional)</span>
          </label>
          <input
            type="datetime-local"
            value={form.data.scheduled_at}
            onChange={(event) => form.setData('scheduled_at', event.target.value)}
            className={selectClass}
          />
          <p className={hintClass}>Leave empty to save as a draft and send when you are ready.</p>
          <InputError message={form.errors.scheduled_at} className="mt-2" />
        </div>

        <details className="group rounded-card border border-gray-100 dark:border-ui-dark-border">
          <summary className="flex cursor-pointer list-none items-center justify-between px-4 py-3 text-sm font-medium text-ui-text dark:text-ui-dark-text [&::-webkit-details-marker]:hidden">
            More options
            <span aria-hidden className="text-ui-text-muted transition group-open:rotate-90">
              ›
            </span>
          </summary>
          <div className="space-y-4 border-t border-gray-100 p-4 dark:border-ui-dark-border">
            <div>
              <label className={labelClass}>Note (only you see this)</label>
              <textarea
                value={form.data.description}
                onChange={(event) => form.setData('description', event.target.value)}
                rows={2}
                className={textareaClass}
              />
            </div>
            <div>
              <label className={labelClass}>Sending speed</label>
              <select
                value={String(form.data.send_delay_seconds)}
                onChange={(event) => form.setData('send_delay_seconds', Number(event.target.value))}
                className={selectClass}
              >
                <option value="0">Fast (recommended)</option>
                <option value="1">1 second between messages</option>
                <option value="2">2 seconds between messages</option>
                <option value="5">5 seconds between messages</option>
                <option value="10">10 seconds between messages</option>
                <option value="30">30 seconds between messages</option>
                <option value="60">60 seconds between messages</option>
              </select>
              <p className={hintClass}>Slower is safer for a brand-new number.</p>
              <InputError message={form.errors.send_delay_seconds} className="mt-2" />
            </div>

            <div className="border-t border-gray-100 pt-4 dark:border-ui-dark-border">
              <p className="text-sm font-medium text-ui-text dark:text-ui-dark-text">Tracking</p>
              <p className={hintClass}>Helps reports show where replies came from.</p>
              <div className="mt-3 grid gap-3 sm:grid-cols-2">
                <div>
                  <label className={labelClass}>Where it came from</label>
                  <select
                    value={form.data.tracking.source}
                    onChange={(event) =>
                      form.setData('tracking', {
                        ...form.data.tracking,
                        source: event.target.value,
                      })
                    }
                    className={selectClass}
                  >
                    <option value="">Not set</option>
                    <option value="ctwa">Ad on Facebook or Instagram</option>
                    <option value="retargeting">Follow-up campaign</option>
                    <option value="organic">Regular message</option>
                    <option value="manual">Uploaded list</option>
                  </select>
                </div>
                <div>
                  <label className={labelClass}>Audience note</label>
                  <TextInput
                    value={form.data.tracking.retargeting_basis}
                    onChange={(event) =>
                      form.setData('tracking', {
                        ...form.data.tracking,
                        retargeting_basis: event.target.value,
                      })
                    }
                    placeholder="e.g. Read last offer"
                    className="w-full"
                  />
                </div>
                {form.data.tracking.source === 'ctwa' && (
                  <>
                    <div>
                      <label className={labelClass}>Ad ID</label>
                      <TextInput
                        value={form.data.tracking.ctwa_ad_id}
                        onChange={(event) =>
                          form.setData('tracking', {
                            ...form.data.tracking,
                            ctwa_ad_id: event.target.value,
                          })
                        }
                        placeholder="Optional"
                        className="w-full"
                      />
                    </div>
                    <div>
                      <label className={labelClass}>Post ID</label>
                      <TextInput
                        value={form.data.tracking.ctwa_post_id}
                        onChange={(event) =>
                          form.setData('tracking', {
                            ...form.data.tracking,
                            ctwa_post_id: event.target.value,
                          })
                        }
                        placeholder="Optional"
                        className="w-full"
                      />
                    </div>
                  </>
                )}
                <div>
                  <label className={labelClass}>Link source</label>
                  <TextInput
                    value={form.data.tracking.utm_source}
                    onChange={(event) =>
                      form.setData('tracking', {
                        ...form.data.tracking,
                        utm_source: event.target.value,
                      })
                    }
                    placeholder="facebook"
                    className="w-full"
                  />
                </div>
                <div>
                  <label className={labelClass}>Link campaign name</label>
                  <TextInput
                    value={form.data.tracking.utm_campaign}
                    onChange={(event) =>
                      form.setData('tracking', {
                        ...form.data.tracking,
                        utm_campaign: event.target.value,
                      })
                    }
                    placeholder="summer_offer"
                    className="w-full"
                  />
                </div>
                <label className="flex items-center gap-2 text-sm text-ui-text dark:text-ui-dark-text sm:col-span-2">
                  <input
                    type="checkbox"
                    checked={Boolean(form.data.tracking.ab_test_enabled)}
                    onChange={(event) =>
                      form.setData('tracking', {
                        ...form.data.tracking,
                        ab_test_enabled: event.target.checked,
                      })
                    }
                    className="h-4 w-4 rounded border-gray-300 text-ui-green focus:ring-ui-green"
                  />
                  Part of an A/B test
                </label>
                {form.data.tracking.ab_test_enabled && (
                  <div className="sm:col-span-2">
                    <label className={labelClass}>Version name</label>
                    <TextInput
                      value={form.data.tracking.ab_variant}
                      onChange={(event) =>
                        form.setData('tracking', {
                          ...form.data.tracking,
                          ab_variant: event.target.value,
                        })
                      }
                      placeholder="e.g. Version A"
                      className="w-full"
                    />
                  </div>
                )}
              </div>
            </div>
          </div>
        </details>
      </form>
    </Drawer>
  );
}

function CampaignDetailDrawer({
  campaign,
  onClose,
}: {
  campaign: CampaignDetail | null;
  onClose: () => void;
}) {
  const testForm = useForm({
    phone: campaign?.testTargetPhone || '',
  });

  useEffect(() => {
    testForm.setData('phone', campaign?.testTargetPhone || '');
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [campaign?.id]);

  if (!campaign) return null;

  const status = campaignStatus(campaign.status);
  const StatusIcon = status.icon;
  const canStart =
    ['draft', 'scheduled', 'paused'].includes(campaign.status) &&
    campaign.stats.total_recipients > 0;
  const canPause = campaign.status === 'sending';
  const canCancel = ['draft', 'scheduled', 'sending', 'paused'].includes(campaign.status);
  const canRetry =
    campaign.stats.failed_count > 0 &&
    ['sending', 'completed', 'paused', 'cancelled'].includes(campaign.status);

  const close = () => {
    onClose();
    router.get(
      route('app.broadcasts.index', {}),
      {},
      {
        preserveScroll: true,
        preserveState: true,
        replace: true,
      }
    );
  };

  const action = (name: 'start' | 'pause' | 'cancel' | 'retry-failed') => {
    router.post(
      route(`app.broadcasts.${name}`, { campaign: campaign.slug }),
      {},
      {
        preserveScroll: true,
        only: ['selectedCampaign', 'campaigns', 'flash', 'errors'],
      }
    );
  };

  const sendTest = (event: React.FormEvent) => {
    event.preventDefault();
    testForm.post(route('app.broadcasts.send-test', { campaign: campaign.slug }), {
      preserveScroll: true,
      only: ['selectedCampaign', 'flash', 'errors'],
    });
  };

  const messagePreview =
    campaign.type === 'template'
      ? campaign.template?.body_text || 'Template preview is not available yet.'
      : campaign.type === 'media'
        ? campaign.media_url || 'Media URL not set.'
        : campaign.message_text || 'Message text not set.';

  return (
    <Drawer
      open={Boolean(campaign)}
      onClose={close}
      title={campaign.name}
      description={`${typeLabel(campaign.type)} · ${AUDIENCE_LABELS[campaign.recipient_type] ?? friendlyStatus(campaign.recipient_type)}`}
      className="sm:max-w-3xl"
      footer={
        <div className="flex flex-wrap justify-end gap-2">
          {canRetry && (
            <Button type="button" variant="secondary" onClick={() => action('retry-failed')}>
              Retry failed
            </Button>
          )}
          {canCancel && (
            <Button type="button" variant="ghost" onClick={() => action('cancel')}>
              Stop campaign
            </Button>
          )}
          {canPause && (
            <Button type="button" variant="warning" onClick={() => action('pause')}>
              <Pause className="h-4 w-4" />
              Pause
            </Button>
          )}
          {canStart && (
            <Button type="button" onClick={() => action('start')}>
              <Play className="h-4 w-4" />
              Send now
            </Button>
          )}
        </div>
      }
    >
      <div className="space-y-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-3">
            <div>
              <div className="flex flex-wrap items-center gap-2">
                <Badge variant={status.variant} className="gap-1">
                  <StatusIcon className="h-3 w-3" />
                  {status.label}
                </Badge>
                {campaign.dry_run && <Badge variant="warning">Test mode</Badge>}
              </div>
              <p className="mt-1 text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
                Created {formatDate(campaign.created_at)}
              </p>
            </div>
          </div>
          <form onSubmit={sendTest} className="flex w-full gap-2 sm:w-auto">
            <TextInput
              value={testForm.data.phone}
              onChange={(event) => testForm.setData('phone', event.target.value)}
              placeholder="Your number for a test"
              aria-label="Phone number for a test message"
              className="h-9 min-w-0 flex-1 sm:w-48"
            />
            <Button type="submit" variant="secondary" disabled={testForm.processing}>
              <Send className="h-4 w-4" />
              Send test
            </Button>
          </form>
        </div>
        <InputError
          message={testForm.errors.phone || (testForm.errors as Record<string, string>).error}
        />

        {['draft', 'scheduled', 'paused'].includes(campaign.status) &&
        campaign.diagnostics?.preflight?.errors?.length ? (
          <div className="rounded-btn border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200">
            {campaign.diagnostics.preflight.errors.map((error) => (
              <p key={error}>{error}</p>
            ))}
          </div>
        ) : null}
        {['draft', 'scheduled', 'paused'].includes(campaign.status) &&
        campaign.diagnostics?.preflight?.warnings?.length ? (
          <div className="rounded-btn border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
            {campaign.diagnostics.preflight.warnings.map((warning) => (
              <p key={warning}>{warning}</p>
            ))}
          </div>
        ) : null}

        <div className="grid grid-cols-2 gap-2 sm:grid-cols-5">
          {(
            [
              ['People', campaign.stats.total_recipients],
              ['Sent', campaign.stats.sent_count],
              ['Delivered', campaign.stats.delivered_count],
              ['Read', campaign.stats.read_count],
              ['Failed', campaign.stats.failed_count],
            ] as const
          ).map(([label, value]) => (
            <div
              key={label}
              className="rounded-card border border-gray-100 px-3 py-2.5 dark:border-ui-dark-border"
            >
              <p className="text-xs text-ui-text-muted dark:text-ui-dark-text-muted">{label}</p>
              <p
                className={`text-lg font-bold tabular-nums ${label === 'Failed' && value > 0 ? 'text-red-600 dark:text-red-300' : 'text-ui-text dark:text-ui-dark-text'}`}
              >
                {formatNumber(value)}
              </p>
            </div>
          ))}
        </div>

        <div>
          <div className="mb-1.5 flex items-center justify-between text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
            <span>
              {Math.round(campaign.stats.completion_percentage)}% done · Delivered{' '}
              {Math.round(campaign.stats.delivery_rate)}% · Read{' '}
              {Math.round(campaign.stats.read_rate)}%
            </span>
            {campaign.stats.pending_count > 0 && (
              <span>{formatNumber(campaign.stats.pending_count)} waiting</span>
            )}
          </div>
          <Progress value={campaign.stats.completion_percentage} />
        </div>

        <div className="grid gap-4 md:grid-cols-[1fr,280px]">
          <div className="rounded-2xl bg-ui-green-soft/60 p-3 dark:bg-emerald-500/10">
            <div className="rounded-2xl rounded-tr-sm bg-white px-3 py-2 text-sm text-ui-text shadow-sm dark:bg-ui-dark-surface dark:text-ui-dark-text">
              <p className="whitespace-pre-wrap">{messagePreview}</p>
              {campaign.media_url && (
                <a
                  href={campaign.media_url}
                  target="_blank"
                  rel="noreferrer"
                  className="mt-2 block truncate text-xs font-semibold text-ui-green-dark dark:text-emerald-300"
                >
                  Open {campaign.media_type || 'file'}
                </a>
              )}
            </div>
          </div>
          <dl className="space-y-2 text-sm">
            {(
              [
                ['Template', campaign.template?.name],
                ['Scheduled', campaign.scheduled_at ? formatDate(campaign.scheduled_at) : null],
                [
                  'Speed',
                  campaign.send_delay_seconds > 0
                    ? `${campaign.send_delay_seconds}s between messages`
                    : 'Fast',
                ],
                [
                  'Source',
                  campaign.tracking?.source
                    ? (SOURCE_LABELS[campaign.tracking.source] ?? campaign.tracking.source)
                    : null,
                ],
                ['Link campaign', campaign.tracking?.utm_campaign],
                [
                  'A/B version',
                  campaign.tracking?.ab_test_enabled ? campaign.tracking.ab_variant || 'Yes' : null,
                ],
              ] as Array<[string, string | null | undefined]>
            )
              .filter(([, value]) => Boolean(value))
              .map(([label, value]) => (
                <div key={label} className="flex justify-between gap-4">
                  <dt className="text-ui-text-muted dark:text-ui-dark-text-muted">{label}</dt>
                  <dd className="text-right font-medium text-ui-text dark:text-ui-dark-text">
                    {value}
                  </dd>
                </div>
              ))}
          </dl>
        </div>

        <Card className="border-transparent dark:border-slate-700/80">
          <CardContent className="p-0">
            <div className="border-b border-gray-100 px-5 py-4 dark:border-ui-dark-border">
              <p className="font-semibold text-ui-text dark:text-ui-dark-text">People</p>
              <p className="text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                Latest 100. Failed and waiting first.
              </p>
            </div>
            <div className="max-h-80 overflow-auto">
              {campaign.recipients.length === 0 ? (
                <div className="p-6 text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
                  No one here yet.
                </div>
              ) : (
                campaign.recipients.map((recipient) => (
                  <div
                    key={recipient.id}
                    className="grid gap-2 border-b border-gray-100 px-5 py-3 text-sm last:border-0 dark:border-ui-dark-border md:grid-cols-[1fr,150px,120px,1fr]"
                  >
                    <div className="min-w-0">
                      <p className="truncate font-medium text-ui-text dark:text-ui-dark-text">
                        {recipient.name || recipient.phone_number || 'Unknown recipient'}
                      </p>
                      <p className="truncate text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                        {recipient.phone_number || 'No phone'}
                      </p>
                    </div>
                    <Badge
                      variant={
                        recipient.status === 'failed'
                          ? 'danger'
                          : recipient.status === 'read'
                            ? 'success'
                            : 'default'
                      }
                      className="w-fit"
                    >
                      {friendlyStatus(recipient.status)}
                    </Badge>
                    <span className="text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                      {formatDate(recipient.sent_at || recipient.failed_at)}
                    </span>
                    <div className="min-w-0">
                      <span className="block truncate text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                        {recipient.failure_reason || ''}
                      </span>
                      {recipient.timeline?.length ? (
                        <span className="mt-1 block truncate text-[11px] text-ui-text-muted dark:text-ui-dark-text-muted">
                          {recipient.timeline
                            .map(
                              (event) =>
                                `${event.label}${event.at ? ` ${formatDate(event.at)}` : ''}`
                            )
                            .join(' · ')}
                        </span>
                      ) : null}
                    </div>
                  </div>
                ))
              )}
            </div>
          </CardContent>
        </Card>
      </div>
    </Drawer>
  );
}

export default function BroadcastsIndex({
  campaigns,
  filters,
  createOptions,
  selectedCampaign,
}: {
  account: any;
  campaigns: {
    data: Campaign[];
    links: any;
    meta: any;
  };
  filters: { status?: string; search?: string };
  createOptions: CreateOptions;
  selectedCampaign?: CampaignDetail | null;
}) {
  const pageUrl = usePage().url;

  const [search, setSearch] = useState(filters.search || '');
  const [status, setStatus] = useState(filters.status || '');
  const [createOpen, setCreateOpen] = useState(
    () =>
      new URLSearchParams(pageUrl.split('?')[1] || '').get('panel') === 'create' ||
      (createOptions.selectedContacts?.length ?? 0) > 0
  );
  const [activeCampaign, setActiveCampaign] = useState<CampaignDetail | null>(
    selectedCampaign || null
  );

  useEffect(() => {
    setActiveCampaign(selectedCampaign || null);
  }, [selectedCampaign]);

  const hasActiveCampaigns =
    campaigns.data.some((campaign) =>
      ['sending', 'scheduled', 'paused'].includes(campaign.status)
    ) ||
    Boolean(activeCampaign && ['sending', 'scheduled', 'paused'].includes(activeCampaign.status));

  const activeFilters = useMemo(() => {
    const payload: Record<string, string> = {};
    if (search.trim()) payload.search = search.trim();
    if (status) payload.status = status;
    return payload;
  }, [search, status]);

  const applyFilters = () => {
    router.get(route('app.broadcasts.index', {}), activeFilters, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    });
  };

  // Search as you type (short delay), so there is no separate Apply button.
  useEffect(() => {
    const normalized = search.trim();
    if (normalized === (filters.search || '')) return;
    const timer = window.setTimeout(applyFilters, 400);
    return () => window.clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search]);

  useEffect(() => {
    if (!hasActiveCampaigns) return;

    const timer = window.setInterval(() => {
      router.reload({
        only: ['campaigns', 'selectedCampaign', 'flash', 'errors'],
      });
    }, 4000);

    return () => window.clearInterval(timer);
  }, [hasActiveCampaigns]);

  const tabs = [
    { value: '', label: 'All' },
    { value: 'draft', label: 'Drafts' },
    { value: 'scheduled', label: 'Scheduled' },
    { value: 'sending', label: 'Sending' },
    { value: 'paused', label: 'Paused' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Stopped' },
  ];

  return (
    <AppShell>
      <Head title="Campaigns" />
      <div className="module-page max-w-[1600px]">
        <PageHeader
          title="Campaigns"
          description="Send one message to many contacts at once."
          actions={
            <Button type="button" onClick={() => setCreateOpen(true)}>
              <Plus className="h-4 w-4" />
              New campaign
            </Button>
          }
        />

        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div className="-mx-1 flex gap-1 overflow-x-auto px-1">
            {tabs.map((tab) => {
              const active = status === tab.value;
              return (
                <button
                  key={tab.value || 'all'}
                  type="button"
                  onClick={() => {
                    setStatus(tab.value);
                    router.get(
                      route('app.broadcasts.index', {}),
                      { ...activeFilters, status: tab.value || undefined },
                      {
                        preserveState: true,
                        preserveScroll: true,
                        replace: true,
                      }
                    );
                  }}
                  className={`h-8 whitespace-nowrap rounded-full px-3 text-sm font-medium transition ${
                    active
                      ? 'bg-ui-green-soft text-ui-green-dark dark:bg-ui-dark-green-soft dark:text-emerald-200'
                      : 'text-ui-text-muted hover:bg-gray-100 hover:text-ui-text dark:text-ui-dark-text-muted dark:hover:bg-ui-dark-surface-2 dark:hover:text-ui-dark-text'
                  }`}
                >
                  {tab.label}
                </button>
              );
            })}
          </div>
          <div className="relative w-full md:w-64">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ui-text-muted dark:text-ui-dark-text-muted" />
            <TextInput
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              onKeyDown={(event) => event.key === 'Enter' && applyFilters()}
              placeholder="Search campaigns"
              className="h-9 w-full rounded-btn border-gray-200 pl-10 text-sm focus:border-ui-green focus:ring-ui-green/20 dark:border-ui-dark-border dark:bg-ui-dark-surface-2"
            />
          </div>
        </div>

        {campaigns.data.length === 0 ? (
          <Card className="border-transparent dark:border-slate-700/80">
            <CardContent className="p-0">
              {filters.search || filters.status ? (
                <EmptyState
                  icon={Search}
                  title="No matches"
                  description="Try a different search or filter."
                />
              ) : (
                <EmptyState
                  icon={Megaphone}
                  title="No campaigns yet"
                  description="Send an offer or update to many contacts at once."
                  action={
                    <Button onClick={() => setCreateOpen(true)}>
                      <Plus className="h-4 w-4" />
                      New campaign
                    </Button>
                  }
                />
              )}
            </CardContent>
          </Card>
        ) : (
          <>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
              {campaigns.data.map((campaign) => (
                <CampaignCard key={campaign.id} campaign={campaign} />
              ))}
            </div>

            {campaigns.links && campaigns.links.length > 3 && (
              <div className="flex flex-wrap justify-center gap-1">
                {campaigns.links.map((link: any, index: number) => (
                  <button
                    key={index}
                    type="button"
                    onClick={() => link.url && router.visit(link.url)}
                    disabled={!link.url}
                    className={`h-8 min-w-8 rounded-md px-2 text-xs font-semibold transition ${
                      link.active
                        ? 'bg-ui-text text-white dark:bg-ui-dark-text dark:text-ui-dark-bg'
                        : 'border border-gray-200 bg-white text-ui-text hover:bg-gray-50 dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text dark:hover:bg-ui-dark-surface-2'
                    } ${!link.url ? 'cursor-not-allowed opacity-45' : ''}`}
                  >
                    {paginationLabel(link.label)}
                  </button>
                ))}
              </div>
            )}
          </>
        )}
      </div>
      <CampaignCreateDrawer
        open={createOpen}
        onClose={() => setCreateOpen(false)}
        options={createOptions}
      />
      <CampaignDetailDrawer campaign={activeCampaign} onClose={() => setActiveCampaign(null)} />
    </AppShell>
  );
}
