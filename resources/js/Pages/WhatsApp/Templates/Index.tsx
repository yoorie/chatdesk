import { brandName } from '../../../lib/brand';
import TextInput from '@/Components/TextInput';
import { Badge } from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { Card, CardContent } from '@/Components/UI/Card';
import { Drawer, IconButton, Modal, PageHeader } from '@/Components/UI/Elements';
import { EmptyState } from '@/Components/UI/EmptyState';
import { useConfirm } from '@/hooks/useConfirm';
import { useToast } from '@/hooks/useToast';
import AppShell from '@/Layouts/AppShell';
import { Head, router, useForm } from '@inertiajs/react';
import {
  Archive,
  ArrowLeft,
  Check,
  CheckCheck,
  Copy,
  Edit,
  ExternalLink,
  Eye,
  FileText,
  Filter,
  Globe,
  Image as ImageIcon,
  MessageSquareText,
  MoreVertical,
  Phone,
  Plus,
  RefreshCw,
  Reply,
  Search,
  Send,
  Sparkles,
  Tag,
  Trash2,
  Upload,
  User,
  Video,
  X,
} from 'lucide-react';
import type { ChangeEvent } from 'react';
import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';

interface Template {
  id: number;
  slug: string;
  name: string;
  language: string;
  category: string;
  status: string;
  body_text: string | null;
  header_type: 'NONE' | 'TEXT' | 'IMAGE' | 'VIDEO' | 'DOCUMENT';
  header_text: string | null;
  header_media_url: string | null;
  footer_text: string | null;
  buttons: TemplateButton[];
  has_buttons: boolean;
  variable_count: number;
  quality_score?: string | null;
  rejection_reason?: string | null;
  stats?: { sent: number; delivered: number; read: number; failed: number };
  connection: {
    id: number;
    name: string;
  };
  last_synced_at: string | null;
}

interface TemplateButton {
  type: 'QUICK_REPLY' | 'URL' | 'PHONE_NUMBER';
  text: string;
  url?: string;
  url_example?: string;
  phone_number?: string;
}

interface LibraryTemplate {
  id: string;
  name: string;
  title: string;
  category: 'MARKETING' | 'UTILITY' | 'AUTHENTICATION';
  language: string;
  header_type: 'NONE' | 'TEXT' | 'IMAGE' | 'VIDEO' | 'DOCUMENT';
  header_text?: string | null;
  body_text: string;
  body_examples?: string[];
  footer_text?: string | null;
  buttons?: TemplateButton[];
  use_case: string;
}

interface TemplateFormData {
  whatsapp_connection_id: string;
  name: string;
  language: string;
  category: 'MARKETING' | 'UTILITY' | 'AUTHENTICATION';
  header_type: 'NONE' | 'TEXT' | 'IMAGE' | 'VIDEO' | 'DOCUMENT';
  header_text: string;
  header_media_url: string;
  body_text: string;
  body_examples: string[];
  footer_text: string;
  buttons: TemplateButton[];
}

interface Filters {
  connection: string;
  status: string;
  category: string;
  language: string;
  search: string;
}

interface ContactOption {
  id: number;
  wa_id: string;
  name: string | null;
}

interface ConversationOption {
  id: number;
  contact: {
    wa_id: string;
    name: string | null;
  };
}

const categoryTone = (category: string) => {
  const normalized = category.toLowerCase();
  if (normalized.includes('marketing'))
    return 'bg-pink-50 text-pink-700 dark:bg-pink-500/10 dark:text-pink-300';
  if (normalized.includes('utility'))
    return 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300';
  if (normalized.includes('authentication'))
    return 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300';
  return 'bg-gray-50 text-gray-700 dark:bg-slate-700 dark:text-slate-200';
};

const CATEGORY_LABELS: Record<string, string> = {
  MARKETING: 'Marketing',
  UTILITY: 'Updates',
  AUTHENTICATION: 'Login codes',
};

const categoryLabel = (category: string) =>
  CATEGORY_LABELS[(category || '').toUpperCase()] ?? category;

const variableNumbersFromText = (text: string) => {
  const numbers = (text.match(/\{\{(\d+)\}\}/g) || [])
    .map((value) => Number(value.match(/\d+/)?.[0] || 0))
    .filter(Boolean);

  return Array.from(new Set(numbers)).sort((a, b) => a - b);
};

const mediaAcceptForHeader = (type: TemplateFormData['header_type']) => {
  if (type === 'IMAGE') return 'image/jpeg,image/jpg,image/png';
  if (type === 'VIDEO') return 'video/mp4';
  if (type === 'DOCUMENT') return 'application/pdf';
  return '';
};

function TemplatePhonePreview({ template }: { template: Template }) {
  const body = template.body_text || 'Template message';
  const parts = body.split(/(\{\{\d+\}\})/g);

  return (
    <div className="relative mx-auto w-[260px] origin-top">
      <div className="rounded-[28px] bg-[#1A1A2E] p-2 shadow-pop ring-1 ring-black/10 dark:ring-white/10">
        <div className="flex h-9 items-center gap-2 rounded-[20px] bg-[#075E54] px-3 text-white">
          <ArrowLeft className="h-3.5 w-3.5" />
          <div className="flex h-6 w-6 items-center justify-center rounded-full bg-white/30 text-[10px] font-bold">
            W
          </div>
          <div className="min-w-0 flex-1">
            <div className="text-[11px] font-semibold leading-tight">
              {template.connection?.name || 'Your business'}
            </div>
            <div className="text-[9px] text-white/70">online</div>
          </div>
          <Video className="h-3 w-3" />
          <Phone className="h-3 w-3" />
        </div>
        <div className="chat-bg min-h-[280px] rounded-b-[20px] p-3">
          <div className="relative max-w-[88%] rounded-bl-lg rounded-br-lg rounded-tl-md rounded-tr-lg bg-white p-2.5 text-ui-text shadow-sm dark:bg-[#202C33] dark:text-[#E9EDEF]">
            {['IMAGE', 'VIDEO', 'DOCUMENT'].includes(String(template.header_type || '')) && (
              <div className="mb-2 -mx-1 -mt-1 flex aspect-[16/9] items-center justify-center overflow-hidden rounded-md bg-gradient-to-br from-ui-green to-ui-green-dark text-white">
                {template.header_type === 'IMAGE' && template.header_media_url ? (
                  <img
                    src={template.header_media_url}
                    alt=""
                    className="h-full w-full object-cover"
                  />
                ) : (
                  <ImageIcon className="h-5 w-5 opacity-80" />
                )}
              </div>
            )}
            {template.header_type === 'TEXT' && template.header_text && (
              <p className="mb-1 text-[12px] font-semibold leading-snug">{template.header_text}</p>
            )}
            <p className="whitespace-pre-wrap text-[12px] leading-snug">
              {parts.map((part, index) =>
                /^\{\{\d+\}\}$/.test(part) ? (
                  <span
                    key={`${part}-${index}`}
                    className="rounded bg-blue-100 px-1 font-mono text-[11px] font-semibold text-blue-700 dark:bg-blue-900/50 dark:text-blue-300"
                  >
                    {part}
                  </span>
                ) : (
                  <span key={`${part}-${index}`}>{part}</span>
                )
              )}
            </p>
            {template.footer_text && (
              <p className="mt-1 text-[10px] text-gray-500 dark:text-[#8696A0]">
                {template.footer_text}
              </p>
            )}
            <div className="mt-1 flex items-center justify-end gap-0.5 text-[9px] text-gray-400 dark:text-[#8696A0]">
              10:24 AM <CheckCheck className="h-2.5 w-2.5 text-blue-500" />
            </div>
          </div>
          {(template.buttons || []).length > 0 && (
            <div className="mt-1.5 space-y-1">
              {(template.buttons || []).map((button, index) => (
                <div
                  key={`${button.text}-${index}`}
                  className="flex w-[88%] items-center justify-center gap-1 rounded-md bg-white py-1.5 text-[11px] font-medium text-blue-600 shadow-sm dark:bg-[#2A3942] dark:text-[#53BDEB]"
                >
                  {button.type === 'URL' ? (
                    <ExternalLink className="h-2.5 w-2.5" />
                  ) : button.type === 'PHONE_NUMBER' ? (
                    <Phone className="h-2.5 w-2.5" />
                  ) : (
                    <Reply className="h-2.5 w-2.5" />
                  )}
                  {button.text || 'Button'}
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
      <div className="absolute left-1/2 top-1 h-1 w-12 -translate-x-1/2 rounded-full bg-white/20" />
    </div>
  );
}

function TemplateBuilderDrawer({
  open,
  onClose,
  template,
  connections,
}: {
  open: boolean;
  onClose: () => void;
  template: Template | null;
  connections: Array<{ id: number; name: string }>;
}) {
  const { toast } = useToast();
  const editing = Boolean(template && template.id > 0);

  const [submitting, setSubmitting] = useState(false);
  const [uploadingMedia, setUploadingMedia] = useState(false);
  const fileInputRef = useRef<HTMLInputElement | null>(null);
  const { data, setData, errors, reset } = useForm<TemplateFormData>({
    whatsapp_connection_id:
      template?.connection?.id?.toString() || connections?.[0]?.id?.toString() || '',
    name: template?.name || '',
    language: template?.language || 'en_US',
    category: (template?.category as TemplateFormData['category']) || 'UTILITY',
    header_type: template?.header_type || 'NONE',
    header_text: template?.header_text || '',
    header_media_url: template?.header_media_url || '',
    body_text: template?.body_text || 'Hi {{1}}, ',
    body_examples: [],
    footer_text: template?.footer_text || '',
    buttons: template?.buttons || [],
  });

  const previewTemplate = useMemo<Template>(
    () => ({
      id: template?.id || 0,
      slug: template?.slug || 'preview',
      name: data.name || 'new_template',
      language: data.language,
      category: data.category,
      status: template?.status || 'pending',
      body_text: data.body_text,
      header_type: data.header_type,
      header_text: data.header_text,
      header_media_url: data.header_media_url,
      footer_text: data.footer_text,
      buttons: data.buttons,
      has_buttons: data.buttons.length > 0,
      variable_count: (data.body_text.match(/\{\{\d+\}\}/g) || []).length,
      connection: template?.connection || {
        id: Number(data.whatsapp_connection_id || connections?.[0]?.id || 0),
        name:
          connections?.find(
            (connection) => connection.id.toString() === data.whatsapp_connection_id
          )?.name || `${brandName()} Business`,
      },
      last_synced_at: template?.last_synced_at || null,
    }),
    [connections, data, template]
  );

  const bodyVariables = useMemo(() => variableNumbersFromText(data.body_text), [data.body_text]);

  const insertVariable = () => {
    const used = (data.body_text.match(/\{\{(\d+)\}\}/g) || []).map((value) =>
      Number(value.match(/\d+/)?.[0] || 0)
    );
    const next = used.length ? Math.max(...used) + 1 : 1;
    setData('body_text', `${data.body_text}{{${next}}}`);
  };

  const updateBodyExample = (variableNumber: number, value: string) => {
    const next = [...data.body_examples];
    next[variableNumber - 1] = value;
    setData('body_examples', next);
  };

  const uploadHeaderMedia = async (file: File) => {
    setUploadingMedia(true);
    try {
      const formData = new FormData();
      formData.append('file', file);
      formData.append('type', data.header_type);

      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      const response = await fetch(route('app.whatsapp.templates.upload-media', {}), {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrf,
        },
        body: formData,
      });

      const result = await response.json();
      if (!response.ok) {
        throw new Error(result.message || result.error || 'Failed to upload file.');
      }

      setData('header_media_url', result.url);
      toast.success('File uploaded', 'Header sample attached to this template.');
    } catch (error: any) {
      toast.error('Upload failed', error.message || 'Failed to upload file.');
    } finally {
      setUploadingMedia(false);
    }
  };

  const handleMediaFileChange = (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (file) {
      uploadHeaderMedia(file);
    }
    event.target.value = '';
  };

  const addButton = (type: 'QUICK_REPLY' | 'URL' | 'PHONE_NUMBER') => {
    if (data.buttons.length >= 3) {
      toast.error('Maximum 3 buttons allowed');
      return;
    }
    setData('buttons', [
      ...data.buttons,
      {
        type,
        text: type === 'URL' ? 'Visit Website' : type === 'PHONE_NUMBER' ? 'Call us' : 'Reply',
      },
    ]);
  };

  const updateButton = (index: number, field: keyof TemplateButton, value: string) => {
    const next = [...data.buttons];
    next[index] = { ...next[index], [field]: value };
    setData('buttons', next);
  };

  const removeButton = (index: number) => {
    setData(
      'buttons',
      data.buttons.filter((_, buttonIndex) => buttonIndex !== index)
    );
  };

  const firstError = Object.values(errors)[0];

  const closeDrawer = () => {
    reset();
    onClose();
  };

  const submit = () => {
    if (submitting) {
      return;
    }

    const cleanedButtons = data.buttons.filter((button) => button.text.trim());
    const cleanedExamples = bodyVariables
      .map((variableNumber) => data.body_examples[variableNumber - 1] || '')
      .filter((example) => example.trim());
    const payload: Record<string, any> = {
      ...data,
      name: data.name.trim(),
      body_text: data.body_text.trim(),
      footer_text: data.footer_text.trim(),
      header_text: data.header_text.trim(),
      header_media_url: data.header_media_url.trim(),
      body_examples: cleanedExamples,
      buttons: cleanedButtons,
    };

    const options = {
      preserveScroll: true,
      onSuccess: () => {
        toast.success(editing ? 'Template updated' : 'Template submitted for approval');
        closeDrawer();
      },
      onError: (serverErrors: Record<string, string>) => {
        const message = Object.values(serverErrors || {}).filter(Boolean)[0];
        toast.error(
          editing ? 'Failed to update template' : 'Failed to create template',
          message || 'Check the highlighted fields and try again.'
        );
      },
      onFinish: () => setSubmitting(false),
    };

    setSubmitting(true);

    if (editing && template) {
      router.put(
        route('app.whatsapp.templates.update', { template: template.slug }),
        payload,
        options
      );
    } else {
      router.post(route('app.whatsapp.templates.store', {}), payload, options);
    }
  };

  return (
    <Drawer
      open={open}
      onClose={closeDrawer}
      title={editing ? 'Edit template' : 'New template'}
      description="WhatsApp checks each template. Most are approved within minutes."
      className="max-w-5xl"
      footer={
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
            You can use it once it is approved.
          </div>
          <div className="grid grid-cols-2 gap-2 sm:flex">
            <Button
              type="button"
              variant="ghost"
              onClick={closeDrawer}
              className="w-full sm:w-auto"
            >
              Cancel
            </Button>
            <Button
              type="button"
              onClick={submit}
              disabled={submitting}
              className="w-full sm:w-auto"
            >
              <Send className="h-4 w-4" />
              Send for approval
            </Button>
          </div>
        </div>
      }
    >
      {firstError && (
        <div className="mb-4 rounded-card border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">
          {firstError}
        </div>
      )}
      <div className="grid min-h-full min-w-0 gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div className="min-w-0 space-y-5">
          <div className="grid gap-3 sm:grid-cols-2">
            <div>
              <label className="mb-1 block text-xs font-medium text-ui-text dark:text-ui-dark-text">
                Name
              </label>
              <TextInput
                value={data.name}
                onChange={(event) => setData('name', event.target.value)}
                placeholder="diwali_offer"
                className="h-10 rounded-btn border-gray-200 dark:border-ui-dark-border dark:bg-ui-dark-surface-2"
              />
              <p className="mt-1 text-[10px] text-ui-text-muted dark:text-ui-dark-text-muted">
                Small letters, numbers and _ only.
              </p>
              {errors.name && (
                <p className="mt-1 text-xs text-red-600 dark:text-red-300">{errors.name}</p>
              )}
            </div>
            <div>
              <label className="mb-1 block text-xs font-medium text-ui-text dark:text-ui-dark-text">
                Language
              </label>
              <select
                value={data.language}
                onChange={(event) => setData('language', event.target.value)}
                className="h-10 w-full rounded-btn border-gray-200 bg-white px-3 text-sm text-ui-text shadow-sm focus:border-ui-green focus:ring-ui-green/20 dark:border-ui-dark-border dark:bg-ui-dark-surface-2 dark:text-ui-dark-text"
              >
                <option value="en_US">English (US)</option>
                <option value="en_GB">English (UK)</option>
                <option value="hi_IN">Hindi</option>
                <option value="mr_IN">Marathi</option>
                <option value="ta_IN">Tamil</option>
              </select>
            </div>
          </div>

          <div>
            <label className="mb-2 block text-xs font-medium text-ui-text dark:text-ui-dark-text">
              Category
            </label>
            <div className="grid gap-2 sm:grid-cols-3">
              {[
                { id: 'MARKETING', label: 'Marketing', desc: 'Offers and news' },
                { id: 'UTILITY', label: 'Updates', desc: 'Orders, bookings, reminders' },
                { id: 'AUTHENTICATION', label: 'Login codes', desc: 'One-time codes' },
              ].map((category) => (
                <button
                  key={category.id}
                  type="button"
                  onClick={() => setData('category', category.id as TemplateFormData['category'])}
                  className={`rounded-btn p-3 text-left ring-1 transition ${data.category === category.id ? 'bg-ui-green-soft ring-ui-green dark:bg-ui-dark-green-soft' : 'bg-white ring-gray-200 hover:ring-gray-300 dark:bg-ui-dark-surface-2 dark:ring-ui-dark-border'}`}
                >
                  <div className="text-sm font-semibold text-ui-text dark:text-ui-dark-text">
                    {category.label}
                  </div>
                  <div className="mt-1 text-[11px] text-ui-text-muted dark:text-ui-dark-text-muted">
                    {category.desc}
                  </div>
                </button>
              ))}
            </div>
          </div>

          <div className="border-t border-gray-100 pt-4 dark:border-ui-dark-border">
            <div className="mb-2 flex items-center justify-between gap-3">
              <label className="text-xs font-medium text-ui-text dark:text-ui-dark-text">
                Header <span className="font-normal text-ui-text-muted">(optional)</span>
              </label>
              <div className="flex flex-wrap items-center gap-1">
                {(
                  [
                    'NONE',
                    'TEXT',
                    'IMAGE',
                    'VIDEO',
                    'DOCUMENT',
                  ] as TemplateFormData['header_type'][]
                ).map((type) => (
                  <button
                    key={type}
                    type="button"
                    onClick={() => setData('header_type', type)}
                    className={`h-7 rounded-md px-2 text-[11px] font-medium transition ${data.header_type === type ? 'bg-ui-green text-white' : 'bg-gray-100 text-ui-text-muted hover:text-ui-text dark:bg-ui-dark-surface-2 dark:text-ui-dark-text-muted dark:hover:text-ui-dark-text'}`}
                  >
                    {type === 'NONE'
                      ? 'None'
                      : type === 'TEXT'
                        ? 'Text'
                        : type === 'IMAGE'
                          ? 'Image'
                          : type === 'VIDEO'
                            ? 'Video'
                            : 'PDF'}
                  </button>
                ))}
              </div>
            </div>
            {data.header_type === 'TEXT' && (
              <TextInput
                value={data.header_text}
                onChange={(event) => setData('header_text', event.target.value)}
                placeholder="Header text"
                maxLength={60}
                className="h-10 rounded-btn border-gray-200 dark:border-ui-dark-border dark:bg-ui-dark-surface-2"
              />
            )}
            {['IMAGE', 'VIDEO', 'DOCUMENT'].includes(data.header_type) && (
              <div className="space-y-3">
                <input
                  ref={fileInputRef}
                  type="file"
                  accept={mediaAcceptForHeader(data.header_type)}
                  onChange={handleMediaFileChange}
                  className="hidden"
                  disabled={uploadingMedia}
                />
                <div className="grid gap-2 sm:grid-cols-[auto_minmax(0,1fr)]">
                  <Button
                    type="button"
                    variant="secondary"
                    onClick={() => fileInputRef.current?.click()}
                    disabled={uploadingMedia}
                    className="w-full sm:w-auto"
                  >
                    <Upload className="h-4 w-4" />
                    {uploadingMedia ? 'Uploading...' : 'Upload sample'}
                  </Button>
                  <TextInput
                    value={data.header_media_url}
                    onChange={(event) => setData('header_media_url', event.target.value)}
                    placeholder="Or paste a link to the file"
                    className="h-10 rounded-btn border-gray-200 dark:border-ui-dark-border dark:bg-ui-dark-surface-2"
                  />
                </div>
                {data.header_media_url && (
                  <div className="rounded-btn border border-gray-100 bg-gray-50 p-3 text-xs text-ui-text-muted dark:border-ui-dark-border dark:bg-ui-dark-surface-2 dark:text-ui-dark-text-muted">
                    <div className="mb-2 flex items-center justify-between gap-3">
                      <span className="font-semibold text-ui-text dark:text-ui-dark-text">
                        File added
                      </span>
                      <button
                        type="button"
                        onClick={() => setData('header_media_url', '')}
                        className="text-red-600 hover:underline dark:text-red-300"
                      >
                        Remove
                      </button>
                    </div>
                    <div className="break-all">{data.header_media_url}</div>
                    {data.header_type === 'IMAGE' && (
                      <img
                        src={data.header_media_url}
                        alt="Header sample"
                        className="mt-3 max-h-40 rounded-card object-contain"
                      />
                    )}
                  </div>
                )}
                {errors.header_media_url && (
                  <p className="text-xs text-red-600 dark:text-red-300">
                    {errors.header_media_url}
                  </p>
                )}
              </div>
            )}
          </div>

          <div>
            <div className="mb-1 flex items-center justify-between">
              <label className="text-xs font-medium text-ui-text dark:text-ui-dark-text">
                Message
              </label>
              <button
                type="button"
                onClick={insertVariable}
                className="inline-flex items-center gap-1 text-[11px] font-medium text-ui-green-dark hover:underline dark:text-emerald-300"
              >
                <Plus className="h-3 w-3" />
                Add a field
              </button>
            </div>
            <textarea
              rows={6}
              value={data.body_text}
              onChange={(event) => setData('body_text', event.target.value)}
              maxLength={1024}
              className="w-full resize-none rounded-btn border border-gray-200 bg-white p-3 text-sm text-ui-text outline-none focus:border-ui-green focus:ring-2 focus:ring-ui-green/15 dark:border-ui-dark-border dark:bg-ui-dark-surface-2 dark:text-ui-dark-text"
              placeholder="Hi {{1}}, your order is ready."
            />
            <div className="mt-1 flex justify-between text-[10px] text-ui-text-muted dark:text-ui-dark-text-muted">
              <span>Fields like {'{{1}}'} are filled in for each person, e.g. their name.</span>
              <span>{data.body_text.length}/1024</span>
            </div>
            {errors.body_text && (
              <p className="mt-1 text-xs text-red-600 dark:text-red-300">{errors.body_text}</p>
            )}
            {bodyVariables.length > 0 && (
              <div className="mt-3 rounded-btn border border-gray-100 bg-gray-50 p-3 dark:border-ui-dark-border dark:bg-ui-dark-surface-2">
                <div className="mb-2 text-xs font-semibold text-ui-text dark:text-ui-dark-text">
                  Example for each field (helps approval)
                </div>
                <div className="grid gap-2 sm:grid-cols-2">
                  {bodyVariables.map((variableNumber) => (
                    <TextInput
                      key={variableNumber}
                      value={data.body_examples[variableNumber - 1] || ''}
                      onChange={(event) => updateBodyExample(variableNumber, event.target.value)}
                      placeholder={`{{${variableNumber}}} e.g. ${variableNumber === 1 ? 'Priya' : 'value'}`}
                      className="h-9 rounded-btn border-gray-200 dark:border-ui-dark-border dark:bg-ui-dark-surface"
                    />
                  ))}
                </div>
              </div>
            )}
          </div>

          <div>
            <label className="mb-1 block text-xs font-medium text-ui-text dark:text-ui-dark-text">
              Footer <span className="font-normal text-ui-text-muted">(optional)</span>
            </label>
            <TextInput
              value={data.footer_text}
              onChange={(event) => setData('footer_text', event.target.value)}
              placeholder="e.g. Reply STOP to unsubscribe"
              maxLength={60}
              className="h-10 rounded-btn border-gray-200 dark:border-ui-dark-border dark:bg-ui-dark-surface-2"
            />
          </div>

          <div>
            <div className="mb-2 flex items-center justify-between">
              <label className="text-xs font-medium text-ui-text dark:text-ui-dark-text">
                Buttons <span className="font-normal text-ui-text-muted">(up to 3)</span>
              </label>
              <div className="flex flex-wrap items-center justify-end gap-1">
                <Button
                  type="button"
                  variant="secondary"
                  size="xs"
                  onClick={() => addButton('QUICK_REPLY')}
                  disabled={data.buttons.length >= 3}
                >
                  + Quick reply
                </Button>
                <Button
                  type="button"
                  variant="secondary"
                  size="xs"
                  onClick={() => addButton('URL')}
                  disabled={data.buttons.length >= 3}
                >
                  + Link
                </Button>
                <Button
                  type="button"
                  variant="secondary"
                  size="xs"
                  onClick={() => addButton('PHONE_NUMBER')}
                  disabled={data.buttons.length >= 3}
                >
                  + Call
                </Button>
              </div>
            </div>
            <div className="space-y-2">
              {data.buttons.length === 0 && (
                <div className="rounded-btn border border-dashed border-gray-200 py-3 text-center text-xs text-ui-text-muted dark:border-ui-dark-border dark:text-ui-dark-text-muted">
                  No buttons yet.
                </div>
              )}
              {data.buttons.map((button, index) => (
                <div
                  key={index}
                  className="rounded-btn bg-gray-50 p-2 dark:bg-ui-dark-surface-2"
                >
                  <div className="flex items-center gap-2">
                    <input
                      value={button.text}
                      onChange={(event) => updateButton(index, 'text', event.target.value)}
                      className="h-9 flex-1 rounded-md border border-gray-200 bg-white px-3 text-sm dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text"
                    />
                    <button
                      type="button"
                      onClick={() => removeButton(index)}
                      aria-label="Remove button"
                      className="rounded-md p-2 text-ui-text-muted hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-200"
                    >
                      <X className="h-4 w-4" />
                    </button>
                  </div>
                  {button.type === 'URL' && (
                    <div className="mt-2 grid gap-2 sm:grid-cols-2">
                      <input
                        value={button.url || ''}
                        onChange={(event) => updateButton(index, 'url', event.target.value)}
                        placeholder="https://example.com"
                        className="h-9 w-full rounded-md border border-gray-200 bg-white px-3 text-sm dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text"
                      />
                      <input
                        value={button.url_example || ''}
                        onChange={(event) => updateButton(index, 'url_example', event.target.value)}
                        placeholder="Example link (only if it changes per person)"
                        className="h-9 w-full rounded-md border border-gray-200 bg-white px-3 text-sm dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text"
                      />
                    </div>
                  )}
                  {button.type === 'PHONE_NUMBER' && (
                    <input
                      value={button.phone_number || ''}
                      onChange={(event) => updateButton(index, 'phone_number', event.target.value)}
                      placeholder="+919988776655"
                      className="mt-2 h-9 w-full rounded-md border border-gray-200 bg-white px-3 text-sm dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text"
                    />
                  )}
                </div>
              ))}
            </div>
          </div>
        </div>

        <aside className="rounded-card border border-gray-100 bg-gray-50/70 p-5 dark:border-ui-dark-border dark:bg-slate-950">
          <div className="mb-3 text-[11px] font-bold uppercase tracking-wider text-ui-text-muted dark:text-ui-dark-text-muted">
            Preview
          </div>
          <TemplatePhonePreview template={previewTemplate} />
        </aside>
      </div>
    </Drawer>
  );
}

function SendTemplateModal({
  template,
  contacts,
  conversations,
  onClose,
}: {
  template: Template | null;
  contacts: ContactOption[];
  conversations: ConversationOption[];
  onClose: () => void;
}) {
  const { toast } = useToast();
  const [recipientType, setRecipientType] = useState<'conversation' | 'contact' | 'manual'>(
    'conversation'
  );
  const [contactSearch, setContactSearch] = useState('');
  const variableNumbers = useMemo(() => {
    if (!template) return [];
    const buttonText = (template.buttons || [])
      .map((button) => `${button.url || ''} ${button.url_example || ''}`)
      .join(' ');
    return variableNumbersFromText(
      `${template.header_text || ''}\n${template.body_text || ''}\n${buttonText}`
    );
  }, [template]);
  const { data, setData, post, processing, errors, reset } = useForm({
    to_wa_id: '',
    variables: [] as string[],
  });

  useEffect(() => {
    setData(
      'variables',
      variableNumbers.map(() => '')
    );
  }, [template?.id, variableNumbers, setData]);

  const filteredContacts = useMemo(() => {
    const search = contactSearch.trim().toLowerCase();
    if (!search) return contacts.slice(0, 60);
    return contacts
      .filter((contact) => `${contact.name || ''} ${contact.wa_id}`.toLowerCase().includes(search))
      .slice(0, 60);
  }, [contactSearch, contacts]);

  const previewBody = useMemo(() => {
    if (!template) return '';
    let body = [template.header_text, template.body_text, template.footer_text]
      .filter(Boolean)
      .join('\n\n');
    variableNumbers.forEach((variableNumber, index) => {
      body = body.replaceAll(
        `{{${variableNumber}}}`,
        data.variables[index] || `{{${variableNumber}}}`
      );
    });
    return body;
  }, [data.variables, template, variableNumbers]);

  const close = () => {
    reset();
    setRecipientType('conversation');
    setContactSearch('');
    onClose();
  };

  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (!template) return;
    post(route('app.whatsapp.templates.send.store', { template: template.slug }) as string, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Template sent');
        close();
      },
      onError: () => toast.error('Failed to send template'),
    });
  };

  return (
    <Modal
      open={Boolean(template)}
      onClose={close}
      title={template ? `Send ${template.name}` : 'Send template'}
      className="max-w-4xl"
    >
      {template && (
        <form onSubmit={submit} className="grid min-w-0 gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
          <div className="min-w-0 space-y-4">
            {template.status.toLowerCase() !== 'approved' && (
              <div className="rounded-card border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                You can send this once it is approved.
              </div>
            )}
            <div className="rounded-card border border-gray-100 bg-white p-4 dark:border-ui-dark-border dark:bg-ui-dark-surface">
              <p className="text-xs font-semibold uppercase tracking-wide text-ui-text-muted dark:text-ui-dark-text-muted">
                Send to
              </p>
              <div className="mt-3 grid gap-2 sm:grid-cols-3">
                {[
                  ['conversation', 'Recent chat', MessageSquareText],
                  ['contact', 'Contact', User],
                  ['manual', 'Phone number', Phone],
                ].map(([value, label, Icon]) => (
                  <button
                    key={value as string}
                    type="button"
                    onClick={() => {
                      setRecipientType(value as 'conversation' | 'contact' | 'manual');
                      setData('to_wa_id', '');
                    }}
                    className={`flex items-center justify-center gap-2 rounded-btn border px-3 py-2 text-sm font-medium transition ${recipientType === value ? 'border-ui-green bg-ui-green/10 text-ui-green-dark dark:text-emerald-300' : 'border-gray-200 text-ui-text-muted hover:text-ui-text dark:border-ui-dark-border dark:text-ui-dark-text-muted dark:hover:text-ui-dark-text'}`}
                  >
                    <Icon className="h-4 w-4" />
                    {label as string}
                  </button>
                ))}
              </div>
              {recipientType === 'conversation' && (
                <select
                  value={data.to_wa_id}
                  onChange={(event) => setData('to_wa_id', event.target.value)}
                  className="mt-3 h-10 w-full rounded-btn border border-gray-200 bg-white px-3 text-sm text-ui-text outline-none focus:border-ui-green focus:ring-2 focus:ring-ui-green/20 dark:border-ui-dark-border dark:bg-ui-dark-surface-2 dark:text-ui-dark-text"
                >
                  <option value="">Choose a chat</option>
                  {conversations.map((conversation) => (
                    <option key={conversation.id} value={conversation.contact.wa_id}>
                      {conversation.contact.name || conversation.contact.wa_id} (
                      {conversation.contact.wa_id})
                    </option>
                  ))}
                </select>
              )}
              {recipientType === 'contact' && (
                <div className="mt-3 space-y-2">
                  <TextInput
                    value={contactSearch}
                    onChange={(event) => setContactSearch(event.target.value)}
                    placeholder="Search contacts"
                    className="w-full"
                  />
                  <select
                    value={data.to_wa_id}
                    onChange={(event) => setData('to_wa_id', event.target.value)}
                    className="h-10 w-full rounded-btn border border-gray-200 bg-white px-3 text-sm text-ui-text outline-none focus:border-ui-green focus:ring-2 focus:ring-ui-green/20 dark:border-ui-dark-border dark:bg-ui-dark-surface-2 dark:text-ui-dark-text"
                  >
                    <option value="">Choose a contact</option>
                    {filteredContacts.map((contact) => (
                      <option key={contact.id} value={contact.wa_id}>
                        {contact.name || contact.wa_id} ({contact.wa_id})
                      </option>
                    ))}
                  </select>
                </div>
              )}
              {recipientType === 'manual' && (
                <TextInput
                  value={data.to_wa_id}
                  onChange={(event) => setData('to_wa_id', event.target.value)}
                  placeholder="Number with country code, e.g. +919988776655"
                  className="mt-3 w-full"
                />
              )}
              {errors.to_wa_id && (
                <p className="mt-2 text-xs text-red-600 dark:text-red-300">{errors.to_wa_id}</p>
              )}
            </div>
            {variableNumbers.length > 0 && (
              <div className="rounded-card border border-gray-100 bg-white p-4 dark:border-ui-dark-border dark:bg-ui-dark-surface">
                <p className="text-xs font-semibold uppercase tracking-wide text-ui-text-muted dark:text-ui-dark-text-muted">
                  Fill in the fields
                </p>
                <div className="mt-3 grid gap-3 sm:grid-cols-2">
                  {variableNumbers.map((variableNumber, index) => (
                    <TextInput
                      key={variableNumber}
                      value={data.variables[index] || ''}
                      onChange={(event) => {
                        const next = [...data.variables];
                        next[index] = event.target.value;
                        setData('variables', next);
                      }}
                      placeholder={`Field {{${variableNumber}}}`}
                      className="w-full"
                    />
                  ))}
                </div>
                {errors.variables && (
                  <p className="mt-2 text-xs text-red-600 dark:text-red-300">{errors.variables}</p>
                )}
              </div>
            )}
            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
              <Button type="button" variant="secondary" onClick={close}>
                Cancel
              </Button>
              <Button
                type="submit"
                disabled={processing || template.status.toLowerCase() !== 'approved'}
              >
                <Send className="h-4 w-4" />
                {processing ? 'Sending...' : 'Send'}
              </Button>
            </div>
          </div>
          <div className="space-y-3">
            <TemplatePhonePreview template={template} />
            <div className="rounded-card border border-gray-100 bg-gray-50 p-3 dark:border-ui-dark-border dark:bg-ui-dark-surface-2">
              <p className="text-xs font-semibold uppercase tracking-wide text-ui-text-muted dark:text-ui-dark-text-muted">
                What they will see
              </p>
              <p className="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-ui-text dark:text-ui-dark-text">
                {previewBody || 'No preview content.'}
              </p>
            </div>
          </div>
        </form>
      )}
    </Modal>
  );
}

export default function TemplatesIndex({
  templates,
  connections,
  filters,
  contacts,
  conversations,
  selected_template,
  sync_report,
  library_templates = [],
}: {
  account: any;
  templates: {
    data: Template[];
    links: any;
    meta: any;
  };
  connections: Array<{
    id: number;
    name: string;
    last_synced_at?: string | null;
    last_sync_error?: string | null;
  }>;
  filters: Filters;
  contacts: ContactOption[];
  conversations: ConversationOption[];
  selected_template?: Template | null;
  library_templates?: LibraryTemplate[];
  sync_report?: {
    total: number;
    created: number;
    updated: number;
    errors_count: number;
    errors?: Array<{ template: string; error: string }>;
  } | null;
}) {
  const { toast } = useToast();
  const confirm = useConfirm();
  const [localFilters, setLocalFilters] = useState<Filters>(filters);
  const [showFilters, setShowFilters] = useState(false);
  const [showSyncReport, setShowSyncReport] = useState(Boolean(sync_report));
  const [copied, setCopied] = useState<string | null>(null);
  const [archiving, setArchiving] = useState<string | null>(null);
  const [deleting, setDeleting] = useState<string | null>(null);
  const [builderTemplate, setBuilderTemplate] = useState<Template | null>(null);
  const [builderOpen, setBuilderOpen] = useState(false);
  const [libraryOpen, setLibraryOpen] = useState(false);
  const [librarySearch, setLibrarySearch] = useState('');
  const [viewTemplate, setViewTemplate] = useState<Template | null>(null);
  const [sendTemplate, setSendTemplate] = useState<Template | null>(null);
  const hasSyncReport = Boolean(
    sync_report ||
    connections.some((connection) => connection.last_synced_at || connection.last_sync_error)
  );

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const sendSlug = params.get('use_template');
    const templateSlug = params.get('template');
    const panel = params.get('panel');

    if (panel === 'create' && !builderOpen) {
      setBuilderTemplate(null);
      setBuilderOpen(true);
      return;
    }
    if (panel === 'library' && !libraryOpen) {
      setLibraryOpen(true);
    }

    const matchSlug = sendSlug || templateSlug;
    if (!matchSlug) return;

    const match =
      selected_template || templates.data.find((template) => template.slug === matchSlug);
    if (!match) return;

    if ((panel === 'send' || sendSlug) && !sendTemplate) {
      setSendTemplate(match);
    } else if (panel === 'edit' && !builderOpen) {
      setBuilderTemplate(match);
      setBuilderOpen(true);
    } else if (!viewTemplate) {
      setViewTemplate(match);
    }
  }, [selected_template, sendTemplate, templates.data, builderOpen, libraryOpen, viewTemplate]);

  const filteredLibraryTemplates = useMemo(() => {
    const search = librarySearch.trim().toLowerCase();
    if (!search) return library_templates;

    return library_templates.filter((template) =>
      [template.title, template.name, template.category, template.body_text, template.use_case]
        .join(' ')
        .toLowerCase()
        .includes(search)
    );
  }, [library_templates, librarySearch]);

  const applyLibraryTemplate = (preset: LibraryTemplate) => {
    setBuilderTemplate({
      id: 0,
      slug: `library-${preset.id}`,
      name: preset.name,
      language: preset.language,
      category: preset.category,
      status: 'draft',
      body_text: preset.body_text,
      header_type: preset.header_type || 'NONE',
      header_text: preset.header_text || null,
      header_media_url: null,
      footer_text: preset.footer_text || null,
      buttons: preset.buttons || [],
      has_buttons: Boolean(preset.buttons?.length),
      variable_count: variableNumbersFromText(preset.body_text).length,
      connection: { id: connections[0]?.id || 0, name: connections[0]?.name || `${brandName()} Business` },
      last_synced_at: null,
    });
    setLibraryOpen(false);
    setBuilderOpen(true);
  };

  const applyFilters = () => {
    router.get(route('app.whatsapp.templates.index', {}), localFilters as any, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const clearFilters = () => {
    const emptyFilters = {
      connection: '',
      status: '',
      category: '',
      language: '',
      search: '',
    };
    setLocalFilters(emptyFilters);
    router.get(route('app.whatsapp.templates.index', {}), emptyFilters);
  };

  const copyTemplateName = (name: string, language: string) => {
    const fullName = `${name}:${language}`;
    navigator.clipboard.writeText(fullName);
    setCopied(fullName);
    toast.success('Template name copied');
    setTimeout(() => setCopied(null), 2000);
  };

  const syncTemplates = () => {
    router.post(
      route('app.whatsapp.templates.sync', {}),
      {
        connection_id: localFilters.connection || connections[0]?.id,
      },
      {
        onSuccess: () => {
          toast.success('Templates are up to date');
          setShowSyncReport(true);
        },
        onError: () => {
          toast.error('Could not refresh templates');
        },
      }
    );
  };

  const setQuickFilter = (type: 'category' | 'status' | 'all', value = '') => {
    const nextFilters =
      type === 'all'
        ? { ...localFilters, category: '', status: '' }
        : { ...localFilters, [type]: value };
    setLocalFilters(nextFilters);
    router.get(route('app.whatsapp.templates.index', {}), nextFilters as any, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handleArchive = async (template: Template) => {
    const confirmed = await confirm({
      title: 'Archive template',
      message: `Archive "${template.name}"? You can bring it back later.`,
      variant: 'warning',
    });

    if (!confirmed) return;

    setArchiving(template.slug);
    router.post(
      route('app.whatsapp.templates.archive', {
        template: template.slug,
      }),
      {},
      {
        onSuccess: () => {
          toast.success('Template archived successfully');
          router.reload({ only: ['templates'] });
        },
        onError: () => {
          toast.error('Failed to archive template');
        },
        onFinish: () => setArchiving(null),
      }
    );
  };

  const handleDelete = async (template: Template) => {
    const confirmed = await confirm({
      title: 'Delete template',
      message: `Delete "${template.name}" for good? This cannot be undone.`,
      variant: 'danger',
      confirmText: 'Delete',
    });

    if (!confirmed) return;

    setDeleting(template.slug);
    router.delete(
      route('app.whatsapp.templates.destroy', {
        template: template.slug,
      }),
      {
        onSuccess: () => {
          toast.success('Template deleted successfully');
          router.reload({ only: ['templates'] });
        },
        onError: () => {
          toast.error('Failed to delete template');
        },
        onFinish: () => setDeleting(null),
      }
    );
  };

  const getStatusBadge = (status: string) => {
    const statusMap: Record<
      string,
      { variant: 'success' | 'warning' | 'danger' | 'default' | 'info'; label: string }
    > = {
      approved: { variant: 'success', label: 'Approved' },
      pending: { variant: 'warning', label: 'Waiting' },
      rejected: { variant: 'danger', label: 'Rejected' },
      paused: { variant: 'default', label: 'Paused' },
      disabled: { variant: 'default', label: 'Disabled' },
    };

    const config = statusMap[status.toLowerCase()] || {
      variant: 'default' as const,
      label: status,
    };
    return (
      <Badge variant={config.variant} className="px-3 py-1">
        {config.label}
      </Badge>
    );
  };

  return (
    <AppShell>
      <Head title="Templates" />
      <div className="module-page max-w-[1600px]">
        <PageHeader
          title="Templates"
          description="Approved messages you can send to anyone, anytime."
          actions={
            <>
              <Button
                type="button"
                variant="ghost"
                onClick={syncTemplates}
                title="Get the latest approval status"
              >
                <RefreshCw className="h-4 w-4" />
                Refresh
              </Button>
              <Button type="button" variant="secondary" onClick={() => setLibraryOpen(true)}>
                <Sparkles className="h-4 w-4" />
                Ideas
              </Button>
              <Button
                type="button"
                onClick={() => {
                  setBuilderTemplate(null);
                  setBuilderOpen(true);
                }}
              >
                <Plus className="h-4 w-4" />
                New template
              </Button>
            </>
          }
        />

        <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
          <div className="-mx-1 flex flex-1 gap-1 overflow-x-auto px-1">
            {[
              { id: 'all', label: 'All', type: 'all', value: '' },
              { id: 'approved', label: 'Approved', type: 'status', value: 'approved' },
              { id: 'pending', label: 'Waiting', type: 'status', value: 'pending' },
              { id: 'rejected', label: 'Rejected', type: 'status', value: 'rejected' },
              { id: 'marketing', label: 'Marketing', type: 'category', value: 'MARKETING' },
              { id: 'utility', label: 'Updates', type: 'category', value: 'UTILITY' },
              {
                id: 'authentication',
                label: 'Login codes',
                type: 'category',
                value: 'AUTHENTICATION',
              },
            ].map((filter) => {
              const active =
                filter.type === 'all'
                  ? !localFilters.category && !localFilters.status
                  : localFilters[filter.type as 'category' | 'status'] === filter.value;
              return (
                <button
                  key={filter.id}
                  type="button"
                  onClick={() =>
                    setQuickFilter(filter.type as 'category' | 'status' | 'all', filter.value)
                  }
                  className={`h-8 whitespace-nowrap rounded-full px-3 text-sm font-medium transition ${active ? 'bg-ui-green-soft text-ui-green-dark dark:bg-ui-dark-green-soft dark:text-emerald-200' : 'text-ui-text-muted hover:bg-gray-100 hover:text-ui-text dark:text-ui-dark-text-muted dark:hover:bg-ui-dark-surface-2 dark:hover:text-ui-dark-text'}`}
                >
                  {filter.label}
                </button>
              );
            })}
          </div>
          <div className="flex items-center gap-2">
            <div className="relative flex-1 lg:w-60">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ui-text-muted dark:text-ui-dark-text-muted" />
              <TextInput
                type="text"
                value={localFilters.search}
                onChange={(e) => setLocalFilters({ ...localFilters, search: e.target.value })}
                onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                className="h-9 w-full pl-10 text-sm"
                placeholder="Search templates"
              />
            </div>
            <IconButton
              size="sm"
              variant="outline"
              className="h-9 w-9"
              onClick={() => setShowFilters(!showFilters)}
              aria-label="More filters"
              title="More filters"
            >
              <Filter className="h-4 w-4" />
            </IconButton>
            {hasSyncReport && (
              <IconButton
                size="sm"
                variant="outline"
                className="h-9 w-9"
                onClick={() => setShowSyncReport(true)}
                aria-label="Last refresh details"
                title="Last refresh details"
              >
                <Eye className="h-4 w-4" />
              </IconButton>
            )}
          </div>
        </div>

        {showFilters && (
          <Card>
            <CardContent className="p-4">
              <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                {connections.length > 1 && (
                  <div>
                    <label className="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                      WhatsApp number
                    </label>
                    <select
                      value={localFilters.connection}
                      onChange={(e) =>
                        setLocalFilters({ ...localFilters, connection: e.target.value })
                      }
                      className="w-full rounded-btn border-gray-200 px-4 py-2.5 shadow-sm focus:border-ui-green focus:ring-ui-green/20 dark:border-gray-700 dark:bg-gray-800"
                    >
                      <option value="">All numbers</option>
                      {connections.map((conn) => (
                        <option key={conn.id} value={conn.id.toString()}>
                          {conn.name}
                        </option>
                      ))}
                    </select>
                  </div>
                )}
                <div>
                  <label className="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                    Status
                  </label>
                  <select
                    value={localFilters.status}
                    onChange={(e) => setLocalFilters({ ...localFilters, status: e.target.value })}
                    className="w-full rounded-btn border-gray-200 px-4 py-2.5 shadow-sm focus:border-ui-green focus:ring-ui-green/20 dark:border-gray-700 dark:bg-gray-800"
                  >
                    <option value="">Any status</option>
                    <option value="approved">Approved</option>
                    <option value="pending">Waiting for approval</option>
                    <option value="rejected">Rejected</option>
                    <option value="paused">Paused</option>
                    <option value="disabled">Disabled</option>
                  </select>
                </div>
                <div>
                  <label className="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">
                    Type
                  </label>
                  <select
                    value={localFilters.category}
                    onChange={(e) => setLocalFilters({ ...localFilters, category: e.target.value })}
                    className="w-full rounded-btn border-gray-200 px-4 py-2.5 shadow-sm focus:border-ui-green focus:ring-ui-green/20 dark:border-gray-700 dark:bg-gray-800"
                  >
                    <option value="">Any type</option>
                    <option value="MARKETING">Marketing</option>
                    <option value="UTILITY">Updates</option>
                    <option value="AUTHENTICATION">Login codes</option>
                  </select>
                </div>
              </div>
              <div className="mt-4 flex items-center gap-2">
                <Button onClick={applyFilters}>Apply</Button>
                <Button onClick={clearFilters} variant="ghost">
                  <X className="h-4 w-4" />
                  Clear
                </Button>
              </div>
            </CardContent>
          </Card>
        )}

        {hasSyncReport && (
          <Modal
            open={showSyncReport}
            onClose={() => setShowSyncReport(false)}
            title="Last refresh"
            className="max-w-2xl"
            footer={
              <Button type="button" variant="secondary" onClick={() => setShowSyncReport(false)}>
                Close
              </Button>
            }
          >
            <div className="space-y-4 text-sm">
              {sync_report ? (
                <div className="rounded-card border border-gray-100 bg-gray-50/80 p-4 dark:border-ui-dark-border dark:bg-ui-dark-surface-2/70">
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <p className="font-semibold text-ui-text dark:text-ui-dark-text">
                        Summary
                      </p>
                      <p className="mt-1 text-ui-text-muted dark:text-ui-dark-text-muted">
                        {sync_report.total} checked, {sync_report.created} new,{' '}
                        {sync_report.updated} updated, {sync_report.errors_count} problems.
                      </p>
                    </div>
                    <Badge variant={sync_report.errors_count > 0 ? 'warning' : 'success'}>
                      {sync_report.errors_count > 0 ? 'Check problems' : 'Up to date'}
                    </Badge>
                  </div>
                  {Array.isArray(sync_report.errors) && sync_report.errors.length > 0 && (
                    <div className="mt-3 rounded-card border border-red-200 bg-red-50 p-3 dark:border-red-500/30 dark:bg-red-500/10">
                      <p className="text-xs font-semibold uppercase tracking-wide text-red-700 dark:text-red-200">
                        Problems
                      </p>
                      <ul className="mt-2 space-y-1 text-xs text-red-700 dark:text-red-200">
                        {sync_report.errors.map((err, idx) => (
                          <li key={idx}>
                            {err.template}: {err.error}
                          </li>
                        ))}
                      </ul>
                    </div>
                  )}
                </div>
              ) : (
                <div className="rounded-card border border-gray-100 bg-gray-50/80 p-4 text-ui-text-muted dark:border-ui-dark-border dark:bg-ui-dark-surface-2/70 dark:text-ui-dark-text-muted">
                  Nothing to show yet.
                </div>
              )}

              <div className="grid gap-3 md:grid-cols-2">
                {connections.map((connection) => (
                  <div
                    key={connection.id}
                    className="rounded-card border border-gray-100 bg-white p-4 shadow-sm dark:border-ui-dark-border dark:bg-ui-dark-surface"
                  >
                    <div className="flex items-start justify-between gap-3">
                      <div className="min-w-0">
                        <p className="truncate font-semibold text-ui-text dark:text-ui-dark-text">
                          {connection.name}
                        </p>
                        <p className="mt-1 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                          Last refresh:{' '}
                          {connection.last_synced_at
                            ? new Date(connection.last_synced_at).toLocaleString()
                            : 'Never'}
                        </p>
                      </div>
                      <Badge
                        variant={
                          connection.last_sync_error
                            ? 'danger'
                            : connection.last_synced_at
                              ? 'success'
                              : 'secondary'
                        }
                      >
                        {connection.last_sync_error
                          ? 'Error'
                          : connection.last_synced_at
                            ? 'Up to date'
                            : 'Never'}
                      </Badge>
                    </div>
                    {connection.last_sync_error && (
                      <p className="mt-3 rounded-md bg-red-50 px-3 py-2 text-xs text-red-700 dark:bg-red-500/10 dark:text-red-200">
                        {connection.last_sync_error}
                      </p>
                    )}
                  </div>
                ))}
              </div>
            </div>
          </Modal>
        )}

        {/* Templates List */}
        {templates.data.length === 0 ? (
          <Card className="border-transparent dark:border-slate-700/80">
            <CardContent className="p-0">
              {localFilters.search ||
              localFilters.status ||
              localFilters.category ||
              localFilters.connection ? (
                <EmptyState
                  icon={Search}
                  title="No matches"
                  description="Try a different search or filter."
                  action={
                    <Button variant="secondary" onClick={clearFilters}>
                      Clear filters
                    </Button>
                  }
                />
              ) : (
                <EmptyState
                  icon={FileText}
                  title="No templates yet"
                  description="Write your own, or start from one of our ideas."
                  action={
                    <Button
                      onClick={() => {
                        setBuilderTemplate(null);
                        setBuilderOpen(true);
                      }}
                    >
                      <Plus className="h-4 w-4" />
                      New template
                    </Button>
                  }
                />
              )}
            </CardContent>
          </Card>
        ) : (
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {templates.data.map((template) => (
              <div
                key={template.id}
                className="surface group overflow-hidden rounded-card border border-transparent bg-white shadow-card transition-all hover:shadow-card-lg dark:border-slate-700/80 dark:bg-slate-800"
              >
                <div className="relative bg-gradient-to-br from-gray-50 to-gray-100 px-4 pb-3 pt-4 dark:from-slate-800 dark:to-slate-900">
                  <div className="mb-3 flex items-center justify-between gap-2">
                    <span
                      className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold ${categoryTone(template.category)}`}
                    >
                      <Tag className="h-3 w-3" />
                      {categoryLabel(template.category)}
                    </span>
                    {getStatusBadge(template.status)}
                  </div>
                  <div className="-mb-12 scale-[0.78]">
                    <TemplatePhonePreview template={template} />
                  </div>
                </div>
                <div className="border-t border-gray-100 px-4 py-4 dark:border-slate-700">
                  <div className="flex items-start gap-2">
                    <button
                      type="button"
                      onClick={() => setViewTemplate(template)}
                      className="min-w-0 flex-1 truncate text-left font-mono text-[12px] font-semibold text-ui-text hover:text-ui-green-dark dark:text-ui-dark-text dark:hover:text-ui-green"
                    >
                      {template.name}
                    </button>
                    <button
                      type="button"
                      onClick={() => copyTemplateName(template.name, template.language)}
                      className="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-ui-text dark:hover:bg-slate-700 dark:hover:text-ui-dark-text"
                      title="Copy name"
                      aria-label="Copy name"
                    >
                      {copied === `${template.name}:${template.language}` ? (
                        <Check className="h-4 w-4 text-green-600 dark:text-green-400" />
                      ) : (
                        <Copy className="h-4 w-4" />
                      )}
                    </button>
                  </div>
                  <div className="mt-1 flex items-center gap-2 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                    <Globe className="h-3.5 w-3.5" />
                    <span>{template.language}</span>
                    <span>·</span>
                    {template.has_buttons && (
                      <>
                        <span>·</span>
                        <span>Buttons</span>
                      </>
                    )}
                    {template.variable_count > 0 && (
                      <>
                        <span>·</span>
                        <span>
                          {template.variable_count} field{template.variable_count === 1 ? '' : 's'}
                        </span>
                      </>
                    )}
                  </div>
                  <p className="mt-2 line-clamp-2 min-h-[34px] text-xs leading-relaxed text-ui-text-muted dark:text-ui-dark-text-muted">
                    {template.body_text || 'No message text.'}
                  </p>
                  {template.status.toLowerCase() === 'rejected' && template.rejection_reason && (
                    <div className="mt-3 rounded-btn border border-red-100 bg-red-50 p-2 text-xs text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">
                      {template.rejection_reason}
                    </div>
                  )}
                  {template.stats && (
                    <p className="mt-3 text-[11px] text-ui-text-muted dark:text-ui-dark-text-muted">
                      {template.stats.sent} sent · {template.stats.read} read
                      {template.stats.failed > 0 ? ` · ${template.stats.failed} failed` : ''}
                    </p>
                  )}
                  <div className="mt-3 flex items-center gap-2">
                    <Button
                      type="button"
                      size="sm"
                      className="flex-1"
                      disabled={template.status.toLowerCase() !== 'approved'}
                      onClick={() => setSendTemplate(template)}
                    >
                      <Send className="h-3.5 w-3.5" />
                      Send
                    </Button>
                    <Button
                      type="button"
                      size="sm"
                      variant="secondary"
                      className="flex-1"
                      onClick={() => setViewTemplate(template)}
                    >
                      View
                    </Button>
                    <details className="relative">
                      <summary
                        aria-label="More actions"
                        className="flex h-8 w-8 cursor-pointer list-none items-center justify-center rounded-btn bg-gray-50 text-ui-text-muted transition hover:bg-gray-100 hover:text-ui-text dark:bg-slate-900 dark:text-ui-dark-text-muted dark:hover:bg-slate-700 dark:hover:text-ui-dark-text [&::-webkit-details-marker]:hidden"
                      >
                        <MoreVertical className="h-4 w-4" />
                      </summary>
                      <div
                        onClick={(event) =>
                          event.currentTarget.closest('details')?.removeAttribute('open')
                        }
                        className="absolute bottom-9 right-0 z-20 w-40 overflow-hidden rounded-card bg-white py-1 shadow-pop ring-1 ring-gray-100 dark:bg-slate-900 dark:ring-slate-700"
                      >
                        <button
                          type="button"
                          onClick={() => {
                            setBuilderTemplate(template);
                            setBuilderOpen(true);
                          }}
                          className="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-ui-text hover:bg-gray-50 dark:text-ui-dark-text dark:hover:bg-ui-dark-surface-2"
                        >
                          <Edit className="h-3.5 w-3.5" />
                          {template.status.toLowerCase() === 'rejected'
                            ? 'Edit & resubmit'
                            : 'Edit'}
                        </button>
                        <button
                          type="button"
                          onClick={() => handleArchive(template)}
                          disabled={archiving === template.slug}
                          className="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-orange-600 hover:bg-orange-50 disabled:opacity-50 dark:hover:bg-orange-500/10"
                        >
                          <Archive className="h-3.5 w-3.5" />
                          Archive
                        </button>
                        <button
                          type="button"
                          onClick={() => handleDelete(template)}
                          disabled={deleting === template.slug}
                          className="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-red-600 hover:bg-red-50 disabled:opacity-50 dark:hover:bg-red-500/10"
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                          Delete
                        </button>
                      </div>
                    </details>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        <Modal
          open={libraryOpen}
          onClose={() => setLibraryOpen(false)}
          title="Template ideas"
          description="Pick one, change what you like, and send it for approval."
          className="max-w-5xl"
        >
          <div className="space-y-4">
            <div className="relative">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ui-text-muted dark:text-ui-dark-text-muted" />
              <TextInput
                value={librarySearch}
                onChange={(event) => setLibrarySearch(event.target.value)}
                placeholder="Search ideas, e.g. order, offer, reminder"
                className="h-11 rounded-btn border-gray-200 pl-9 dark:border-ui-dark-border dark:bg-ui-dark-surface-2"
              />
            </div>
            <div className="grid max-h-[68vh] gap-3 overflow-y-auto pr-1 md:grid-cols-2">
              {filteredLibraryTemplates.map((preset) => (
                <Card key={preset.id} className="border-gray-200/80 dark:border-ui-dark-border">
                  <CardContent className="p-4">
                    <div className="flex items-start justify-between gap-3">
                      <div className="min-w-0">
                        <p className="truncate font-semibold text-ui-text dark:text-ui-dark-text">
                          {preset.title}
                        </p>
                        <p className="mt-1 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                          {preset.use_case}
                        </p>
                      </div>
                      <span
                        className={`inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ${categoryTone(preset.category)}`}
                      >
                        {categoryLabel(preset.category)}
                      </span>
                    </div>
                    <p className="mt-3 line-clamp-3 whitespace-pre-wrap text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
                      {preset.body_text}
                    </p>
                    <div className="mt-4 flex items-center justify-between gap-3">
                      <span className="text-xs font-medium text-ui-text-muted dark:text-ui-dark-text-muted">
                        {preset.language}
                      </span>
                      <Button type="button" size="sm" onClick={() => applyLibraryTemplate(preset)}>
                        <Plus className="h-3.5 w-3.5" />
                        Use this
                      </Button>
                    </div>
                  </CardContent>
                </Card>
              ))}
              {filteredLibraryTemplates.length === 0 && (
                <div className="rounded-card border border-dashed border-gray-200 p-8 text-center text-sm text-ui-text-muted dark:border-ui-dark-border dark:text-ui-dark-text-muted md:col-span-2">
                  No ideas match this search.
                </div>
              )}
            </div>
          </div>
        </Modal>

        <TemplateBuilderDrawer
          key={builderTemplate?.slug ?? 'new-template'}
          open={builderOpen}
          onClose={() => setBuilderOpen(false)}
          template={builderTemplate}
          connections={connections}
        />

        <Modal
          open={Boolean(viewTemplate)}
          onClose={() => setViewTemplate(null)}
          title={viewTemplate?.name ?? 'Template preview'}
          className="max-w-4xl"
          footer={
            <div className="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
              <Button type="button" variant="secondary" onClick={() => setViewTemplate(null)}>
                Close
              </Button>
              {viewTemplate && (
                <Button
                  type="button"
                  onClick={() => {
                    setSendTemplate(viewTemplate);
                    setViewTemplate(null);
                  }}
                  disabled={viewTemplate.status.toLowerCase() !== 'approved'}
                >
                  <Send className="h-4 w-4" />
                  Send
                </Button>
              )}
            </div>
          }
        >
          {viewTemplate && (
            <div className="grid gap-5 lg:grid-cols-[320px_1fr]">
              <TemplatePhonePreview template={viewTemplate} />
              <div className="space-y-4">
                <div className="grid grid-cols-2 gap-3">
                  <div className="rounded-card border border-gray-100 bg-gray-50 p-3 dark:border-ui-dark-border dark:bg-ui-dark-surface-2">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-ui-text-muted dark:text-ui-dark-text-muted">
                      Status
                    </p>
                    <div className="mt-2">{getStatusBadge(viewTemplate.status)}</div>
                  </div>
                  <div className="rounded-card border border-gray-100 bg-gray-50 p-3 dark:border-ui-dark-border dark:bg-ui-dark-surface-2">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-ui-text-muted dark:text-ui-dark-text-muted">
                      Language
                    </p>
                    <p className="mt-2 flex items-center gap-2 text-sm font-semibold text-ui-text dark:text-ui-dark-text">
                      <Globe className="h-4 w-4 text-ui-green" />
                      {viewTemplate.language}
                    </p>
                  </div>
                  <div className="rounded-card border border-gray-100 bg-gray-50 p-3 dark:border-ui-dark-border dark:bg-ui-dark-surface-2">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-ui-text-muted dark:text-ui-dark-text-muted">
                      Type
                    </p>
                    <span
                      className={`mt-2 inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold ${categoryTone(viewTemplate.category)}`}
                    >
                      <Tag className="h-3 w-3" />
                      {categoryLabel(viewTemplate.category)}
                    </span>
                  </div>
                  <div className="rounded-card border border-gray-100 bg-gray-50 p-3 dark:border-ui-dark-border dark:bg-ui-dark-surface-2">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-ui-text-muted dark:text-ui-dark-text-muted">
                      Fields
                    </p>
                    <p className="mt-2 text-sm font-semibold text-ui-text dark:text-ui-dark-text">
                      {viewTemplate.variable_count}
                    </p>
                  </div>
                </div>
                <div className="rounded-card border border-gray-100 bg-white p-4 dark:border-ui-dark-border dark:bg-ui-dark-surface">
                  <p className="text-[11px] font-semibold uppercase tracking-wide text-ui-text-muted dark:text-ui-dark-text-muted">
                    Message
                  </p>
                  <p className="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-ui-text dark:text-ui-dark-text">
                    {viewTemplate.body_text || 'No message text.'}
                  </p>
                </div>
                {viewTemplate.status.toLowerCase() === 'rejected' &&
                  viewTemplate.rejection_reason && (
                    <div className="rounded-card border border-red-100 bg-red-50 p-4 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-100">
                      <p className="text-[11px] font-semibold uppercase tracking-wide">
                        Why it was rejected
                      </p>
                      <p className="mt-2">{viewTemplate.rejection_reason}</p>
                    </div>
                  )}
                {viewTemplate.stats && (
                  <div className="grid grid-cols-4 gap-3">
                    {Object.entries(viewTemplate.stats).map(([label, value]) => (
                      <div
                        key={label}
                        className="rounded-card border border-gray-100 bg-gray-50 p-3 text-center dark:border-ui-dark-border dark:bg-ui-dark-surface-2"
                      >
                        <p className="text-lg font-bold text-ui-text dark:text-ui-dark-text">
                          {value}
                        </p>
                        <p className="text-[11px] uppercase tracking-wide text-ui-text-muted dark:text-ui-dark-text-muted">
                          {label}
                        </p>
                      </div>
                    ))}
                  </div>
                )}
                {viewTemplate.footer_text && (
                  <div className="rounded-card border border-gray-100 bg-white p-4 dark:border-ui-dark-border dark:bg-ui-dark-surface">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-ui-text-muted dark:text-ui-dark-text-muted">
                      Footer
                    </p>
                    <p className="mt-2 text-sm text-ui-text dark:text-ui-dark-text">
                      {viewTemplate.footer_text}
                    </p>
                  </div>
                )}
                {viewTemplate.buttons?.length > 0 && (
                  <div className="rounded-card border border-gray-100 bg-white p-4 dark:border-ui-dark-border dark:bg-ui-dark-surface">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-ui-text-muted dark:text-ui-dark-text-muted">
                      Buttons
                    </p>
                    <div className="mt-3 flex flex-wrap gap-2">
                      {viewTemplate.buttons.map((button, index) => (
                        <span
                          key={`${button.text}-${index}`}
                          className="inline-flex items-center gap-2 rounded-full border border-gray-200 px-3 py-1.5 text-xs font-semibold text-ui-text dark:border-ui-dark-border dark:text-ui-dark-text"
                        >
                          {button.type === 'URL' ? (
                            <ExternalLink className="h-3.5 w-3.5" />
                          ) : button.type === 'PHONE_NUMBER' ? (
                            <Phone className="h-3.5 w-3.5" />
                          ) : (
                            <Reply className="h-3.5 w-3.5" />
                          )}
                          {button.text}
                        </span>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            </div>
          )}
        </Modal>

        <SendTemplateModal
          template={sendTemplate}
          contacts={contacts}
          conversations={conversations}
          onClose={() => setSendTemplate(null)}
        />
      </div>
    </AppShell>
  );
}
