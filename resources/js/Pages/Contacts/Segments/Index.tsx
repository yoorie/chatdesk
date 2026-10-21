import TextInput from '@/Components/TextInput';
import { Badge } from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { Card, CardContent } from '@/Components/UI/Card';
import { Drawer, IconButton, PageHeader, ThemedIconTile } from '@/Components/UI/Elements';
import { EmptyState } from '@/Components/UI/EmptyState';
import { useConfirm } from '@/hooks/useConfirm';
import { useToast } from '@/hooks/useToast';
import AppShell from '@/Layouts/AppShell';
import { usePage, Head, Link, router, useForm } from '@inertiajs/react';
import { FolderOpen, Megaphone, Pencil, Plus, RefreshCw, Search, Trash2, X } from 'lucide-react';
import { useMemo, useState } from 'react';

interface Segment {
  id: number;
  name: string;
  description: string | null;
  contact_count: number;
  last_calculated_at: string | null;
  filters: Array<{ field: string; operator: string; value?: string }> | null;
  created_at: string;
}

function formatNumber(value: number | string | null | undefined) {
  const numeric = Number(value ?? 0);
  return Number.isFinite(numeric) ? new Intl.NumberFormat('en-IN').format(numeric) : '0';
}

function formatRelative(value: string | null) {
  if (!value) return 'Not calculated';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  const diffMs = Date.now() - date.getTime();
  const diffMinutes = Math.max(1, Math.round(diffMs / 60000));
  if (diffMinutes < 60) return `${diffMinutes}m ago`;
  const diffHours = Math.round(diffMinutes / 60);
  if (diffHours < 24) return `${diffHours}h ago`;
  const diffDays = Math.round(diffHours / 24);
  if (diffDays < 30) return `${diffDays}d ago`;
  return date.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

const FIELD_LABELS: Record<string, string> = {
  name: 'Name',
  wa_id: 'WhatsApp number',
  email: 'Email',
  phone: 'Phone',
  company: 'Company',
  status: 'Status',
  source: 'Source',
};

const OPERATOR_LABELS: Record<string, string> = {
  equals: 'is',
  not_equals: 'is not',
  contains: 'contains',
  not_contains: 'does not contain',
  starts_with: 'starts with',
  ends_with: 'ends with',
  greater_than: 'is more than',
  less_than: 'is less than',
  is_empty: 'is empty',
  is_not_empty: 'is not empty',
};

const NO_VALUE_OPERATORS = ['is_empty', 'is_not_empty'];

function ruleLabel(rule: { field: string; operator: string; value?: string }) {
  return [
    FIELD_LABELS[rule.field] ?? rule.field,
    OPERATOR_LABELS[rule.operator] ?? rule.operator,
    NO_VALUE_OPERATORS.includes(rule.operator) ? '' : rule.value,
  ]
    .filter(Boolean)
    .join(' ');
}

export default function SegmentsIndex({ segments }: { account: any; segments: Segment[] }) {
  const pageUrl = usePage().url;

  const { toast } = useToast();
  const confirm = useConfirm();
  const [search, setSearch] = useState('');
  const [busySegmentId, setBusySegmentId] = useState<number | null>(null);
  const [drawerMode, setDrawerMode] = useState<'create' | 'edit' | 'view' | null>(() => {
    const params = new URLSearchParams(pageUrl.split('?')[1] || '');
    if (params.get('create') === '1') return 'create';
    if (params.get('edit') === '1') return 'edit';
    if (params.get('segment')) return 'view';
    return null;
  });
  const [activeSegmentId, setActiveSegmentId] = useState<number | null>(() => {
    const id = Number(new URLSearchParams(pageUrl.split('?')[1] || '').get('segment'));
    return Number.isFinite(id) && id > 0 ? id : null;
  });
  const activeSegment = segments.find((segment) => segment.id === activeSegmentId) || null;
  const segmentForm = useForm({
    name: '',
    description: '',
    filters: [{ field: 'status', operator: 'equals', value: 'active' }],
  });

  const filteredSegments = useMemo(() => {
    const query = search.trim().toLowerCase();
    if (!query) return segments;
    return segments.filter((segment) => {
      return (
        segment.name.toLowerCase().includes(query) ||
        (segment.description || '').toLowerCase().includes(query) ||
        (segment.filters || []).some((filter) => ruleLabel(filter).toLowerCase().includes(query))
      );
    });
  }, [search, segments]);

  const handleDelete = async (segment: Segment) => {
    const confirmed = await confirm({
      title: 'Delete segment',
      message: `Delete "${segment.name}"? Your contacts are not deleted.`,
      confirmText: 'Delete segment',
      variant: 'danger',
    });
    if (!confirmed) return;
    setBusySegmentId(segment.id);
    router.delete(route('app.contacts.segments.destroy', { segment: segment.id }), {
      preserveScroll: true,
      onSuccess: () => toast.success('Segment deleted'),
      onError: () => toast.error('Failed to delete segment'),
      onFinish: () => setBusySegmentId(null),
    });
  };

  const handleRecalculate = (segmentId: number) => {
    setBusySegmentId(segmentId);
    router.post(
      route('app.contacts.segments.recalculate', { segment: segmentId }),
      {},
      {
        preserveScroll: true,
        onSuccess: () => toast.success('Count updated'),
        onError: () => toast.error('Could not update the count'),
        onFinish: () => setBusySegmentId(null),
      }
    );
  };

  const openCreate = () => {
    segmentForm.setData({
      name: '',
      description: '',
      filters: [{ field: 'status', operator: 'equals', value: 'active' }],
    });
    setActiveSegmentId(null);
    setDrawerMode('create');
  };

  const openSegment = (segment: Segment, mode: 'view' | 'edit') => {
    setActiveSegmentId(segment.id);
    if (mode === 'edit') {
      const normalizedFilters = (
        segment.filters?.length
          ? segment.filters
          : [{ field: 'status', operator: 'equals', value: 'active' }]
      ).map((filter) => ({
        field: filter.field,
        operator: filter.operator,
        value: filter.value || '',
      }));
      segmentForm.setData({
        name: segment.name,
        description: segment.description || '',
        filters: normalizedFilters,
      });
    }
    setDrawerMode(mode);
  };

  const closeDrawer = () => {
    setDrawerMode(null);
    setActiveSegmentId(null);
  };

  const saveSegment = () => {
    if (drawerMode === 'edit' && activeSegment) {
      segmentForm.put(route('app.contacts.segments.update', { segment: activeSegment.id }), {
        preserveScroll: true,
        onSuccess: closeDrawer,
        onError: () => toast.error('Failed to update segment'),
      });
      return;
    }

    segmentForm.post(route('app.contacts.segments.store', {}), {
      preserveScroll: true,
      onSuccess: closeDrawer,
      onError: () => toast.error('Failed to create segment'),
    });
  };

  return (
    <AppShell>
      <Head title="Segments" />
      <div className="module-page max-w-[1600px]">
        <PageHeader
          title="Segments"
          description="Groups of contacts that update by themselves. Use them in campaigns."
          actions={
            <Button onClick={openCreate}>
              <Plus className="h-4 w-4" />
              New segment
            </Button>
          }
        />

        {segments.length > 0 && (
          <div className="relative max-w-sm">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ui-text-muted dark:text-ui-dark-text-muted" />
            <TextInput
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search segments"
              className="h-9 w-full rounded-btn border-gray-200 pl-10 text-sm focus:border-ui-green focus:ring-ui-green/20 dark:border-ui-dark-border dark:bg-ui-dark-surface-2"
            />
          </div>
        )}

        {filteredSegments.length === 0 ? (
          <Card className="border-transparent dark:border-slate-700/80">
            <CardContent className="p-0">
              {segments.length > 0 ? (
                <EmptyState
                  icon={Search}
                  title="No matches"
                  description="Try a different search."
                />
              ) : (
                <EmptyState
                  icon={FolderOpen}
                  title="No segments yet"
                  description="Group contacts by rules, like everyone from one city."
                  action={
                    <Button onClick={openCreate}>
                      <Plus className="h-4 w-4" />
                      New segment
                    </Button>
                  }
                />
              )}
            </CardContent>
          </Card>
        ) : (
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            {filteredSegments.map((segment) => {
              const rules = segment.filters || [];

              return (
                <Card
                  key={segment.id}
                  className="group border-transparent transition-shadow hover:shadow-card-lg dark:border-slate-700/80"
                >
                  <CardContent className="p-4">
                    <div className="mb-3 flex items-start justify-between gap-3">
                      <div className="flex min-w-0 items-center gap-3">
                        <ThemedIconTile tone="green">
                          <FolderOpen className="h-5 w-5" />
                        </ThemedIconTile>
                        <div className="min-w-0">
                          <button
                            type="button"
                            onClick={() => openSegment(segment, 'view')}
                            className="font-semibold text-ui-text hover:text-ui-green-dark dark:text-ui-dark-text dark:hover:text-emerald-300"
                          >
                            {segment.name}
                          </button>
                          <p className="mt-0.5 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                            {formatNumber(segment.contact_count)} contacts · updated{' '}
                            {formatRelative(segment.last_calculated_at || segment.created_at)}
                          </p>
                        </div>
                      </div>
                    </div>

                    {segment.description && (
                      <p className="mb-3 line-clamp-2 text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
                        {segment.description}
                      </p>
                    )}

                    <div className="mb-4 flex min-h-7 flex-wrap gap-1.5">
                      {rules.length > 0 ? (
                        rules.slice(0, 4).map((rule, ruleIndex) => (
                          <Badge
                            key={`${segment.id}-${ruleIndex}`}
                            variant="secondary"
                            className="rounded-md"
                          >
                            {ruleLabel(rule)}
                          </Badge>
                        ))
                      ) : (
                        <Badge variant="outline" className="rounded-md">
                          No rules
                        </Badge>
                      )}
                      {rules.length > 4 && (
                        <Badge variant="outline" className="rounded-md">
                          +{rules.length - 4} more
                        </Badge>
                      )}
                    </div>

                    <div className="flex items-center gap-2 border-t border-gray-100 pt-3 dark:border-ui-dark-border">
                      <Link
                        href={route('app.broadcasts.index', { panel: 'create' })}
                        className="flex-1"
                      >
                        <Button size="sm" className="w-full">
                          <Megaphone className="h-3.5 w-3.5" />
                          Send campaign
                        </Button>
                      </Link>
                      <IconButton
                        size="sm"
                        variant="outline"
                        onClick={() => openSegment(segment, 'edit')}
                        aria-label={`Edit ${segment.name}`}
                        title="Edit"
                      >
                        <Pencil className="h-4 w-4" />
                      </IconButton>
                      <IconButton
                        size="sm"
                        variant="outline"
                        disabled={busySegmentId === segment.id}
                        onClick={() => handleRecalculate(segment.id)}
                        aria-label={`Refresh count for ${segment.name}`}
                        title="Refresh count"
                      >
                        {busySegmentId === segment.id ? (
                          <RefreshCw className="h-4 w-4 animate-spin" />
                        ) : (
                          <RefreshCw className="h-4 w-4" />
                        )}
                      </IconButton>
                      <IconButton
                        size="sm"
                        variant="danger"
                        disabled={busySegmentId === segment.id}
                        onClick={() => handleDelete(segment)}
                        aria-label={`Delete ${segment.name}`}
                        title="Delete"
                      >
                        <Trash2 className="h-4 w-4" />
                      </IconButton>
                    </div>
                  </CardContent>
                </Card>
              );
            })}
          </div>
        )}
        <Drawer
          open={drawerMode !== null}
          onClose={closeDrawer}
          title={
            drawerMode === 'create'
              ? 'New segment'
              : drawerMode === 'edit'
                ? 'Edit segment'
                : activeSegment?.name || 'Segment details'
          }
          description={
            drawerMode === 'view' ? undefined : 'Contacts that match all rules join this segment.'
          }
          className="max-w-xl"
          footer={
            drawerMode === 'view' ? (
              <div className="flex justify-end gap-2">
                {activeSegment && (
                  <Button variant="secondary" onClick={() => openSegment(activeSegment, 'edit')}>
                    <Pencil className="h-4 w-4" /> Edit
                  </Button>
                )}
                <Button onClick={closeDrawer}>Done</Button>
              </div>
            ) : (
              <div className="flex justify-end gap-2">
                <Button variant="secondary" onClick={closeDrawer}>
                  Cancel
                </Button>
                <Button onClick={saveSegment} disabled={segmentForm.processing}>
                  {drawerMode === 'edit' ? 'Save' : 'Create segment'}
                </Button>
              </div>
            )
          }
        >
          {drawerMode === 'view' && activeSegment ? (
            <div className="space-y-4">
              <div className="rounded-card border border-gray-100 p-4 dark:border-ui-dark-border">
                <p className="text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
                  {activeSegment.description || 'No description'}
                </p>
                <div className="mt-3 flex flex-wrap gap-2">
                  <Badge variant="secondary">
                    {formatNumber(activeSegment.contact_count)} contacts
                  </Badge>
                  <Badge variant="secondary">
                    Updated{' '}
                    {formatRelative(activeSegment.last_calculated_at || activeSegment.created_at)}
                  </Badge>
                </div>
              </div>
              <div className="space-y-2">
                {(activeSegment.filters || []).map((filter, index) => (
                  <div
                    key={index}
                    className="rounded-btn bg-gray-50 p-3 text-sm text-ui-text dark:bg-ui-dark-surface-2 dark:text-ui-dark-text"
                  >
                    {ruleLabel(filter)}
                  </div>
                ))}
                {(activeSegment.filters || []).length === 0 && (
                  <Badge variant="outline">No rules</Badge>
                )}
              </div>
            </div>
          ) : (
            <div className="space-y-4">
              <div>
                <label className="mb-1.5 block text-sm font-medium text-ui-text dark:text-ui-dark-text">
                  Name
                </label>
                <TextInput
                  value={segmentForm.data.name}
                  onChange={(e) => segmentForm.setData('name', e.target.value)}
                  className="w-full"
                />
              </div>
              <div>
                <label className="mb-1.5 block text-sm font-medium text-ui-text dark:text-ui-dark-text">
                  Description <span className="font-normal text-ui-text-muted">(optional)</span>
                </label>
                <TextInput
                  value={segmentForm.data.description}
                  onChange={(e) => segmentForm.setData('description', e.target.value)}
                  className="w-full"
                />
              </div>
              <div className="rounded-card border border-gray-100 p-4 dark:border-ui-dark-border">
                <p className="mb-3 text-sm font-semibold text-ui-text dark:text-ui-dark-text">
                  Rules
                </p>
                <div className="space-y-2">
                  {segmentForm.data.filters.map((filter, index) => (
                    <div key={index} className="grid gap-2 sm:grid-cols-[1fr_1fr_1fr_auto]">
                      <select
                        value={filter.field}
                        onChange={(e) => {
                          const filters = [...segmentForm.data.filters];
                          filters[index] = { ...filter, field: e.target.value };
                          segmentForm.setData('filters', filters);
                        }}
                        className="h-10 rounded-btn border-gray-200 bg-white px-3 text-sm dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text"
                      >
                        {['name', 'wa_id', 'email', 'phone', 'company', 'status', 'source'].map(
                          (field) => (
                            <option key={field} value={field}>
                              {FIELD_LABELS[field] ?? field}
                            </option>
                          )
                        )}
                      </select>
                      <select
                        value={filter.operator}
                        onChange={(e) => {
                          const filters = [...segmentForm.data.filters];
                          filters[index] = { ...filter, operator: e.target.value };
                          segmentForm.setData('filters', filters);
                        }}
                        className="h-10 rounded-btn border-gray-200 bg-white px-3 text-sm dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text"
                      >
                        {[
                          'equals',
                          'not_equals',
                          'contains',
                          'not_contains',
                          'starts_with',
                          'ends_with',
                          'is_empty',
                          'is_not_empty',
                        ].map((operator) => (
                          <option key={operator} value={operator}>
                            {OPERATOR_LABELS[operator] ?? operator}
                          </option>
                        ))}
                      </select>
                      <TextInput
                        value={filter.value || ''}
                        onChange={(e) => {
                          const filters = [...segmentForm.data.filters];
                          filters[index] = { ...filter, value: e.target.value };
                          segmentForm.setData('filters', filters);
                        }}
                        placeholder="Value"
                        disabled={NO_VALUE_OPERATORS.includes(filter.operator)}
                      />
                      <IconButton
                        size="sm"
                        variant="ghost"
                        className="self-center"
                        disabled={segmentForm.data.filters.length <= 1}
                        onClick={() =>
                          segmentForm.setData(
                            'filters',
                            segmentForm.data.filters.filter((_, i) => i !== index)
                          )
                        }
                        aria-label="Remove rule"
                      >
                        <X className="h-4 w-4" />
                      </IconButton>
                    </div>
                  ))}
                </div>
                <Button
                  type="button"
                  variant="link"
                  size="sm"
                  className="mt-2"
                  onClick={() =>
                    segmentForm.setData('filters', [
                      ...segmentForm.data.filters,
                      { field: 'name', operator: 'contains', value: '' },
                    ])
                  }
                >
                  <Plus className="h-3.5 w-3.5" />
                  Add rule
                </Button>
              </div>
            </div>
          )}
        </Drawer>
      </div>
    </AppShell>
  );
}
