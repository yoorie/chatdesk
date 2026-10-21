import TextInput from '@/Components/TextInput';
import Button from '@/Components/UI/Button';
import { Card, CardContent } from '@/Components/UI/Card';
import {
  IconButton,
  Modal,
  PageHeader,
  StatusBadge,
  ThemedIconTile,
} from '@/Components/UI/Elements';
import { EmptyState } from '@/Components/UI/EmptyState';
import { useConfirm } from '@/hooks/useConfirm';
import { useToast } from '@/hooks/useToast';
import AppShell from '@/Layouts/AppShell';
import { Head, router, useForm } from '@inertiajs/react';
import { Copy, Edit3, MessageSquareText, Plus, Power, Search, Trash2, Zap } from 'lucide-react';
import { useMemo, useState } from 'react';

type QuickReply = {
  id: number;
  type?: 'reply' | 'button';
  label: string;
  shortcut: string;
  message: string;
  is_active: boolean;
  usage_count: number;
  last_used_at: string | null;
  updated_at: string | null;
};

function shortcutFromLabel(label: string) {
  const shortcut = label
    .toLowerCase()
    .replace(/[^a-z0-9\s_-]/g, '')
    .replace(/[\s-]+/g, '_')
    .replace(/^_+|_+$/g, '');

  return shortcut || 'reply';
}

function formatNumber(value: number | string | null | undefined) {
  const numeric = Number(value ?? 0);
  return Number.isFinite(numeric) ? new Intl.NumberFormat('en-IN').format(numeric) : '0';
}

export default function QuickRepliesIndex({
  quickReplies,
  filters,
}: {
  quickReplies: QuickReply[];
  filters: { search?: string; type?: 'all' | 'reply' | 'button' };
  stats: {
    total: number;
    active: number;
    buttons: number;
    replies: number;
    variables: number;
    uses: number;
  };
}) {
  const { toast } = useToast();
  const confirm = useConfirm();
  const [search, setSearch] = useState(filters.search || '');
  const [typeFilter, setTypeFilter] = useState(filters.type || 'all');
  const [editingReply, setEditingReply] = useState<QuickReply | null>(null);
  const [modalOpen, setModalOpen] = useState(false);
  const { data, setData, processing, errors, reset } = useForm({
    type: 'reply' as 'reply' | 'button',
    label: '',
    shortcut: '',
    message: 'Hi {{name}}, ',
    is_active: true,
  });

  const openCreate = (type: 'reply' | 'button' = 'reply') => {
    reset();
    setEditingReply(null);
    setData({
      type,
      label: '',
      shortcut: '',
      message: type === 'button' ? '' : 'Hi {{name}}, ',
      is_active: true,
    });
    setModalOpen(true);
  };

  const openEdit = (reply: QuickReply) => {
    setEditingReply(reply);
    setData({
      type: reply.type || 'reply',
      label: reply.label,
      shortcut: reply.shortcut,
      message: reply.message,
      is_active: reply.is_active,
    });
    setModalOpen(true);
  };

  const submit = () => {
    const payload = {
      ...data,
      type: data.type,
      label: data.label.trim(),
      shortcut: (data.shortcut || shortcutFromLabel(data.label)).trim(),
      message: data.type === 'button' ? data.label.trim() : data.message.trim(),
    };

    const options = {
      preserveScroll: true,
      onSuccess: () => {
        toast.success(editingReply ? 'Quick reply updated' : 'Quick reply created');
        setModalOpen(false);
      },
      onError: () =>
        toast.error(editingReply ? 'Failed to update quick reply' : 'Failed to create quick reply'),
    };

    if (editingReply) {
      router.patch(
        route('app.quick-replies.update', { quickReply: editingReply.id }),
        payload,
        options
      );
      return;
    }

    router.post(route('app.quick-replies.store', {}), payload, options);
  };

  const toggleReply = (reply: QuickReply) => {
    router.post(
      route('app.quick-replies.toggle', { quickReply: reply.id }),
      {},
      {
        preserveScroll: true,
        onSuccess: () =>
          toast.success(reply.is_active ? 'Quick reply disabled' : 'Quick reply enabled'),
        onError: () => toast.error('Failed to update quick reply'),
      }
    );
  };

  const deleteReply = async (reply: QuickReply) => {
    const confirmed = await confirm({
      title: 'Delete quick reply?',
      message: `Agents will no longer see "/${reply.shortcut}" in the inbox.`,
      confirmText: 'Delete',
      cancelText: 'Cancel',
    });
    if (!confirmed) return;

    router.delete(route('app.quick-replies.destroy', { quickReply: reply.id }), {
      preserveScroll: true,
      onSuccess: () => toast.success('Quick reply deleted'),
      onError: () => toast.error('Failed to delete quick reply'),
    });
  };

  const applySearch = () => {
    router.get(
      route('app.quick-replies.index', {}),
      { search, type: typeFilter },
      {
        preserveState: true,
        preserveScroll: true,
      }
    );
  };

  const applyType = (type: 'all' | 'reply' | 'button') => {
    setTypeFilter(type);
    router.get(
      route('app.quick-replies.index', {}),
      { search, type },
      {
        preserveState: true,
        preserveScroll: true,
      }
    );
  };

  const firstError = useMemo(() => Object.values(errors)[0], [errors]);

  return (
    <AppShell>
      <Head title="Quick replies" />
      <div className="module-page max-w-[1200px]">
        <PageHeader
          title="Quick replies"
          description={'Saved answers you can drop into any chat from the inbox.'}
          actions={
            <>
              <Button type="button" variant="secondary" onClick={() => openCreate('button')}>
                <Zap className="h-4 w-4" />
                New button
              </Button>
              <Button type="button" onClick={() => openCreate('reply')}>
                <Plus className="h-4 w-4" />
                New quick reply
              </Button>
            </>
          }
        />

        <Card className="border-transparent dark:border-slate-700/80">
          <CardContent className="flex flex-col gap-3 p-3 sm:flex-row sm:items-center">
            <div className="flex shrink-0 gap-1 rounded-btn bg-gray-100 p-1 dark:bg-slate-800">
              {[
                ['all', 'All'],
                ['reply', 'Replies'],
                ['button', 'Buttons'],
              ].map(([type, label]) => (
                <button
                  key={type}
                  type="button"
                  onClick={() => applyType(type as 'all' | 'reply' | 'button')}
                  className={`h-8 rounded-md px-3 text-xs font-semibold transition ${typeFilter === type ? 'bg-white text-ui-green-dark shadow-sm dark:bg-slate-950 dark:text-ui-green' : 'text-ui-text-muted hover:text-ui-text dark:text-ui-dark-text-muted dark:hover:text-ui-dark-text'}`}
                >
                  {label}
                </button>
              ))}
            </div>
            <div className="relative min-w-0 flex-1">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ui-text-muted" />
              <TextInput
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                onKeyDown={(event) => event.key === 'Enter' && applySearch()}
                placeholder="Search (press Enter)"
                className="h-9 w-full pl-10 text-sm"
              />
            </div>
          </CardContent>
        </Card>

        {quickReplies.length === 0 ? (
          <Card className="border-transparent dark:border-slate-700/80">
            <CardContent className="p-0">
              <EmptyState
                icon={Zap}
                title={typeFilter === 'button' ? 'No saved buttons yet' : 'No quick replies yet'}
                description={
                  typeFilter === 'button'
                    ? 'Save button labels to send with your messages.'
                    : 'Save answers you send often, like opening hours.'
                }
                action={
                  <Button
                    type="button"
                    onClick={() => openCreate(typeFilter === 'button' ? 'button' : 'reply')}
                  >
                    <Plus className="h-4 w-4" />
                    {typeFilter === 'button' ? 'New button' : 'New quick reply'}
                  </Button>
                }
              />
            </CardContent>
          </Card>
        ) : (
          <div className="space-y-2">
            {quickReplies.map((reply) => (
              <Card key={reply.id} className="border-transparent dark:border-slate-700/80">
                <CardContent className="flex flex-col gap-3 p-4 md:flex-row md:items-start md:justify-between">
                  <div className="flex min-w-0 flex-1 gap-3">
                    <ThemedIconTile
                      size="sm"
                      tone={reply.is_active ? (reply.type === 'button' ? 'blue' : 'green') : 'gray'}
                    >
                      {reply.type === 'button' ? (
                        <Zap className="h-4 w-4" />
                      ) : (
                        <MessageSquareText className="h-4 w-4" />
                      )}
                    </ThemedIconTile>
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <h2 className="text-sm font-semibold text-ui-text dark:text-ui-dark-text">
                          {reply.label}
                        </h2>
                        <StatusBadge tone={reply.type === 'button' ? 'info' : 'muted'}>
                          {reply.type === 'button' ? 'Button' : 'Reply'}
                        </StatusBadge>
                        <StatusBadge tone={reply.is_active ? 'success' : 'muted'} dot>
                          {reply.is_active ? 'On' : 'Off'}
                        </StatusBadge>
                        {reply.type !== 'button' && (
                          <span className="rounded-full bg-gray-100 px-2 py-0.5 font-mono text-[11px] font-semibold text-ui-text-muted dark:bg-ui-dark-surface-2 dark:text-ui-dark-text-muted">
                            /{reply.shortcut}
                          </span>
                        )}
                      </div>
                      {reply.type !== 'button' && (
                        <p className="mt-1 line-clamp-3 whitespace-pre-wrap text-sm leading-6 text-ui-text-muted dark:text-ui-dark-text-muted">
                          {reply.message}
                        </p>
                      )}
                      <p className="mt-1 text-[11px] text-ui-text-muted dark:text-ui-dark-text-muted">
                        Used {formatNumber(reply.usage_count)} times
                      </p>
                    </div>
                  </div>
                  <div className="flex shrink-0 items-center gap-1 self-end md:self-start">
                    <IconButton
                      size="sm"
                      aria-label="Copy"
                      title="Copy"
                      onClick={() => {
                        navigator.clipboard?.writeText(reply.message);
                        toast.success('Copied');
                      }}
                    >
                      <Copy className="h-4 w-4" />
                    </IconButton>
                    <IconButton
                      size="sm"
                      aria-label="Edit"
                      title="Edit"
                      variant="ghost"
                      onClick={() => openEdit(reply)}
                    >
                      <Edit3 className="h-4 w-4" />
                    </IconButton>
                    <IconButton
                      size="sm"
                      aria-label={reply.is_active ? 'Turn off' : 'Turn on'}
                      title={reply.is_active ? 'Turn off' : 'Turn on'}
                      variant="ghost"
                      onClick={() => toggleReply(reply)}
                    >
                      <Power className="h-4 w-4" />
                    </IconButton>
                    <IconButton
                      size="sm"
                      aria-label="Delete"
                      title="Delete"
                      variant="danger"
                      onClick={() => deleteReply(reply)}
                    >
                      <Trash2 className="h-4 w-4" />
                    </IconButton>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        )}

        <Modal
          open={modalOpen}
          onClose={() => setModalOpen(false)}
          title={
            editingReply
              ? data.type === 'button'
                ? 'Edit saved button'
                : 'Edit quick reply'
              : data.type === 'button'
                ? 'New saved button'
                : 'New quick reply'
          }
          description={
            data.type === 'button'
              ? 'Up to 3 buttons can go with one message.'
              : 'Short and clear works best.'
          }
          className="max-w-xl"
          footer={
            <div className="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
              <Button type="button" variant="secondary" onClick={() => setModalOpen(false)}>
                Cancel
              </Button>
              <Button type="button" onClick={submit} disabled={processing}>
                Save
              </Button>
            </div>
          }
        >
          <div className="space-y-4">
            {firstError && (
              <div className="rounded-card border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">
                {firstError}
              </div>
            )}
            {!editingReply && (
              <div className="grid grid-cols-2 gap-2 rounded-card bg-gray-100 p-1 dark:bg-slate-800">
                {[
                  ['reply', 'Quick reply', MessageSquareText],
                  ['button', 'Button', Zap],
                ].map(([type, label, Icon]) => (
                  <button
                    key={type as string}
                    type="button"
                    onClick={() =>
                      setData({
                        ...data,
                        type: type as 'reply' | 'button',
                        message: type === 'button' ? data.label : data.message || 'Hi {{name}}, ',
                      })
                    }
                    className={`inline-flex h-9 items-center justify-center gap-2 rounded-md text-xs font-semibold transition ${data.type === type ? 'bg-white text-ui-green-dark shadow-sm dark:bg-slate-950 dark:text-ui-green' : 'text-ui-text-muted hover:text-ui-text dark:text-ui-dark-text-muted dark:hover:text-ui-dark-text'}`}
                  >
                    <Icon className="h-4 w-4" />
                    {label as string}
                  </button>
                ))}
              </div>
            )}
            <div className="grid gap-3 sm:grid-cols-2">
              <div>
                <label className="mb-1 block text-xs font-medium text-ui-text dark:text-ui-dark-text">
                  {data.type === 'button' ? 'Button text' : 'Label'}
                </label>
                <TextInput
                  value={data.label}
                  onChange={(event) => {
                    const value =
                      data.type === 'button' ? event.target.value.slice(0, 20) : event.target.value;
                    setData('label', value);
                    if (!editingReply && !data.shortcut) {
                      setData('shortcut', shortcutFromLabel(value));
                    }
                  }}
                  placeholder={data.type === 'button' ? 'Talk to sales' : 'Order status'}
                />
                {data.type === 'button' && (
                  <p className="mt-1 text-[11px] text-ui-text-muted dark:text-ui-dark-text-muted">
                    {data.label.length}/20 characters
                  </p>
                )}
              </div>
              <div className={data.type === 'button' ? 'hidden' : ''}>
                <label className="mb-1 block text-xs font-medium text-ui-text dark:text-ui-dark-text">
                  Shortcut
                </label>
                <TextInput
                  value={data.shortcut}
                  onChange={(event) =>
                    setData(
                      'shortcut',
                      event.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '_')
                    )
                  }
                  placeholder="order_status"
                />
              </div>
            </div>
            {data.type !== 'button' && (
              <div>
                <label className="mb-1 block text-xs font-medium text-ui-text dark:text-ui-dark-text">
                  Message
                </label>
                <textarea
                  rows={7}
                  value={data.message}
                  onChange={(event) => setData('message', event.target.value)}
                  className="w-full resize-none rounded-btn border border-gray-200 bg-white p-3 text-sm text-ui-text outline-none focus:border-ui-green focus:ring-2 focus:ring-ui-green/15 dark:border-ui-dark-border dark:bg-ui-dark-surface-2 dark:text-ui-dark-text"
                  placeholder="Hi {{name}}, your order is being checked now."
                />
                <p className="mt-1 text-[11px] text-ui-text-muted dark:text-ui-dark-text-muted">
                  You can use {'{{name}}'} to add the customer's name.
                </p>
              </div>
            )}
            <label className="flex items-center gap-3 rounded-card border border-gray-100 p-3 dark:border-ui-dark-border">
              <input
                type="checkbox"
                checked={data.is_active}
                onChange={(event) => setData('is_active', event.target.checked)}
                className="rounded border-gray-300 text-ui-green focus:ring-ui-green"
              />
              <span className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
                {data.type === 'button' ? 'Show this button in chats' : 'Show this reply in chats'}
              </span>
            </label>
          </div>
        </Modal>
      </div>
    </AppShell>
  );
}
