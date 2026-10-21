import { friendlyStatus } from '@/lib/friendly';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import { Badge } from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { Card, CardContent } from '@/Components/UI/Card';
import { Drawer, IconButton, Modal, PageHeader } from '@/Components/UI/Elements';
import { LearnMore } from '@/Components/UI/LearnMore';
import { EmptyState } from '@/Components/UI/EmptyState';
import { useConfirm } from '@/hooks/useConfirm';
import { useToast } from '@/hooks/useToast';
import AppShell from '@/Layouts/AppShell';
import { usePage, Head, Link, router, useForm } from '@inertiajs/react';
import {
  Activity,
  AlertCircle,
  Bot,
  Clock,
  Edit3,
  Play,
  Plus,
  Trash2,
  Workflow,
} from 'lucide-react';
import { useEffect, useState } from 'react';

interface BotItem {
  id: number;
  name: string;
  description: string | null;
  status: string;
  is_default: boolean;
  applies_to: {
    all_connections: boolean;
    connection_ids: number[];
  };
  version: number;
  flows_count: number;
  enabled_flows_count?: number;
  runnable_flows_count?: number;
  is_runnable?: boolean;
  executions_count: number;
  errors_count: number;
  last_run_at: string | null;
  created_at: string;
}

type Connection = { id: number; name: string };
export type AutomationOptions = {
  agents: Array<{ id: number; name: string; email?: string; role?: string }>;
  ai_agents?: Array<{ id: number; name: string; role?: string; mode?: string }>;
  tags: Array<{ id: number; name: string; color?: string }>;
  segments?: Array<{ id: number; name: string; contact_count?: number }>;
  templates: Array<{ id: number; name: string; language?: string; status?: string }>;
  lists: Array<{ id: number; name: string }>;
  flows?: Array<{ id: number; name: string; meta_flow_id?: string; status?: string }>;
};

export interface BotDetail extends BotItem {
  stop_on_first_flow: boolean;
  session_timeout_minutes: number;
  session_resume_mode: string;
  session_expired_message?: string | null;
  failed_executions_count: number;
  updated_at: string | null;
  created_by: { id: number; name: string } | null;
  updated_by: { id: number; name: string } | null;
  flows: Array<{
    id: number;
    name: string;
    enabled: boolean;
    priority: number;
    trigger: Record<string, any>;
    health: {
      has_nodes: boolean;
      has_executable_node: boolean;
      has_start_node: boolean;
      has_edges: boolean;
      is_runnable: boolean;
    };
    nodes: Array<{
      id: number;
      type: string;
      config: Record<string, any>;
      sort_order: number;
      pos_x?: number | null;
      pos_y?: number | null;
    }>;
    edges: Array<{
      id: number;
      from_node_id: number;
      to_node_id: number;
      label?: string | null;
      sort_order?: number | null;
    }>;
  }>;
  executions: Array<{
    id: number;
    status: string;
    flow_name: string | null;
    started_at: string | null;
    finished_at: string | null;
    duration_ms?: number | null;
    error_message: string | null;
    logs?: Array<{ level?: string; message?: string; at?: string }>;
    conversation_id?: number | null;
  }>;
  analytics?: {
    window_days: number;
    runs: number;
    success: number;
    failed: number;
    skipped: number;
    node_hits: Array<{
      node_id: number;
      type: string;
      total: number;
      success: number;
      failed: number;
      skipped: number;
    }>;
    drop_offs: Array<{ node_id: number; count: number }>;
  };
}

function formatNumber(value: number | string | null | undefined) {
  const numeric = Number(value ?? 0);
  return Number.isFinite(numeric) ? new Intl.NumberFormat('en-IN').format(numeric) : '0';
}

function formatRelative(value: string | null) {
  if (!value) return 'Never';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return 'Never';
  return date.toLocaleString('en-IN', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function statusBadge(status: string) {
  const normalized = status.toLowerCase();
  if (normalized === 'active') return <Badge variant="success">Active</Badge>;
  if (normalized === 'paused') return <Badge variant="warning">Paused</Badge>;
  return <Badge variant="default">Draft</Badge>;
}

function actionLabel(actionType?: string) {
  return String(actionType || 'send_text').replace(/_/g, ' ');
}

function AutomationCard({ bot, onDelete }: { bot: BotItem; onDelete: (bot: BotItem) => void }) {
  const notRunnable = bot.status === 'active' && bot.is_runnable === false;
  return (
    <div className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center">
      <div className="flex min-w-0 flex-1 items-center gap-3">
        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-ui-green-soft text-ui-green-dark dark:bg-emerald-500/15 dark:text-emerald-300">
          <Workflow className="h-4 w-4" />
        </span>
        <div className="min-w-0">
          <div className="flex min-w-0 flex-wrap items-center gap-2">
            <Link
              href={route('app.chatbots.index', { bot: bot.id })}
              className="truncate text-sm font-semibold text-ui-text hover:text-ui-green-dark dark:text-ui-dark-text dark:hover:text-emerald-300"
            >
              {bot.name}
            </Link>
            {statusBadge(bot.status)}
          </div>
          <p className="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
            <span>
              {bot.applies_to?.all_connections
                ? 'All numbers'
                : `${bot.applies_to?.connection_ids?.length || 0} number(s)`}
            </span>
            <span>· {formatNumber(bot.executions_count)} runs</span>
            <span className="inline-flex items-center gap-1">
              · <Clock className="h-3 w-3" /> {formatRelative(bot.last_run_at)}
            </span>
            {notRunnable && (
              <span className="inline-flex items-center gap-1 font-medium text-amber-700 dark:text-amber-300">
                · <AlertCircle className="h-3 w-3" /> Nothing to run yet
              </span>
            )}
            {bot.errors_count > 0 && (
              <span className="inline-flex items-center gap-1 font-medium text-red-600 dark:text-red-300">
                · <AlertCircle className="h-3 w-3" /> {formatNumber(bot.errors_count)} failed
              </span>
            )}
          </p>
        </div>
      </div>
      <div className="flex shrink-0 items-center gap-1">
        <Link href={route('app.chatbots.builder', { bot: bot.id })}>
          <Button size="sm" variant="secondary">
            <Workflow className="h-4 w-4" />
            Edit flow
          </Button>
        </Link>
        <Link href={route('app.chatbots.index', { bot: bot.id })}>
          <IconButton size="sm" variant="ghost" title="Settings" aria-label="Settings">
            <Edit3 className="h-4 w-4" />
          </IconButton>
        </Link>
        <IconButton
          variant="ghost"
          size="sm"
          onClick={() => onDelete(bot)}
          aria-label="Delete chatbot"
          title="Delete"
        >
          <Trash2 className="h-4 w-4" />
        </IconButton>
      </div>
    </div>
  );
}

function CreateBotDrawer({
  open,
  onClose,
  connections,
}: {
  open: boolean;
  onClose: () => void;
  connections: Connection[];
}) {
  const form = useForm({
    name: '',
    description: '',
    status: 'draft',
    applies_to: {
      all_connections: true,
      connection_ids: [] as number[],
    },
    stop_on_first_flow: true,
    starter_flow_mode: 'guided',
    starter_trigger_type: 'inbound_message',
    starter_keywords: '',
    starter_reply_message:
      'Hi! Thanks for messaging us. A team member will get back to you shortly.',
  });

  const submit = (event: React.FormEvent) => {
    event.preventDefault();
    form.post(route('app.chatbots.store', {}), {
      preserveScroll: true,
      onSuccess: onClose,
    });
  };

  return (
    <Drawer
      open={open}
      onClose={onClose}
      title="New chatbot"
      description="Start with a simple reply. You can add more steps later."
      className="sm:max-w-2xl"
      footer={
        <div className="flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" form="bot-create-form" disabled={form.processing}>
            {form.processing ? 'Creating...' : 'Create chatbot'}
          </Button>
        </div>
      }
    >
      <form id="bot-create-form" onSubmit={submit} className="space-y-5">
        <div className="grid gap-4 md:grid-cols-2">
          <div>
            <label className="mb-1.5 block text-sm font-medium text-ui-text dark:text-ui-dark-text">
              Name
            </label>
            <TextInput
              value={form.data.name}
              onChange={(event) => form.setData('name', event.target.value)}
              placeholder="Welcome reply"
            />
            <InputError message={form.errors.name} className="mt-2" />
          </div>
          <div>
            <label className="mb-1.5 block text-sm font-medium text-ui-text dark:text-ui-dark-text">
              Status
            </label>
            <select
              value={form.data.status}
              onChange={(event) => form.setData('status', event.target.value)}
              className="h-10 w-full rounded-btn border border-gray-200 bg-white px-3 text-sm dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text"
            >
              <option value="draft">Draft (off)</option>
              <option value="active">On</option>
              <option value="paused">Paused</option>
            </select>
          </div>
        </div>
        <div>
          <label className="mb-1.5 block text-sm font-medium text-ui-text dark:text-ui-dark-text">
            Start when
          </label>
          <select
            value={form.data.starter_trigger_type}
            onChange={(event) => form.setData('starter_trigger_type', event.target.value)}
            className="h-10 w-full rounded-btn border border-gray-200 bg-white px-3 text-sm dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text"
          >
            <option value="inbound_message">A customer sends any message</option>
            <option value="keyword">A message has certain words</option>
          </select>
        </div>
        {form.data.starter_trigger_type === 'keyword' && (
          <TextInput
            value={form.data.starter_keywords}
            onChange={(event) => form.setData('starter_keywords', event.target.value)}
            placeholder="pricing, demo, help"
          />
        )}
        <div>
          <label className="mb-1.5 block text-sm font-medium text-ui-text dark:text-ui-dark-text">
            Reply with
          </label>
          <textarea
            value={form.data.starter_reply_message}
            onChange={(event) => form.setData('starter_reply_message', event.target.value)}
            rows={3}
            className="w-full rounded-btn border border-gray-200 bg-white px-3 py-2 text-sm dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text"
          />
        </div>
        <LearnMore label="More options">
          <div className="space-y-4 pt-2">
            <div>
              <label className="mb-1.5 block text-sm font-medium text-ui-text dark:text-ui-dark-text">
                Note (optional)
              </label>
              <textarea
                value={form.data.description}
                onChange={(event) => form.setData('description', event.target.value)}
                rows={2}
                className="w-full rounded-btn border border-gray-200 bg-white px-3 py-2 text-sm dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text"
              />
            </div>
            <label className="flex items-center justify-between rounded-btn border border-gray-100 p-3 dark:border-ui-dark-border">
              <span>
                <span className="block text-sm font-medium text-ui-text dark:text-ui-dark-text">
                  All WhatsApp numbers
                </span>
                <span className="text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                  Reply on every WhatsApp number in this workspace.
                </span>
              </span>
              <input
                type="checkbox"
                checked={form.data.applies_to.all_connections}
                onChange={(event) =>
                  form.setData('applies_to', {
                    ...form.data.applies_to,
                    all_connections: event.target.checked,
                  })
                }
                className="rounded border-gray-300 text-ui-green focus:ring-ui-green/30"
              />
            </label>
            {!form.data.applies_to.all_connections && (
              <div>
                <label className="mb-1.5 block text-sm font-medium text-ui-text dark:text-ui-dark-text">
                  Which numbers
                </label>
                <select
                  multiple
                  value={form.data.applies_to.connection_ids.map(String)}
                  onChange={(event) =>
                    form.setData('applies_to', {
                      ...form.data.applies_to,
                      connection_ids: Array.from(event.target.selectedOptions).map((option) =>
                        Number(option.value)
                      ),
                    })
                  }
                  className="min-h-28 w-full rounded-btn border border-gray-200 bg-white px-3 py-2 text-sm dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text"
                >
                  {connections.map((connection) => (
                    <option key={connection.id} value={connection.id}>
                      {connection.name}
                    </option>
                  ))}
                </select>
                <InputError message={form.errors['applies_to.connection_ids']} className="mt-2" />
              </div>
            )}
          </div>
        </LearnMore>
      </form>
    </Drawer>
  );
}

export function NodeConfigFields({
  editor,
  options,
  onChange,
}: {
  editor: {
    node: BotDetail['flows'][number]['nodes'][number];
    config: Record<string, any>;
    type: string;
  };
  options: AutomationOptions;
  onChange: (key: string, value: any) => void;
}) {
  const config = editor.config || {};
  const actionType = config.action_type || 'send_text';
  const inputClass =
    'mt-1 h-10 w-full rounded-btn border border-gray-200 bg-white px-3 text-sm text-ui-text outline-none transition focus:border-ui-green focus:ring-2 focus:ring-ui-green/15 dark:border-ui-dark-border dark:bg-ui-dark-surface-2 dark:text-ui-dark-text';
  const textAreaClass =
    'mt-1 w-full rounded-btn border border-gray-200 bg-white px-3 py-2 text-sm text-ui-text outline-none transition focus:border-ui-green focus:ring-2 focus:ring-ui-green/15 dark:border-ui-dark-border dark:bg-ui-dark-surface-2 dark:text-ui-dark-text';

  if (editor.type === 'condition') {
    return (
      <div className="grid gap-3">
        <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
          Condition type
          <select
            className={inputClass}
            value={config.type || 'text_contains'}
            onChange={(event) => onChange('type', event.target.value)}
          >
            <option value="text_contains">Text contains</option>
            <option value="text_equals">Text equals</option>
            <option value="text_starts_with">Text starts with</option>
            <option value="regex_match">Regex match</option>
            <option value="tags_contains">Contact has tag</option>
            <option value="conversation_status">Conversation status</option>
          </select>
        </label>
        {config.type === 'regex_match' ? (
          <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
            Pattern
            <TextInput
              className="mt-1"
              value={config.pattern || ''}
              onChange={(event) => onChange('pattern', event.target.value)}
              placeholder="/pricing|plans|price/i"
            />
          </label>
        ) : (
          <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
            Value
            <TextInput
              className="mt-1"
              value={config.value || ''}
              onChange={(event) => onChange('value', event.target.value)}
            />
          </label>
        )}
      </div>
    );
  }

  if (editor.type === 'delay') {
    return (
      <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
        Delay seconds
        <TextInput
          className="mt-1"
          type="number"
          min="1"
          value={config.seconds || 60}
          onChange={(event) => onChange('seconds', Number(event.target.value))}
        />
      </label>
    );
  }

  if (editor.type === 'webhook') {
    return (
      <div className="grid gap-3">
        <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
          Send data to this URL
          <TextInput
            className="mt-1"
            value={config.url || ''}
            onChange={(event) => onChange('url', event.target.value)}
          />
        </label>
        <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
          Method
          <select
            className={inputClass}
            value={config.method || 'POST'}
            onChange={(event) => onChange('method', event.target.value)}
          >
            <option>POST</option>
            <option>GET</option>
          </select>
        </label>
      </div>
    );
  }

  return (
    <div className="grid gap-3">
      <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
        Action
        <select
          className={inputClass}
          value={actionType}
          onChange={(event) => onChange('action_type', event.target.value)}
        >
          <option value="send_text">Send text</option>
          <option value="send_buttons">Send buttons</option>
          <option value="send_template">Send template</option>
          <option value="send_media">Send media</option>
          <option value="send_flow">Send form flow</option>
          <option value="send_list">Send interactive list</option>
          <option value="add_tag">Add tag</option>
          <option value="add_segment">Add segment</option>
          <option value="update_contact">Update contact</option>
          <option value="assign_agent">Assign agent</option>
          <option value="handoff">Human handoff</option>
          <option value="create_deal">Create deal</option>
          <option value="create_appointment">Create appointment</option>
        </select>
      </label>

      {actionType === 'send_text' && (
        <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
          Message
          <textarea
            className={textAreaClass}
            rows={5}
            value={config.message || ''}
            onChange={(event) => onChange('message', event.target.value)}
          />
        </label>
      )}

      {actionType === 'send_buttons' && (
        <div className="grid gap-3">
          <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
            Header
            <TextInput
              className="mt-1"
              value={config.header_text || ''}
              onChange={(event) => onChange('header_text', event.target.value)}
            />
          </label>
          <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
            Body
            <textarea
              className={textAreaClass}
              rows={4}
              value={config.body_text || ''}
              onChange={(event) => onChange('body_text', event.target.value)}
            />
          </label>
          <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
            Footer
            <TextInput
              className="mt-1"
              value={config.footer_text || ''}
              onChange={(event) => onChange('footer_text', event.target.value)}
            />
          </label>
          <div className="grid gap-2">
            {[0, 1, 2].map((index) => {
              const buttons = Array.isArray(config.buttons) ? config.buttons : [];
              const button = buttons[index] || {};
              const setButton = (key: string, value: string) => {
                const next = [...buttons];
                next[index] = { ...(next[index] || {}), [key]: value };
                onChange(
                  'buttons',
                  next.filter((item) => item?.id || item?.text)
                );
              };

              return (
                <div key={index} className="grid gap-2 sm:grid-cols-2">
                  <TextInput
                    value={button.id || ''}
                    onChange={(event) => setButton('id', event.target.value)}
                    placeholder={`button_${index + 1}`}
                  />
                  <TextInput
                    value={button.text || ''}
                    onChange={(event) => setButton('text', event.target.value)}
                    placeholder={`Button ${index + 1}`}
                  />
                </div>
              );
            })}
          </div>
        </div>
      )}

      {actionType === 'send_template' && (
        <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
          Template
          <select
            className={inputClass}
            value={config.template_id || ''}
            onChange={(event) => onChange('template_id', Number(event.target.value))}
          >
            <option value="">Select template</option>
            {options.templates.map((template) => (
              <option key={template.id} value={template.id}>
                {template.name} · {template.language || 'default'} · {template.status || 'unknown'}
              </option>
            ))}
          </select>
        </label>
      )}

      {actionType === 'send_list' && (
        <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
          Interactive list
          <select
            className={inputClass}
            value={config.list_id || ''}
            onChange={(event) => onChange('list_id', Number(event.target.value))}
          >
            <option value="">Select list</option>
            {options.lists.map((list) => (
              <option key={list.id} value={list.id}>
                {list.name}
              </option>
            ))}
          </select>
        </label>
      )}

      {actionType === 'send_flow' && (
        <div className="grid gap-3">
          <div className="grid gap-3 sm:grid-cols-2">
            <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
              WhatsApp Flow
              <select
                className={inputClass}
                value={config.flow_id || ''}
                onChange={(event) => onChange('flow_id', Number(event.target.value))}
              >
                <option value="">Use Meta flow ID</option>
                {(options.flows || []).map((flow) => (
                  <option key={flow.id} value={flow.id}>
                    {flow.name} · {flow.status || 'draft'}
                  </option>
                ))}
              </select>
            </label>
            <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
              Meta flow ID
              <TextInput
                className="mt-1"
                value={config.meta_flow_id || ''}
                onChange={(event) => onChange('meta_flow_id', event.target.value)}
                placeholder="1234567890"
              />
            </label>
          </div>
          <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
            Body
            <textarea
              className={textAreaClass}
              rows={3}
              value={config.body_text || ''}
              onChange={(event) => onChange('body_text', event.target.value)}
            />
          </label>
          <div className="grid gap-3 sm:grid-cols-2">
            <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
              CTA
              <TextInput
                className="mt-1"
                value={config.cta || 'Open form'}
                onChange={(event) => onChange('cta', event.target.value)}
              />
            </label>
            <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
              Start screen
              <TextInput
                className="mt-1"
                value={config.screen || ''}
                onChange={(event) => onChange('screen', event.target.value)}
                placeholder="optional"
              />
            </label>
          </div>
          <div className="grid gap-3 sm:grid-cols-2">
            <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
              Header
              <TextInput
                className="mt-1"
                value={config.header_text || ''}
                onChange={(event) => onChange('header_text', event.target.value)}
              />
            </label>
            <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
              Footer
              <TextInput
                className="mt-1"
                value={config.footer_text || ''}
                onChange={(event) => onChange('footer_text', event.target.value)}
              />
            </label>
          </div>
        </div>
      )}

      {actionType === 'send_media' && (
        <div className="grid gap-3">
          <div className="grid gap-3 sm:grid-cols-2">
            <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
              Media type
              <select
                className={inputClass}
                value={config.media_type || 'image'}
                onChange={(event) => onChange('media_type', event.target.value)}
              >
                <option value="image">Image</option>
                <option value="document">Document</option>
                <option value="video">Video</option>
                <option value="audio">Audio</option>
              </select>
            </label>
            <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
              Filename
              <TextInput
                className="mt-1"
                value={config.filename || ''}
                onChange={(event) => onChange('filename', event.target.value)}
                placeholder="ui-overview.pdf"
              />
            </label>
          </div>
          <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
            Public media URL
            <TextInput
              className="mt-1"
              value={config.media_url || ''}
              onChange={(event) => onChange('media_url', event.target.value)}
              placeholder="https://..."
            />
          </label>
          <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
            Caption
            <textarea
              className={textAreaClass}
              rows={3}
              value={config.caption || ''}
              onChange={(event) => onChange('caption', event.target.value)}
            />
          </label>
        </div>
      )}

      {actionType === 'add_tag' && (
        <div className="grid gap-3 sm:grid-cols-2">
          <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
            Existing tag
            <select
              className={inputClass}
              value={config.tag_id || ''}
              onChange={(event) => onChange('tag_id', Number(event.target.value))}
            >
              <option value="">Create by name</option>
              {options.tags.map((tag) => (
                <option key={tag.id} value={tag.id}>
                  {tag.name}
                </option>
              ))}
            </select>
          </label>
          <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
            New tag name
            <TextInput
              className="mt-1"
              value={config.tag_name || ''}
              onChange={(event) => onChange('tag_name', event.target.value)}
            />
          </label>
        </div>
      )}

      {actionType === 'add_segment' && (
        <div className="grid gap-3 sm:grid-cols-2">
          <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
            Existing segment
            <select
              className={inputClass}
              value={config.segment_id || ''}
              onChange={(event) => onChange('segment_id', Number(event.target.value))}
            >
              <option value="">Create by name</option>
              {(options.segments || []).map((segment) => (
                <option key={segment.id} value={segment.id}>
                  {segment.name}
                </option>
              ))}
            </select>
          </label>
          <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
            New segment name
            <TextInput
              className="mt-1"
              value={config.segment_name || ''}
              onChange={(event) => onChange('segment_name', event.target.value)}
            />
          </label>
        </div>
      )}

      {actionType === 'update_contact' && (
        <div className="grid gap-3 sm:grid-cols-2">
          <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
            Status
            <TextInput
              className="mt-1"
              value={config.status || ''}
              onChange={(event) => onChange('status', event.target.value)}
            />
          </label>
          <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
            Source
            <TextInput
              className="mt-1"
              value={config.source || ''}
              onChange={(event) => onChange('source', event.target.value)}
            />
          </label>
          <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
            Company
            <TextInput
              className="mt-1"
              value={config.company || ''}
              onChange={(event) => onChange('company', event.target.value)}
            />
          </label>
          <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
            Email
            <TextInput
              className="mt-1"
              value={config.email || ''}
              onChange={(event) => onChange('email', event.target.value)}
            />
          </label>
        </div>
      )}

      {(actionType === 'assign_agent' || actionType === 'handoff') && (
        <div className="grid gap-3 sm:grid-cols-2">
          <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
            Agent
            <select
              className={inputClass}
              value={config.agent_id || ''}
              onChange={(event) => onChange('agent_id', Number(event.target.value))}
            >
              <option value="">No specific agent</option>
              {options.agents.map((agent) => (
                <option key={agent.id} value={agent.id}>
                  {agent.name}
                </option>
              ))}
            </select>
          </label>
          {actionType === 'handoff' && (
            <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
              Priority
              <TextInput
                className="mt-1"
                value={config.priority || 'high'}
                onChange={(event) => onChange('priority', event.target.value)}
              />
            </label>
          )}
          {actionType === 'handoff' && (
            <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text sm:col-span-2">
              Reason
              <TextInput
                className="mt-1"
                value={config.reason || ''}
                onChange={(event) => onChange('reason', event.target.value)}
              />
            </label>
          )}
        </div>
      )}

      {actionType === 'create_deal' && (
        <div className="grid gap-3 sm:grid-cols-2">
          <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text sm:col-span-2">
            Deal title
            <TextInput
              className="mt-1"
              value={config.title || ''}
              onChange={(event) => onChange('title', event.target.value)}
            />
          </label>
          <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
            Stage
            <TextInput
              className="mt-1"
              value={config.stage || 'new'}
              onChange={(event) => onChange('stage', event.target.value)}
            />
          </label>
          <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
            Value paise
            <TextInput
              className="mt-1"
              type="number"
              value={config.value || 0}
              onChange={(event) => onChange('value', Number(event.target.value))}
            />
          </label>
        </div>
      )}

      {actionType === 'create_appointment' && (
        <div className="grid gap-3 sm:grid-cols-2">
          <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text sm:col-span-2">
            Title
            <TextInput
              className="mt-1"
              value={config.title || 'WhatsApp appointment'}
              onChange={(event) => onChange('title', event.target.value)}
            />
          </label>
          <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
            Minutes from now
            <TextInput
              className="mt-1"
              type="number"
              value={config.minutes_from_now || 60}
              onChange={(event) => onChange('minutes_from_now', Number(event.target.value))}
            />
          </label>
          <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
            Duration minutes
            <TextInput
              className="mt-1"
              type="number"
              value={config.duration_minutes || 30}
              onChange={(event) => onChange('duration_minutes', Number(event.target.value))}
            />
          </label>
          <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
            Staff
            <TextInput
              className="mt-1"
              value={config.staff_name || ''}
              onChange={(event) => onChange('staff_name', event.target.value)}
            />
          </label>
          <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
            Location
            <TextInput
              className="mt-1"
              value={config.location || ''}
              onChange={(event) => onChange('location', event.target.value)}
            />
          </label>
          <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text sm:col-span-2">
            Meeting URL
            <TextInput
              className="mt-1"
              value={config.meeting_url || ''}
              onChange={(event) => onChange('meeting_url', event.target.value)}
            />
          </label>
          <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text sm:col-span-2">
            Notes
            <textarea
              className={textAreaClass}
              rows={3}
              value={config.description || ''}
              onChange={(event) => onChange('description', event.target.value)}
            />
          </label>
        </div>
      )}
    </div>
  );
}

function BotDetailDrawer({
  bot,
  connections,
  automationOptions,
  onClose,
}: {
  bot: BotDetail | null;
  connections: Connection[];
  automationOptions: AutomationOptions;
  onClose: () => void;
}) {
  const [tab, setTab] = useState<'overview' | 'flows' | 'runs'>('overview');
  const [activeFlowId, setActiveFlowId] = useState<number | null>(bot?.flows?.[0]?.id ?? null);
  useConfirm();
  const [nodeEditor, setNodeEditor] = useState<{
    node: BotDetail['flows'][number]['nodes'][number];
    config: Record<string, any>;
    type: string;
  } | null>(null);
  const [edgeEditor, setEdgeEditor] = useState<{
    edge: BotDetail['flows'][number]['edges'][number];
    label: string;
  } | null>(null);
  const form = useForm({
    name: bot?.name || '',
    description: bot?.description || '',
    status: bot?.status || 'draft',
    applies_to: bot?.applies_to || { all_connections: true, connection_ids: [] as number[] },
    stop_on_first_flow: bot?.stop_on_first_flow ?? true,
    session_timeout_minutes: bot?.session_timeout_minutes ?? 1440,
    session_resume_mode: bot?.session_resume_mode || 'resume',
    session_expired_message: bot?.session_expired_message || '',
  });

  useEffect(() => {
    if (!bot) return;
    form.setData({
      name: bot.name || '',
      description: bot.description || '',
      status: bot.status || 'draft',
      applies_to: bot.applies_to || { all_connections: true, connection_ids: [] },
      stop_on_first_flow: bot.stop_on_first_flow ?? true,
      session_timeout_minutes: bot.session_timeout_minutes ?? 1440,
      session_resume_mode: bot.session_resume_mode || 'resume',
      session_expired_message: bot.session_expired_message || '',
    });
    setTab('overview');
    setActiveFlowId(bot.flows?.[0]?.id ?? null);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [bot?.id]);

  const activeFlow = bot?.flows?.length
    ? (bot.flows.find((flow) => flow.id === activeFlowId) ?? bot.flows[0])
    : null;

  if (!bot) return null;

  const close = () => {
    onClose();
    router.get(
      route('app.chatbots.index', {}),
      {},
      {
        preserveScroll: true,
        preserveState: true,
        replace: true,
      }
    );
  };

  const save = (event: React.FormEvent) => {
    event.preventDefault();
    form.patch(route('app.chatbots.update', { bot: bot.id }), {
      preserveScroll: true,
      only: ['selectedBot', 'bots', 'flash', 'errors'],
    });
  };

  const setNodeConfig = (key: string, value: any) => {
    setNodeEditor((current) =>
      current ? { ...current, config: { ...current.config, [key]: value } } : current
    );
  };

  const saveNodeConfig = () => {
    if (!nodeEditor) return;
    const nodeType = ['condition', 'delay', 'webhook'].includes(nodeEditor.type)
      ? nodeEditor.type
      : 'action';

    router.patch(
      route('app.chatbots.nodes.update', { node: nodeEditor.node.id }),
      {
        type: nodeType,
        config: nodeEditor.config,
      },
      {
        preserveScroll: true,
        only: ['selectedBot', 'bots', 'flash', 'errors'],
        onSuccess: () => setNodeEditor(null),
      }
    );
  };

  const saveEdgeLabel = () => {
    if (!edgeEditor) return;

    router.patch(
      route('app.chatbots.edges.update', { edge: edgeEditor.edge.id }),
      {
        label: edgeEditor.label,
      },
      {
        preserveScroll: true,
        only: ['selectedBot', 'bots', 'flash', 'errors'],
        onSuccess: () => setEdgeEditor(null),
      }
    );
  };

  const runTest = () => {
    router.post(
      route('app.chatbots.test', { bot: bot.id }),
      {},
      {
        preserveScroll: true,
        only: ['selectedBot', 'bots', 'flash', 'errors'],
      }
    );
  };

  return (
    <>
      <Drawer
        open={Boolean(bot)}
        onClose={close}
        title={bot.name}
        description={`${bot.flows.length} flow${bot.flows.length === 1 ? '' : 's'}`}
        className="sm:max-w-5xl"
        footer={
          <div className="flex flex-wrap justify-end gap-2">
            {bot.flows[0] && (
              <Link href={route('app.chatbots.builder', { bot: bot.id, flow: bot.flows[0].id })}>
                <Button type="button">
                  <Workflow className="h-4 w-4" />
                  Edit flow
                </Button>
              </Link>
            )}
            <Button type="button" variant="secondary" onClick={runTest}>
              <Play className="h-4 w-4" />
              Run test
            </Button>
            <Button type="button" variant="secondary" onClick={close}>
              Close
            </Button>
            <Button type="submit" form="bot-detail-form" disabled={form.processing}>
              {form.processing ? 'Saving...' : 'Save automation'}
            </Button>
          </div>
        }
      >
        <div className="space-y-5">
          <div className="flex gap-1 overflow-x-auto border-b border-gray-100 dark:border-ui-dark-border">
            {[
              ['overview', 'Overview'],
              ['flows', 'Flows'],
              ['runs', 'Runs'],
            ].map(([value, label]) => (
              <button
                key={value}
                type="button"
                onClick={() => setTab(value as any)}
                className={`relative h-10 whitespace-nowrap px-3 text-sm font-semibold transition ${
                  tab === value
                    ? 'text-ui-text dark:text-ui-dark-text'
                    : 'text-ui-text-muted hover:text-ui-text dark:text-ui-dark-text-muted dark:hover:text-ui-dark-text'
                }`}
              >
                {label}
                {tab === value && (
                  <span className="absolute bottom-0 left-2 right-2 h-0.5 rounded-full bg-ui-green" />
                )}
              </button>
            ))}
          </div>

          {tab === 'overview' && (
            <form
              id="bot-detail-form"
              onSubmit={save}
              className="grid gap-5 lg:grid-cols-[1fr,340px]"
            >
              <Card className="border-transparent dark:border-slate-700/80">
                <CardContent className="space-y-4 p-5">
                  <div className="grid gap-4 md:grid-cols-2">
                    <div>
                      <label className="mb-1.5 block text-sm font-medium text-ui-text dark:text-ui-dark-text">
                        Name
                      </label>
                      <TextInput
                        value={form.data.name}
                        onChange={(event) => form.setData('name', event.target.value)}
                      />
                      <InputError message={form.errors.name} className="mt-2" />
                    </div>
                    <div>
                      <label className="mb-1.5 block text-sm font-medium text-ui-text dark:text-ui-dark-text">
                        Status
                      </label>
                      <select
                        value={form.data.status}
                        onChange={(event) => form.setData('status', event.target.value)}
                        className="h-10 w-full rounded-btn border border-gray-200 bg-white px-3 text-sm dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text"
                      >
                        <option value="draft">Draft</option>
                        <option value="active">Active</option>
                        <option value="paused">Paused</option>
                      </select>
                    </div>
                  </div>
                  <div>
                    <label className="mb-1.5 block text-sm font-medium text-ui-text dark:text-ui-dark-text">
                      Description
                    </label>
                    <textarea
                      value={form.data.description}
                      onChange={(event) => form.setData('description', event.target.value)}
                      rows={3}
                      className="w-full rounded-btn border border-gray-200 bg-white px-3 py-2 text-sm dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text"
                    />
                  </div>
                  <label className="flex items-center justify-between rounded-btn border border-gray-100 p-3 dark:border-ui-dark-border">
                    <span>
                      <span className="block text-sm font-medium text-ui-text dark:text-ui-dark-text">
                        Stop after the first match
                      </span>
                      <span className="text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                        Only one chatbot replies to each message.
                      </span>
                    </span>
                    <input
                      type="checkbox"
                      checked={form.data.stop_on_first_flow}
                      onChange={(event) => form.setData('stop_on_first_flow', event.target.checked)}
                      className="rounded border-gray-300 text-ui-green focus:ring-ui-green/30"
                    />
                  </label>
                  <div className="rounded-btn border border-gray-100 p-3 dark:border-ui-dark-border">
                    <div className="grid gap-3 md:grid-cols-2">
                      <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
                        Session timeout
                        <select
                          value={form.data.session_timeout_minutes}
                          onChange={(event) =>
                            form.setData('session_timeout_minutes', Number(event.target.value))
                          }
                          className="mt-1 h-10 w-full rounded-btn border border-gray-200 bg-white px-3 text-sm dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text"
                        >
                          <option value={30}>30 minutes</option>
                          <option value={120}>2 hours</option>
                          <option value={480}>8 hours</option>
                          <option value={1440}>24 hours</option>
                          <option value={10080}>7 days</option>
                        </select>
                      </label>
                      <label className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
                        After timeout
                        <select
                          value={form.data.session_resume_mode}
                          onChange={(event) =>
                            form.setData('session_resume_mode', event.target.value)
                          }
                          className="mt-1 h-10 w-full rounded-btn border border-gray-200 bg-white px-3 text-sm dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text"
                        >
                          <option value="resume">Resume until timeout</option>
                          <option value="restart">Restart matching flows</option>
                          <option value="expire">Expire waiting session</option>
                        </select>
                      </label>
                    </div>
                    <label className="mt-3 block text-sm font-medium text-ui-text dark:text-ui-dark-text">
                      Expired session note
                      <textarea
                        value={form.data.session_expired_message}
                        onChange={(event) =>
                          form.setData('session_expired_message', event.target.value)
                        }
                        rows={2}
                        className="mt-1 w-full rounded-btn border border-gray-200 bg-white px-3 py-2 text-sm dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text"
                        placeholder="Optional internal note for expired sessions"
                      />
                    </label>
                  </div>
                  <label className="flex items-center justify-between rounded-btn border border-gray-100 p-3 dark:border-ui-dark-border">
                    <span>
                      <span className="block text-sm font-medium text-ui-text dark:text-ui-dark-text">
                        All WhatsApp numbers
                      </span>
                      <span className="text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                        Reply on every WhatsApp number in this workspace.
                      </span>
                    </span>
                    <input
                      type="checkbox"
                      checked={form.data.applies_to.all_connections}
                      onChange={(event) =>
                        form.setData('applies_to', {
                          ...form.data.applies_to,
                          all_connections: event.target.checked,
                        })
                      }
                      className="rounded border-gray-300 text-ui-green focus:ring-ui-green/30"
                    />
                  </label>
                  {!form.data.applies_to.all_connections && (
                    <select
                      multiple
                      value={(form.data.applies_to.connection_ids || []).map(String)}
                      onChange={(event) =>
                        form.setData('applies_to', {
                          ...form.data.applies_to,
                          connection_ids: Array.from(event.target.selectedOptions).map((option) =>
                            Number(option.value)
                          ),
                        })
                      }
                      className="min-h-28 w-full rounded-btn border border-gray-200 bg-white px-3 py-2 text-sm dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text"
                    >
                      {connections.map((connection) => (
                        <option key={connection.id} value={connection.id}>
                          {connection.name}
                        </option>
                      ))}
                    </select>
                  )}
                </CardContent>
              </Card>
              <div className="space-y-4">
                <Card className="border-transparent dark:border-slate-700/80">
                  <CardContent className="grid grid-cols-2 gap-3 p-5">
                    {[
                      ['Flows', bot.flows.length],
                      ['Runs', bot.executions_count],
                      ['Failures', bot.failed_executions_count],
                      ['Updated', formatRelative(bot.updated_at)],
                    ].map(([label, value]) => (
                      <div
                        key={String(label)}
                        className="rounded-btn bg-gray-50 p-3 dark:bg-ui-dark-surface-2"
                      >
                        <p className="text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                          {label as string}
                        </p>
                        <p className="mt-1 font-semibold text-ui-text dark:text-ui-dark-text">
                          {typeof value === 'number' ? formatNumber(value) : value}
                        </p>
                      </div>
                    ))}
                  </CardContent>
                </Card>
                <Card className="border-transparent dark:border-slate-700/80">
                  <CardContent className="p-5">
                    <p className="font-semibold text-ui-text dark:text-ui-dark-text">Scope</p>
                    <p className="mt-2 text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
                      {bot.applies_to?.all_connections
                        ? 'Runs on your WhatsApp number.'
                        : `Runs on ${bot.applies_to?.connection_ids?.length || 0} selected number(s).`}
                    </p>
                  </CardContent>
                </Card>
              </div>
            </form>
          )}

          {tab === 'flows' && (
            <div className="space-y-4">
              {bot.flows.length > 1 && (
                <div className="flex gap-2 overflow-x-auto">
                  {bot.flows.map((flow) => (
                    <button
                      key={flow.id}
                      type="button"
                      onClick={() => setActiveFlowId(flow.id)}
                      className={`rounded-full px-3 py-1.5 text-xs font-semibold transition ${
                        activeFlow?.id === flow.id
                          ? 'bg-ui-green-soft text-ui-green-dark dark:bg-emerald-500/10 dark:text-emerald-300'
                          : 'bg-gray-100 text-ui-text-muted hover:text-ui-text dark:bg-ui-dark-surface-2 dark:text-ui-dark-text-muted dark:hover:text-ui-dark-text'
                      }`}
                    >
                      {flow.name}
                    </button>
                  ))}
                </div>
              )}

              {bot.flows.length > 0 ? (
                <div className="grid gap-3 md:grid-cols-2">
                  {bot.flows.map((flow) => (
                    <Card key={flow.id} className="border-transparent dark:border-slate-700/80">
                      <CardContent className="space-y-4 p-5">
                        <div className="flex items-start justify-between gap-3">
                          <div className="min-w-0">
                            <p className="truncate font-semibold text-ui-text dark:text-ui-dark-text">
                              {flow.name}
                            </p>
                            <p className="mt-1 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                              {flow.nodes.length} steps · {flow.edges.length} links · priority{' '}
                              {flow.priority}
                            </p>
                          </div>
                          {flow.health?.is_runnable ? (
                            <Badge variant="success">Ready</Badge>
                          ) : (
                            <Badge variant="warning">Needs steps</Badge>
                          )}
                        </div>
                        <Link href={route('app.chatbots.builder', { bot: bot.id, flow: flow.id })}>
                          <Button className="w-full">
                            <Workflow className="h-4 w-4" />
                            Edit flow
                          </Button>
                        </Link>
                      </CardContent>
                    </Card>
                  ))}
                </div>
              ) : (
                <Card className="border-transparent dark:border-slate-700/80">
                  <CardContent className="p-8 text-center text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
                    No flows yet.
                  </CardContent>
                </Card>
              )}
            </div>
          )}

          {tab === 'runs' && (
            <div className="space-y-4">
              {bot.analytics && (
                <div className="grid gap-3 md:grid-cols-4">
                  {[
                    ['Runs', bot.analytics.runs],
                    ['Success', bot.analytics.success],
                    ['Failed', bot.analytics.failed],
                    ['Skipped', bot.analytics.skipped],
                  ].map(([label, value]) => (
                    <Card key={label} className="border-transparent dark:border-slate-700/80">
                      <CardContent className="p-4">
                        <p className="text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                          {label} · {bot.analytics?.window_days || 30}d
                        </p>
                        <p className="mt-1 text-xl font-bold text-ui-text dark:text-ui-dark-text">
                          {formatNumber(value as number)}
                        </p>
                      </CardContent>
                    </Card>
                  ))}
                </div>
              )}
              {bot.analytics && bot.analytics.node_hits.length > 0 && (
                <Card className="border-transparent dark:border-slate-700/80">
                  <CardContent className="p-5">
                    <p className="text-sm font-semibold text-ui-text dark:text-ui-dark-text">
                      Step activity
                    </p>
                    <div className="mt-3 space-y-2">
                      {bot.analytics.node_hits.slice(0, 8).map((node) => (
                        <div
                          key={node.node_id}
                          className="grid gap-2 rounded-btn bg-gray-50 p-3 text-xs dark:bg-ui-dark-surface-2 md:grid-cols-[1fr,80px,80px,80px]"
                        >
                          <span className="font-medium text-ui-text dark:text-ui-dark-text">
                            Node #{node.node_id} · {node.type}
                          </span>
                          <span className="text-ui-text-muted dark:text-ui-dark-text-muted">
                            Hits {node.total}
                          </span>
                          <span className="text-emerald-700 dark:text-emerald-300">
                            OK {node.success}
                          </span>
                          <span className="text-red-700 dark:text-red-300">
                            Fail {node.failed + node.skipped}
                          </span>
                        </div>
                      ))}
                    </div>
                  </CardContent>
                </Card>
              )}
              <Card className="border-transparent dark:border-slate-700/80">
                <CardContent className="p-0">
                  {bot.executions.length === 0 ? (
                    <div className="p-8 text-center text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
                      No runs yet.
                    </div>
                  ) : (
                    bot.executions.map((execution) => (
                      <div
                        key={execution.id}
                        className="border-b border-gray-100 px-5 py-3 text-sm last:border-0 dark:border-ui-dark-border"
                      >
                        <div className="grid gap-2 md:grid-cols-[1fr,120px,160px,1fr]">
                          <div>
                            <p className="font-medium text-ui-text dark:text-ui-dark-text">
                              {execution.flow_name || 'Automation flow'}
                            </p>
                            <p className="text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                              Execution #{execution.id}
                            </p>
                          </div>
                          <Badge
                            variant={
                              execution.status === 'failed'
                                ? 'danger'
                                : execution.status === 'success'
                                  ? 'success'
                                  : 'default'
                            }
                            className="w-fit"
                          >
                            {friendlyStatus(execution.status)}
                          </Badge>
                          <span className="text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                            {formatRelative(execution.started_at)}
                          </span>
                          <span className="truncate text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                            {execution.error_message || 'No error'}
                          </span>
                        </div>
                        {(execution.logs || []).length > 0 && (
                          <div className="mt-3 rounded-btn bg-gray-50 p-3 dark:bg-ui-dark-surface-2">
                            {(execution.logs || []).slice(0, 6).map((log: any, index) => (
                              <div
                                key={`${execution.id}-log-${index}`}
                                className="flex gap-2 text-xs text-ui-text-muted dark:text-ui-dark-text-muted"
                              >
                                <span
                                  className={
                                    log.level === 'warning' || log.result === 'skipped'
                                      ? 'font-semibold text-amber-700 dark:text-amber-300'
                                      : log.level === 'error' || log.result === 'failed'
                                        ? 'font-semibold text-red-700 dark:text-red-300'
                                        : 'font-semibold text-ui-text dark:text-ui-dark-text'
                                  }
                                >
                                  {log.level || log.result || 'info'}
                                </span>
                                <span>
                                  {log.message ||
                                    log.reason ||
                                    (log.node_id
                                      ? `Node #${log.node_id} ${log.type || ''}`
                                      : JSON.stringify(log))}
                                </span>
                              </div>
                            ))}
                          </div>
                        )}
                      </div>
                    ))
                  )}
                </CardContent>
              </Card>
            </div>
          )}
        </div>
      </Drawer>

      <Modal
        open={Boolean(nodeEditor)}
        onClose={() => setNodeEditor(null)}
        title={`Edit ${nodeEditor?.type === 'action' ? actionLabel(nodeEditor?.config?.action_type) : nodeEditor?.type || 'step'}`}
        description="Configure what this automation step should do."
        footer={
          <>
            <Button type="button" variant="secondary" onClick={() => setNodeEditor(null)}>
              Cancel
            </Button>
            <Button type="button" onClick={saveNodeConfig}>
              Save node
            </Button>
          </>
        }
      >
        {nodeEditor && (
          <NodeConfigFields
            editor={nodeEditor}
            options={automationOptions}
            onChange={setNodeConfig}
          />
        )}
      </Modal>

      <Modal
        open={Boolean(edgeEditor)}
        onClose={() => setEdgeEditor(null)}
        title="Edit branch label"
        description="Name this connection so the flow is easier to scan."
        footer={
          <>
            <Button type="button" variant="secondary" onClick={() => setEdgeEditor(null)}>
              Cancel
            </Button>
            <Button type="button" onClick={saveEdgeLabel}>
              Save label
            </Button>
          </>
        }
      >
        <label className="mb-1.5 block text-sm font-medium text-ui-text dark:text-ui-dark-text">
          Label
        </label>
        <TextInput
          value={edgeEditor?.label || ''}
          onChange={(event) =>
            setEdgeEditor((current) =>
              current ? { ...current, label: event.target.value } : current
            )
          }
          placeholder="next"
        />
      </Modal>
    </>
  );
}

export default function ChatbotsIndex({
  bots,
  connections = [],
  automationOptions = { agents: [], tags: [], templates: [], lists: [] },
  selectedBot,
}: {
  account: any;
  bots: BotItem[];
  connections?: Connection[];
  automationOptions?: AutomationOptions;
  selectedBot?: BotDetail | null;
}) {
  const pageUrl = usePage().url;

  const { toast } = useToast();
  const confirm = useConfirm();
  const runnableCount = bots.reduce((sum, bot) => sum + Number(bot.runnable_flows_count || 0), 0);
  const errors = bots.reduce((sum, bot) => sum + Number(bot.errors_count || 0), 0);
  const hasAutomationAlert = bots.length > 0 && (runnableCount === 0 || errors > 0);
  const [createOpen, setCreateOpen] = useState(
    () => new URLSearchParams(pageUrl.split('?')[1] || '').get('panel') === 'create'
  );
  const [activeBot, setActiveBot] = useState<BotDetail | null>(selectedBot || null);

  useEffect(() => {
    setActiveBot(selectedBot || null);
  }, [selectedBot]);

  const deleteBot = async (bot: BotItem) => {
    const confirmed = await confirm({
      title: 'Delete automation?',
      message: `"${bot.name}" and its flows will be removed permanently.`,
      confirmText: 'Delete',
      cancelText: 'Cancel',
    });
    if (!confirmed) return;

    router.post(
      route('app.chatbots.destroy.post', { bot: bot.id }),
      { _method: 'delete' },
      {
        preserveScroll: true,
        onSuccess: () => toast.success('Automation deleted'),
        onError: () => toast.error('Failed to delete automation'),
      }
    );
  };

  return (
    <AppShell>
      <Head title="Chatbots" />
      <div className="module-page">
        <PageHeader
          title="Chatbots"
          description="Automatic replies for keywords, new chats and leads."
          actions={
            <>
              <Link href={route('app.chatbots.executions.index', {})}>
                <Button variant="ghost">
                  <Activity className="h-4 w-4" />
                  Activity
                </Button>
              </Link>
              <Button onClick={() => setCreateOpen(true)}>
                <Plus className="h-4 w-4" />
                New chatbot
              </Button>
            </>
          }
        />

        {hasAutomationAlert && (
          <div className="flex flex-col gap-2 rounded-btn bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-100 sm:flex-row sm:items-center sm:justify-between">
            <span className="flex items-center gap-2">
              <AlertCircle className="h-4 w-4 shrink-0" />
              {runnableCount === 0
                ? 'None of your chatbots has a flow that can run yet.'
                : `${formatNumber(errors)} recent run${errors === 1 ? '' : 's'} failed.`}
            </span>
            <Link
              href={route('app.chatbots.executions.index', {})}
              className="font-medium underline-offset-2 hover:underline"
            >
              See activity
            </Link>
          </div>
        )}

        <Card className="overflow-hidden border-transparent dark:border-slate-700/80">
          {bots.length === 0 ? (
            <EmptyState
              icon={Bot}
              title="No chatbots yet"
              description="Greet customers and answer common questions automatically."
              action={
                <Button onClick={() => setCreateOpen(true)}>
                  <Plus className="h-4 w-4" />
                  New chatbot
                </Button>
              }
            />
          ) : (
            <div className="divide-y divide-gray-100 dark:divide-slate-700">
              {bots.map((bot) => (
                <AutomationCard key={bot.id} bot={bot} onDelete={deleteBot} />
              ))}
            </div>
          )}
        </Card>
      </div>
      <CreateBotDrawer
        open={createOpen}
        onClose={() => setCreateOpen(false)}
        connections={connections}
      />
      <BotDetailDrawer
        bot={activeBot}
        connections={connections}
        automationOptions={automationOptions}
        onClose={() => setActiveBot(null)}
      />
    </AppShell>
  );
}
