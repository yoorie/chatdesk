import TextInput from '@/Components/TextInput';
import { Badge } from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { Card, CardContent } from '@/Components/UI/Card';
import { Drawer, IconButton, PageHeader } from '@/Components/UI/Elements';
import { EmptyState } from '@/Components/UI/EmptyState';
import { useConfirm } from '@/hooks/useConfirm';
import { useToast } from '@/hooks/useToast';
import AppShell from '@/Layouts/AppShell';
import { usePage, Head, router, useForm } from '@inertiajs/react';
import { Edit, Eye, List, Plus, ToggleLeft, ToggleRight, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface ListItem {
  id: number;
  name: string;
  button_text: string;
  description: string | null;
  footer_text: string | null;
  sections: Array<{
    title: string;
    rows: Array<{ id: string; title: string; description?: string | null }>;
  }>;
  sections_count: number;
  total_rows: number;
  is_active: boolean;
  connection: {
    id: number;
    name: string;
  };
  created_at: string;
}

function formatNumber(value: number | string | null | undefined) {
  const numeric = Number(value ?? 0);
  return Number.isFinite(numeric) ? new Intl.NumberFormat('en-IN').format(numeric) : '0';
}

function formatDate(value: string | null | undefined) {
  if (!value) return 'Never';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

export default function ListsIndex({
  lists,
  connections,
}: {
  account: any;
  lists: ListItem[];
  connections: Array<{ id: number; name: string }>;
}) {
  const pageUrl = usePage().url;

  const { toast } = useToast();
  const confirm = useConfirm();
  const [toggling, setToggling] = useState<number | null>(null);
  const [deleting, setDeleting] = useState<number | null>(null);
  const [drawerMode, setDrawerMode] = useState<'create' | 'edit' | 'view' | null>(() => {
    const params = new URLSearchParams(pageUrl.split('?')[1] || '');
    if (params.get('create') === '1') return 'create';
    if (params.get('edit') === '1') return 'edit';
    if (params.get('list')) return 'view';
    return null;
  });
  const [activeListId, setActiveListId] = useState<number | null>(() => {
    const value = Number(new URLSearchParams(pageUrl.split('?')[1] || '').get('list'));
    return Number.isFinite(value) && value > 0 ? value : null;
  });
  const activeList = lists.find((list) => list.id === activeListId) || null;
  const defaultConnectionId = connections[0]?.id ?? '';
  const listForm = useForm({
    whatsapp_connection_id: defaultConnectionId,
    name: '',
    button_text: 'View options',
    description: '',
    footer_text: '',
    sections: [
      { title: 'Options', rows: [{ id: 'option_1', title: 'Option 1', description: '' }] },
    ],
  });

  const handleToggle = async (list: ListItem) => {
    setToggling(list.id);
    try {
      await router.post(
        route('app.whatsapp.lists.toggle', { list: list.id }),
        {},
        {
          preserveScroll: true,
          onSuccess: () => {
            toast.success(`List ${list.is_active ? 'deactivated' : 'activated'}`);
          },
          onError: () => {
            toast.error('Failed to update list status');
          },
          onFinish: () => setToggling(null),
        }
      );
    } catch (error) {
      setToggling(null);
    }
  };

  const handleDelete = async (list: ListItem) => {
    const confirmed = await confirm({
      title: 'Delete List',
      message: `Are you sure you want to delete "${list.name}"? This action cannot be undone.`,
      confirmText: 'Delete',
      cancelText: 'Cancel',
    });

    if (!confirmed) return;

    setDeleting(list.id);
    try {
      await router.delete(route('app.whatsapp.lists.destroy', { list: list.id }), {
        preserveScroll: true,
        onSuccess: () => {
          toast.success('List deleted successfully');
        },
        onError: () => {
          toast.error('Failed to delete list');
        },
        onFinish: () => setDeleting(null),
      });
    } catch (error) {
      setDeleting(null);
    }
  };

  const openCreate = () => {
    listForm.reset();
    listForm.setData({
      whatsapp_connection_id: defaultConnectionId,
      name: '',
      button_text: 'View options',
      description: '',
      footer_text: '',
      sections: [
        { title: 'Options', rows: [{ id: 'option_1', title: 'Option 1', description: '' }] },
      ],
    });
    setActiveListId(null);
    setDrawerMode('create');
  };

  const openList = (list: ListItem, mode: 'view' | 'edit') => {
    setActiveListId(list.id);
    if (mode === 'edit') {
      const normalizedSections = (
        list.sections?.length
          ? list.sections
          : [{ title: 'Options', rows: [{ id: 'option_1', title: 'Option 1', description: '' }] }]
      ).map((section) => ({
        title: section.title,
        rows: (section.rows || []).map((row) => ({
          id: row.id,
          title: row.title,
          description: row.description || '',
        })),
      }));
      listForm.setData({
        whatsapp_connection_id: list.connection.id,
        name: list.name,
        button_text: list.button_text,
        description: list.description || '',
        footer_text: list.footer_text || '',
        sections: normalizedSections,
      });
    }
    setDrawerMode(mode);
  };

  const closeDrawer = () => {
    setDrawerMode(null);
    setActiveListId(null);
  };

  const addRow = () => {
    const sections = [...listForm.data.sections];
    const first = sections[0] || { title: 'Options', rows: [] };
    first.rows = [
      ...(first.rows || []),
      {
        id: `option_${(first.rows || []).length + 1}`,
        title: `Option ${(first.rows || []).length + 1}`,
        description: '',
      },
    ];
    sections[0] = first;
    listForm.setData('sections', sections);
  };

  const submitList = () => {
    if (drawerMode === 'edit' && activeList) {
      listForm.put(route('app.whatsapp.lists.update', { list: activeList.id }), {
        preserveScroll: true,
        onSuccess: closeDrawer,
        onError: () => toast.error('Failed to update list'),
      });
      return;
    }

    listForm.post(route('app.whatsapp.lists.store', {}), {
      preserveScroll: true,
      onSuccess: closeDrawer,
      onError: () => toast.error('Failed to create list'),
    });
  };

  return (
    <AppShell>
      <Head title="List messages" />
      <div className="module-page max-w-[1600px]">
        <PageHeader
          title="List messages"
          description="A menu customers tap to pick an option, like a product or time slot."
          actions={
            <Button onClick={openCreate}>
              <Plus className="h-4 w-4" />
              New list
            </Button>
          }
        />

        {lists.length === 0 ? (
          <Card className="border-transparent dark:border-slate-700/80">
            <CardContent className="p-0">
              <EmptyState
                icon={List}
                title="No list messages yet"
                description="Let customers tap to choose instead of typing."
                action={
                  <Button onClick={openCreate}>
                    <Plus className="h-4 w-4" />
                    New list
                  </Button>
                }
              />
            </CardContent>
          </Card>
        ) : (
          <Card className="overflow-hidden border-transparent dark:border-slate-700/80">
            <CardContent className="p-0">
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="bg-gray-50/70 text-left text-[11px] uppercase tracking-wider text-ui-text-muted dark:bg-ui-dark-surface-2/60 dark:text-ui-dark-text-muted">
                      <th className="px-5 py-3 font-medium">List</th>
                      <th className="hidden px-5 py-3 font-medium sm:table-cell">Button</th>
                      <th className="hidden px-5 py-3 font-medium md:table-cell">Options</th>
                      <th className="px-5 py-3 font-medium">Status</th>
                      <th className="px-5 py-3 text-right font-medium">
                        <span className="sr-only">Actions</span>
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {lists.map((list) => (
                      <tr
                        key={list.id}
                        className="border-t border-gray-100 transition hover:bg-gray-50/70 dark:border-ui-dark-border dark:hover:bg-ui-dark-surface-2/50"
                      >
                        <td className="px-5 py-3">
                          <button
                            type="button"
                            onClick={() => openList(list, 'view')}
                            className="font-semibold text-ui-text hover:text-ui-green-dark dark:text-ui-dark-text dark:hover:text-emerald-300"
                          >
                            {list.name}
                          </button>
                          {list.description && (
                            <div className="mt-1 max-w-xs truncate text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                              {list.description}
                            </div>
                          )}
                          <div className="mt-1 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                            Created {formatDate(list.created_at)}
                          </div>
                        </td>
                        <td className="hidden whitespace-nowrap px-5 py-3 sm:table-cell">
                          <Badge variant="secondary" className="text-xs">
                            {list.button_text}
                          </Badge>
                        </td>
                        <td className="hidden whitespace-nowrap px-5 py-3 text-sm text-ui-text-muted dark:text-ui-dark-text-muted md:table-cell">
                          {formatNumber(list.total_rows)} options
                        </td>
                        <td className="whitespace-nowrap px-5 py-3">
                          <button
                            onClick={() => handleToggle(list)}
                            disabled={toggling === list.id}
                            className="flex items-center gap-2"
                            title={list.is_active ? 'Turn off' : 'Turn on'}
                          >
                            {list.is_active ? (
                              <Badge variant="success" className="flex items-center gap-1">
                                <ToggleRight className="h-3 w-3" />
                                On
                              </Badge>
                            ) : (
                              <Badge variant="secondary" className="flex items-center gap-1">
                                <ToggleLeft className="h-3 w-3" />
                                Off
                              </Badge>
                            )}
                          </button>
                        </td>
                        <td className="whitespace-nowrap px-5 py-3 text-right text-sm font-medium">
                          <div className="flex items-center justify-end gap-2">
                            <IconButton
                              size="sm"
                              aria-label="View list"
                              onClick={() => openList(list, 'view')}
                            >
                              <Eye className="h-4 w-4" />
                            </IconButton>
                            <IconButton
                              size="sm"
                              aria-label="Edit list"
                              onClick={() => openList(list, 'edit')}
                            >
                              <Edit className="h-4 w-4" />
                            </IconButton>
                            <IconButton
                              size="sm"
                              variant="danger"
                              onClick={() => handleDelete(list)}
                              disabled={deleting === list.id}
                              aria-label="Delete list"
                            >
                              <Trash2 className="h-4 w-4" />
                            </IconButton>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </CardContent>
          </Card>
        )}

        <Drawer
          open={drawerMode !== null}
          onClose={closeDrawer}
          title={
            drawerMode === 'create'
              ? 'New list'
              : drawerMode === 'edit'
                ? 'Edit list'
                : activeList?.name || 'List details'
          }
          description={
            drawerMode === 'view' ? undefined : 'Customers tap the button to see your options.'
          }
          className="max-w-2xl"
          footer={
            drawerMode === 'view' ? (
              <div className="flex justify-end gap-2">
                {activeList && (
                  <Button variant="secondary" onClick={() => openList(activeList, 'edit')}>
                    <Edit className="h-4 w-4" /> Edit
                  </Button>
                )}
                <Button onClick={closeDrawer}>Done</Button>
              </div>
            ) : (
              <div className="flex justify-end gap-2">
                <Button variant="secondary" onClick={closeDrawer}>
                  Cancel
                </Button>
                <Button onClick={submitList} disabled={listForm.processing}>
                  {drawerMode === 'edit' ? 'Save' : 'Create list'}
                </Button>
              </div>
            )
          }
        >
          {drawerMode === 'view' && activeList ? (
            <div className="space-y-4">
              <div className="rounded-card border border-gray-100 p-4 dark:border-ui-dark-border">
                <p className="text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
                  {activeList.description || activeList.name}
                </p>
                <div className="mt-3 flex flex-wrap gap-2">
                  <Badge variant={activeList.is_active ? 'success' : 'secondary'}>
                    {activeList.is_active ? 'On' : 'Off'}
                  </Badge>
                  <Badge variant="secondary">{activeList.button_text}</Badge>
                </div>
              </div>
              {(activeList.sections || []).map((section, sectionIndex) => (
                <div
                  key={sectionIndex}
                  className="rounded-card border border-gray-100 p-4 dark:border-ui-dark-border"
                >
                  <p className="font-semibold text-ui-text dark:text-ui-dark-text">
                    {section.title}
                  </p>
                  <div className="mt-3 space-y-2">
                    {(section.rows || []).map((row) => (
                      <div
                        key={row.id}
                        className="rounded-btn bg-gray-50 p-3 dark:bg-ui-dark-surface-2"
                      >
                        <p className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
                          {row.title}
                        </p>
                        {row.description && (
                          <p className="text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                            {row.description}
                          </p>
                        )}
                      </div>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="space-y-4">
              <div className="grid gap-3 sm:grid-cols-2">
                <div>
                  <label className="mb-1.5 block text-sm font-medium text-ui-text dark:text-ui-dark-text">
                    Name
                  </label>
                  <TextInput
                    value={listForm.data.name}
                    onChange={(e) => listForm.setData('name', e.target.value)}
                    className="w-full"
                  />
                </div>
                <div>
                  <label className="mb-1.5 block text-sm font-medium text-ui-text dark:text-ui-dark-text">
                    Button text
                  </label>
                  <TextInput
                    value={listForm.data.button_text}
                    onChange={(e) => listForm.setData('button_text', e.target.value)}
                    className="w-full"
                  />
                </div>
              </div>
              <div className={connections.length > 1 ? '' : 'hidden'}>
                <label className="mb-1.5 block text-sm font-medium text-ui-text dark:text-ui-dark-text">
                  WhatsApp number
                </label>
                <select
                  value={listForm.data.whatsapp_connection_id}
                  onChange={(e) =>
                    listForm.setData('whatsapp_connection_id', Number(e.target.value))
                  }
                  className="h-10 w-full rounded-btn border border-gray-200 bg-white px-3 text-sm text-ui-text dark:border-ui-dark-border dark:bg-ui-dark-surface dark:text-ui-dark-text"
                >
                  {connections.map((connection) => (
                    <option key={connection.id} value={connection.id}>
                      {connection.name}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="mb-1.5 block text-sm font-medium text-ui-text dark:text-ui-dark-text">
                  Message text
                </label>
                <TextInput
                  value={listForm.data.description}
                  onChange={(e) => listForm.setData('description', e.target.value)}
                  className="w-full"
                  placeholder="e.g. Pick a service below"
                />
                <p className="mt-1 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                  If empty, the name is used.
                </p>
              </div>
              <div className="rounded-card border border-gray-100 p-4 dark:border-ui-dark-border">
                <label className="mb-1.5 block text-sm font-medium text-ui-text dark:text-ui-dark-text">
                  Menu heading
                </label>
                <TextInput
                  value={listForm.data.sections[0]?.title || ''}
                  onChange={(e) => {
                    const sections = [...listForm.data.sections];
                    sections[0] = { ...(sections[0] || { rows: [] }), title: e.target.value };
                    listForm.setData('sections', sections);
                  }}
                  className="w-full"
                />
                <div className="mt-3 space-y-2">
                  {(listForm.data.sections[0]?.rows || []).map((row, rowIndex) => (
                    <div
                      key={rowIndex}
                      className="grid gap-2 rounded-btn bg-gray-50 p-3 dark:bg-ui-dark-surface-2 sm:grid-cols-2"
                    >
                      <TextInput
                        value={row.title}
                        onChange={(e) => {
                          const sections = [...listForm.data.sections];
                          sections[0].rows[rowIndex] = {
                            ...row,
                            title: e.target.value,
                            id: row.id || `option_${rowIndex + 1}`,
                          };
                          listForm.setData('sections', sections);
                        }}
                        placeholder="Option, e.g. Book a table"
                      />
                      <TextInput
                        value={row.description || ''}
                        onChange={(e) => {
                          const sections = [...listForm.data.sections];
                          sections[0].rows[rowIndex] = { ...row, description: e.target.value };
                          listForm.setData('sections', sections);
                        }}
                        placeholder="Short detail (optional)"
                      />
                    </div>
                  ))}
                </div>
                <Button
                  type="button"
                  variant="secondary"
                  size="sm"
                  className="mt-3"
                  onClick={addRow}
                >
                  <Plus className="h-3.5 w-3.5" />
                  Add option
                </Button>
              </div>
            </div>
          )}
        </Drawer>
      </div>
    </AppShell>
  );
}
